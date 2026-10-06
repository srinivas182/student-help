import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    name: string;
    subject: string | null;
    owner: string | null;
    members: number;
    messages: number;
    reports: number;
    isFlagged: boolean;
    isLocked: boolean;
    lockedReason: string | null;
    createdAt: string | null;
}

export default function StudyGroups({
    groups,
    filters,
    counts,
}: {
    groups: { data: Row[] };
    filters: { filter: string };
    counts: { flagged: number; locked: number; all: number };
}) {
    const [locking, setLocking] = useState<number | null>(null);
    const lock = useForm({ reason: '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Study groups</h2>}>
            <Head title="Study group review" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <p className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                    Study groups have no teacher in the room. A group is flagged automatically once three
                    concerns are raised, so problems surface without anyone having to watch every group.
                </p>

                <div className="flex gap-2">
                    {[
                        ['flagged', `Flagged (${counts.flagged})`],
                        ['locked', `Closed (${counts.locked})`],
                        ['all', `All (${counts.all})`],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            onClick={() => router.get(route('moderation.groups'), { filter: value })}
                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                filters.filter === value
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {groups.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing to review.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {groups.data.map((group) => (
                            <li
                                key={group.id}
                                className={`rounded-xl border bg-white p-5 ${
                                    group.isFlagged ? 'border-rose-300' : 'border-slate-200'
                                }`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-slate-900">{group.name}</p>
                                            {group.isFlagged && (
                                                <span className="rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-medium text-rose-800">
                                                    {group.reports} concerns
                                                </span>
                                            )}
                                            {group.isLocked && (
                                                <span className="rounded-full bg-slate-200 px-2.5 py-0.5 text-[11px] font-medium text-slate-700">
                                                    Closed
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {group.subject ? `${group.subject} · ` : ''}
                                            owner {group.owner} · {group.members} members ·{' '}
                                            {group.messages} messages · {group.createdAt}
                                        </p>
                                        {group.lockedReason && (
                                            <p className="mt-2 rounded bg-slate-50 p-2 text-xs text-slate-600">
                                                {group.lockedReason}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex shrink-0 gap-2">
                                        <Link
                                            href={route('moderation.groups.show', group.id)}
                                            className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Read thread
                                        </Link>
                                        {group.isLocked ? (
                                            <button
                                                onClick={() =>
                                                    router.post(route('moderation.groups.unlock', group.id), {}, {
                                                        preserveScroll: true,
                                                    })
                                                }
                                                className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white"
                                            >
                                                Reopen
                                            </button>
                                        ) : (
                                            <button
                                                onClick={() => setLocking(locking === group.id ? null : group.id)}
                                                className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white"
                                            >
                                                Close group
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {locking === group.id && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            lock.post(route('moderation.groups.lock', group.id), {
                                                preserveScroll: true,
                                                onSuccess: () => setLocking(null),
                                            });
                                        }}
                                        className="mt-3 flex gap-2"
                                    >
                                        <input
                                            value={lock.data.reason}
                                            onChange={(e) => lock.setData('reason', e.target.value)}
                                            placeholder="Why — members see this"
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
