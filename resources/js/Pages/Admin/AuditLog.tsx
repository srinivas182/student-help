import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

interface Entry {
    id: number;
    action: string;
    actor: string;
    role: string | null;
    subject: string | null;
    context: Record<string, unknown>;
    ip: string | null;
    at: string;
}

export default function AuditLog({
    logs,
    filters,
    staff,
}: {
    logs: { data: Entry[] };
    filters: { action?: string; actor?: string };
    staff: { id: number; name: string }[];
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Audit log</h2>}>
            <Head title="Audit log" />

            <div className="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
                <p className="text-sm text-slate-600">
                    Every administrative, moderation and safety-relevant action, in order. Entries cannot be
                    edited or deleted.
                </p>

                <div className="flex flex-wrap gap-3">
                    <input
                        defaultValue={filters.action ?? ''}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                router.get(route('admin.audit'), {
                                    ...filters,
                                    action: (e.target as HTMLInputElement).value,
                                });
                            }
                        }}
                        placeholder="Filter by action, e.g. consent or tutor"
                        className="flex-1 rounded-lg border-slate-300 text-sm"
                    />
                    <select
                        value={filters.actor ?? ''}
                        onChange={(e) => router.get(route('admin.audit'), { ...filters, actor: e.target.value })}
                        className="rounded-lg border-slate-300 text-sm"
                    >
                        <option value="">Any staff member</option>
                        {staff.map((member) => (
                            <option key={member.id} value={member.id}>
                                {member.name}
                            </option>
                        ))}
                    </select>
                </div>

                <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    {logs.data.map((entry) => (
                        <li key={entry.id} className="px-5 py-3">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-mono text-xs text-indigo-700">{entry.action}</p>
                                <p className="text-xs text-slate-400">{entry.at}</p>
                            </div>
                            <p className="mt-1 text-sm text-slate-700">
                                {entry.actor}
                                {entry.role && <span className="text-slate-400"> ({entry.role})</span>}
                                {entry.subject && <span className="text-slate-500"> · {entry.subject}</span>}
                                {entry.ip && <span className="text-slate-400"> · {entry.ip}</span>}
                            </p>
                            {Object.keys(entry.context ?? {}).length > 0 && (
                                <p className="mt-1 font-mono text-[11px] text-slate-500">
                                    {JSON.stringify(entry.context)}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
