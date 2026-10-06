<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Access\Models\ReviewerScope;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Permissions;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Roles', [
            'roles' => Role::withCount('users')->orderBy('name')->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'permissions' => $role->permissions,
                    'isSystem' => $role->is_system,
                    'users' => $role->users_count,
                ]),
            'permissionGroups' => Permissions::GROUPS,
            'staff' => User::whereIn('role', [User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
                ->orWhereHas('roles')
                ->with(['roles:id,name', 'reviewerScopes.subject:id,name', 'reviewerScopes.language:id,name'])
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'baseRole' => $user->role,
                    'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
                    'scopes' => $user->reviewerScopes->map(fn (ReviewerScope $scope) => [
                        'id' => $scope->id,
                        'subject' => $scope->subject?->name ?? 'All subjects',
                        'language' => $scope->language?->name ?? 'All languages',
                    ]),
                ]),
            'tutors' => User::where('role', User::ROLE_TUTOR)
                ->whereHas('tutorProfile', fn ($q) => $q->where('verification_status', 'approved'))
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
            'subjects' => CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
                ->active()->orderBy('name')->limit(300)->get(['id', 'name'])
                ->unique('name')->values(),
            'languages' => Language::active()->get(['id', 'name', 'native_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
        ]);

        $permissions = array_values(array_filter($validated['permissions'], Permissions::exists(...)));

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'permissions' => $permissions,
            'is_system' => false,
        ]);

        audit('role.created', $role, ['permissions' => $permissions]);

        return back()->with('success', "Role \"{$role->name}\" created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
        ]);

        $before = $role->permissions;
        $permissions = array_values(array_filter($validated['permissions'], Permissions::exists(...)));

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $permissions,
        ]);

        audit('role.updated', $role, ['before' => $before, 'after' => $permissions]);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 422, 'Built-in roles cannot be deleted.');

        audit('role.deleted', $role, ['name' => $role->name]);
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        DB::table('role_user')->updateOrInsert(
            ['user_id' => $validated['user_id'], 'role_id' => $validated['role_id']],
            ['assigned_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()],
        );

        $user = User::find($validated['user_id']);
        $role = Role::find($validated['role_id']);

        audit('role.assigned', $user, ['role' => $role?->name, 'by' => $request->user()->id]);

        return back()->with('success', "{$role?->name} assigned to {$user?->first_name}.");
    }

    public function revoke(Request $request, Role $role, User $user): RedirectResponse
    {
        DB::table('role_user')->where('user_id', $user->id)->where('role_id', $role->id)->delete();

        audit('role.revoked', $user, ['role' => $role->name, 'by' => $request->user()->id]);

        return back()->with('success', 'Role removed.');
    }

    /** Narrow a reviewer to the subjects and languages they are qualified for. */
    public function addScope(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'curriculum_item_id' => ['nullable', 'integer', 'exists:curriculum_items,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
        ]);

        ReviewerScope::updateOrCreate($validated);

        audit('reviewer.scope_added', User::find($validated['user_id']), $validated);

        return back()->with('success', 'Reviewer scope added.');
    }

    public function removeScope(ReviewerScope $scope): RedirectResponse
    {
        audit('reviewer.scope_removed', $scope->user, [
            'subject_id' => $scope->curriculum_item_id,
            'language_id' => $scope->language_id,
        ]);

        $scope->delete();

        return back()->with('success', 'Scope removed.');
    }

    /** Who did what with content and access — the questions DX will actually ask. */
    public function activity(Request $request): Response
    {
        $actions = [
            'role.created', 'role.updated', 'role.deleted', 'role.assigned', 'role.revoked',
            'reviewer.scope_added', 'reviewer.scope_removed',
            'topic.created', 'topic.generated', 'topic.reviewed', 'topic.published', 'topic.rejected',
        ];

        return Inertia::render('Admin/AccessActivity', [
            'entries' => DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
                ->whereIn('audit_logs.action', $actions)
                ->when($request->string('action')->toString(),
                    fn ($q, $action) => $q->where('audit_logs.action', $action))
                ->select([
                    'audit_logs.id', 'audit_logs.action', 'audit_logs.context',
                    'audit_logs.created_at', 'users.first_name', 'users.last_name',
                ])
                ->orderByDesc('audit_logs.id')
                ->paginate(40)
                ->withQueryString()
                ->through(fn ($row) => [
                    'id' => $row->id,
                    'action' => $row->action,
                    'actor' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: 'System',
                    'context' => json_decode($row->context ?? '{}', true),
                    'at' => $row->created_at,
                ]),
            'actions' => $actions,
            'filters' => $request->only('action'),
        ]);
    }
}
