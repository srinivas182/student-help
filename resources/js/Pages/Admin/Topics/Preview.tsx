import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Segment {
    title: string;
    narration: string;
    visual?: string;
    check?: { question: string; answer: string };
}

interface Question {
    id: number;
    level: string;
    question: string;
    options: { text: string; misconception?: string }[];
    correctIndex: number;
    explanation: string;
}

const LEVEL_STYLE: Record<string, string> = {
    basic: 'bg-slate-100 text-slate-700',
    easy: 'bg-emerald-100 text-emerald-800',
    intermediate: 'bg-amber-100 text-amber-800',
    difficult: 'bg-orange-100 text-orange-800',
    extreme: 'bg-rose-100 text-rose-800',
};

export default function Preview({
    version,
    topic,
    segments,
    notes,
    flashcards,
    questions,
}: {
    version: {
        id: number;
        status: string;
        language: string | null;
        reviewer: string | null;
        reviewedAt: string | null;
        reviewNotes: string | null;
        generatedAt: string | null;
        provider: string | null;
        model: string | null;
        costUsd: number;
    };
    topic: {
        id: number;
        title: string;
        subject: string | null;
        summary: string | null;
        objectives: string[];
        minutes: number | null;
    };
    segments: Segment[];
    notes: string | null;
    flashcards: { front: string; back: string }[];
    questions: Question[];
}) {
    const [tab, setTab] = useState<'lesson' | 'notes' | 'cards' | 'questions'>('lesson');
    const [flipped, setFlipped] = useState<number | null>(null);

    const tabs = [
        ['lesson', `Lesson (${segments.length})`],
        ['notes', 'Notes'],
        ['cards', `Flashcards (${flashcards.length})`],
        ['questions', `Questions (${questions.length})`],
    ] as const;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Lesson preview</h2>}
        >
            <Head title={`${topic.title} — preview`} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <Link
                    href={route('admin.topics.show', topic.id)}
                    className="text-sm text-slate-500 hover:text-slate-800"
                >
                    ← Back to topic
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-slate-900">{topic.title}</h1>
                            <p className="mt-1 text-sm text-slate-600">
                                {topic.subject} · {version.language}
                                {topic.minutes ? ` · about ${topic.minutes} minutes` : ''}
                            </p>
                        </div>
                        <span
                            className={`rounded-full px-3 py-1 text-xs font-medium ${
                                version.status === 'published'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : version.status === 'review'
                                      ? 'bg-amber-100 text-amber-800'
                                      : 'bg-slate-100 text-slate-600'
                            }`}
                        >
                            {version.status === 'review' ? 'awaiting review' : version.status}
                        </span>
                    </div>

                    {topic.summary && <p className="mt-3 text-sm text-slate-700">{topic.summary}</p>}

                    {topic.objectives.length > 0 && (
                        <ul className="mt-4 space-y-1.5">
                            {topic.objectives.map((objective) => (
                                <li key={objective} className="flex gap-2 text-sm text-slate-600">
                                    <span className="text-indigo-500">•</span>
                                    {objective}
                                </li>
                            ))}
                        </ul>
                    )}

                    {/* Provenance: who checked this, and what it was made with */}
                    <p className="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        {version.reviewer
                            ? `Written with AI and checked by ${version.reviewer}${version.reviewedAt ? ` on ${version.reviewedAt}` : ''}.`
                            : 'Not yet checked by a person.'}
                        {version.model ? ` Generated with ${version.model}.` : ''}
                        {version.costUsd > 0 ? ` Cost $${version.costUsd.toFixed(3)}.` : ''}
                    </p>

                    {version.reviewNotes && (
                        <p className="mt-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                            Reviewer notes: {version.reviewNotes}
                        </p>
                    )}
                </section>

                <div className="flex flex-wrap gap-2">
                    {tabs.map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => setTab(key)}
                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                tab === key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'lesson' && (
                    <div className="space-y-4">
                        {segments.length === 0 && (
                            <p className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                                No lesson content yet.
                            </p>
                        )}

                        {segments.map((segment, index) => (
                            <section key={index} className="rounded-xl border border-slate-200 bg-white p-6">
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-400">
                                    Part {index + 1} of {segments.length}
                                </p>
                                <h3 className="mt-1 font-semibold text-slate-900">{segment.title}</h3>

                                <div className="mt-3 space-y-3 text-sm leading-relaxed text-slate-700">
                                    {segment.narration.split('\n\n').map((paragraph, i) => (
                                        <p key={i}>{paragraph}</p>
                                    ))}
                                </div>

                                {segment.visual && (
                                    <p className="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                                        <span className="font-medium text-slate-700">On screen:</span>{' '}
                                        {segment.visual}
                                    </p>
                                )}

                                {segment.check && (
                                    <div className="mt-3 rounded-lg border border-indigo-100 bg-indigo-50 p-3">
                                        <p className="text-sm font-medium text-indigo-900">
                                            {segment.check.question}
                                        </p>
                                        <p className="mt-1 text-sm text-indigo-800">{segment.check.answer}</p>
                                    </div>
                                )}
                            </section>
                        ))}
                    </div>
                )}

                {tab === 'notes' && (
                    <section className="rounded-xl border border-slate-200 bg-white p-6">
                        {notes ? (
                            <pre className="whitespace-pre-wrap font-sans text-sm leading-relaxed text-slate-700">
                                {notes}
                            </pre>
                        ) : (
                            <p className="text-sm text-slate-500">No revision notes.</p>
                        )}
                    </section>
                )}

                {tab === 'cards' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        {flashcards.map((card, index) => (
                            <button
                                key={index}
                                onClick={() => setFlipped(flipped === index ? null : index)}
                                className="min-h-28 rounded-xl border border-slate-200 bg-white p-5 text-left transition hover:border-slate-300"
                            >
                                <p className="text-sm font-medium text-slate-900">{card.front}</p>
                                {flipped === index ? (
                                    <p className="mt-2 text-sm text-slate-600">{card.back}</p>
                                ) : (
                                    <p className="mt-2 text-xs text-slate-400">Tap to see the answer</p>
                                )}
                            </button>
                        ))}
                    </div>
                )}

                {tab === 'questions' && (
                    <div className="space-y-4">
                        {questions.map((question, index) => (
                            <section key={question.id} className="rounded-xl border border-slate-200 bg-white p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <p className="text-sm font-medium text-slate-900">
                                        {index + 1}. {question.question}
                                    </p>
                                    <span
                                        className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-medium ${
                                            LEVEL_STYLE[question.level] ?? LEVEL_STYLE.basic
                                        }`}
                                    >
                                        {question.level}
                                    </span>
                                </div>

                                <ul className="mt-3 space-y-1.5">
                                    {question.options.map((option, optionIndex) => (
                                        <li
                                            key={optionIndex}
                                            className={`rounded-lg px-3 py-2 text-sm ${
                                                optionIndex === question.correctIndex
                                                    ? 'bg-emerald-50 text-emerald-900'
                                                    : 'bg-slate-50 text-slate-700'
                                            }`}
                                        >
                                            <span className="font-medium">
                                                {String.fromCharCode(65 + optionIndex)}.
                                            </span>{' '}
                                            {option.text}
                                            {optionIndex === question.correctIndex && (
                                                <span className="ml-2 text-xs font-medium">correct</span>
                                            )}
                                            {option.misconception && (
                                                <span className="mt-1 block text-xs text-slate-500">
                                                    Shown if chosen: {option.misconception}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>

                                <p className="mt-3 text-xs text-slate-600">
                                    <span className="font-medium">Explanation:</span> {question.explanation}
                                </p>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
