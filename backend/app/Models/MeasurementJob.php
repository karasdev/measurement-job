<?php

namespace App\Models;

use App\Jobs\AggregateResultsJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementJob extends Model
{
    protected $fillable = [
        'user_id',
        'requested_rows',
        'status',
        'file_path',
        'total_chunks',
        'progress_percent',
        'rows_processed',
        'execution_time_ms',
        'memory_used_bytes',
        'error_message',
        'completed_at',
        'aggregate_dispatched_at',
    ];

    protected $casts = [
        'requested_rows' => 'integer',
        'rows_processed' => 'integer',
        'execution_time_ms' => 'integer',
        'memory_used_bytes' => 'integer',
        'progress_percent' => 'integer',
        'completed_at' => 'datetime',
        'aggregate_dispatched_at' => 'datetime',
    ];

    protected $appends = [
        'memory_generating_bytes',
        'memory_processing_bytes',
        'memory_aggregate_bytes',
        'memory_total_bytes',
    ];

    public function getMemoryGeneratingBytesAttribute(): ?int
    {
        $m = $this->metrics->firstWhere('phase', 'generating');
        return $m === null ? null : ($m->memory_used_bytes !== null ? (int) $m->memory_used_bytes : null);
    }

    public function getMemoryProcessingBytesAttribute(): ?int
    {
        $sum = $this->metrics->filter(fn ($m) => str_starts_with((string) $m->phase, 'chunk_'))
            ->sum('memory_used_bytes');
        return $sum !== 0 ? (int) $sum : 0;
    }

    public function getMemoryAggregateBytesAttribute(): ?int
    {
        $m = $this->metrics->firstWhere('phase', 'aggregating');
        return $m === null ? null : ($m->memory_used_bytes !== null ? (int) $m->memory_used_bytes : null);
    }

    public function getMemoryTotalBytesAttribute(): ?int
    {
        $g = $this->memory_generating_bytes;
        $p = $this->memory_processing_bytes;
        $a = $this->memory_aggregate_bytes;
        if ($g === null || $p === null || $a === null) {
            return null;
        }
        return $g + $p + $a;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(JobMetric::class);
    }

    public function temperatureResults(): HasMany
    {
        return $this->hasMany(TemperatureResult::class);
    }

    /**
     * If this job is stuck at 100% (chunks done but aggregate never ran), dispatch AggregateResultsJob once.
     */
    public function dispatchAggregateIfStuck(): void
    {
        if ($this->status !== 'processing') {
            return;
        }
        if ($this->progress_percent < 100 || ($this->total_chunks ?? 0) <= 0) {
            return;
        }
        if ($this->metrics->contains('phase', 'aggregating')) {
            return;
        }
        $chunksDone = ChunkTemperatureResult::where('measurement_job_id', $this->id)->select('chunk_index')->groupBy('chunk_index')->count();
        if ($chunksDone < $this->total_chunks) {
            return;
        }
        $canRedispatch = $this->aggregate_dispatched_at === null
            || $this->aggregate_dispatched_at->lt(now()->subMinutes(5));
        if (! $canRedispatch) {
            return;
        }
        $this->update(['aggregate_dispatched_at' => now()]);
        AggregateResultsJob::dispatch($this->fresh())->onQueue('default');
    }
}
