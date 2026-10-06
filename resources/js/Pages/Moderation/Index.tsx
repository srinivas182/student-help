import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface ReportRow {
    id: number;
    reason: string;
    notes: string | null;
    severity: string;
    status: string;
    reporter: string | null;
    type: string;
    subjectId: number;
    raised: string | null;
    outcome: string | null;
}

const REASON_LABELS: Record<string, string> = {
    inappropriate: 'Inappropriate content',
    contact_details: 'Moving off the platform',
    academic_dishonesty: 'Academic dishonesty',
    harassment: 'Harassment or bullying',
    spam: 'Spam or advertising',
    other: 'Other',
};

export default function Index({
    reports,
    filters,
    openCount,
    highPriorityCount,
}: {
    reports: { data: ReportRow[] };
    filters: { status: string };
    openCount: number;
    highPriorityCount: number;
}) {
    const [acting, setActing] = useState<number | null>(null);

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Moderation queue</h2>}
        >
            <Head title="Moderation queue" />

            <div className="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <p className="text-xs uppercase tracking-wide text-slate-500">Open reports</p>
                        <p className="mt-1 text-2xl font-semibold text-slate-900">{openCount}</p>
                    </div>
                    <div className="rounded-xl border border-rose-200 bg-rose-50 p-5">
                        <p className="text-xs uppercase tracking-wide text-rose-700">
                            High priority (involving a minor)
                        </p>
                        <p className="mt-1 text-2xl font-semibold text-rose-900">{highPriorityCount}</p>
                    </div>
                </div>

                <div className="flex gap-2">
                    {['open', 'closed'].map((status) => (
                        <button
                            key={status}
                            onClick={() =>
                                router.get(route('moderation.index'), status === 'closed' ? { status } : {}, {
                                    preserveState: true,
                                })
                            }
                            className={`rounded-full px-4 py-1.5 text-sm capitalize transition ${
                                (filters.status || 'open') === status
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200'
                            }`}
                        >
                            {status}
                        </button>
                    ))}
                </div>

                {reports.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing in the queue. Reports from students and tutors appear here.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {reports.data.map((report) => (
                            <li
                                key={report.id}
                                className={`rounded-xl border bg-white p-5 ${
                                    report.severity === 'high' ? 'border-rose-300' : 'border-slate-200'
                                }`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-slate-900">
                                                {REASON_LABELS[report.reason] ?? report.reason}
                                            </p>
                                            {report.severity === 'high' && (
                                                <span className="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-800">
                                                    Minor involved
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {report.type} #{report.subjectId} · reported by {report.reporter} ·{' '}
                                            {report.raised}
                                        </p>
                                        {report.notes && (
                                            <p className="mt-2 text-sm text-slate-600">{report.notes}</p>
                                        )}
                                        {report.outcome && (
                                            <p className="mt-2 rounded bg-slate-50 p-2 text-xs text-slate-600">
                                                Outcome: {report.outcome}
                                            </p>
                                        )}
                                    </div>

                                    {report.status === 'open' && (
                                        <button
                                            onClick={() => setActing(acting === report.id ? null : report.id)}
                                            className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                                        >
                                            Take action
                                        </button>
                                    )}
                                </div>

                                {acting === report.id && <ActionForm reportId={report.id} />}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function ActionForm({ reportId }: { reportId: number }) {
    const { data, setData, post, processing } = useForm({ action: 'dismiss', outcome: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('moderation.resolve', reportId), { preserveScroll: true });
            }}
            className="mt-4 space-y-3 rounded-lg bg-slate-50 p-4"
        >
            <div className="flex flex-wrap gap-2">
                {[
                    ['dismiss', 'Dismiss'],
                    ['remove_content', 'Remove content'],
                    ['warn_user', 'Warn user'],
                    ['suspend_user', 'Suspend user'],
                ].map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => setData('action', value)}
                        className={`rounded-full px-4 py-1.5 text-xs font-medium transition ${
                            data.action === value
                                ? 'bg-slate-900 text-white'
                                : 'bg-white text-slate-600 ring-1 ring-slate-200'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <textarea
                rows={2}
                required
                value={data.outcome}
                onChange={(e) => setData('outcome', e.target.value)}
                placeholder="Record what you decided and why. This is kept in the audit log."
                className="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            />

            <button
                type="submit"
                disabled={processing}
                className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
            >
                Close report
            </button>
        </form>
    );
}
