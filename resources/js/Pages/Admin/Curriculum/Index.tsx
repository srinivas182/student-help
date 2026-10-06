import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Item {
    id: number;
    name: string;
    type: string;
    code: string | null;
    description: string | null;
    children: number;
    isActive: boolean;
    position: number;
}

export default function Index({
    items,
    parent,
    breadcrumb,
    childType,
    typeOptions,
}: {
    items: Item[];
    parent: { id: number; name: string; type: string; parentId: number | null } | null;
    breadcrumb: { id: number; name: string }[];
    childType: string;
    typeOptions: string[];
}) {
    const [adding, setAdding] = useState(false);
    const [importing, setImporting] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);

    const create = useForm({
        parent_id: parent?.id ?? null,
        type: childType,
        name: '',
        code: '',
        description: '',
    });

    const bulk = useForm({ parent_id: parent?.id ?? 0, type: childType, names: '' });
    const edit = useForm({ name: '', code: '', description: '' });

    const go = (id: number | null) =>
        router.get(route('admin.curriculum.index'), id ? { parent: id } : {}, { preserveState: false });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Curriculum</h2>}>
            <Head title="Curriculum" />

            <div className="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
                <p className="text-sm text-slate-600">
                    Everything students choose during onboarding comes from this tree. Changes take effect
                    immediately, with no release needed.
                </p>

                <nav className="flex flex-wrap items-center gap-2 text-sm">
                    <button onClick={() => go(null)} className="text-indigo-600 hover:text-indigo-500">
                        All pathways
                    </button>
                    {breadcrumb.map((crumb) => (
                        <span key={crumb.id} className="flex items-center gap-2">
                            <span className="text-slate-300">/</span>
                            <button onClick={() => go(crumb.id)} className="text-indigo-600 hover:text-indigo-500">
                                {crumb.name}
                            </button>
                        </span>
                    ))}
                </nav>

                <div className="flex flex-wrap gap-3">
                    <button
                        onClick={() => setAdding((v) => !v)}
                        className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Add {childType.replace('_', ' ')}
                    </button>
                    {parent && (
                        <button
                            onClick={() => setImporting((v) => !v)}
                            className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Bulk add
                        </button>
                    )}
                </div>

                {adding && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            create.post(route('admin.curriculum.store'), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    create.reset('name', 'code', 'description');
                                    setAdding(false);
                                },
                            });
                        }}
                        className="grid gap-3 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-4"
                    >
                        <input
                            value={create.data.name}
                            onChange={(e) => create.setData('name', e.target.value)}
                            placeholder="Name, e.g. Grade 11 or Accounting"
                            className="rounded-lg border-slate-300 text-sm sm:col-span-2"
                        />
                        <input
                            value={create.data.code}
                            onChange={(e) => create.setData('code', e.target.value)}
                            placeholder="Code (optional)"
                            className="rounded-lg border-slate-300 text-sm"
                        />
                        <select
                            value={create.data.type}
                            onChange={(e) => create.setData('type', e.target.value)}
                            className="rounded-lg border-slate-300 text-sm capitalize"
                        >
                            {typeOptions.map((type) => (
                                <option key={type} value={type}>
                                    {type.replace('_', ' ')}
                                </option>
                            ))}
                        </select>
                        <button className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white sm:col-span-4 sm:w-40">
                            Add
                        </button>
                        {create.errors.name && (
                            <p className="text-xs text-rose-600 sm:col-span-4">{create.errors.name}</p>
                        )}
                    </form>
                )}

                {importing && parent && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            bulk.post(route('admin.curriculum.import'), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    bulk.reset('names');
                                    setImporting(false);
                                },
                            });
                        }}
                        className="space-y-3 rounded-xl border border-slate-200 bg-white p-5"
                    >
                        <p className="text-sm font-medium text-slate-900">
                            Paste one name per line. Duplicates are skipped.
                        </p>
                        <textarea
                            rows={6}
                            value={bulk.data.names}
                            onChange={(e) => bulk.setData('names', e.target.value)}
                            placeholder={'Mathematics\nPhysical Sciences\nAccounting'}
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />
                        <button className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">
                            Import
                        </button>
                    </form>
                )}

                {items.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-sm text-slate-500">
                        Nothing here yet. Add the first {childType.replace('_', ' ')}.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {items.map((item) => (
                            <li key={item.id} className="px-5 py-3.5">
                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <p
                                                className={`font-medium ${
                                                    item.isActive ? 'text-slate-900' : 'text-slate-400 line-through'
                                                }`}
                                            >
                                                {item.name}
                                            </p>
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] capitalize text-slate-500">
                                                {item.type.replace('_', ' ')}
                                            </span>
                                            {item.code && (
                                                <span className="text-[11px] text-slate-400">{item.code}</span>
                                            )}
                                        </div>
                                        {item.description && (
                                            <p className="mt-0.5 text-xs text-slate-500">{item.description}</p>
                                        )}
                                    </div>

                                    {item.children > 0 && (
                                        <button
                                            onClick={() => go(item.id)}
                                            className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            {item.children} inside →
                                        </button>
                                    )}
                                    {item.children === 0 && item.type !== 'subject' && (
                                        <button
                                            onClick={() => go(item.id)}
                                            className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            Open →
                                        </button>
                                    )}

                                    <button
                                        onClick={() => {
                                            setEditing(editing === item.id ? null : item.id);
                                            edit.setData({
                                                name: item.name,
                                                code: item.code ?? '',
                                                description: item.description ?? '',
                                            });
                                        }}
                                        className="text-xs text-slate-500 hover:text-slate-700"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        onClick={() =>
                                            router.post(route('admin.curriculum.toggle', item.id), {}, {
                                                preserveScroll: true,
                                            })
                                        }
                                        className="text-xs text-slate-500 hover:text-slate-700"
                                    >
                                        {item.isActive ? 'Hide' : 'Show'}
                                    </button>
                                </div>

                                {editing === item.id && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            edit.put(route('admin.curriculum.update', item.id), {
                                                preserveScroll: true,
                                                onSuccess: () => setEditing(null),
                                            });
                                        }}
                                        className="mt-3 grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-4"
                                    >
                                        <input
                                            value={edit.data.name}
                                            onChange={(e) => edit.setData('name', e.target.value)}
                                            className="rounded-lg border-slate-300 text-sm sm:col-span-2"
                                        />
                                        <input
                                            value={edit.data.code}
                                            onChange={(e) => edit.setData('code', e.target.value)}
                                            placeholder="Code"
                                            className="rounded-lg border-slate-300 text-sm"
                                        />
                                        <button className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                                            Save
                                        </button>
                                    </form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <Link href={route('admin.dashboard')} className="inline-block text-sm text-indigo-600 hover:text-indigo-500">
                    ← Back to overview
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
