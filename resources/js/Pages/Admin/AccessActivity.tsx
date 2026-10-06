import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Entry {
    id: number;
    action: string;
    actor: string;
    context: Record<string, unknown>;
    at: string;
}

const LABELS: Record<string, string> = {
    'role.created': 'Role created',
    'role.updated': 'Role changed',
    'role.deleted': 'Role deleted',
    'role.assigned': 'Role assigned',
    'role.revoked': 'Role removed',
    'reviewer.scope_added': 'Reviewer limited to subject',
    'reviewer.scope_removed': 'Reviewer limit removed',
    'topic.created': 'Topic created',
    'topic.generated': 'Lesson generated',
    'topic.reviewed': 'Lesson reviewed',
    'topic.published': 'Lesson published',
    'topic.rejected': 'Lesson rejected',
};

export default function AccessActivity({
    entries,
    actions,
    filters,
}: {
    entries: { data: Entry[] };
    actions: string[];
    filters: { action?: string };
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Access activity</h2>}>
            <Head title="Access activity" />

            <div className="mx-auto max-w-4xl space-y-4 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">
                        Who was given access, and who approved which lesson. Append-only.
                    </p>
                    <Link href={route('admin.roles.index')} className="text-sm text-indigo-600 hover:text-indigo-500">
                        ← Roles
                    </Link>
                </div>

                <select
                    value={filters.action ?? ''}
                    onChange={(e) => router.get(route('admin.roles.activity'), { action: e.target.value })}
                    className="rounded-lg border-slate-300 text-sm"
                >
                    <option value="">All activity</option>
                    {actions.map((action) => (
                        <option key={action} value={action}>
                            {LABELS[action] ?? action}
                        </option>
                    ))}
                </select>

                {entries.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing recorded yet.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {entries.data.map((entry) => (
                            <li key={entry.id} className="px-5 py-3">
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <p className="text-sm font-medium text-slate-900">
                                        {LABELS[entry.action] ?? entry.action}
                                    </p>
                                    <p className="text-xs text-slate-400">{entry.at}</p>
                                </div>
                                <p className="mt-0.5 text-sm text-slate-600">{entry.actor}</p>
                                {Object.keys(entry.context ?? {}).length > 0 && (
                                    <p className="mt-1 font-mono text-[11px] text-slate-500">
                                        {JSON.stringify(entry.context)}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
