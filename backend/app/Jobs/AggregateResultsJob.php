<?php

namespace App\Jobs;

use App\Events\MeasurementJobProgress;
use App\Models\ChunkTemperatureResult;
use App\Models\JobMetric;
use App\Models\MeasurementJob;
use App\Models\TemperatureResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AggregateResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    /** 15 minutes: aggregating many chunks for large jobs. */
    public int $timeout = 900;

    public function __construct(
        public MeasurementJob $measurementJob
    ) {}

    public function handle(): void
    {
        $jobId = $this->measurementJob->id;

        // Idempotent: if we already have final results (e.g. recovery re-run), set status from rows processed.
        if (TemperatureResult::where('measurement_job_id', $jobId)->exists()) {
            $job = $this->measurementJob->fresh();
            if ($job && $job->status !== 'completed' && $job->status !== 'partial') {
                $processingMemory = (int) JobMetric::where('measurement_job_id', $jobId)
                    ->where('phase', 'like', 'chunk_%')
                    ->sum('memory_used_bytes');
                $requested = (int) $job->requested_rows;
                $processed = (int) $job->rows_processed;
                $allProcessed = $requested > 0 && $processed >= $requested;
                $progressPercent = $requested > 0 ? (int) min(100, round($processed / $requested * 100)) : 100;
                $job->update([
                    'status' => $allProcessed ? 'completed' : 'partial',
                    'progress_percent' => $allProcessed ? 100 : $progressPercent,
                    'execution_time_ms' => (int) $job->created_at->diffInMilliseconds(now()),
                    'memory_used_bytes' => $processingMemory,
                    'error_message' => $allProcessed ? null : "Only ".number_format($processed)." of ".number_format($requested)." requested rows were processed.",
                    'completed_at' => now(),
                ]);
                broadcast(MeasurementJobProgress::fromJob($job->fresh()));
            }
            return;
        }

        $startTime = microtime(true);
        $startCurrent = memory_get_usage(true);
        $startEmalloc = memory_get_usage(false);
        $maxCurrent = $startCurrent;
        $maxEmalloc = $startEmalloc;

        $aggregated = ChunkTemperatureResult::query()
            ->where('measurement_job_id', $jobId)
            ->select([
                'city',
                DB::raw('MIN(min_temp) as min_temp'),
                DB::raw('MAX(max_temp) as max_temp'),
                DB::raw('SUM(sum_temp) as sum_temp'),
                DB::raw('SUM(count) as count'),
            ])
            ->groupBy('city')
            ->get();

        $maxCurrent = max($maxCurrent, memory_get_usage(true));
        $maxEmalloc = max($maxEmalloc, memory_get_usage(false));

        $rows = [];
        foreach ($aggregated as $row) {
            $avg = $row->count > 0 ? round($row->sum_temp / $row->count, 1) : 0;
            $rows[] = [
                'measurement_job_id' => $jobId,
                'city' => $row->city,
                'min_temp' => $row->min_temp,
                'max_temp' => $row->max_temp,
                'avg_temp' => $avg,
                'count' => $row->count,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $maxCurrent = max($maxCurrent, memory_get_usage(true));
        $maxEmalloc = max($maxEmalloc, memory_get_usage(false));

        foreach (array_chunk($rows, 500) as $chunk) {
            TemperatureResult::insert($chunk);
            $maxCurrent = max($maxCurrent, memory_get_usage(true));
            $maxEmalloc = max($maxEmalloc, memory_get_usage(false));
        }

        $job = $this->measurementJob->fresh();
        $startedAt = $job->created_at;
        $executionMs = (int) $startedAt->diffInMilliseconds(now());
        $aggregateExecutionMs = (int) round((microtime(true) - $startTime) * 1000);
        $maxCurrent = max($maxCurrent, memory_get_usage(true));
        $maxEmalloc = max($maxEmalloc, memory_get_usage(false));
        $deltaReal = $maxCurrent - $startCurrent;
        $deltaEmalloc = $maxEmalloc - $startEmalloc;
        $delta = (int) max(0, $deltaReal, $deltaEmalloc);
        $oneMb = 1024 * 1024;
        $maxPlausibleMb = 30;
        $memoryUsed = ($delta <= $maxPlausibleMb * $oneMb) ? $delta : null;

        JobMetric::create([
            'measurement_job_id' => $jobId,
            'phase' => 'aggregating',
            'execution_time_ms' => $aggregateExecutionMs,
            'memory_used_bytes' => $memoryUsed,
            'rows_processed' => 0,
        ]);

        // Store processing memory (sum of all chunk metrics) on the job as memory_used_bytes.
        $processingMemory = (int) JobMetric::where('measurement_job_id', $jobId)
            ->where('phase', 'like', 'chunk_%')
            ->sum('memory_used_bytes');

        $job = $this->measurementJob->fresh();
        $requested = (int) $job->requested_rows;
        $processed = (int) $job->rows_processed;
        $allProcessed = $requested > 0 && $processed >= $requested;
        $progressPercent = $requested > 0 ? (int) min(100, round($processed / $requested * 100)) : 100;

        $job->update([
            'status' => $allProcessed ? 'completed' : 'partial',
            'progress_percent' => $allProcessed ? 100 : $progressPercent,
            'execution_time_ms' => $executionMs,
            'memory_used_bytes' => $processingMemory,
            'error_message' => $allProcessed ? null : "Only ".number_format($processed)." of ".number_format($requested)." requested rows were processed.",
            'completed_at' => now(),
        ]);
        broadcast(MeasurementJobProgress::fromJob($job->fresh()));

        ChunkTemperatureResult::where('measurement_job_id', $jobId)->delete();
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
