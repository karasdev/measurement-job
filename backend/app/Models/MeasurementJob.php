<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'requested_rows' => 'integer',
        'rows_processed' => 'integer',
        'execution_time_ms' => 'integer',
        'memory_used_bytes' => 'integer',
        'progress_percent' => 'integer',
        'completed_at' => 'datetime',
    ];

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
}
