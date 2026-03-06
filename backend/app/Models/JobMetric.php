<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMetric extends Model
{
    protected $fillable = [
        'measurement_job_id',
        'phase',
        'execution_time_ms',
        'memory_used_bytes',
        'rows_processed',
    ];

    protected $casts = [
        'execution_time_ms' => 'integer',
        'memory_used_bytes' => 'integer',
        'rows_processed' => 'integer',
    ];

    public function measurementJob(): BelongsTo
    {
        return $this->belongsTo(MeasurementJob::class);
    }
}
