import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Question {
    id: number;
    question: string;
    options: { text: string }[];
}

export default function Take({
    topic,
    level,
    levelLabel,
    isReview,
    passMark,
    questions,
}: {
    topic: { id: number; title: string };
    level: string;
    levelLabel: string;
    isReview: boolean;
    passMark: number;
    questions: Question[];
}) {
    const [current, setCurrent] = useState(0);

    const { data, setData, post, processing } = useForm<{
        answers: Record<number, number | null>;
        is_review: boolean;
    }>({ answers: {}, is_review: isReview });

    const question = questions[current];
    const answered = Object.keys(data.answers).length;
    const chosen = data.answers[question?.id];

    const choose = (index: number) =>
        setData('answers', { ...data.answers, [question.id]: index });

    const submit = () => post(route('assessment.submit', [topic.id, level]));

    if (!question) {
        return (
            <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">{topic.title}</h2>}>
                <Head title="Assessment" />
                <p className="mx-auto max-w-2xl px-4 py-16 text-center text-sm text-slate-500">
                    There are no questions at this level yet.
                </p>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">{levelLabel}</h2>}>
            <Head title={`${levelLabel}: ${topic.title}`} />

            <div className="mx-auto max-w-2xl space-y-5 px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between text-sm text-slate-500">
                    <span>
                        Question {current + 1} of {questions.length}
                    </span>
                    <span>{passMark}% to pass</span>
                </div>

                <div className="h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div
                        className="h-full rounded-full bg-indigo-500 transition-all"
                        style={{ width: `${((current + 1) / questions.length) * 100}%` }}
                    />
                </div>

                <article className="rounded-xl border border-slate-200 bg-white p-6">
                    <p className="text-base font-medium text-slate-900">{question.question}</p>

                    <div className="mt-5 space-y-2">
                        {question.options.map((option, index) => (
                            <button
                                key={index}
                                onClick={() => choose(index)}
                                className={`block w-full rounded-xl border p-4 text-left text-sm transition ${
                                    chosen === index
                                        ? 'border-indigo-500 bg-indigo-50 font-medium text-indigo-900'
                                        : 'border-slate-200 text-slate-700 hover:border-slate-300'
                                }`}
                            >
                                <span className="mr-2 font-semibold text-slate-400">
                                    {String.fromCharCode(65 + index)}
                                </span>
                                {option.text}
                            </button>
                        ))}
                    </div>
                </article>

                <div className="flex items-center justify-between">
                    <button
                        onClick={() => setCurrent(Math.max(0, current - 1))}
                        disabled={current === 0}
                        className="rounded-lg border border-slate-300 px-5 py-2 text-sm text-slate-700 disabled:opacity-40"
                    >
                        Back
                    </button>

                    {current < questions.length - 1 ? (
                        <button
                            onClick={() => setCurrent(current + 1)}
                            className="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Next
                        </button>
                    ) : (
                        <button
                            onClick={submit}
                            disabled={processing || answered === 0}
                            className="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:bg-slate-300"
                        >
                            {processing ? 'Marking…' : 'Submit answers'}
                        </button>
                    )}
                </div>

                <div className="flex flex-wrap gap-1.5">
                    {questions.map((item, index) => (
                        <button
                            key={item.id}
                            onClick={() => setCurrent(index)}
                            className={`h-8 w-8 rounded-lg text-xs font-medium transition ${
                                data.answers[item.id] !== undefined
                                    ? 'bg-indigo-600 text-white'
                                    : index === current
                                      ? 'bg-slate-300 text-slate-700'
                                      : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                            }`}
                        >
                            {index + 1}
                        </button>
                    ))}
                </div>

                <p className="text-center text-xs text-slate-400">
                    {answered} of {questions.length} answered. You can go back and change any answer before
                    submitting.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
