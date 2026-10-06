import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface TopicRow {
    id: number;
    title: string;
    subject: string | null;
    summary: string | null;
    sources: number;
    isPublished: boolean;
    versions: { language: string | null; code: string | null; status: string }[];
}

const STATUS_STYLE: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-600',
    generating: 'bg-sky-100 text-sky-800',
    review: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
};

export default function Index({ topics }: { topics: { data: TopicRow[] } }) {
    const [creating, setCreating] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        curriculum_item_id: '',
        title: '',
        summary: '',
        estimated_minutes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.topics.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">AI Tutor</h2>}>
            <Head title="AI Tutor" />

            <div className="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">
                        Build a lesson once per topic, in as many languages as you need. Every lesson is
                        reviewed by a subject teacher before students see it.
                    </p>
                    <button
                        onClick={() => setCreating((v) => !v)}
                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        New topic
                    </button>
                </div>

                {creating && (
                    <form onSubmit={submit} className="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
                        <input
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Topic title, e.g. Factorising trinomials"
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />
                        {errors.title && <p className="text-xs text-rose-600">{errors.title}</p>}

                        <input
                            value={data.curriculum_item_id}
                            onChange={(e) => setData('curriculum_item_id', e.target.value)}
                            placeholder="Subject ID (pick from the curriculum editor)"
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />
                        {errors.curriculum_item_id && (
                            <p className="text-xs text-rose-600">{errors.curriculum_item_id}</p>
                        )}

                        <textarea
                            rows={2}
                            value={data.summary}
                            onChange={(e) => setData('summary', e.target.value)}
                            placeholder="One or two lines on what this topic covers"
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />

                        <button
                            disabled={processing}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white"
                        >
                            Create topic
                        </button>
                    </form>
                )}

                {topics.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        No topics yet. Create one, add source material, then generate the lesson.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {topics.data.map((topic) => (
                            <li key={topic.id} className="rounded-xl border border-slate-200 bg-white p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={route('admin.topics.show', topic.id)}
                                            className="font-medium text-slate-900 hover:text-indigo-700"
                                        >
                                            {topic.title}
                                        </Link>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {topic.subject} · {topic.sources} source
                                            {topic.sources === 1 ? '' : 's'}
                                        </p>
                                        {topic.summary && (
                                            <p className="mt-1 text-sm text-slate-600">{topic.summary}</p>
                                        )}

                                        <div className="mt-3 flex flex-wrap gap-1.5">
                                            {topic.versions.length === 0 ? (
                                                <span className="text-xs text-slate-400">
                                                    No lesson generated yet
                                                </span>
                                            ) : (
                                                topic.versions.map((version) => (
                                                    <span
                                                        key={version.code ?? version.language ?? ''}
                                                        className={`rounded-full px-2.5 py-0.5 text-[11px] font-medium ${
                                                            STATUS_STYLE[version.status] ??
                                                            'bg-slate-100 text-slate-600'
                                                        }`}
                                                    >
                                                        {version.language} · {version.status}
                                                    </span>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
