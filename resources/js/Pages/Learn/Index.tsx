import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

interface TopicCard {
    id: number;
    title: string;
    subject: string | null;
    summary: string | null;
    minutes: number | null;
    languages: { code: string | null; name: string | null }[];
    percent: number;
    completed: boolean;
}

export default function Index({
    topics,
    subjects,
    filters,
    preferredLanguage,
    languages,
}: {
    topics: TopicCard[];
    subjects: { id: number; name: string }[];
    filters: Record<string, string>;
    preferredLanguage: string;
    languages: { id: number; code: string; name: string; native_name: string; tts_supported: boolean }[];
}) {
    const { data, setData, post } = useForm({
        language_id: String(languages.find((l) => l.code === preferredLanguage)?.id ?? ''),
    });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Learn</h2>}>
            <Head title="Learn" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <label className="text-sm font-medium text-slate-900">
                        Which language do you want to learn in?
                    </label>
                    <p className="mt-1 text-xs text-slate-500">
                        Lessons load in your language where they exist, and in English where they do not.
                        Formulas and technical words stay in English so you recognise them in the exam.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {languages.map((language) => (
                            <button
                                key={language.id}
                                onClick={() => {
                                    setData('language_id', String(language.id));
                                    post(route('learn.language'), {
                                        data: { language_id: language.id },
                                        preserveScroll: true,
                                    } as never);
                                }}
                                className={`rounded-full px-4 py-1.5 text-sm transition ${
                                    data.language_id === String(language.id)
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                }`}
                            >
                                {language.native_name}
                            </button>
                        ))}
                    </div>
                </div>

                {subjects.length > 1 && (
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={() => router.get(route('learn.index'))}
                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                !filters.subject ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'
                            }`}
                        >
                            All subjects
                        </button>
                        {subjects.map((subject) => (
                            <button
                                key={subject.id}
                                onClick={() => router.get(route('learn.index'), { subject: subject.id })}
                                className={`rounded-full px-4 py-1.5 text-sm transition ${
                                    filters.subject === String(subject.id)
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-white text-slate-600 ring-1 ring-slate-200'
                                }`}
                            >
                                {subject.name}
                            </button>
                        ))}
                    </div>
                )}

                {topics.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        No lessons for your subjects yet. They appear here as DX publishes them.
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {topics.map((topic) => (
                            <Link
                                key={topic.id}
                                href={route('learn.topic', topic.id)}
                                className="flex flex-col rounded-xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <p className="text-xs font-medium uppercase tracking-wide text-indigo-600">
                                    {topic.subject}
                                </p>
                                <p className="mt-1 font-medium text-slate-900">{topic.title}</p>
                                {topic.summary && (
                                    <p className="mt-1 line-clamp-2 text-sm text-slate-600">{topic.summary}</p>
                                )}

                                <div className="mt-3 flex flex-wrap gap-1">
                                    {topic.languages.slice(0, 5).map((language) => (
                                        <span
                                            key={language.code ?? ''}
                                            className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600"
                                        >
                                            {language.name}
                                        </span>
                                    ))}
                                </div>

                                <div className="mt-auto pt-4">
                                    {topic.completed ? (
                                        <p className="text-xs font-medium text-emerald-700">✓ Completed</p>
                                    ) : topic.percent > 0 ? (
                                        <>
                                            <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className="h-full rounded-full bg-indigo-500"
                                                    style={{ width: `${topic.percent}%` }}
                                                />
                                            </div>
                                            <p className="mt-1 text-xs text-slate-500">{topic.percent}% done</p>
                                        </>
                                    ) : (
                                        <p className="text-xs text-slate-400">
                                            {topic.minutes ? `About ${topic.minutes} minutes` : 'Not started'}
                                        </p>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
