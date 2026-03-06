<?php

namespace App\Events;

use App\Models\MeasurementJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeasurementJobProgress implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public static function fromJob(MeasurementJob $job): self
    {
        return new self(
            jobId: $job->id,
            status: $job->status,
            progressPercent: (int) ($job->progress_percent ?? 0),
            rowsProcessed: (int) ($job->rows_processed ?? 0),
            executionTimeMs: $job->execution_time_ms,
            memoryUsedBytes: $job->memory_used_bytes,
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
        return [new Channel('measurement_job.'.$this->jobId)];
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
}
