import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Source {
    id: number;
    kind: string;
    title: string | null;
    uploader: string | null;
    status: string;
    words: number;
    addedAt: string | null;
}

interface Version {
    id: number;
    language: string | null;
    nativeName: string | null;
    code: string | null;
    ttsSupported: boolean;
    status: string;
    segments: number;
    questions: number;
    reviewer: string | null;
    reviewedAt: string | null;
    costUsd: number;
}

interface Lang {
    id: number;
    code: string;
    name: string;
    native_name: string;
    tts_supported: boolean;
}

export default function Show({
    topic,
    sources,
    versions,
    languages,
    canGenerate,
}: {
    topic: {
        id: number;
        title: string;
        subject: string | null;
        summary: string | null;
        objectives: string[];
        isPublished: boolean;
    };
    sources: Source[];
    versions: Version[];
    languages: Lang[];
    canGenerate: boolean;
}) {
    const [addingSource, setAddingSource] = useState(false);

    const source = useForm<{
        kind: string;
        title: string;
        text: string;
        external_url: string;
        rights_declared: boolean;
        file: File | null;
    }>({
        kind: 'text',
        title: '',
        text: '',
        external_url: '',
        rights_declared: false,
        file: null,
    });

    const generate = useForm<{ language_ids: number[] }>({ language_ids: [] });

    const submitSource: FormEventHandler = (e) => {
        e.preventDefault();
        source.post(route('admin.topics.sources', topic.id), {
            forceFormData: true,
            onSuccess: () => {
                source.reset();
                setAddingSource(false);
            },
        });
    };

    const toggleLanguage = (id: number) =>
        generate.setData(
            'language_ids',
            generate.data.language_ids.includes(id)
                ? generate.data.language_ids.filter((x) => x !== id)
                : [...generate.data.language_ids, id],
        );

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">{topic.title}</h2>}>
            <Head title={topic.title} />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <Link href={route('admin.topics.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← All topics
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h1 className="font-semibold text-slate-900">{topic.title}</h1>
                    <p className="mt-0.5 text-sm text-slate-500">{topic.subject}</p>
                    {topic.summary && <p className="mt-3 text-sm text-slate-700">{topic.summary}</p>}
                    {topic.objectives.length > 0 && (
                        <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600">
                            {topic.objectives.map((objective) => (
                                <li key={objective}>{objective}</li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold text-slate-900">Source material</h2>
                        <button
                            onClick={() => setAddingSource((v) => !v)}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Add source
                        </button>
                    </div>

                    {addingSource && (
                        <form onSubmit={submitSource} className="mt-4 space-y-3 rounded-lg bg-slate-50 p-4">
                            <div className="flex gap-2">
                                {[
                                    ['text', 'Typed notes'],
                                    ['pdf', 'PDF'],
                                    ['document', 'Document'],
                                    ['link', 'Link'],
                                ].map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        onClick={() => source.setData('kind', value)}
                                        className={`rounded-full px-3 py-1 text-xs transition ${
                                            source.data.kind === value
                                                ? 'bg-slate-900 text-white'
                                                : 'bg-white text-slate-600 ring-1 ring-slate-200'
                                        }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>

                            <input
                                value={source.data.title}
                                onChange={(e) => source.setData('title', e.target.value)}
                                placeholder="What is this? e.g. DBE 2025 Paper 1"
                                className="block w-full rounded-lg border-slate-300 text-sm"
                            />

                            {source.data.kind === 'text' && (
                                <textarea
                                    rows={5}
                                    value={source.data.text}
                                    onChange={(e) => source.setData('text', e.target.value)}
                                    placeholder="Paste the notes or explanation the lesson should be built from."
                                    className="block w-full rounded-lg border-slate-300 text-sm"
                                />
                            )}

                            {['pdf', 'document'].includes(source.data.kind) && (
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.txt"
                                    onChange={(e) => source.setData('file', e.target.files?.[0] ?? null)}
                                    className="block w-full text-sm"
                                />
                            )}

                            {source.data.kind === 'link' && (
                                <input
                                    value={source.data.external_url}
                                    onChange={(e) => source.setData('external_url', e.target.value)}
                                    placeholder="https://…"
                                    className="block w-full rounded-lg border-slate-300 text-sm"
                                />
                            )}

                            <label className="flex items-start gap-3 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    checked={source.data.rights_declared}
                                    onChange={(e) => source.setData('rights_declared', e.target.checked)}
                                    className="mt-0.5 rounded border-slate-300 text-indigo-600"
                                />
                                <span>
                                    This material may lawfully be used. Department of Basic Education past
                                    papers, the curriculum statement and our own notes are fine. Scanned
                                    textbook chapters are not.
                                </span>
                            </label>
                            {source.errors.rights_declared && (
                                <p className="text-xs text-rose-600">{source.errors.rights_declared}</p>
                            )}

                            <button className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">
                                Add source
                            </button>
                        </form>
                    )}

                    {sources.length === 0 ? (
                        <p className="mt-4 text-sm text-slate-500">
                            No sources yet. The lesson is built from what you add here.
                        </p>
                    ) : (
                        <ul className="mt-4 divide-y divide-slate-100">
                            {sources.map((item) => (
                                <li key={item.id} className="flex items-center justify-between py-3 text-sm">
                                    <div>
                                        <p className="font-medium text-slate-900">{item.title ?? item.kind}</p>
                                        <p className="text-xs text-slate-500">
                                            {item.kind} · {item.words} words · {item.uploader} · {item.addedAt}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span
                                            className={`rounded-full px-2.5 py-0.5 text-[11px] font-medium ${
                                                item.status === 'done'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : item.status === 'failed'
                                                      ? 'bg-rose-100 text-rose-800'
                                                      : 'bg-amber-100 text-amber-800'
                                            }`}
                                        >
                                            {item.status}
                                        </span>
                                        <button
                                            onClick={() =>
                                                router.delete(route('admin.topics.sources.destroy', item.id), {
                                                    preserveScroll: true,
                                                })
                                            }
                                            className="text-xs text-slate-400 hover:text-rose-600"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">Generate the lesson</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Pick the languages. Each one is generated separately and must be reviewed by someone
                        who speaks it before students see it.
                    </p>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {languages.map((language) => (
                            <button
                                key={language.id}
                                type="button"
                                onClick={() => toggleLanguage(language.id)}
                                className={`rounded-full px-4 py-1.5 text-sm transition ${
                                    generate.data.language_ids.includes(language.id)
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                }`}
                            >
                                {language.native_name}
                                {!language.tts_supported && (
                                    <span className="ml-1.5 text-[11px] opacity-70">text only</span>
                                )}
                            </button>
                        ))}
                    </div>

                    <p className="mt-2 text-xs text-slate-500">
                        Languages marked "text only" have no reliable speech voice yet. Those lessons ship with
                        the transcript, and DX can record a human voice later.
                    </p>

                    <button
                        onClick={() => generate.post(route('admin.topics.generate', topic.id))}
                        disabled={!canGenerate || generate.processing || generate.data.language_ids.length === 0}
                        className="mt-4 rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                    >
                        {generate.processing ? 'Generating…' : 'Generate lesson'}
                    </button>
                    {!canGenerate && (
                        <p className="mt-2 text-xs text-amber-700">
                            Add at least one readable source first.
                        </p>
                    )}
                </section>

                {versions.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-6">
                        <h2 className="font-semibold text-slate-900">Language versions</h2>
                        <ul className="mt-4 divide-y divide-slate-100">
                            {versions.map((version) => (
                                <li key={version.id} className="flex flex-wrap items-center gap-3 py-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-slate-900">
                                            {version.nativeName}
                                            {!version.ttsSupported && (
                                                <span className="ml-2 text-[11px] font-normal text-slate-400">
                                                    text only
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {version.segments} segments · {version.questions} questions ·{' '}
                                            ${version.costUsd.toFixed(3)}
                                            {version.reviewer
                                                ? ` · reviewed by ${version.reviewer} on ${version.reviewedAt}`
                                                : ''}
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
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
