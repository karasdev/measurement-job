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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use App\Models\ChunkTemperatureResult;

class GenerateMeasurementsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 7200;

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
        $startCurrent = memory_get_usage(true);
        $startEmalloc = memory_get_usage(false);

        $dir = storage_path('app/measurement_jobs/'.$job->id);
        File::ensureDirectoryExists($dir);
        $filePath = $dir.'/measurements.txt';

        Artisan::call('generate:measurements', [
            'path' => $dir,
            'count' => (int) $job->requested_rows,
            'batch-size' => 50000,
            '--job-id' => (string) $job->id,
        ]);

        $executionMs = (int) round((microtime(true) - $startTime) * 1000);
        $memoryUsed = null;
        $cached = Cache::pull('gen_mem_'.$job->id);
        if (is_array($cached)) {
            $deltaReal = ($cached['max_current'] ?? 0) - ($cached['start_current'] ?? 0);
            $deltaEmalloc = ($cached['max_emalloc'] ?? 0) - ($cached['start_emalloc'] ?? 0);
            $memoryUsed = (int) max(0, $deltaReal, $deltaEmalloc);
        }
        if ($memoryUsed === null) {
            $maxCurrent = max($startCurrent, memory_get_usage(true));
            $maxEmalloc = max($startEmalloc, memory_get_usage(false));
            $memoryUsed = (int) max(0, $maxCurrent - $startCurrent, $maxEmalloc - $startEmalloc);
        }
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

        // Split file into chunk files using system `split` (fast, reliable C-level I/O)
        $chunkSize = 2_000_000;
        $prefix = $dir.'/chunk_';

        // GNU split: -l = lines per file, -d = numeric suffixes, -a = suffix length
        $cmd = sprintf(
            'split -l %d -d -a 6 %s %s',
            $chunkSize,
            escapeshellarg($filePath),
            escapeshellarg($prefix)
        );
        $exitCode = 0;
        $output = [];
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Failed to split measurements file: '.implode("\n", $output));
        }

        // Rename split output files (chunk_000000, chunk_000001, ...) to chunk_0.txt, chunk_1.txt, ...
        $splitFiles = glob($prefix.'*');
        sort($splitFiles); // ensure numeric order
        $totalChunks = count($splitFiles);
        for ($i = 0; $i < $totalChunks; $i++) {
            $target = $dir.'/chunk_'.$i.'.txt';
            if ($splitFiles[$i] !== $target) {
                rename($splitFiles[$i], $target);
            }
        }

        if ($totalChunks === 0) {
            throw new \RuntimeException('File splitting produced no chunks. File may be empty.');
        }
        $job->update(['total_chunks' => $totalChunks]);
        broadcast(MeasurementJobProgress::fromJob($job->fresh()));

        $jobs = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $jobs[] = new ProcessChunkJob($job->fresh(), $dir.'/chunk_'.$i.'.txt', $i);
        }

        Bus::batch($jobs)
            ->name('measurement-'.$job->id)
            ->onQueue('default')
            ->allowFailures()
            ->finally(function () use ($job) {
                $fresh = $job->fresh();

                if (!$fresh || $fresh->status !== 'processing') {
                    return;
                }

                $hasChunkResults = ChunkTemperatureResult::where(
                    'measurement_job_id',
                    $fresh->id
                )->exists();

                if ($hasChunkResults) {
                    AggregateResultsJob::dispatch($fresh)->onQueue('default');
                    return;
                }

                $fresh->update([
                    'status' => 'failed',
                    'error_message' => 'All chunk jobs failed during processing.',
                    'completed_at' => now(),
                ]);

                broadcast(MeasurementJobProgress::fromJob($fresh->fresh()));
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
