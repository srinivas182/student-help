<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** ADM-06: the append-only record of who did what. */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
            ->when($request->string('action')->toString(), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($request->integer('actor'), fn ($q, $actor) => $q->where('actor_id', $actor))
            ->select([
                'audit_logs.id', 'audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id',
                'audit_logs.context', 'audit_logs.ip', 'audit_logs.created_at',
                'users.first_name', 'users.last_name', 'users.role',
            ])
            ->orderByDesc('audit_logs.id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => $row->id,
                'action' => $row->action,
                'actor' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: 'System',
                'role' => $row->role,
                'subject' => $row->subject_type ? class_basename($row->subject_type).' #'.$row->subject_id : null,
                'context' => json_decode($row->context ?? '{}', true),
                'ip' => $row->ip,
                'at' => $row->created_at,
            ]);

        return Inertia::render('Admin/AuditLog', [
            'logs' => $logs,
            'filters' => $request->only('action', 'actor'),
            'staff' => User::whereIn('role', [User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }
}
