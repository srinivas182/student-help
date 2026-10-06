import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Item {
    id: number;
    title: string;
    body: string;
    priority: string;
    author: string | null;
    targeting: { roles?: string[]; curriculum_item_ids?: number[] } | null;
    publishAt: string | null;
    isScheduled: boolean;
    createdAt: string | null;
}

interface Level {
    id: number;
    name: string;
    type: string;
    context: string;
}

export default function Announcements({
    announcements,
    levels,
}: {
    announcements: { data: Item[] };
    levels: Level[];
}) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<{
        title: string;
        body: string;
        priority: string;
        roles: string[];
        curriculum_item_ids: number[];
        publish_at: string;
        expires_at: string;
    }>({
        title: '',
        body: '',
        priority: 'normal',
        roles: [],
        curriculum_item_ids: [],
        publish_at: '',
        expires_at: '',
    });

    const toggle = (key: 'roles' | 'curriculum_item_ids', value: never) =>
        setData(
            key,
            (data[key] as never[]).includes(value)
                ? (data[key] as never[]).filter((x) => x !== value)
                : [...(data[key] as never[]), value],
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.announcements.store'), {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Announcements</h2>}>
            <Head title="Announcements" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600">
                        Target by audience and by grade, faculty or pathway. Important ones are pushed
                        straight away.
                    </p>
                    <button
                        onClick={() => setOpen((v) => !v)}
                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        New announcement
                    </button>
                </div>

                {open && (
                    <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
                        <div>
                            <input
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="Title"
                                className="block w-full rounded-lg border-slate-300 text-sm"
                            />
                            {errors.title && <p className="mt-1 text-xs text-rose-600">{errors.title}</p>}
                        </div>

                        <div>
                            <textarea
                                rows={5}
                                value={data.body}
                                onChange={(e) => setData('body', e.target.value)}
                                placeholder="What do you want them to know?"
                                className="block w-full rounded-lg border-slate-300 text-sm"
                            />
                            {errors.body && <p className="mt-1 text-xs text-rose-600">{errors.body}</p>}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Priority
                                </label>
                                <select
                                    value={data.priority}
                                    onChange={(e) => setData('priority', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                >
                                    <option value="normal">Normal</option>
                                    <option value="important">Important (notifies everyone)</option>
                                </select>
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Publish at
                                </label>
                                <input
                                    type="datetime-local"
                                    value={data.publish_at}
                                    onChange={(e) => setData('publish_at', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Expires
                                </label>
                                <input
                                    type="datetime-local"
                                    value={data.expires_at}
                                    onChange={(e) => setData('expires_at', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Who should see it? (leave empty for everyone)
                            </label>
                            <div className="mt-2 flex gap-2">
                                {['student', 'tutor'].map((role) => (
                                    <button
                                        key={role}
                                        type="button"
                                        onClick={() => toggle('roles', role as never)}
                                        className={`rounded-full px-4 py-1.5 text-sm capitalize transition ${
                                            data.roles.includes(role)
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-slate-100 text-slate-700'
                                        }`}
                                    >
                                        {role === 'tutor' ? 'Teachers' : 'Students'}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Narrow by grade, faculty or pathway (optional)
                            </label>
                            <div className="mt-2 flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
                                {levels.map((level) => (
                                    <button
                                        key={level.id}
                                        type="button"
                                        onClick={() => toggle('curriculum_item_ids', level.id as never)}
                                        title={level.context}
                                        className={`rounded-full px-3 py-1 text-xs transition ${
                                            data.curriculum_item_ids.includes(level.id)
                                                ? 'bg-slate-900 text-white'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                    >
                                        {level.name}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Publish
                        </button>
                    </form>
                )}

                <ul className="space-y-3">
                    {announcements.data.map((item) => (
                        <li key={item.id} className="rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        {item.priority === 'important' && (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                                                Important
                                            </span>
                                        )}
                                        {item.isScheduled && (
                                            <span className="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-medium text-sky-800">
                                                Scheduled
                                            </span>
                                        )}
                                        <p className="font-medium text-slate-900">{item.title}</p>
                                    </div>
                                    <p className="mt-1 line-clamp-2 text-sm text-slate-600">{item.body}</p>
                                    <p className="mt-2 text-xs text-slate-400">
                                        {item.author} · {item.publishAt ?? item.createdAt}
                                        {item.targeting?.roles?.length
                                            ? ` · ${item.targeting.roles.join(', ')}`
                                            : ' · everyone'}
                                    </p>
                                </div>

                                <button
                                    onClick={() =>
                                        router.delete(route('admin.announcements.destroy', item.id), {
                                            preserveScroll: true,
                                        })
                                    }
                                    className="text-xs font-medium text-rose-600 hover:text-rose-500"
                                >
                                    Remove
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
