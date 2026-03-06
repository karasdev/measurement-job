<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemperatureResult extends Model
{
    protected $fillable = [
        'measurement_job_id',
        'city',
        'min_temp',
        'max_temp',
        'avg_temp',
        'count',
    ];

    protected $casts = [
        'min_temp' => 'float',
        'max_temp' => 'float',
        'avg_temp' => 'float',
        'count' => 'integer',
    ];

    public function measurementJob(): BelongsTo
    {
        return $this->belongsTo(MeasurementJob::class);
    }
}
