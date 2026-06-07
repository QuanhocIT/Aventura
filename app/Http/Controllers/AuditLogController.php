<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('view_audit_log'), 403);

        $restaurantId = $user->restaurant_id;

        $query = AuditLog::with('user')
            ->where('restaurant_id', $restaurantId)
            ->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('user_role')) {
            $query->where('user_role', $request->user_role);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from . ' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->paginate(30)->withQueryString();

        return Inertia::render('audit-logs/Index', [
            'logs'    => $logs->through(fn ($l) => [
                'id'           => $l->id,
                'user_name'    => $l->user?->name ?? 'Hệ thống',
                'user_email'   => $l->user?->email,
                'user_role'    => $l->user_role,
                'event'        => $l->event,
                'action'       => $l->action,
                'subject_type' => $l->subject_type ? class_basename($l->subject_type) : null,
                'subject_id'   => $l->subject_id,
                'ip_address'   => $l->ip_address,
                'old_values'   => $l->old_values,
                'new_values'   => $l->new_values,
                'created_at'   => $l->created_at->format('d/m/Y H:i:s'),
            ]),
            'filters' => $request->only(['action', 'user_role', 'from', 'to']),
            'total'   => $logs->total(),
        ]);
    }
}
