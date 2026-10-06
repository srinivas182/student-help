import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Props {
    days: number;
    people: {
        students: number; newStudents: number; tutors: number;
        pendingTutors: number; awaitingConsent: number; suspended: number;
    };
    requests: {
        total: number; inPeriod: number; byStatus: Record<string, number>;
        unassigned: number; escalated: number;
    };
    service: {
        medianAcceptHours: number | null; medianResolveHours: number | null;
        resolutionRate: number | null; averageRating: number; lowRatings: number;
    };
    safety: { openReports: number; highPriority: number; flaggedMessages: number };
    topSubjects: { subject: string; total: number }[];
    busiestTutors: { name: string | null; resolved: number; rating: string | null }[];
}

function Card({ label, value, tone = 'text-slate-900', sub }: { label: string; value: string | number; tone?: string; sub?: string }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5">
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-1 text-2xl font-semibold ${tone}`}>{value}</p>
            {sub && <p className="mt-1 text-xs text-slate-400">{sub}</p>}
        </div>
    );
}

export default function Dashboard({ days, people, requests, service, safety, topSubjects, busiestTutors }: Props) {
    const needsAttention = [
        people.pendingTutors > 0 && {
            label: `${people.pendingTutors} tutor${people.pendingTutors === 1 ? '' : 's'} awaiting verification`,
            href: route('admin.verification.index'),
        },
        requests.escalated > 0 && {
            label: `${requests.escalated} request${requests.escalated === 1 ? '' : 's'} escalated with no tutor`,
            href: route('admin.users.index'),
        },
        safety.openReports > 0 && {
            label: `${safety.openReports} open report${safety.openReports === 1 ? '' : 's'}${safety.highPriority ? `, ${safety.highPriority} involving a minor` : ''}`,
            href: route('moderation.index'),
        },
    ].filter(Boolean) as { label: string; href: string }[];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Overview</h2>}>
            <Head title="Admin overview" />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">Showing the last {days} days.</p>
                    <div className="flex gap-2">
                        {[7, 30, 90].map((option) => (
                            <button
                                key={option}
                                onClick={() => router.get(route('admin.dashboard'), { days: option }, { preserveState: true })}
                                className={`rounded-full px-4 py-1.5 text-sm transition ${
                                    days === option ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'
                                }`}
                            >
                                {option} days
                            </button>
                        ))}
                    </div>
                </div>

                {needsAttention.length > 0 && (
                    <section className="rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <h3 className="font-semibold text-amber-900">Needs your attention</h3>
                        <ul className="mt-2 space-y-1">
                            {needsAttention.map((item) => (
                                <li key={item.label}>
                                    <Link href={item.href} className="text-sm text-amber-800 underline hover:text-amber-900">
                                        {item.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card label="Students" value={people.students} sub={`${people.newStudents} new`} />
                    <Card label="Verified tutors" value={people.tutors} sub={`${people.pendingTutors} pending`} />
                    <Card label="Help requests" value={requests.total} sub={`${requests.inPeriod} in period`} />
                    <Card
                        label="Awaiting guardian consent"
                        value={people.awaitingConsent}
                        tone={people.awaitingConsent > 0 ? 'text-amber-700' : 'text-slate-900'}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
                        <h3 className="font-semibold text-slate-900">Service levels</h3>
                        <dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                ['Median time to accept', service.medianAcceptHours !== null ? `${service.medianAcceptHours}h` : '—'],
                                ['Median time to resolve', service.medianResolveHours !== null ? `${service.medianResolveHours}h` : '—'],
                                ['Resolution rate', service.resolutionRate !== null ? `${service.resolutionRate}%` : '—'],
                                ['Average rating', service.averageRating ? `★ ${service.averageRating}` : '—'],
                            ].map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-xs uppercase tracking-wide text-slate-500">{label}</dt>
                                    <dd className="mt-1 text-xl font-semibold text-slate-900">{value}</dd>
                                </div>
                            ))}
                        </dl>

                        <div className="mt-5 flex flex-wrap gap-2">
                            {Object.entries(requests.byStatus).map(([status, total]) => (
                                <span key={status} className="rounded-full bg-slate-100 px-3 py-1 text-xs capitalize text-slate-700">
                                    {status}: {total}
                                </span>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 className="font-semibold text-slate-900">Safety</h3>
                        <dl className="mt-4 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Open reports</dt>
                                <dd className="font-semibold text-slate-900">{safety.openReports}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Involving a minor</dt>
                                <dd className={`font-semibold ${safety.highPriority ? 'text-rose-700' : 'text-slate-900'}`}>
                                    {safety.highPriority}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Flagged messages</dt>
                                <dd className="font-semibold text-slate-900">{safety.flaggedMessages}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Suspended accounts</dt>
                                <dd className="font-semibold text-slate-900">{people.suspended}</dd>
                            </div>
                        </dl>
                        <Link href={route('moderation.index')} className="mt-4 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-500">
                            Open moderation queue →
                        </Link>
                    </section>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 className="font-semibold text-slate-900">Most requested subjects</h3>
                        {topSubjects.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">No requests in this period.</p>
                        ) : (
                            <ul className="mt-4 space-y-2">
                                {topSubjects.map((row) => (
                                    <li key={row.subject} className="flex items-center gap-3">
                                        <span className="w-44 truncate text-sm text-slate-700">{row.subject}</span>
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="h-full rounded-full bg-indigo-500"
                                                style={{ width: `${(row.total / topSubjects[0].total) * 100}%` }}
                                            />
                                        </div>
                                        <span className="w-8 text-right text-sm text-slate-500">{row.total}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 className="font-semibold text-slate-900">Busiest tutors</h3>
                        <ul className="mt-4 divide-y divide-slate-100">
                            {busiestTutors.map((tutor) => (
                                <li key={tutor.name} className="flex items-center justify-between py-2.5 text-sm">
                                    <span className="text-slate-700">{tutor.name}</span>
                                    <span className="text-slate-500">
                                        {tutor.resolved} resolved {tutor.rating && `· ★ ${tutor.rating}`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
