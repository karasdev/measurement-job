<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeasurementJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        if (! $request->user()?->is_admin) {
            abort(403, 'Admin access required.');
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $jobsByStatus = MeasurementJob::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return response()->json([
            'total_jobs' => MeasurementJob::count(),
            'total_users' => User::count(),
            'jobs_by_status' => $jobsByStatus,
        ]);
    }

    public function jobs(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $query = MeasurementJob::with('user:id,name,email');

        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $sort = $request->query('sort', 'created_at');
        $order = strtolower($request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['created_at', 'status', 'progress_percent', 'requested_rows', 'user_id'];
        if (in_array($sort, $allowedSort, true)) {
            $query->orderBy($sort, $order);
        } else {
            $query->orderByDesc('created_at');
        }

        $jobs = $query->paginate((int) $request->query('per_page', 15));

        return response()->json($jobs);
    }

    public function users(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $users = User::query()
            ->select('id', 'name', 'email', 'is_admin', 'created_at')
            ->withCount('measurementJobs')
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($users);
    }
}
