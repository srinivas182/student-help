import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface Stats {
    studentsHelped: number;
    resolved: number;
    active: number;
    rating: string | null;
    ratingsCount: number;
    fiveStars: number;
    averageAcceptHours: number | null;
    averageResolveHours: number | null;
    resourcesShared: number;
    resourceViews: number;
    thisMonth: number;
    streak: number;
}

export default function Tutor({
    stats,
    leaderboard,
    isVerified,
    name,
}: {
    stats: Stats;
    leaderboard: { name: string | null; resolved: number; rating: string | null }[];
    isVerified: boolean;
    name: string;
}) {
    const myRank = leaderboard.findIndex((row) => row.name === name);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">My impact</h2>}>
            <Head title="My impact" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
                <section className="rounded-2xl bg-gradient-to-r from-teal-600 to-emerald-600 p-6 text-white">
                    <h1 className="text-2xl font-semibold">
                        {stats.studentsHelped} student{stats.studentsHelped === 1 ? '' : 's'} helped
                    </h1>
                    <p className="mt-1 text-sm text-teal-50">
                        {stats.resolved} questions answered
                        {stats.rating ? ` · ★ ${stats.rating} from ${stats.ratingsCount} ratings` : ''}
                        {myRank >= 0 ? ` · #${myRank + 1} this month` : ''}
                    </p>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['This month', stats.thisMonth, 'questions answered'],
                        ['In progress', stats.active, 'right now'],
                        ['Five-star ratings', stats.fiveStars, `of ${stats.ratingsCount}`],
                        ['Material shared', stats.resourcesShared, `${stats.resourceViews} views`],
                    ].map(([label, value, sub]) => (
                        <div key={label as string} className="rounded-xl border border-slate-200 bg-white p-5">
                            <p className="text-xs uppercase tracking-wide text-slate-500">{label as string}</p>
                            <p className="mt-1 text-2xl font-semibold text-slate-900">{value as number}</p>
                            <p className="mt-0.5 text-xs text-slate-400">{sub as string}</p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold text-slate-900">How quickly you help</h2>
                        <dl className="mt-4 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Average time to accept</dt>
                                <dd className="font-semibold text-slate-900">
                                    {stats.averageAcceptHours !== null ? `${stats.averageAcceptHours}h` : '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-600">Average time to resolve</dt>
                                <dd className="font-semibold text-slate-900">
                                    {stats.averageResolveHours !== null ? `${stats.averageResolveHours}h` : '—'}
                                </dd>
                            </div>
                        </dl>

                        {isVerified && (
                            <Link
                                href={route('progress.certificate')}
                                className="mt-5 inline-block rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800"
                            >
                                Get my contribution certificate
                            </Link>
                        )}
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold text-slate-900">This month's top tutors</h2>
                        {leaderboard.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">Nothing resolved yet this month.</p>
                        ) : (
                            <ol className="mt-4 space-y-2">
                                {leaderboard.map((row, index) => (
                                    <li
                                        key={row.name}
                                        className={`flex items-center justify-between rounded-lg px-3 py-2 text-sm ${
                                            row.name === name ? 'bg-teal-50 font-medium' : ''
                                        }`}
                                    >
                                        <span className="text-slate-700">
                                            {index + 1}. {row.name}
                                        </span>
                                        <span className="text-slate-500">
                                            {row.resolved} {row.rating && `· ★ ${row.rating}`}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
