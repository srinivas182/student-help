import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

interface RequestRow {
    id: number;
    topic: string;
    subject: string | null;
    status: string;
    counterpart: string | null;
    updated: string | null;
}

interface Props {
    context: string[];
    subjects: { id: number; name: string; code: string | null }[];
    requests: RequestRow[];
    stats: { open: number; resolved: number; subjects: number; rating: string | null };
    participation: { canParticipate: boolean; isMinor: boolean; consentStatus: string | null };
}

const STATUS_STYLES: Record<string, string> = {
    open: 'bg-amber-100 text-amber-800',
    escalated: 'bg-rose-100 text-rose-800',
    assigned: 'bg-sky-100 text-sky-800',
    resolved: 'bg-emerald-100 text-emerald-800',
    closed: 'bg-slate-100 text-slate-600',
    cancelled: 'bg-slate-100 text-slate-500',
};

function Stat({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
        </div>
    );
}

export default function Dashboard({ context, subjects, requests, stats, participation }: Props) {
    const user = usePage().props.auth.user as { first_name?: string; name: string };
    const firstName = user.first_name ?? user.name.split(' ')[0];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* CON-03: a minor without guardian consent can browse but not participate */}
                {!participation.canParticipate && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5" role="status">
                        <h3 className="font-semibold text-amber-900">Waiting for parent or guardian approval</h3>
                        <p className="mt-1 text-sm text-amber-800">
                            You can browse study resources and announcements now. Asking a tutor for help,
                            messaging and posting in the community unlock as soon as your guardian approves
                            the consent email we sent them.
                        </p>
                    </div>
                )}

                <section className="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white">
                    <h1 className="text-2xl font-semibold">Hello {firstName}</h1>
                    <p className="mt-1 text-sm text-indigo-100">
                        {context.length > 0 ? context.join(' · ') : 'Your learning space'}
                    </p>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label="Open requests" value={stats.open} />
                    <Stat label="Resolved" value={stats.resolved} />
                    <Stat label="My subjects" value={stats.subjects} />
                    <Stat label={stats.rating ? 'Average rating' : 'Study streak'} value={stats.rating ?? '—'} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="rounded-xl border border-slate-200 bg-white lg:col-span-2">
                        <header className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <h2 className="font-semibold text-slate-900">Recent help requests</h2>
                            <span className="text-xs text-slate-400">Last 5</span>
                        </header>

                        {requests.length === 0 ? (
                            <div className="px-5 py-12 text-center">
                                <p className="text-sm text-slate-500">
                                    No help requests yet. When you get stuck on a topic, ask a tutor and it
                                    will appear here.
                                </p>
                                <button
                                    disabled={!participation.canParticipate}
                                    className="mt-4 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                                >
                                    Ask for help
                                </button>
                            </div>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {requests.map((request) => (
                                    <li key={request.id} className="flex items-center gap-4 px-5 py-4">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium text-slate-900">{request.topic}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">
                                                {request.subject}
                                                {request.counterpart ? ` · ${request.counterpart}` : ''}
                                                {request.updated ? ` · ${request.updated}` : ''}
                                            </p>
                                        </div>
                                        <span
                                            className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium capitalize ${
                                                STATUS_STYLES[request.status] ?? 'bg-slate-100 text-slate-600'
                                            }`}
                                        >
                                            {request.status}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white">
                        <header className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <h2 className="font-semibold text-slate-900">My subjects</h2>
                            <Link
                                href={route('onboarding.show')}
                                className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Edit
                            </Link>
                        </header>

                        {subjects.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-slate-500">
                                No subjects selected yet.
                            </p>
                        ) : (
                            <ul className="flex flex-wrap gap-2 p-5">
                                {subjects.map((subject) => (
                                    <li
                                        key={subject.id}
                                        className="rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-700"
                                    >
                                        {subject.name}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
