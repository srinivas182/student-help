import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    isMinor: boolean;
    consent: string | null;
    joined: string | null;
}

interface Invitation {
    id: number;
    name: string;
    email: string;
    role: string;
}

export default function Index({
    users,
    filters,
    roles,
    canInvite,
    invitations,
}: {
    users: { data: Row[] };
    filters: Record<string, string>;
    roles: string[];
    canInvite: boolean;
    invitations: Invitation[];
}) {
    const [inviting, setInviting] = useState(false);
    const [suspending, setSuspending] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const invite = useForm({ name: '', email: '', role: 'moderator' });
    const suspend = useForm({ reason: '' });

    const applyFilter = (changes: Record<string, string>) =>
        router.get(route('admin.users.index'), { ...filters, ...changes }, { preserveState: true, replace: true });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Users</h2>}>
            <Head title="Users" />

            <div className="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-end gap-3">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            applyFilter({ search });
                        }}
                        className="flex-1"
                    >
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or email…"
                            className="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </form>

                    <select
                        value={filters.role ?? ''}
                        onChange={(e) => applyFilter({ role: e.target.value })}
                        className="rounded-lg border-slate-300 text-sm capitalize focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All roles</option>
                        {roles.map((role) => (
                            <option key={role} value={role}>
                                {role.replace('_', ' ')}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>

                    {canInvite && (
                        <button
                            onClick={() => setInviting((value) => !value)}
                            className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            Invite staff
                        </button>
                    )}
                </div>

                {inviting && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            invite.post(route('admin.invitations.send'), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    invite.reset();
                                    setInviting(false);
                                },
                            });
                        }}
                        className="grid gap-3 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-4"
                    >
                        <div>
                            <input
                                value={invite.data.name}
                                onChange={(e) => invite.setData('name', e.target.value)}
                                placeholder="Full name"
                                className="w-full rounded-lg border-slate-300 text-sm"
                            />
                            {invite.errors.name && <p className="mt-1 text-xs text-rose-600">{invite.errors.name}</p>}
                        </div>
                        <div>
                            <input
                                type="email"
                                value={invite.data.email}
                                onChange={(e) => invite.setData('email', e.target.value)}
                                placeholder="Email address"
                                className="w-full rounded-lg border-slate-300 text-sm"
                            />
                            {invite.errors.email && <p className="mt-1 text-xs text-rose-600">{invite.errors.email}</p>}
                        </div>
                        <select
                            value={invite.data.role}
                            onChange={(e) => invite.setData('role', e.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        >
                            <option value="moderator">Moderator</option>
                            <option value="admin">Administrator</option>
                        </select>
                        <button
                            type="submit"
                            disabled={invite.processing}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Send invitation
                        </button>
                    </form>
                )}

                {invitations.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 className="text-sm font-semibold text-slate-900">Pending invitations</h3>
                        <ul className="mt-3 divide-y divide-slate-100">
                            {invitations.map((invitation) => (
                                <li key={invitation.id} className="flex items-center justify-between py-2.5 text-sm">
                                    <span className="text-slate-700">
                                        {invitation.name} · {invitation.email} ·{' '}
                                        <span className="capitalize">{invitation.role}</span>
                                    </span>
                                    <button
                                        onClick={() =>
                                            router.delete(route('admin.invitations.revoke', invitation.id), {
                                                preserveScroll: true,
                                            })
                                        }
                                        className="text-xs font-medium text-rose-600 hover:text-rose-500"
                                    >
                                        Revoke
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-5 py-3">Name</th>
                                <th className="px-5 py-3">Role</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3">Joined</th>
                                <th className="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-slate-900">{user.name}</p>
                                        <p className="text-xs text-slate-500">{user.email}</p>
                                        {user.isMinor && (
                                            <span className="mt-1 inline-block rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-800">
                                                Minor · consent {user.consent ?? 'not requested'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 capitalize text-slate-600">
                                        {user.role.replace('_', ' ')}
                                    </td>
                                    <td className="px-5 py-3">
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                user.status === 'active'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-rose-100 text-rose-800'
                                            }`}
                                        >
                                            {user.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-slate-500">{user.joined}</td>
                                    <td className="px-5 py-3 text-right">
                                        {user.status === 'active' ? (
                                            <button
                                                onClick={() => setSuspending(suspending === user.id ? null : user.id)}
                                                className="text-xs font-medium text-rose-600 hover:text-rose-500"
                                            >
                                                Suspend
                                            </button>
                                        ) : (
                                            <button
                                                onClick={() =>
                                                    router.post(route('admin.users.reinstate', user.id), {}, {
                                                        preserveScroll: true,
                                                    })
                                                }
                                                className="text-xs font-medium text-emerald-700 hover:text-emerald-600"
                                            >
                                                Reinstate
                                            </button>
                                        )}

                                        {suspending === user.id && (
                                            <form
                                                onSubmit={(e) => {
                                                    e.preventDefault();
                                                    suspend.post(route('admin.users.suspend', user.id), {
                                                        preserveScroll: true,
                                                        onSuccess: () => setSuspending(null),
                                                    });
                                                }}
                                                className="mt-2 flex gap-2"
                                            >
                                                <input
                                                    value={suspend.data.reason}
                                                    onChange={(e) => suspend.setData('reason', e.target.value)}
                                                    placeholder="Reason (recorded in the audit log)"
                                                    className="w-56 rounded-lg border-slate-300 text-xs"
                                                />
                                                <button className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white">
                                                    Confirm
                                                </button>
                                            </form>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
