import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { router } from '@inertiajs/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Item {
    id: number;
    displayName: string;
    school: string | null;
    subject: string | null;
    teacher: string | null;
    students: number;
}

export default function StudentIndex({
    upcoming,
    discoverable, classrooms }: {
    upcoming: { id: number; title: string; classroom: string | null; classroomId: number; whenLabel: string; mode: string; location: string | null; isJoinable: boolean }[];
    discoverable: { id: number; name: string; about: string | null; teacher: string | null; subject: string | null; students: number; nextSession: string | null }[]; classrooms: Item[] }) {
    const { data, setData, post, processing, errors } = useForm({ join_code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('classrooms.join'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">My classes</h2>}>
            <Head title="My classes" />

                {/* What is actually happening, before the list of classes */}
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
                                        <p className="text-sm font-medium text-slate-900">{session.title}</p>
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

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-6">
                    <label className="text-sm font-medium text-slate-900">Join a class</label>
                    <p className="mt-1 text-xs text-slate-500">
                        Your teacher will give you a six-character code.
                    </p>
                    <div className="mt-3 flex gap-3">
                        <input
                            value={data.join_code}
                            onChange={(e) => setData('join_code', e.target.value.toUpperCase())}
                            placeholder="ABC123"
                            maxLength={12}
                            className="w-40 rounded-lg border-slate-300 text-center font-mono text-lg tracking-widest uppercase"
                        />
                        <button
                            type="submit"
                            disabled={processing || data.join_code.length < 4}
                            className="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                        >
                            Join
                        </button>
                    </div>
                    {errors.join_code && <p className="mt-2 text-sm text-rose-600">{errors.join_code}</p>}
                </form>

                {classrooms.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-sm text-slate-500">
                        You are not in a class yet. Ask your teacher for a join code.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {classrooms.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={route('classrooms.show', item.id)}
                                    className="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm"
                                >
                                    <p className="font-medium text-slate-900">{item.displayName}</p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {item.teacher}
                                        {item.subject ? ` · ${item.subject}` : ''} · {item.students} student
                                        {item.students === 1 ? '' : 's'}
                                    </p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        
                {discoverable.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-6">
                        <h2 className="font-semibold text-slate-900">Classes you can join</h2>
                        <p className="mt-1 text-sm text-slate-600">
                            Open classes in the subjects you study. No code needed.
                        </p>

                        <ul className="mt-4 space-y-3">
                            {discoverable.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-4"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-slate-900">{item.name}</p>
                                        <p className="text-xs text-slate-500">
                                            {item.teacher} · {item.subject} · {item.students} learners
                                            {item.nextSession ? ` · next: ${item.nextSession}` : ''}
                                        </p>
                                        {item.about && (
                                            <p className="mt-1 text-xs text-slate-600">{item.about}</p>
                                        )}
                                    </div>

                                    <button
                                        onClick={() =>
                                            router.post(route('classrooms.requestJoin', item.id))
                                        }
                                        className="rounded-lg bg-indigo-600 px-5 py-2 text-xs font-semibold text-white hover:bg-indigo-500"
                                    >
                                        Join
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </AuthenticatedLayout>
    );
}
