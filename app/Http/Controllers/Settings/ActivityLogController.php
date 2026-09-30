<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->role->canManageUsers(), 403);

        $filters = $request->only(['user_id', 'search']);

        return Inertia::render('settings/activity-log', [
            'logs' => ActivityLog::query()
                ->with('user:id,name')
                ->when(($filters['user_id'] ?? 'all') !== 'all', fn ($q) => $q->where('user_id', $filters['user_id']))
                ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($qq) => $qq
                    ->where('action', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
                ->latest('created_at')->latest('id')
                ->paginate(25)->withQueryString(),
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
