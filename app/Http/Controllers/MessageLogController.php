<?php

namespace App\Http\Controllers;

use App\Models\MessageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every outbound SMS/WhatsApp the app attempted, so staff can see what was
 * actually sent (and what failed) instead of guessing.
 */
class MessageLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MessageLog::class);

        $filters = $request->only(['search', 'status', 'channel']);

        $logs = MessageLog::query()
            ->with(['customer:id,full_name', 'invoice:id,invoice_number'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('to', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($cq) => $cq->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['channel'] ?? 'all') !== 'all', fn ($query) => $query->where('channel', $filters['channel']))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('message-logs', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }
}
