import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Item {
    id: number;
    title: string;
    typeLabel: string;
    status: string;
    subjects: string[];
    views: number;
    downloads: number;
    addedAt: string | null;
}

const STATUS_STYLE: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    unpublished: 'bg-slate-100 text-slate-600',
};

export default function Mine({
    resources,
    types,
    subjects,
    maxMb,
}: {
    resources: { data: Item[] };
    types: Record<string, string>;
    subjects: { id: number; name: string }[];
    maxMb: number;
}) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<{
        title: string;
        description: string;
        resource_type: string;
        external_url: string;
        curriculum_item_ids: number[];
        rights_declared: boolean;
        file: File | null;
    }>({
        title: '',
        description: '',
        resource_type: 'notes',
        external_url: '',
        curriculum_item_ids: [],
        rights_declared: false,
        file: null,
    });

    const toggleSubject = (id: number) =>
        setData(
            'curriculum_item_ids',
            data.curriculum_item_ids.includes(id)
                ? data.curriculum_item_ids.filter((x) => x !== id)
                : [...data.curriculum_item_ids, id],
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('resources.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">My material</h2>}>
            <Head title="My material" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">
                        Share notes, past papers, solutions and voice notes with your students.
                    </p>
                    <button
                        onClick={() => setOpen((v) => !v)}
                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Share material
                    </button>
                </div>

                {open && (
                    <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium text-slate-700">Title</label>
                                <input
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="e.g. Grade 11 Trigonometry — worked examples"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                                {errors.title && <p className="mt-1 text-xs text-rose-600">{errors.title}</p>}
                            </div>

                            <div>
                                <label className="text-sm font-medium text-slate-700">Type</label>
                                <select
                                    value={data.resource_type}
                                    onChange={(e) => setData('resource_type', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                >
                                    {Object.entries(types).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-slate-700">Description</label>
                            <textarea
                                rows={3}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="What does this cover, and how should students use it?"
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium text-slate-700">File</label>
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.mp3,.m4a"
                                    onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                                    className="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm"
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    PDF, Word, PowerPoint, images or audio, up to {maxMb} MB.
                                </p>
                                {errors.file && <p className="mt-1 text-xs text-rose-600">{errors.file}</p>}
                            </div>

                            <div>
                                <label className="text-sm font-medium text-slate-700">Or a link</label>
                                <input
                                    value={data.external_url}
                                    onChange={(e) => setData('external_url', e.target.value)}
                                    placeholder="https://…"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                                {errors.external_url && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.external_url}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-slate-700">
                                Which subjects is this for?
                            </label>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {subjects.map((subject) => (
                                    <button
                                        key={subject.id}
                                        type="button"
                                        onClick={() => toggleSubject(subject.id)}
                                        className={`rounded-full px-3 py-1.5 text-sm transition ${
                                            data.curriculum_item_ids.includes(subject.id)
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                        }`}
                                    >
                                        {subject.name}
                                    </button>
                                ))}
                            </div>
                            {errors.curriculum_item_ids && (
                                <p className="mt-1 text-xs text-rose-600">{errors.curriculum_item_ids}</p>
                            )}
                        </div>

                        <label className="flex items-start gap-3 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                checked={data.rights_declared}
                                onChange={(e) => setData('rights_declared', e.target.checked)}
                                className="mt-0.5 rounded border-slate-300 text-indigo-600"
                            />
                            <span>
                                I have the right to share this material. It is my own work, or it is publicly
                                available material such as a Department of Basic Education past paper.
                            </span>
                        </label>
                        {errors.rights_declared && (
                            <p className="text-xs text-rose-600">{errors.rights_declared}</p>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                        >
                            Share with students
                        </button>
                    </form>
                )}

                {resources.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-sm text-slate-500">
                        You have not shared anything yet.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {resources.data.map((item) => (
                            <li key={item.id} className="flex flex-wrap items-center gap-4 px-5 py-4">
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-slate-900">{item.title}</p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {item.typeLabel} · {item.subjects.join(', ')} · {item.addedAt}
                                    </p>
                                </div>
                                <span className="text-xs text-slate-400">
                                    {item.views} views · {item.downloads} downloads
                                </span>
                                <span
                                    className={`rounded-full px-3 py-1 text-xs font-medium capitalize ${
                                        STATUS_STYLE[item.status] ?? 'bg-slate-100 text-slate-600'
                                    }`}
                                >
                                    {item.status}
                                </span>
                                {item.status === 'published' && (
                                    <button
                                        onClick={() => router.get(route('resources.show', item.id))}
                                        className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                    >
                                        View
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
