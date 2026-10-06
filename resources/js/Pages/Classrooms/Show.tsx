import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Post {
    id: number;
    type: string;
    title: string;
    body: string;
    author: string | null;
    resource: { id: number; title: string } | null;
    dueAt: string | null;
    isOverdue: boolean;
    completed: boolean;
    completedCount: number | null;
    postedAt: string | null;
}

export default function Show({
    classroom,
    isTeacher,
    posts,
    members,
    memberCount,
}: {
    classroom: {
        id: number;
        displayName: string;
        description: string | null;
        subject: string | null;
        teacher: string | null;
        joinCode: string | null;
        schoolLinkStatus: string | null;
        pendingSchool: string | null;
    };
    isTeacher: boolean;
    posts: Post[];
    members: { id: number; name: string; joinedAt: string | null }[];
    memberCount: number;
}) {
    const [posting, setPosting] = useState(false);

    const form = useForm({ type: 'note', title: '', body: '', due_at: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('classrooms.posts.store', classroom.id), {
            onSuccess: () => {
                form.reset();
                setPosting(false);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">{classroom.displayName}</h2>}
        >
            <Head title={classroom.displayName} />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <Link href={route('classrooms.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← All classes
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-lg font-semibold text-slate-900">{classroom.displayName}</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                {classroom.teacher}
                                {classroom.subject ? ` · ${classroom.subject}` : ''} · {memberCount} student
                                {memberCount === 1 ? '' : 's'}
                            </p>
                            {classroom.description && (
                                <p className="mt-3 text-sm text-slate-700">{classroom.description}</p>
                            )}
                            {classroom.pendingSchool && (
                                <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    Link to {classroom.pendingSchool} is awaiting DX approval.
                                </p>
                            )}
                        </div>

                        {isTeacher && classroom.joinCode && (
                            <div className="rounded-lg bg-slate-50 px-5 py-3 text-center">
                                <p className="text-[11px] uppercase tracking-wide text-slate-500">Join code</p>
                                <p className="font-mono text-xl font-semibold tracking-widest text-slate-900">
                                    {classroom.joinCode}
                                </p>
                            </div>
                        )}
                    </div>

                    {!isTeacher && (
                        <button
                            onClick={() => router.post(route('classrooms.leave', classroom.id))}
                            className="mt-4 text-xs text-slate-500 hover:text-rose-600"
                        >
                            Leave this class
                        </button>
                    )}
                </section>

                {isTeacher && (
                    <div>
                        <button
                            onClick={() => setPosting((v) => !v)}
                            className="rounded-lg bg-teal-600 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-500"
                        >
                            Post to the class
                        </button>

                        {posting && (
                            <form
                                onSubmit={submit}
                                className="mt-4 space-y-3 rounded-xl border border-slate-200 bg-white p-5"
                            >
                                <div className="flex gap-2">
                                    {[
                                        ['note', 'Note'],
                                        ['task', 'Task with a due date'],
                                    ].map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => form.setData('type', value)}
                                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                                form.data.type === value
                                                    ? 'bg-slate-900 text-white'
                                                    : 'bg-slate-100 text-slate-600'
                                            }`}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>

                                <input
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                    placeholder="Title"
                                    className="block w-full rounded-lg border-slate-300 text-sm"
                                />
                                <textarea
                                    rows={3}
                                    value={form.data.body}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                    placeholder="What do your students need to know or do?"
                                    className="block w-full rounded-lg border-slate-300 text-sm"
                                />

                                {form.data.type === 'task' && (
                                    <input
                                        type="datetime-local"
                                        value={form.data.due_at}
                                        onChange={(e) => form.setData('due_at', e.target.value)}
                                        className="block w-full max-w-xs rounded-lg border-slate-300 text-sm"
                                    />
                                )}

                                <button className="rounded-lg bg-teal-600 px-5 py-2 text-sm font-semibold text-white">
                                    Post
                                </button>
                            </form>
                        )}
                    </div>
                )}

                <section className="space-y-3">
                    {posts.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                            {isTeacher
                                ? 'Nothing posted yet. Share a note or set a task for your class.'
                                : 'Your teacher has not posted anything yet.'}
                        </p>
                    ) : (
                        posts.map((post) => (
                            <article
                                key={post.id}
                                className={`rounded-xl border bg-white p-5 ${
                                    post.isOverdue && !post.completed ? 'border-rose-300' : 'border-slate-200'
                                }`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            {post.type === 'task' && (
                                                <span className="rounded-full bg-indigo-100 px-2.5 py-0.5 text-[11px] font-medium text-indigo-800">
                                                    Task
                                                </span>
                                            )}
                                            <p className="font-medium text-slate-900">{post.title}</p>
                                        </div>
                                        <p className="mt-2 whitespace-pre-line text-sm text-slate-700">
                                            {post.body}
                                        </p>
                                        {post.resource && (
                                            <Link
                                                href={route('resources.show', post.resource.id)}
                                                className="mt-2 inline-block text-xs font-medium text-indigo-600"
                                            >
                                                📎 {post.resource.title}
                                            </Link>
                                        )}
                                        <p className="mt-3 text-xs text-slate-400">
                                            {post.author} · {post.postedAt}
                                            {post.dueAt ? ` · due ${post.dueAt}` : ''}
                                            {isTeacher && post.type === 'task'
                                                ? ` · ${post.completedCount}/${memberCount} done`
                                                : ''}
                                        </p>
                                    </div>

                                    {!isTeacher && post.type === 'task' && (
                                        <button
                                            onClick={() =>
                                                router.post(
                                                    route('classrooms.posts.complete', [classroom.id, post.id]),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                            disabled={post.completed}
                                            className={`shrink-0 rounded-lg px-4 py-2 text-xs font-semibold ${
                                                post.completed
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-indigo-600 text-white hover:bg-indigo-500'
                                            }`}
                                        >
                                            {post.completed ? 'Done ✓' : 'Mark as done'}
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))
                    )}
                </section>

                {isTeacher && members.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="font-semibold text-slate-900">Students</h2>
                        <ul className="mt-3 divide-y divide-slate-100">
                            {members.map((member) => (
                                <li key={member.id} className="flex items-center justify-between py-2.5 text-sm">
                                    <span className="text-slate-700">
                                        {member.name}
                                        <span className="ml-2 text-xs text-slate-400">
                                            joined {member.joinedAt}
                                        </span>
                                    </span>
                                    <button
                                        onClick={() =>
                                            router.post(
                                                route('classrooms.members.remove', [classroom.id, member.id]),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="text-xs text-slate-500 hover:text-rose-600"
                                    >
                                        Remove
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
