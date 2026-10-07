import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Item {
    id: number;
    title: string;
    description: string | null;
    type: string;
    uploader: string | null;
    subjects: string[];
    sizeKb: number | null;
    hasFile: boolean;
    externalUrl: string | null;
    addedAt: string | null;
}

export default function Resources({
    resources,
    filters,
    counts,
}: {
    resources: { data: Item[] };
    filters: { status: string };
    counts: { pending: number; published: number; rejected: number };
}) {
    const [rejecting, setRejecting] = useState<number | null>(null);
    const reject = useForm({ reason: '' });

    const tabs = [
        ['pending', `Pending (${counts.pending})`],
        ['published', `Published (${counts.published})`],
        ['rejected', `Rejected (${counts.rejected})`],
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Material review</h2>}>
            <Head title="Material review" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <div className="flex gap-2">
                    {tabs.map(([value, label]) => (
                        <button
                            key={value}
                            onClick={() => router.get(route('admin.resources.index'), { status: value })}
                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                filters.status === value
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {resources.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing here. Material shared by teachers waits for review in this queue.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {resources.data.map((item) => (
                            <li key={item.id} className="rounded-xl border border-slate-200 bg-white p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">{item.title}</p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {item.type} · {item.uploader} · {item.addedAt}
                                            {item.sizeKb ? ` · ${item.sizeKb} KB` : ''}
                                        </p>
                                        {item.description && (
                                            <p className="mt-2 text-sm text-slate-600">{item.description}</p>
                                        )}
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {item.subjects.map((subject) => (
                                                <span
                                                    key={subject}
                                                    className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-600"
                                                >
                                                    {subject}
                                                </span>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="flex shrink-0 gap-2">
                                        {item.hasFile && (
                                            <a
                                                href={route('resources.download', item.id)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                            >
                                                Open
                                            </a>
                                        )}
                                        {filters.status === 'pending' && (
                                            <>
                                                <button
                                                    onClick={() =>
                                                        router.post(route('admin.resources.approve', item.id), {}, {
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                    className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                                                >
                                                    Publish
                                                </button>
                                                <button
                                                    onClick={() => setRejecting(rejecting === item.id ? null : item.id)}
                                                    className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-600"
                                                >
                                                    Reject
                                                </button>
                                            </>
                                        )}
                                        {filters.status === 'published' && (
                                            <button
                                                onClick={() => setRejecting(rejecting === item.id ? null : item.id)}
                                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-600"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {rejecting === item.id && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            reject.post(
                                                route(
                                                    filters.status === 'published'
                                                        ? 'admin.resources.unpublish'
                                                        : 'admin.resources.reject',
                                                    item.id,
                                                ),
                                                { preserveScroll: true, onSuccess: () => setRejecting(null) },
                                            );
                                        }}
                                        className="mt-3 flex gap-2"
                                    >
                                        <input
                                            value={reject.data.reason}
                                            onChange={(e) => reject.setData('reason', e.target.value)}
                                            placeholder="Reason — the uploader is told, and it goes to the audit log"
                                            className="flex-1 rounded-lg border-slate-300 text-sm"
                                        />
                                        <button className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white">
                                            Confirm
                                        </button>
                                    </form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
