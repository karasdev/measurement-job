<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChunkTemperatureResult extends Model
{
    protected $fillable = [
        'measurement_job_id',
        'chunk_index',
        'city',
        'min_temp',
        'max_temp',
        'sum_temp',
        'count',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'min_temp' => 'float',
        'max_temp' => 'float',
        'sum_temp' => 'float',
        'count' => 'integer',
    ];

    public function measurementJob(): BelongsTo
    {
        return $this->belongsTo(MeasurementJob::class);
    }
}
