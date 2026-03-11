<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMeasurementsJob;
use App\Models\MeasurementJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JobController extends Controller
{
    private const MAX_ROWS = 1000000000;

    public function store(Request $request): JsonResponse
    {
        $raw = $request->input('rows');
        $normalized = is_string($raw) ? (int) str_replace(['_', ',', ' '], '', $raw) : (int) $raw;
        $request->merge(['rows' => $normalized]);

        $minRows = config('app.env') === 'local' ? 10_000 : 100_000_000;
        $validated = $request->validate([
            'rows' => ['required', 'integer', 'min:'.$minRows],
        ]);

        if ($validated['rows'] > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'rows' => ['The rows must not be greater than '.number_format(self::MAX_ROWS).'.'],
            ]);
        }

        try {
            $job = MeasurementJob::create([
                'user_id' => $request->user()->id,
                'requested_rows' => $validated['rows'],
                'status' => 'pending',
            ]);

            GenerateMeasurementsJob::dispatch($job);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not create or queue job.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'message' => 'Job submitted.',
            'job_id' => $job->id,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = MeasurementJob::where('user_id', $request->user()->id);

        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $sort = $request->query('sort', 'created_at');
        $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['created_at', 'status', 'progress_percent', 'requested_rows', 'rows_processed'];
        if (in_array($sort, $allowedSort, true)) {
            $query->orderBy($sort, $order);
        } else {
            $query->orderByDesc('created_at');
        }

        $jobs = $query->paginate((int) $request->query('per_page', 15));

        return response()->json($jobs);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $job = MeasurementJob::where('user_id', $request->user()->id)
            ->with(['metrics', 'temperatureResults'])
            ->findOrFail($id);

        $job->dispatchAggregateIfStuck();

        return response()->json($job);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        $job = MeasurementJob::where('user_id', $request->user()->id)->findOrFail($id);

        if ($job->status !== 'failed' && $job->status !== 'partial') {
            return response()->json(['message' => 'Only failed or partial jobs can be retried.'], 422);
        }

        $newJob = MeasurementJob::create([
            'user_id' => $request->user()->id,
            'requested_rows' => $job->requested_rows,
            'status' => 'pending',
        ]);

        GenerateMeasurementsJob::dispatch($newJob);

        return response()->json([
            'message' => 'Job submitted for retry.',
            'job_id' => $newJob->id,
        ], 201);
    }
}
