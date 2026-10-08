import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export interface ClassPost {
    id: number;
    type: string;
    title: string | null;
    body: string;
    author: string | null;
    isMine: boolean;
    resource: { id: number; title: string } | null;
    dueAt: string | null;
    isOverdue: boolean;
    completed: boolean;
    completedCount: number | null;
    postedAt: string | null;
    replies: {
        id: number;
        body: string;
        author: string | null;
        isTeacher: boolean;
        postedAt: string | null;
    }[];
}

const TYPE_STYLE: Record<string, string> = {
    task: 'bg-amber-100 text-amber-800',
    note: 'bg-slate-100 text-slate-700',
    question: 'bg-indigo-100 text-indigo-800',
};

/**
 * The class conversation. Students can ask; the teacher posts notes and tasks;
 * everyone can reply — a class where only the teacher may speak is a
 * broadcast, not a class.
 */
export default function ClassFeed({
    classroomId,
    posts,
    isTeacher,
}: {
    classroomId: number;
    posts: ClassPost[];
    isTeacher: boolean;
}) {
    const [replyingTo, setReplyingTo] = useState<number | null>(null);
    const reply = useForm({ body: '' });
    const ask = useForm({ type: 'question', title: '', body: '' });

    return (
        <div className="space-y-4">
            {!isTeacher && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        ask.post(route('classrooms.posts.store', classroomId), {
                            preserveScroll: true,
                            onSuccess: () => ask.reset(),
                        });
                    }}
                    className="rounded-xl border border-slate-200 bg-white p-5"
                >
                    <label className="text-sm font-medium text-slate-700">
                        Ask your class a question
                    </label>
                    <input
                        value={ask.data.title}
                        onChange={(e) => ask.setData('title', e.target.value)}
                        placeholder="What are you stuck on?"
                        className="mt-2 block w-full rounded-lg border-slate-300 text-sm"
                    />
                    {ask.errors.title && <p className="mt-1 text-xs text-rose-600">{ask.errors.title}</p>}

                    <textarea
                        value={ask.data.body}
                        onChange={(e) => ask.setData('body', e.target.value)}
                        rows={3}
                        placeholder="Explain it in your own words. Your teacher and classmates will see this."
                        className="mt-2 block w-full rounded-lg border-slate-300 text-sm"
                    />
                    {ask.errors.body && <p className="mt-1 text-xs text-rose-600">{ask.errors.body}</p>}

                    <button
                        disabled={ask.processing}
                        className="mt-3 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                    >
                        {ask.processing ? 'Posting…' : 'Post question'}
                    </button>
                </form>
            )}

            {posts.length === 0 ? (
                <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                    {isTeacher
                        ? 'Nothing posted yet. Share a note or set a task.'
                        : 'Nothing here yet. Be the first to ask something.'}
                </p>
            ) : (
                <ul className="space-y-3">
                    {posts.map((post) => (
                        <li key={post.id} className="rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                    {post.title && (
                                        <p className="font-medium text-slate-900">{post.title}</p>
                                    )}
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {post.author}
                                        {post.isMine && ' (you)'} · {post.postedAt}
                                    </p>
                                </div>

                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-medium ${
                                        TYPE_STYLE[post.type] ?? TYPE_STYLE.note
                                    }`}
                                >
                                    {post.type}
                                </span>
                            </div>

                            <p className="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                                {post.body}
                            </p>

                            {post.dueAt && (
                                <p
                                    className={`mt-2 text-xs ${
                                        post.isOverdue ? 'text-rose-600' : 'text-slate-500'
                                    }`}
                                >
                                    Due {post.dueAt}
                                    {post.completedCount !== null && ` · ${post.completedCount} done`}
                                </p>
                            )}

                            {post.type === 'task' && !isTeacher && (
                                <button
                                    onClick={() =>
                                        router.post(
                                            route('classrooms.posts.complete', [classroomId, post.id]),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    disabled={post.completed}
                                    className={`mt-3 rounded-lg px-4 py-1.5 text-xs font-medium ${
                                        post.completed
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-slate-900 text-white hover:bg-slate-700'
                                    }`}
                                >
                                    {post.completed ? 'Done' : 'Mark as done'}
                                </button>
                            )}

                            {post.replies.length > 0 && (
                                <ul className="mt-4 space-y-2 border-l-2 border-slate-100 pl-4">
                                    {post.replies.map((item) => (
                                        <li key={item.id}>
                                            <p className="text-xs font-medium text-slate-700">
                                                {item.author}
                                                {item.isTeacher && (
                                                    <span className="ml-1.5 rounded bg-teal-100 px-1.5 py-0.5 text-[10px] text-teal-800">
                                                        teacher
                                                    </span>
                                                )}
                                                <span className="ml-2 font-normal text-slate-400">
                                                    {item.postedAt}
                                                </span>
                                            </p>
                                            <p className="mt-0.5 whitespace-pre-wrap text-sm text-slate-700">
                                                {item.body}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {replyingTo === post.id ? (
                                <div className="mt-3 flex gap-2">
                                    <input
                                        value={reply.data.body}
                                        onChange={(e) => reply.setData('body', e.target.value)}
                                        placeholder="Write a reply"
                                        autoFocus
                                        className="flex-1 rounded-lg border-slate-300 text-sm"
                                    />
                                    <button
                                        onClick={() =>
                                            reply.post(
                                                route('classrooms.posts.reply', [classroomId, post.id]),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        reply.reset();
                                                        setReplyingTo(null);
                                                    },
                                                },
                                            )
                                        }
                                        className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white"
                                    >
                                        Send
                                    </button>
                                </div>
                            ) : (
                                <button
                                    onClick={() => setReplyingTo(post.id)}
                                    className="mt-3 text-xs font-medium text-slate-500 hover:text-slate-800"
                                >
                                    Reply
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
