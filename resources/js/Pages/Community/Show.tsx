import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Post {
    id: number;
    title: string | null;
    body: string;
    author: string | null;
    authorRole: string | null;
    subject: string | null;
    votes: number;
    replies: number;
    views: number;
    isAccepted: boolean;
    isMine: boolean;
    hasVoted: boolean;
    askedAt: string | null;
}

const REASONS = [
    ['inappropriate', 'Inappropriate content'],
    ['contact_details', 'Sharing contact details'],
    ['academic_dishonesty', 'Asking for assessment answers'],
    ['harassment', 'Harassment or bullying'],
    ['spam', 'Spam'],
    ['other', 'Something else'],
];

function Vote({ post }: { post: Post }) {
    return (
        <button
            onClick={() => router.post(route('community.vote', post.id), {}, { preserveScroll: true })}
            disabled={post.isMine}
            className={`flex w-12 shrink-0 flex-col items-center rounded-lg py-2 transition ${
                post.hasVoted ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
            } disabled:opacity-40`}
            title={post.isMine ? 'You cannot upvote your own post' : 'Helpful'}
        >
            <span className="text-lg leading-none">▲</span>
            <span className="text-sm font-semibold">{post.votes}</span>
        </button>
    );
}

export default function Show({
    question,
    replies,
    canPost,
    canAccept,
}: {
    question: Post;
    replies: Post[];
    canPost: boolean;
    canAccept: boolean;
}) {
    const [reporting, setReporting] = useState<number | null>(null);
    const { data, setData, post, processing, reset } = useForm({ body: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('community.reply', question.id), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Community</h2>}>
            <Head title={question.title ?? 'Question'} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <Link href={route('community.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← All questions
                </Link>

                <article className="flex gap-4 rounded-xl border border-slate-200 bg-white p-6">
                    <Vote post={question} />

                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-medium uppercase tracking-wide text-indigo-600">
                            {question.subject}
                        </p>
                        <h1 className="mt-1 text-lg font-semibold text-slate-900">{question.title}</h1>
                        <p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                            {question.body}
                        </p>
                        <p className="mt-4 text-xs text-slate-400">
                            {question.author}
                            {question.authorRole === 'tutor' && ' (tutor)'} · {question.askedAt} ·{' '}
                            {question.views} views
                        </p>
                    </div>
                </article>

                <h2 className="font-semibold text-slate-900">
                    {replies.length} answer{replies.length === 1 ? '' : 's'}
                </h2>

                {replies.map((reply) => (
                    <article
                        key={reply.id}
                        className={`flex gap-4 rounded-xl border bg-white p-5 ${
                            reply.isAccepted ? 'border-emerald-300' : 'border-slate-200'
                        }`}
                    >
                        <Vote post={reply} />

                        <div className="min-w-0 flex-1">
                            {reply.isAccepted && (
                                <p className="mb-2 text-xs font-semibold text-emerald-700">✓ Accepted answer</p>
                            )}
                            <p className="whitespace-pre-line text-sm leading-relaxed text-slate-700">
                                {reply.body}
                            </p>

                            <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                                <span>
                                    {reply.author}
                                    {reply.authorRole === 'tutor' && (
                                        <span className="ml-1 rounded-full bg-teal-100 px-2 py-0.5 text-[11px] font-medium text-teal-800">
                                            Verified tutor
                                        </span>
                                    )}{' '}
                                    · {reply.askedAt}
                                </span>

                                {canAccept && !reply.isAccepted && (
                                    <button
                                        onClick={() =>
                                            router.post(route('community.accept', reply.id), {}, {
                                                preserveScroll: true,
                                            })
                                        }
                                        className="font-medium text-emerald-700 hover:text-emerald-600"
                                    >
                                        Accept this answer
                                    </button>
                                )}

                                {!reply.isMine && (
                                    <button
                                        onClick={() => setReporting(reporting === reply.id ? null : reply.id)}
                                        className="underline hover:text-rose-600"
                                    >
                                        Report
                                    </button>
                                )}
                            </div>

                            {reporting === reply.id && (
                                <div className="mt-2 rounded-lg border border-rose-200 bg-rose-50 p-2">
                                    {REASONS.map(([value, label]) => (
                                        <button
                                            key={value}
                                            onClick={() =>
                                                router.post(
                                                    route('community.report', reply.id),
                                                    { reason: value },
                                                    { preserveScroll: true, onFinish: () => setReporting(null) },
                                                )
                                            }
                                            className="block w-full rounded px-2 py-1 text-left text-xs text-rose-800 hover:bg-rose-100"
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </article>
                ))}

                {canPost ? (
                    <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5">
                        <label className="text-sm font-medium text-slate-900">Your answer</label>
                        <textarea
                            rows={4}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Explain the method rather than just giving the answer — it helps more."
                            className="mt-2 block w-full rounded-lg border-slate-300 text-sm"
                        />
                        <button
                            disabled={processing || data.body.trim().length < 5}
                            className="mt-3 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white disabled:bg-slate-300"
                        >
                            Post answer
                        </button>
                    </form>
                ) : (
                    <p className="rounded-xl bg-amber-50 p-4 text-center text-sm text-amber-800">
                        A parent or guardian needs to approve your account before you can answer.
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
