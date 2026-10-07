import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface Answer {
    question: string | null;
    options: string[];
    chosen: number | null;
    correctIndex: number;
    correct: boolean;
    explanation: string;
    misconception: string | null;
}

export default function Result({
    topic,
    attempt,
    passMark,
    answers,
    nextReview,
}: {
    topic: { id: number; title: string };
    attempt: {
        level: string;
        levelLabel: string;
        score: number;
        total: number;
        percent: number;
        passed: boolean;
        isReview: boolean;
    };
    passMark: number;
    answers: Answer[];
    nextReview: string | null;
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Results</h2>}>
            <Head title={`Results: ${topic.title}`} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <section
                    className={`rounded-2xl p-6 text-white ${
                        attempt.passed
                            ? 'bg-gradient-to-r from-emerald-600 to-teal-600'
                            : 'bg-gradient-to-r from-amber-500 to-orange-500'
                    }`}
                >
                    <p className="text-sm opacity-90">
                        {attempt.levelLabel}
                        {attempt.isReview && ' · review'}
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold">
                        {attempt.score} out of {attempt.total}
                    </h1>
                    <p className="mt-1 text-sm opacity-90">
                        {attempt.passed
                            ? `${attempt.percent}% — the next level is now open.`
                            : `${attempt.percent}% — you need ${passMark}% to move on. Read the explanations below and try again.`}
                    </p>
                </section>

                {nextReview && attempt.passed && (
                    <p className="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                        We will bring this topic back on {nextReview}. Seeing it again after a gap is what makes
                        it stick.
                    </p>
                )}

                <section className="space-y-3">
                    {answers.map((answer, index) => (
                        <article
                            key={index}
                            className={`rounded-xl border bg-white p-5 ${
                                answer.correct ? 'border-emerald-200' : 'border-rose-200'
                            }`}
                        >
                            <p className="text-sm font-medium text-slate-900">
                                {index + 1}. {answer.question}
                            </p>

                            <ul className="mt-3 space-y-1.5">
                                {answer.options.map((option, optionIndex) => {
                                    const isCorrect = optionIndex === answer.correctIndex;
                                    const isChosen = optionIndex === answer.chosen;

                                    return (
                                        <li
                                            key={optionIndex}
                                            className={`rounded-lg px-3 py-2 text-sm ${
                                                isCorrect
                                                    ? 'bg-emerald-50 font-medium text-emerald-900'
                                                    : isChosen
                                                      ? 'bg-rose-50 text-rose-900'
                                                      : 'text-slate-600'
                                            }`}
                                        >
                                            {isCorrect && '✓ '}
                                            {isChosen && !isCorrect && '✗ '}
                                            {option}
                                            {isChosen && !isCorrect && (
                                                <span className="ml-2 text-xs">your answer</span>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>

                            {/* Naming the specific mistake is the whole point */}
                            {answer.misconception && (
                                <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                                    <span className="font-medium">What went wrong: </span>
                                    {answer.misconception}
                                </p>
                            )}

                            <p className="mt-3 text-sm text-slate-600">{answer.explanation}</p>
                        </article>
                    ))}
                </section>

                <div className="flex flex-wrap gap-3">
                    <Link
                        href={route('assessment.index', topic.id)}
                        className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        {attempt.passed ? 'Next level' : 'Try again'}
                    </Link>
                    <Link
                        href={route('learn.topic', topic.id)}
                        className="rounded-lg border border-slate-300 px-6 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Back to the lesson
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
