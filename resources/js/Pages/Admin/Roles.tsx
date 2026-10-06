import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    permissions: string[];
    isSystem: boolean;
    users: number;
}

interface Staff {
    id: number;
    name: string;
    email: string;
    baseRole: string;
    roles: { id: number; name: string }[];
    scopes: { id: number; subject: string; language: string }[];
}

export default function Roles({
    roles,
    permissionGroups,
    staff,
    tutors,
    subjects,
    languages,
}: {
    roles: Role[];
    permissionGroups: Record<string, Record<string, string>>;
    staff: Staff[];
    tutors: { id: number; name: string }[];
    subjects: { id: number; name: string }[];
    languages: { id: number; name: string; native_name: string }[];
}) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);
    const [scopingFor, setScopingFor] = useState<number | null>(null);

    const roleForm = useForm<{ name: string; description: string; permissions: string[] }>({
        name: '',
        description: '',
        permissions: [],
    });

    const assign = useForm({ user_id: '', role_id: '' });
    const scope = useForm({ user_id: '', curriculum_item_id: '', language_id: '' });

    const openRole = (role: Role | null) => {
        if (role) {
            setEditing(role.id);
            roleForm.setData({
                name: role.name,
                description: role.description ?? '',
                permissions: role.permissions,
            });
        } else {
            setEditing('new');
            roleForm.reset();
        }
    };

    const togglePermission = (permission: string) =>
        roleForm.setData(
            'permissions',
            roleForm.data.permissions.includes(permission)
                ? roleForm.data.permissions.filter((p) => p !== permission)
                : [...roleForm.data.permissions, permission],
        );

    const submitRole: FormEventHandler = (e) => {
        e.preventDefault();

        if (editing === 'new') {
            roleForm.post(route('admin.roles.store'), { onSuccess: () => setEditing(null) });
        } else if (typeof editing === 'number') {
            roleForm.put(route('admin.roles.update', editing), { onSuccess: () => setEditing(null) });
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Roles & access</h2>}>
            <Head title="Roles and access" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">
                        Give people exactly the access their job needs. A content reviewer can approve lessons
                        without reaching users, settings or private conversations.
                    </p>
                    <div className="flex gap-2">
                        <Link
                            href={route('admin.roles.activity')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Activity log
                        </Link>
                        <button
                            onClick={() => openRole(null)}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            New role
                        </button>
                    </div>
                </div>

                {editing !== null && (
                    <form onSubmit={submitRole} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <input
                                value={roleForm.data.name}
                                onChange={(e) => roleForm.setData('name', e.target.value)}
                                placeholder="Role name"
                                className="rounded-lg border-slate-300 text-sm"
                            />
                            <input
                                value={roleForm.data.description}
                                onChange={(e) => roleForm.setData('description', e.target.value)}
                                placeholder="What is this role for?"
                                className="rounded-lg border-slate-300 text-sm"
                            />
                        </div>

                        {Object.entries(permissionGroups).map(([group, permissions]) => (
                            <div key={group}>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {group}
                                </p>
                                <div className="mt-2 space-y-1.5">
                                    {Object.entries(permissions).map(([key, label]) => (
                                        <label key={key} className="flex items-start gap-3 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={roleForm.data.permissions.includes(key)}
                                                onChange={() => togglePermission(key)}
                                                className="mt-0.5 rounded border-slate-300 text-indigo-600"
                                            />
                                            <span>{label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}

                        <div className="flex gap-3">
                            <button className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">
                                {editing === 'new' ? 'Create role' : 'Save role'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setEditing(null)}
                                className="rounded-lg border border-slate-300 px-5 py-2 text-sm text-slate-600"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}

                <section className="space-y-3">
                    {roles.map((role) => (
                        <div key={role.id} className="rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-slate-900">{role.name}</p>
                                        {role.isSystem && (
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">
                                                built in
                                            </span>
                                        )}
                                    </div>
                                    {role.description && (
                                        <p className="mt-0.5 text-sm text-slate-600">{role.description}</p>
                                    )}
                                    <p className="mt-1 text-xs text-slate-400">
                                        {role.permissions.length} permissions · {role.users} people
                                    </p>
                                </div>

                                <div className="flex gap-2">
                                    <button
                                        onClick={() => openRole(role)}
                                        className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                    >
                                        Edit
                                    </button>
                                    {!role.isSystem && (
                                        <button
                                            onClick={() =>
                                                router.delete(route('admin.roles.destroy', role.id), {
                                                    preserveScroll: true,
                                                })
                                            }
                                            className="text-xs font-medium text-rose-600 hover:text-rose-500"
                                        >
                                            Delete
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">Assign a role</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Verified tutors make the best content reviewers — they have the subject knowledge.
                    </p>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            assign.post(route('admin.roles.assign'), { preserveScroll: true });
                        }}
                        className="mt-3 flex flex-wrap gap-3"
                    >
                        <select
                            value={assign.data.user_id}
                            onChange={(e) => assign.setData('user_id', e.target.value)}
                            className="flex-1 rounded-lg border-slate-300 text-sm"
                        >
                            <option value="">Choose a person…</option>
                            <optgroup label="Staff">
                                {staff.map((person) => (
                                    <option key={person.id} value={person.id}>
                                        {person.name}
                                    </option>
                                ))}
                            </optgroup>
                            <optgroup label="Verified tutors">
                                {tutors.map((tutor) => (
                                    <option key={tutor.id} value={tutor.id}>
                                        {tutor.name}
                                    </option>
                                ))}
                            </optgroup>
                        </select>

                        <select
                            value={assign.data.role_id}
                            onChange={(e) => assign.setData('role_id', e.target.value)}
                            className="flex-1 rounded-lg border-slate-300 text-sm"
                        >
                            <option value="">Choose a role…</option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                </option>
                            ))}
                        </select>

                        <button className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white">
                            Assign
                        </button>
                    </form>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">People and their access</h2>

                    <ul className="mt-4 divide-y divide-slate-100">
                        {staff.map((person) => (
                            <li key={person.id} className="py-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">{person.name}</p>
                                        <p className="text-xs text-slate-500">
                                            {person.email} · {person.baseRole.replace('_', ' ')}
                                        </p>

                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {person.roles.length === 0 ? (
                                                <span className="text-xs text-slate-400">No roles assigned</span>
                                            ) : (
                                                person.roles.map((role) => (
                                                    <span
                                                        key={role.id}
                                                        className="flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-[11px] font-medium text-indigo-800"
                                                    >
                                                        {role.name}
                                                        <button
                                                            onClick={() =>
                                                                router.delete(
                                                                    route('admin.roles.revoke', [role.id, person.id]),
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                            className="text-indigo-500 hover:text-rose-600"
                                                        >
                                                            ×
                                                        </button>
                                                    </span>
                                                ))
                                            )}
                                        </div>

                                        {person.scopes.length > 0 && (
                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {person.scopes.map((item) => (
                                                    <span
                                                        key={item.id}
                                                        className="flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-600"
                                                    >
                                                        {item.subject} · {item.language}
                                                        <button
                                                            onClick={() =>
                                                                router.delete(
                                                                    route('admin.roles.scopes.remove', item.id),
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                            className="hover:text-rose-600"
                                                        >
                                                            ×
                                                        </button>
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    <button
                                        onClick={() => {
                                            setScopingFor(scopingFor === person.id ? null : person.id);
                                            scope.setData('user_id', String(person.id));
                                        }}
                                        className="text-xs font-medium text-slate-600 hover:text-slate-900"
                                    >
                                        Limit to subjects
                                    </button>
                                </div>

                                {scopingFor === person.id && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            scope.post(route('admin.roles.scopes.add'), {
                                                preserveScroll: true,
                                                onSuccess: () => setScopingFor(null),
                                            });
                                        }}
                                        className="mt-3 flex flex-wrap gap-2 rounded-lg bg-slate-50 p-3"
                                    >
                                        <select
                                            value={scope.data.curriculum_item_id}
                                            onChange={(e) => scope.setData('curriculum_item_id', e.target.value)}
                                            className="flex-1 rounded-lg border-slate-300 text-xs"
                                        >
                                            <option value="">All subjects</option>
                                            {subjects.map((subject) => (
                                                <option key={subject.id} value={subject.id}>
                                                    {subject.name}
                                                </option>
                                            ))}
                                        </select>

                                        <select
                                            value={scope.data.language_id}
                                            onChange={(e) => scope.setData('language_id', e.target.value)}
                                            className="flex-1 rounded-lg border-slate-300 text-xs"
                                        >
                                            <option value="">All languages</option>
                                            {languages.map((language) => (
                                                <option key={language.id} value={language.id}>
                                                    {language.native_name}
                                                </option>
                                            ))}
                                        </select>

                                        <button className="rounded-lg bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white">
                                            Add limit
                                        </button>
                                    </form>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
