<?php

namespace App\Events;

use App\Models\JobMetric;
use App\Models\MeasurementJob;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeasurementJobProgress implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public static function fromJob(MeasurementJob $job): self
    {
        $executionTimeMs = self::resolveExecutionTimeMs($job);
        $memoryUsedBytes = self::resolveMemoryUsedBytes($job);

        return new self(
            jobId: $job->id,
            status: $job->status,
            progressPercent: (int) ($job->progress_percent ?? 0),
            rowsProcessed: (int) ($job->rows_processed ?? 0),
            executionTimeMs: $executionTimeMs,
            memoryUsedBytes: $memoryUsedBytes,
            errorMessage: $job->error_message,
            completedAt: $job->completed_at?->toIso8601String(),
        );
    }

    public function __construct(
        public int $jobId,
        public string $status,
        public int $progressPercent,
        public int $rowsProcessed,
        public ?int $executionTimeMs,
        public ?int $memoryUsedBytes,
        public ?string $errorMessage,
        public ?string $completedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('measurement_job.'.$this->jobId)];
    }

    public function broadcastAs(): string
    {
        return 'progress';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->jobId,
            'status' => $this->status,
            'progress_percent' => $this->progressPercent,
            'rows_processed' => $this->rowsProcessed,
            'execution_time_ms' => $this->executionTimeMs,
            'memory_used_bytes' => $this->memoryUsedBytes,
            'error_message' => $this->errorMessage,
            'completed_at' => $this->completedAt,
        ];
    }

    private static function resolveExecutionTimeMs(MeasurementJob $job): ?int
    {
        if ($job->created_at === null) {
            return $job->execution_time_ms;
        }

        if (in_array($job->status, ['completed', 'partial', 'failed'], true) && $job->execution_time_ms !== null) {
            return (int) $job->execution_time_ms;
        }

        $end = $job->completed_at ?? now();

        return (int) max(0, $job->created_at->diffInMilliseconds($end));
    }

    private static function resolveMemoryUsedBytes(MeasurementJob $job): ?int
    {
        if ($job->status === 'pending') {
            return 0;
        }

        if (in_array($job->status, ['completed', 'partial', 'failed'], true) && $job->memory_used_bytes !== null) {
            return (int) $job->memory_used_bytes;
        }

        $totals = JobMetric::query()
            ->where('measurement_job_id', $job->id)
            ->selectRaw("COALESCE(MAX(CASE WHEN phase = 'generating' THEN memory_used_bytes END), 0) as generating_bytes")
            ->selectRaw("COALESCE(SUM(CASE WHEN phase LIKE 'chunk_%' THEN memory_used_bytes ELSE 0 END), 0) as processing_bytes")
            ->selectRaw("COALESCE(MAX(CASE WHEN phase = 'aggregating' THEN memory_used_bytes END), 0) as aggregating_bytes")
            ->first();

        $generating = (int) ($totals?->generating_bytes ?? 0);
        $processing = (int) ($totals?->processing_bytes ?? 0);
        $aggregating = (int) ($totals?->aggregating_bytes ?? 0);

        return match ($job->status) {
            'generating' => $generating,
            'processing' => $generating + $processing,
            'aggregating' => $generating + $processing + $aggregating,
            default => $job->memory_used_bytes ?? ($generating + $processing + $aggregating),
        };
    }
}
