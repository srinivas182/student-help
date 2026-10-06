import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Question {
    id: number;
    title: string | null;
    body: string;
    author: string | null;
    authorRole: string | null;
    subject: string | null;
    votes: number;
    replies: number;
    views: number;
    askedAt: string | null;
}

export default function Index({
    questions,
    subjects,
    filters,
    canPost,
    unansweredCount,
}: {
    questions: { data: Question[] };
    subjects: { id: number; name: string; questions: number }[];
    filters: Record<string, string>;
    canPost: boolean;
    unansweredCount: number;
}) {
    const [asking, setAsking] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        curriculum_item_id: '',
        title: '',
        body: '',
    });

    const apply = (changes: Record<string, string>) =>
        router.get(route('community.index'), { ...filters, ...changes }, { preserveState: true, replace: true });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('community.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Community</h2>}>
            <Head title="Community" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">
                        Ask your classmates and tutors. Boards cover your own subjects only.
                    </p>
                    {canPost && (
                        <button
                            onClick={() => setAsking((v) => !v)}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Ask a question
                        </button>
                    )}
                </div>

                {asking && (
                    <form onSubmit={submit} className="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
                        <select
                            value={data.curriculum_item_id}
                            onChange={(e) => setData('curriculum_item_id', e.target.value)}
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        >
                            <option value="">Choose a subject…</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name}
                                </option>
                            ))}
                        </select>
                        {errors.curriculum_item_id && (
                            <p className="text-xs text-rose-600">{errors.curriculum_item_id}</p>
                        )}

                        <input
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Summarise your question in one line"
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />
                        {errors.title && <p className="text-xs text-rose-600">{errors.title}</p>}

                        <textarea
                            rows={5}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Explain what you have tried and where you are stuck."
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />
                        {errors.body && <p className="text-xs text-rose-600">{errors.body}</p>}

                        <button
                            disabled={processing}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white"
                        >
                            Post question
                        </button>
                    </form>
                )}

                <div className="flex flex-wrap gap-2">
                    <button
                        onClick={() => apply({ subject: '', filter: '' })}
                        className={`rounded-full px-4 py-1.5 text-sm transition ${
                            !filters.subject && !filters.filter
                                ? 'bg-slate-900 text-white'
                                : 'bg-white text-slate-600 ring-1 ring-slate-200'
                        }`}
                    >
                        All
                    </button>
                    <button
                        onClick={() => apply({ filter: 'unanswered', subject: '' })}
                        className={`rounded-full px-4 py-1.5 text-sm transition ${
                            filters.filter === 'unanswered'
                                ? 'bg-slate-900 text-white'
                                : 'bg-white text-slate-600 ring-1 ring-slate-200'
                        }`}
                    >
                        Unanswered ({unansweredCount})
                    </button>
                    {subjects.map((subject) => (
                        <button
                            key={subject.id}
                            onClick={() => apply({ subject: String(subject.id), filter: '' })}
                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                filters.subject === String(subject.id)
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200'
                            }`}
                        >
                            {subject.name} ({subject.questions})
                        </button>
                    ))}
                </div>

                {questions.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        No questions here yet. Be the first to ask.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {questions.data.map((question) => (
                            <li key={question.id}>
                                <Link
                                    href={route('community.show', question.id)}
                                    className="flex gap-4 px-5 py-4 transition hover:bg-slate-50"
                                >
                                    <div className="w-12 shrink-0 text-center">
                                        <p className="text-sm font-semibold text-slate-900">{question.votes}</p>
                                        <p className="text-[11px] text-slate-400">votes</p>
                                        <p
                                            className={`mt-1 rounded px-1 py-0.5 text-[11px] ${
                                                question.replies > 0
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-slate-100 text-slate-500'
                                            }`}
                                        >
                                            {question.replies}
                                        </p>
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">{question.title}</p>
                                        <p className="mt-0.5 line-clamp-2 text-sm text-slate-600">
                                            {question.body}
                                        </p>
                                        <p className="mt-1.5 text-xs text-slate-400">
                                            {question.subject} · {question.author}
                                            {question.authorRole === 'tutor' && ' (tutor)'} · {question.askedAt}
                                        </p>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
