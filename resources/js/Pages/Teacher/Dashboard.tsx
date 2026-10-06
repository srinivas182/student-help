import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Waiting {
    id: number;
    topic: string;
    subject: string | null;
    student: string | null;
    waiting: string | null;
    isEscalated: boolean;
}

interface Active {
    id: number;
    topic: string;
    subject: string | null;
    student: string | null;
    status: string;
    updatedAt: string | null;
}

interface Props {
    verification: { status: string | null; isVerified: boolean; isAvailable: boolean; notes: string | null };
    impact: {
        studentsHelped: number; resolved: number; rating: string | null; ratingsCount: number;
        materialShared: number; materialViews: number; thisMonth: number;
    };
    waiting: Waiting[];
    active: Active[];
    subjects: string[];
    recentFeedback: { stars: number; comment: string }[];
}

export default function Dashboard({ verification, impact, waiting, active, subjects, recentFeedback }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Teacher home</h2>}>
            <Head title="Teacher home" />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {!verification.isVerified && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5" role="status">
                        <p className="font-semibold text-amber-900">
                            {verification.status === 'rejected'
                                ? 'We need more information before you can start'
                                : 'Your application is being reviewed'}
                        </p>
                        <p className="mt-1 text-sm text-amber-800">
                            {verification.notes ??
                                'A DX administrator checks every tutor before they can work with students, usually within 48 hours.'}
                        </p>
                        <Link
                            href={route('tutor.profile')}
                            className="mt-3 inline-block text-sm font-semibold text-amber-900 underline"
                        >
                            Open my application
                        </Link>
                    </div>
                )}

                <section className="rounded-2xl bg-gradient-to-r from-teal-600 to-emerald-600 p-6 text-white">
                    <h1 className="text-2xl font-semibold">
                        You have helped {impact.studentsHelped} student
                        {impact.studentsHelped === 1 ? '' : 's'}
                    </h1>
                    <p className="mt-1 text-sm text-teal-50">
                        {impact.resolved} questions answered
                        {impact.rating ? ` · ★ ${impact.rating} from ${impact.ratingsCount} ratings` : ''}
                        {impact.materialShared
                            ? ` · ${impact.materialShared} resources shared, viewed ${impact.materialViews} times`
                            : ''}
                    </p>
                </section>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="rounded-xl border border-slate-200 bg-white lg:col-span-2">
                        <header className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <h2 className="font-semibold text-slate-900">Students waiting for you</h2>
                            <Link href={route('tutor.queue')} className="text-xs font-medium text-teal-700 hover:text-teal-600">
                                Full queue →
                            </Link>
                        </header>

                        {waiting.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-slate-500">
                                Nothing waiting right now. We will notify you when a student needs help in{' '}
                                {subjects.slice(0, 3).join(', ') || 'your subjects'}.
                            </p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {waiting.map((item) => (
                                    <li key={item.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium text-slate-900">{item.topic}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">
                                                {item.subject} · {item.student} · waiting {item.waiting}
                                            </p>
                                        </div>
                                        {item.isEscalated && (
                                            <span className="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800">
                                                Urgent
                                            </span>
                                        )}
                                        <button
                                            onClick={() => router.post(route('tutor.requests.accept', item.id))}
                                            className="rounded-lg bg-teal-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-teal-500"
                                        >
                                            Accept
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="space-y-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-5">
                            <h3 className="text-sm font-semibold text-slate-900">This month</h3>
                            <p className="mt-2 text-3xl font-semibold text-slate-900">{impact.thisMonth}</p>
                            <p className="text-xs text-slate-500">questions answered</p>

                            <button
                                onClick={() => router.post(route('tutor.availability'))}
                                className={`mt-4 w-full rounded-lg px-4 py-2 text-sm font-medium transition ${
                                    verification.isAvailable
                                        ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                        : 'bg-slate-200 text-slate-600 hover:bg-slate-300'
                                }`}
                            >
                                {verification.isAvailable ? 'Available for requests' : 'Not available'}
                            </button>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-5">
                            <h3 className="text-sm font-semibold text-slate-900">Share with your students</h3>
                            <p className="mt-1 text-xs text-slate-500">
                                Notes, past papers, solutions and voice notes.
                            </p>
                            <Link
                                href={route('resources.mine')}
                                className="mt-3 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                            >
                                Share material
                            </Link>
                        </div>
                    </section>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl border border-slate-200 bg-white">
                        <header className="border-b border-slate-100 px-5 py-4">
                            <h2 className="font-semibold text-slate-900">In progress</h2>
                        </header>
                        {active.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-slate-500">Nothing in progress.</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {active.map((item) => (
                                    <li key={item.id} className="flex items-center gap-3 px-5 py-3.5">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium text-slate-900">{item.topic}</p>
                                            <p className="text-xs text-slate-500">
                                                {item.student} · {item.updatedAt}
                                            </p>
                                        </div>
                                        <Link
                                            href={route('conversations.show', item.id)}
                                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Open
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold text-slate-900">What students say</h2>
                        {recentFeedback.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">No written feedback yet.</p>
                        ) : (
                            <ul className="mt-4 space-y-4">
                                {recentFeedback.map((item, index) => (
                                    <li key={index} className="border-l-2 border-amber-300 pl-3">
                                        <p className="text-amber-500">{'★'.repeat(item.stars)}</p>
                                        <p className="mt-1 text-sm text-slate-700">{item.comment}</p>
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
