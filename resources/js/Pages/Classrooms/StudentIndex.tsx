import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Classroom {
    id: number;
    name: string;
    teacher: string | null;
    subject: string | null;
    institution: string | null;
    students: number;
    type: string;
}

interface Upcoming {
    id: number;
    title: string;
    classroom: string | null;
    classroomId: number;
    whenLabel: string;
    mode: string;
    location: string | null;
    isJoinable: boolean;
}

interface Discoverable {
    id: number;
    name: string;
    about: string | null;
    teacher: string | null;
    subject: string | null;
    students: number;
    nextSession: string | null;
}

/**
 * Two columns on desktop: what is happening on the left, joining on the right.
 * Joining used to be at the very bottom, below every class, so a learner with
 * no classes had to scroll past an empty state to find the one thing they
 * needed.
 */
export default function StudentIndex({
    classrooms,
    upcoming,
    discoverable,
}: {
    classrooms: Classroom[];
    upcoming: Upcoming[];
    discoverable: Discoverable[];
}) {
    const join = useForm({ join_code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        join.post(route('classrooms.join'), { onSuccess: () => join.reset() });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Classes</h2>}>
            <Head title="Classes" />

            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left: what is happening */}
                    <div className="space-y-6 lg:col-span-2">
                        {upcoming.length > 0 && (
                            <section className="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5">
                                <h2 className="font-semibold text-slate-900">Coming up</h2>

                                <ul className="mt-3 space-y-2">
                                    {upcoming.map((session) => (
                                        <li
                                            key={session.id}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-slate-900">
                                                    {session.title}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {session.classroom} · {session.whenLabel}
                                                    {session.mode === 'in_person' && session.location
                                                        ? ` · ${session.location}`
                                                        : ''}
                                                </p>
                                            </div>

                                            {session.isJoinable ? (
                                                <a
                                                    href={route('classrooms.sessions.join', session.id)}
                                                    className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white"
                                                >
                                                    {session.mode === 'online' ? 'Join now' : "I'm here"}
                                                </a>
                                            ) : (
                                                <Link
                                                    href={route('classrooms.show', session.classroomId)}
                                                    className="text-xs font-medium text-indigo-600"
                                                >
                                                    Details
                                                </Link>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        <section>
                            <h2 className="mb-3 font-semibold text-slate-900">
                                Your classes
                                {classrooms.length > 0 && (
                                    <span className="ml-2 text-sm font-normal text-slate-500">
                                        {classrooms.length}
                                    </span>
                                )}
                            </h2>

                            {classrooms.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                                    You are not in a class yet. Join one with a code, or pick an open
                                    class alongside.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {classrooms.map((classroom) => (
                                        <li key={classroom.id}>
                                            <Link
                                                href={route('classrooms.show', classroom.id)}
                                                className="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-slate-300"
                                            >
                                                <p className="font-medium text-slate-900">
                                                    {classroom.name}
                                                </p>
                                                <p className="mt-0.5 text-sm text-slate-600">
                                                    {classroom.teacher}
                                                    {classroom.subject && ` · ${classroom.subject}`}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {classroom.institution ?? 'Personal group'} ·{' '}
                                                    {classroom.students} learners
                                                </p>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>

                    {/* Right: joining, always visible without scrolling past anything */}
                    <aside className="space-y-5">
                        <section className="rounded-xl border border-slate-200 bg-white p-5">
                            <h2 className="font-semibold text-slate-900">Join with a code</h2>
                            <p className="mt-1 text-sm text-slate-600">
                                Your teacher will have given you a six-character code.
                            </p>

                            <form onSubmit={submit} className="mt-3 space-y-2">
                                <input
                                    value={join.data.join_code}
                                    onChange={(e) =>
                                        join.setData('join_code', e.target.value.toUpperCase())
                                    }
                                    placeholder="6WX7XU"
                                    maxLength={6}
                                    className="block w-full rounded-lg border-slate-300 text-center font-mono text-lg tracking-[0.3em] uppercase"
                                />
                                {join.errors.join_code && (
                                    <p className="text-xs text-rose-600">{join.errors.join_code}</p>
                                )}

                                <button
                                    disabled={join.processing || join.data.join_code.length < 6}
                                    className="w-full rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                                >
                                    {join.processing ? 'Joining…' : 'Join class'}
                                </button>
                            </form>
                        </section>

                        {discoverable.length > 0 && (
                            <section className="rounded-xl border border-slate-200 bg-white p-5">
                                <h2 className="font-semibold text-slate-900">Open classes</h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    In subjects you study. No code needed.
                                </p>

                                <ul className="mt-3 space-y-3">
                                    {discoverable.map((item) => (
                                        <li key={item.id} className="rounded-lg border border-slate-200 p-3">
                                            <p className="text-sm font-medium text-slate-900">{item.name}</p>
                                            <p className="text-xs text-slate-500">
                                                {item.teacher} · {item.students} learners
                                                {item.nextSession ? ` · next: ${item.nextSession}` : ''}
                                            </p>
                                            {item.about && (
                                                <p className="mt-1 text-xs text-slate-600">{item.about}</p>
                                            )}

                                            <button
                                                onClick={() =>
                                                    router.post(route('classrooms.requestJoin', item.id))
                                                }
                                                className="mt-2 w-full rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                            >
                                                Join
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
