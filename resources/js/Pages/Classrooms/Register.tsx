import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface Entry {
    id: number;
    name: string;
    response: string | null;
    attended: boolean;
    attendedAt: string | null;
    watchedRecording: boolean;
}

/**
 * Who said they would come, who did, and who caught up afterwards.
 *
 * The last column is what makes uploading a recording worth the effort: a
 * teacher can see that eleven people who missed the session still watched it.
 */
export default function Register({
    session,
    register,
    summary,
}: {
    session: {
        id: number;
        title: string;
        whenLabel: string;
        mode: string;
        hasRecording: boolean;
        classroomId: number;
        classroomName: string | null;
    };
    register: Entry[];
    summary: {
        members: number;
        attended: number;
        saidGoing: number;
        watchedRecording: number;
    };
}) {
    const stats = [
        ['In the class', summary.members],
        ['Said they would come', summary.saidGoing],
        ['Turned up', summary.attended],
        ...(session.hasRecording
            ? ([['Watched the recording', summary.watchedRecording]] as [string, number][])
            : []),
    ] as [string, number][];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Attendance</h2>}
        >
            <Head title={`Attendance — ${session.title}`} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <Link
                    href={route('classrooms.show', session.classroomId)}
                    className="text-sm text-slate-500 hover:text-slate-800"
                >
                    ← {session.classroomName ?? 'Back to the class'}
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h1 className="text-lg font-semibold text-slate-900">{session.title}</h1>
                    <p className="mt-0.5 text-sm text-slate-600">
                        {session.whenLabel} · {session.mode === 'online' ? 'Online' : 'In person'}
                    </p>

                    <dl className="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-4">
                        {stats.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs uppercase tracking-wide text-slate-500">
                                    {label}
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold text-slate-900">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    {register.length === 0 ? (
                        <p className="px-6 py-10 text-center text-sm text-slate-500">
                            Nobody has joined this class yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {register.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                                >
                                    <span className="text-sm font-medium text-slate-800">
                                        {entry.name}
                                    </span>

                                    <span className="flex items-center gap-2 text-xs">
                                        {entry.attended ? (
                                            <span className="rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-800">
                                                Attended{entry.attendedAt && ` · ${entry.attendedAt}`}
                                            </span>
                                        ) : entry.response === 'going' ? (
                                            <span className="rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-800">
                                                Said going, did not attend
                                            </span>
                                        ) : entry.response === 'not_going' ? (
                                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">
                                                Could not make it
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-slate-500">
                                                No response
                                            </span>
                                        )}

                                        {entry.watchedRecording && (
                                            <span className="rounded-full bg-indigo-100 px-2.5 py-1 font-medium text-indigo-800">
                                                Watched recording
                                            </span>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <p className="text-xs text-slate-500">
                    Attendance is recorded when someone opens the session, so a learner who joined late
                    still counts. It is not a substitute for a school register.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
