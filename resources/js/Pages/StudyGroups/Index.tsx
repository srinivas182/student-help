import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Group {
    id: number;
    name: string;
    description?: string | null;
    subject: string | null;
    members: number;
    capacity: number;
    isOwner?: boolean;
    isLocked?: boolean;
}

export default function Index({
    groups,
    suggested,
    subjects,
    canParticipate,
}: {
    groups: Group[];
    suggested: Group[];
    subjects: { id: number; name: string }[];
    canParticipate: boolean;
}) {
    const [creating, setCreating] = useState(false);

    const create = useForm({ name: '', description: '', curriculum_item_id: '', capacity: '' });
    const join = useForm({ join_code: '' });

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        create.post(route('studyGroups.store'));
    };

    const submitJoin: FormEventHandler = (e) => {
        e.preventDefault();
        join.post(route('studyGroups.join'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Study groups</h2>}>
            <Head title="Study groups" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                {!canParticipate && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                        A parent or guardian needs to approve your account before you can join or create a
                        study group.
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <form onSubmit={submitJoin} className="rounded-xl border border-slate-200 bg-white p-5">
                        <p className="text-sm font-medium text-slate-900">Join with a code</p>
                        <div className="mt-3 flex gap-2">
                            <input
                                value={join.data.join_code}
                                onChange={(e) => join.setData('join_code', e.target.value.toUpperCase())}
                                placeholder="ABC123"
                                maxLength={12}
                                disabled={!canParticipate}
                                className="w-32 rounded-lg border-slate-300 text-center font-mono uppercase tracking-widest"
                            />
                            <button
                                disabled={!canParticipate || join.processing}
                                className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                            >
                                Join
                            </button>
                        </div>
                        {join.errors.join_code && (
                            <p className="mt-2 text-sm text-rose-600">{join.errors.join_code}</p>
                        )}
                    </form>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <p className="text-sm font-medium text-slate-900">Start your own</p>
                        <p className="mt-1 text-xs text-slate-500">
                            Study together with classmates in one of your subjects.
                        </p>
                        <button
                            onClick={() => setCreating((v) => !v)}
                            disabled={!canParticipate}
                            className="mt-3 rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:bg-slate-300"
                        >
                            Create a group
                        </button>
                    </div>
                </div>

                {creating && (
                    <form onSubmit={submitCreate} className="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
                        <input
                            value={create.data.name}
                            onChange={(e) => create.setData('name', e.target.value)}
                            placeholder="Group name, e.g. Grade 11 Maths crew"
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />
                        {create.errors.name && <p className="text-xs text-rose-600">{create.errors.name}</p>}

                        <textarea
                            rows={2}
                            value={create.data.description}
                            onChange={(e) => create.setData('description', e.target.value)}
                            placeholder="What will you work on together?"
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        />

                        <select
                            value={create.data.curriculum_item_id}
                            onChange={(e) => create.setData('curriculum_item_id', e.target.value)}
                            className="block w-full rounded-lg border-slate-300 text-sm"
                        >
                            <option value="">No specific subject</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name}
                                </option>
                            ))}
                        </select>

                        <p className="text-xs text-slate-500">
                            Groups hold up to 30 students. Everything posted is checked the same way as private
                            messages, and moderators can review any group.
                        </p>

                        <button className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">
                            Create group
                        </button>
                    </form>
                )}

                <section>
                    <h3 className="mb-3 font-semibold text-slate-900">My groups</h3>
                    {groups.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                            You are not in a study group yet.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {groups.map((group) => (
                                <li key={group.id}>
                                    <Link
                                        href={route('studyGroups.show', group.id)}
                                        className="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300"
                                    >
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-slate-900">{group.name}</p>
                                            {group.isOwner && (
                                                <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-800">
                                                    Owner
                                                </span>
                                            )}
                                            {group.isLocked && (
                                                <span className="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-medium text-rose-800">
                                                    Closed
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {group.subject ? `${group.subject} · ` : ''}
                                            {group.members} of {group.capacity} members
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {suggested.length > 0 && (
                    <section>
                        <h3 className="mb-3 font-semibold text-slate-900">Groups in your subjects</h3>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {suggested.map((group) => (
                                <div key={group.id} className="rounded-xl border border-slate-200 bg-white p-5">
                                    <p className="font-medium text-slate-900">{group.name}</p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {group.subject} · {group.members} of {group.capacity}
                                    </p>
                                    <button
                                        onClick={() => router.post(route('studyGroups.joinById', group.id))}
                                        disabled={!canParticipate}
                                        className="mt-3 rounded-lg border border-slate-300 px-4 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                    >
                                        Join group
                                    </button>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
