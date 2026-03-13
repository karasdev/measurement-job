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

        $byCity = [];
        $chunkPath = $this->chunkFilePath;
        if (! is_readable($chunkPath)) {
            throw new \RuntimeException('Chunk file not found or not readable: '.$chunkPath);
        }
        $handle = fopen($chunkPath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Chunk file not readable: '.$chunkPath);
        }

        // Read in large buffers instead of line-by-line for speed
        $leftover = '';
        $bufferSize = 65536; // 64KB
        $samplesCollected = 0;
        while (! feof($handle)) {
            $buffer = fread($handle, $bufferSize);
            if ($buffer === false) {
                break;
            }
            $buffer = $leftover.$buffer;
            $lastNewline = strrpos($buffer, "\n");
            if ($lastNewline === false) {
                $leftover = $buffer;
                continue;
            }
            $leftover = substr($buffer, $lastNewline + 1);
            $lines = substr($buffer, 0, $lastNewline);

            foreach (explode("\n", $lines) as $line) {
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
            }

            $samplesCollected++;
            if ($samplesCollected % 50 === 0) {
                $maxCurrent = max($maxCurrent, memory_get_usage(true));
                $maxEmalloc = max($maxEmalloc, memory_get_usage(false));
            }
        }
        // Process any remaining data
        if ($leftover !== '') {
            $pos = strpos($leftover, ';');
            if ($pos !== false) {
                $city = substr($leftover, 0, $pos);
                $temp = (float) substr($leftover, $pos + 1);
                if (! isset($byCity[$city])) {
                    $byCity[$city] = ['min' => $temp, 'max' => $temp, 'sum' => $temp, 'count' => 1];
                } else {
                    $byCity[$city]['min'] = min($byCity[$city]['min'], $temp);
                    $byCity[$city]['max'] = max($byCity[$city]['max'], $temp);
                    $byCity[$city]['sum'] += $temp;
                    $byCity[$city]['count']++;
                }
            }
        }
        fclose($handle);

        $now = now();
        $rows = [];
        foreach ($byCity as $city => $data) {
            $rows[] = [
                'measurement_job_id' => $jobId,
                'chunk_index' => $chunkIndex,
                'city' => $city,
                'min_temp' => $data['min'],
                'max_temp' => $data['max'],
                'sum_temp' => $data['sum'],
                'count' => $data['count'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $maxCurrent = max($maxCurrent, memory_get_usage(true));
        $maxEmalloc = max($maxEmalloc, memory_get_usage(false));

        foreach (array_chunk($rows, 1000) as $chunk) {
            ChunkTemperatureResult::insert($chunk);
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
     * Also ensures rows_processed is correct if the previous attempt inserted rows but crashed before incrementing.
     */
    private function updateJobProgressOnly(int $jobId): void
    {
        $job = $this->measurementJob->fresh();
        if (! $job) {
            return;
        }

        // Check if rows_processed was already incremented for this chunk by comparing
        // the metric record. If the chunk metric exists, the increment already happened.
        $metricExists = JobMetric::where('measurement_job_id', $jobId)
            ->where('phase', 'chunk_'.$this->chunkIndex)
            ->exists();

        if (! $metricExists) {
            // Previous attempt inserted ChunkTemperatureResult rows but crashed before
            // incrementing rows_processed. Recover the count from the stored results.
            $chunkRowCount = (int) ChunkTemperatureResult::where('measurement_job_id', $jobId)
                ->where('chunk_index', $this->chunkIndex)
                ->sum('count');

            if ($chunkRowCount > 0) {
                $this->measurementJob->increment('rows_processed', $chunkRowCount);

                JobMetric::create([
                    'measurement_job_id' => $jobId,
                    'phase' => 'chunk_'.$this->chunkIndex,
                    'execution_time_ms' => 0,
                    'memory_used_bytes' => 0,
                    'rows_processed' => $chunkRowCount,
                ]);
            }

            $job = $this->measurementJob->fresh();
        }

        // Clean up chunk file if still present
        if (file_exists($this->chunkFilePath)) {
            File::delete($this->chunkFilePath);
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
