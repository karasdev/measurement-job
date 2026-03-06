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

    public int $timeout = 600;

    public function __construct(
        public MeasurementJob $measurementJob,
        public string $chunkFilePath,
        public int $chunkIndex
    ) {}

    public function handle(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $jobId = $this->measurementJob->id;
        $chunkIndex = $this->chunkIndex;

        $byCity = [];
        $handle = fopen($this->chunkFilePath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Chunk file not found or not readable: '.$this->chunkFilePath);
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
        }
        fclose($handle);

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
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ChunkTemperatureResult::insert($chunk);
        }

        $totalRows = array_sum(array_column($byCity, 'count'));
        $executionMs = (int) round((microtime(true) - $startTime) * 1000);
        $memoryUsed = memory_get_usage(true) - $startMemory;

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
                ->distinct()
                ->count('chunk_index');
            $totalChunks = $job->total_chunks;
            if ($totalChunks === null || $totalChunks <= 0) {
                $totalChunks = ChunkTemperatureResult::where('measurement_job_id', $job->id)
                    ->max('chunk_index') + 1;
                $job->update(['total_chunks' => $totalChunks]);
            }
            if ($totalChunks > 0) {
                $chunkPercent = (int) round($done / $totalChunks * 100);
                $rowPercent = $job->requested_rows > 0
                    ? (int) round($job->rows_processed / $job->requested_rows * 100)
                    : 100;
                $progressPercent = min($chunkPercent, $rowPercent, 100);
                $job->update(['progress_percent' => $progressPercent]);
            }
            broadcast(MeasurementJobProgress::fromJob($job->fresh()));
        }

        if (file_exists($this->chunkFilePath)) {
            File::delete($this->chunkFilePath);
        }
    }
}
