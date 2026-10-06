import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface Stats {
    requestsRaised: number;
    requestsResolved: number;
    requestsOpen: number;
    inPeriod: number;
    subjects: number;
    subjectBreakdown: { subject: string; total: number }[];
    questionsAsked: number;
    answersGiven: number;
    acceptedAnswers: number;
    streak: number;
    activeDays: number;
}

function Tile({ label, value, sub }: { label: string; value: string | number; sub?: string }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5">
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-slate-900">{value}</p>
            {sub && <p className="mt-0.5 text-xs text-slate-400">{sub}</p>}
        </div>
    );
}

export default function Student({ stats, name }: { stats: Stats; name: string }) {
    const top = stats.subjectBreakdown[0]?.total ?? 1;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">My progress</h2>}>
            <Head title="My progress" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
                <section className="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white">
                    <h1 className="text-2xl font-semibold">
                        {stats.streak > 0
                            ? `${stats.streak} day streak, ${name}`
                            : `Welcome back, ${name}`}
                    </h1>
                    <p className="mt-1 text-sm text-indigo-100">
                        {stats.requestsResolved} question{stats.requestsResolved === 1 ? '' : 's'} answered ·{' '}
                        {stats.activeDays} active day{stats.activeDays === 1 ? '' : 's'} in the last month
                    </p>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Tile label="Questions asked" value={stats.requestsRaised} sub={`${stats.inPeriod} this month`} />
                    <Tile label="Resolved" value={stats.requestsResolved} />
                    <Tile label="Still open" value={stats.requestsOpen} />
                    <Tile label="Subjects" value={stats.subjects} />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold text-slate-900">Where you needed help</h2>
                        {stats.subjectBreakdown.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">
                                No requests yet. When you ask for help, this shows which subjects you use most.
                            </p>
                        ) : (
                            <ul className="mt-4 space-y-2">
                                {stats.subjectBreakdown.map((row) => (
                                    <li key={row.subject} className="flex items-center gap-3">
                                        <span className="w-36 truncate text-sm text-slate-700">{row.subject}</span>
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="h-full rounded-full bg-indigo-500"
                                                style={{ width: `${(row.total / top) * 100}%` }}
                                            />
                                        </div>
                                        <span className="w-6 text-right text-sm text-slate-500">{row.total}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold text-slate-900">Helping others</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Explaining something is one of the best ways to learn it.
                        </p>
                        <dl className="mt-4 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Questions you asked in the community</dt>
                                <dd className="font-semibold text-slate-900">{stats.questionsAsked}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Answers you gave</dt>
                                <dd className="font-semibold text-slate-900">{stats.answersGiven}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Answers marked as the best</dt>
                                <dd className="font-semibold text-emerald-700">{stats.acceptedAnswers}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
