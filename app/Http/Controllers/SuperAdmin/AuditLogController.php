<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::with(['user', 'restaurant'])
            ->latest('created_at');

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('super-admin/audit-log/Index', [
            'logs'        => $logs->through(fn ($l) => [
                'id'           => $l->id,
                'restaurant'   => $l->restaurant?->name ?? null,
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
            'restaurants' => Restaurant::orderBy('name')->get(['id', 'name']),
            'filters'     => $request->only(['restaurant_id', 'event', 'action', 'from', 'to']),
            'total'       => $logs->total(),
        ]);
    }
}
