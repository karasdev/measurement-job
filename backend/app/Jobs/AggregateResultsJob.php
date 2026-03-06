<?php

namespace App\Jobs;

use App\Events\MeasurementJobProgress;
use App\Models\ChunkTemperatureResult;
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

    public int $timeout = 600;

    public function __construct(
        public MeasurementJob $measurementJob
    ) {}

    public function handle(): void
    {
        $jobId = $this->measurementJob->id;

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

        foreach (array_chunk($rows, 500) as $chunk) {
            TemperatureResult::insert($chunk);
        }

        $job = $this->measurementJob->fresh();
        $startedAt = $job->created_at;
        $executionMs = (int) $startedAt->diffInMilliseconds(now());

        $job->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'execution_time_ms' => $executionMs,
            'memory_used_bytes' => memory_get_usage(true),
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
