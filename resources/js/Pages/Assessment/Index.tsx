import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface Level {
    level: string;
    label: string;
    questions: number;
    best: number;
    passed: boolean;
    unlocked: boolean;
    attempts: number;
}

export default function Index({
    topic,
    levels,
    passMark,
    mastery,
    history,
}: {
    topic: { id: number; title: string; subject: string | null };
    levels: Level[];
    passMark: number;
    mastery: { highestLevel: string | null; bestPercent: number; nextReview: string | null; reviewStage: number };
    history: { level: string; percent: number; passed: boolean; isReview: boolean; at: string | null }[];
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Test yourself</h2>}>
            <Head title={`Assessment: ${topic.title}`} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <Link href={route('learn.topic', topic.id)} className="text-sm text-slate-500 hover:text-slate-800">
                    ← Back to the lesson
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h1 className="font-semibold text-slate-900">{topic.title}</h1>
                    <p className="mt-0.5 text-sm text-slate-500">{topic.subject}</p>
                    <p className="mt-3 text-sm text-slate-600">
                        Testing yourself beats reading your notes again. Score {passMark}% to unlock the next
                        level.
                    </p>
                    {mastery.nextReview && (
                        <p className="mt-3 rounded-lg bg-indigo-50 p-3 text-sm text-indigo-900">
                            We will bring this topic back on {mastery.nextReview} so it stays in your memory.
                        </p>
                    )}
                </section>

                <ul className="space-y-3">
                    {levels.map((level) => (
                        <li
                            key={level.level}
                            className={`rounded-xl border bg-white p-5 ${
                                level.passed
                                    ? 'border-emerald-300'
                                    : level.unlocked
                                      ? 'border-slate-200'
                                      : 'border-slate-200 opacity-60'
                            }`}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-slate-900">{level.label}</p>
                                        {level.passed && (
                                            <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">
                                                Mastered
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {level.questions} question{level.questions === 1 ? '' : 's'}
                                        {level.attempts > 0 && ` · best ${level.best}% over ${level.attempts} attempt${level.attempts === 1 ? '' : 's'}`}
                                    </p>
                                </div>

                                {level.unlocked ? (
                                    <Link
                                        href={route('assessment.start', [topic.id, level.level])}
                                        className={`rounded-lg px-5 py-2 text-sm font-semibold text-white ${
                                            level.passed
                                                ? 'bg-slate-700 hover:bg-slate-600'
                                                : 'bg-indigo-600 hover:bg-indigo-500'
                                        }`}
                                    >
                                        {level.passed ? 'Try again' : 'Start'}
                                    </Link>
                                ) : (
                                    <span className="text-xs text-slate-400">
                                        {level.questions === 0 ? 'No questions yet' : 'Locked'}
                                    </span>
                                )}
                            </div>

                            {level.attempts > 0 && (
                                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className={`h-full rounded-full ${
                                            level.passed ? 'bg-emerald-500' : 'bg-amber-400'
                                        }`}
                                        style={{ width: `${level.best}%` }}
                                    />
                                </div>
                            )}
                        </li>
                    ))}
                </ul>

                {history.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h2 className="text-sm font-semibold text-slate-900">Your attempts</h2>
                        <ul className="mt-3 divide-y divide-slate-100">
                            {history.map((item, index) => (
                                <li key={index} className="flex items-center justify-between py-2 text-sm">
                                    <span className="capitalize text-slate-700">
                                        {item.level}
                                        {item.isReview && (
                                            <span className="ml-2 text-xs text-indigo-600">review</span>
                                        )}
                                    </span>
                                    <span className={item.passed ? 'text-emerald-700' : 'text-slate-500'}>
                                        {item.percent}% · {item.at}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
