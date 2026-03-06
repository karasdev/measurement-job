<?php

namespace App\Jobs;

use App\Events\MeasurementJobProgress;
use App\Models\JobMetric;
use App\Models\MeasurementJob;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;

class GenerateMeasurementsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 3600;

    public function __construct(
        public MeasurementJob $measurementJob
    ) {
        $this->onQueue('generation');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $job = $this->measurementJob;
        $job->update(['status' => 'generating', 'progress_percent' => 0]);
        broadcast(MeasurementJobProgress::fromJob($job->fresh()));

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $dir = storage_path('app/measurement_jobs/'.$job->id);
        File::ensureDirectoryExists($dir);
        $filePath = $dir.'/measurements.txt';

        Artisan::call('generate:measurements', [
            'path' => $dir,
            'count' => (int) $job->requested_rows,
            'batch-size' => 500,
        ]);

        $executionMs = (int) round((microtime(true) - $startTime) * 1000);
        $memoryUsed = memory_get_usage(true) - $startMemory;
        JobMetric::create([
            'measurement_job_id' => $job->id,
            'phase' => 'generating',
            'execution_time_ms' => $executionMs,
            'memory_used_bytes' => $memoryUsed,
            'rows_processed' => $job->requested_rows,
        ]);

        $job->update([
            'file_path' => $filePath,
            'status' => 'processing',
            'rows_processed' => 0,
        ]);
        broadcast(MeasurementJobProgress::fromJob($job->fresh()));

        // Split file into chunk files (e.g. 2M lines per chunk to limit memory)
        $chunkSize = 2_000_000;
        $handle = fopen($filePath, 'r');
        $chunkIndex = 0;
        $lineCount = 0;
        $chunkHandle = null;
        $chunkPath = null;

        while (($line = fgets($handle)) !== false) {
            if ($lineCount % $chunkSize === 0) {
                if ($chunkHandle !== null) {
                    fclose($chunkHandle);
                }
                $chunkPath = $dir.'/chunk_'.$chunkIndex.'.txt';
                $chunkHandle = fopen($chunkPath, 'w');
                $chunkIndex++;
            }
            fwrite($chunkHandle, $line);
            $lineCount++;
        }

        if ($chunkHandle !== null) {
            fclose($chunkHandle);
        }
        fclose($handle);

        $totalChunks = $chunkIndex;
        $job->update(['total_chunks' => $totalChunks]);
        broadcast(MeasurementJobProgress::fromJob($job->fresh()));

        $jobs = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $jobs[] = new ProcessChunkJob($job->fresh(), $dir.'/chunk_'.$i.'.txt', $i);
        }

        Bus::batch($jobs)
            ->name('measurement-'.$job->id)
            ->onQueue('default')
            ->then(function () use ($job) {
                AggregateResultsJob::dispatch($job->fresh())->onQueue('default');
            })
            ->catch(function () use ($job) {
                $job->update([
                    'status' => 'failed',
                    'error_message' => 'One or more chunk jobs failed',
                    'completed_at' => now(),
                ]);
                broadcast(MeasurementJobProgress::fromJob($job->fresh()));
            })
            ->dispatch();
    }

    public function failed(\Throwable $e): void
    {
        $job = $this->measurementJob->fresh();
        if ($job) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            broadcast(MeasurementJobProgress::fromJob($job));
        }
    }
}
