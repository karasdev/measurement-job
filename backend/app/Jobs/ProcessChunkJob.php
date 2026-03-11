<?php

namespace App\Jobs;

use App\Events\MeasurementJobProgress;
use App\Models\ChunkTemperatureResult;
use App\Models\JobMetric;
use App\Models\MeasurementJob;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class ProcessChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    /** 20 minutes: 2M-line chunks can be slow on large jobs. */
    public int $timeout = 1200;

    public function __construct(
        public MeasurementJob $measurementJob,
        public string $chunkFilePath,
        public int $chunkIndex
    ) {}

    public function handle(): void
    {
        $jobId = $this->measurementJob->id;
        $chunkIndex = $this->chunkIndex;

        // Idempotency: if this chunk was already processed (e.g. previous attempt succeeded but job retried),
        // skip processing to avoid double-counting rows_processed and duplicate ChunkTemperatureResult rows.
        try {
            if (ChunkTemperatureResult::where('measurement_job_id', $jobId)->where('chunk_index', $chunkIndex)->exists()) {
                $this->updateJobProgressOnly($jobId);
                return;
            }
        } catch (\Throwable $e) {
            // If the check fails (e.g. table missing, DB error), proceed with normal processing.
        }

        $startTime = microtime(true);
        $startCurrent = memory_get_usage(true);
        $startEmalloc = memory_get_usage(false);
        $maxCurrent = $startCurrent;
        $maxEmalloc = $startEmalloc;
        $linesRead = 0;
        $sampleEvery = 1000;

        $byCity = [];
        $chunkPath = $this->chunkFilePath;
        if (! is_readable($chunkPath)) {
            throw new \RuntimeException('Chunk file not found or not readable: '.$chunkPath);
        }
        $handle = fopen($chunkPath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Chunk file not readable: '.$chunkPath);
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $pos = strpos($line, ';');
            if ($pos === false) {
                continue;
            }
            $city = substr($line, 0, $pos);
            $temp = (float) substr($line, $pos + 1);

            if (! isset($byCity[$city])) {
                $byCity[$city] = ['min' => $temp, 'max' => $temp, 'sum' => $temp, 'count' => 1];
            } else {
                $byCity[$city]['min'] = min($byCity[$city]['min'], $temp);
                $byCity[$city]['max'] = max($byCity[$city]['max'], $temp);
                $byCity[$city]['sum'] += $temp;
                $byCity[$city]['count']++;
            }

            $linesRead++;
            if ($linesRead === 1 || $linesRead % $sampleEvery === 0) {
                $maxCurrent = max($maxCurrent, memory_get_usage(true));
                $maxEmalloc = max($maxEmalloc, memory_get_usage(false));
            }
        }
        fclose($handle);

        $rows = [];
        $cityIndex = 0;
        foreach ($byCity as $city => $data) {
            $rows[] = [
                'measurement_job_id' => $jobId,
                'chunk_index' => $chunkIndex,
                'city' => $city,
                'min_temp' => $data['min'],
                'max_temp' => $data['max'],
                'sum_temp' => $data['sum'],
                'count' => $data['count'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $cityIndex++;
            if ($cityIndex % 100 === 0) {
                $maxCurrent = max($maxCurrent, memory_get_usage(true));
                $maxEmalloc = max($maxEmalloc, memory_get_usage(false));
            }
        }

        $maxCurrent = max($maxCurrent, memory_get_usage(true));
        $maxEmalloc = max($maxEmalloc, memory_get_usage(false));

        foreach (array_chunk($rows, 500) as $chunk) {
            ChunkTemperatureResult::insert($chunk);
            $maxCurrent = max($maxCurrent, memory_get_usage(true));
            $maxEmalloc = max($maxEmalloc, memory_get_usage(false));
        }

        $totalRows = array_sum(array_column($byCity, 'count'));
        $executionMs = (int) round((microtime(true) - $startTime) * 1000);
        $maxCurrent = max($maxCurrent, memory_get_usage(true));
        $maxEmalloc = max($maxEmalloc, memory_get_usage(false));
        $deltaReal = $maxCurrent - $startCurrent;
        $deltaEmalloc = $maxEmalloc - $startEmalloc;
        $memoryUsed = (int) max(0, $deltaReal, $deltaEmalloc);

        JobMetric::create([
            'measurement_job_id' => $jobId,
            'phase' => 'chunk_'.$chunkIndex,
            'execution_time_ms' => $executionMs,
            'memory_used_bytes' => $memoryUsed,
            'rows_processed' => $totalRows,
        ]);

        $this->measurementJob->increment('rows_processed', $totalRows);

        $job = $this->measurementJob->fresh();
        if ($job) {
            $done = ChunkTemperatureResult::where('measurement_job_id', $job->id)
                ->select('chunk_index')
                ->groupBy('chunk_index')
                ->count();
            $totalChunks = $job->total_chunks;
            if ($totalChunks === null || $totalChunks <= 0) {
                $totalChunks = (int) ChunkTemperatureResult::where('measurement_job_id', $job->id)->max('chunk_index') + 1;
                $job->update(['total_chunks' => $totalChunks]);
            }
            $totalChunks = (int) $totalChunks;
            $chunkPercent = $totalChunks > 0 ? (int) round($done / $totalChunks * 100) : 0;
            $rowPercent = $job->requested_rows > 0
                ? (int) round($job->rows_processed / $job->requested_rows * 100)
                : 100;
            $progressPercent = min(max($chunkPercent, 0), max($rowPercent, 0), 100);
            $job->update(['progress_percent' => $progressPercent]);
            broadcast(MeasurementJobProgress::fromJob($job->fresh()));
        }

        if (file_exists($this->chunkFilePath)) {
            File::delete($this->chunkFilePath);
        }
    }

    /**
     * Update job progress_percent and broadcast (used when chunk was already processed on a retry).
     */
    private function updateJobProgressOnly(int $jobId): void
    {
        $job = $this->measurementJob->fresh();
        if (! $job) {
            return;
        }
        $done = ChunkTemperatureResult::where('measurement_job_id', $jobId)
            ->select('chunk_index')
            ->groupBy('chunk_index')
            ->count();
        $totalChunks = $job->total_chunks;
        if ($totalChunks === null || $totalChunks <= 0) {
            $totalChunks = (int) ChunkTemperatureResult::where('measurement_job_id', $jobId)->max('chunk_index') + 1;
            $job->update(['total_chunks' => $totalChunks]);
        }
        $totalChunks = (int) $totalChunks;
        $chunkPercent = $totalChunks > 0 ? (int) round($done / $totalChunks * 100) : 0;
        $rowPercent = $job->requested_rows > 0
            ? (int) round($job->rows_processed / $job->requested_rows * 100)
            : 100;
        $progressPercent = min(max($chunkPercent, 0), max($rowPercent, 0), 100);
        $job->update(['progress_percent' => $progressPercent]);
        broadcast(MeasurementJobProgress::fromJob($job->fresh()));
    }
}
