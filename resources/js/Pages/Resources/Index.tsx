import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Item {
    id: number;
    title: string;
    type: string;
    typeLabel: string;
    uploader: string | null;
    subjects: string[];
    sizeKb: number | null;
    isDownloadable: boolean;
    views: number;
    downloads: number;
    addedAt: string | null;
}

const TYPE_ICON: Record<string, string> = {
    notes: '📝',
    course_material: '📘',
    past_paper: '📄',
    solution: '✅',
    video: '🎬',
    other: '📎',
};

export default function Index({
    resources,
    filters,
    types,
    subjects,
    canUpload,
}: {
    resources: { data: Item[] };
    filters: Record<string, string>;
    types: Record<string, string>;
    subjects: { id: number; name: string }[];
    canUpload: boolean;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (changes: Record<string, string>) =>
        router.get(route('resources.index'), { ...filters, ...changes }, { preserveState: true, replace: true });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Study material</h2>}>
            <Head title="Study material" />

            <div className="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ search });
                        }}
                        className="flex-1"
                    >
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search notes, past papers and solutions…"
                            className="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </form>

                    <select
                        value={filters.type ?? ''}
                        onChange={(e) => apply({ type: e.target.value })}
                        className="rounded-lg border-slate-300 text-sm"
                    >
                        <option value="">All types</option>
                        {Object.entries(types).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>

                    {subjects.length > 0 && (
                        <select
                            value={filters.subject ?? ''}
                            onChange={(e) => apply({ subject: e.target.value })}
                            className="rounded-lg border-slate-300 text-sm"
                        >
                            <option value="">All my subjects</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name}
                                </option>
                            ))}
                        </select>
                    )}

                    {canUpload && (
                        <Link
                            href={route('resources.mine')}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Share material
                        </Link>
                    )}
                </div>

                {resources.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <p className="text-sm text-slate-600">
                            Nothing here yet for your subjects. As teachers share notes and past papers, they
                            will appear here automatically.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {resources.data.map((item) => (
                            <Link
                                key={item.id}
                                href={route('resources.show', item.id)}
                                className="flex flex-col rounded-xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <span className="text-2xl">{TYPE_ICON[item.type] ?? '📎'}</span>
                                <p className="mt-3 font-medium text-slate-900">{item.title}</p>
                                <p className="mt-1 text-xs text-slate-500">
                                    {item.typeLabel}
                                    {item.sizeKb ? ` · ${item.sizeKb} KB` : ''}
                                </p>

                                <div className="mt-3 flex flex-wrap gap-1.5">
                                    {item.subjects.slice(0, 2).map((subject) => (
                                        <span
                                            key={subject}
                                            className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-600"
                                        >
                                            {subject}
                                        </span>
                                    ))}
                                </div>

                                <p className="mt-auto pt-4 text-[11px] text-slate-400">
                                    {item.uploader} · {item.addedAt}
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
