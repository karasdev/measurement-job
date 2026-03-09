<?php

use App\Models\MeasurementJob;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Only the job owner may subscribe to a job's progress channel.
|
*/

Broadcast::channel('measurement_job.{jobId}', function ($user, $jobId) {
    return MeasurementJob::where('id', (int) $jobId)
        ->where('user_id', $user->id)
        ->exists();
});
