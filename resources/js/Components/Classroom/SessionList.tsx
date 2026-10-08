import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export interface ClassSession {
    id: number;
    title: string;
    description: string | null;
    mode: 'online' | 'in_person';
    meetingUrl: string | null;
    location: string | null;
    startsAt: string;
    whenLabel: string;
    durationMinutes: number;
    status: string;
    cancelReason: string | null;
    isJoinable: boolean;
    isPast: boolean;
    repeats: string | null;
    goingCount: number;
    myResponse: string | null;
}

/**
 * The schedule, from both sides. A learner sees when and where, and a join
 * button that only appears once the session is actually open — a link posted
 * days early just gets lost.
 */
export default function SessionList({
    sessions,
    isTeacher,
}: {
    sessions: ClassSession[];
    isTeacher: boolean;
}) {
    const [cancelling, setCancelling] = useState<number | null>(null);
    const cancel = useForm({ reason: '', whole_series: false as boolean });

    const upcoming = sessions.filter((s) => !s.isPast && s.status !== 'cancelled');
    const other = sessions.filter((s) => s.isPast || s.status === 'cancelled');

    if (sessions.length === 0) {
        return (
            <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                {isTeacher
                    ? 'No sessions yet. Schedule one so your learners know when to turn up.'
                    : 'Your teacher has not scheduled any sessions yet.'}
            </p>
        );
    }

    const card = (session: ClassSession) => (
        <li
            key={session.id}
            className={`rounded-xl border p-5 ${
                session.status === 'cancelled'
                    ? 'border-slate-200 bg-slate-50 opacity-75'
                    : session.isJoinable
                      ? 'border-emerald-300 bg-emerald-50/40'
                      : 'border-slate-200 bg-white'
            }`}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="font-medium text-slate-900">
                        {session.title}
                        {session.repeats === 'weekly' && (
                            <span className="ml-2 text-[11px] font-normal text-slate-400">weekly</span>
                        )}
                    </p>

                    <p className="mt-0.5 text-sm text-slate-600">
                        {session.whenLabel} · {session.durationMinutes} min
                    </p>

                    <p className="mt-1 text-xs text-slate-500">
                        {session.mode === 'online'
                            ? 'Online'
                            : `In person${session.location ? ` · ${session.location}` : ''}`}
                        {session.goingCount > 0 && ` · ${session.goingCount} going`}
                    </p>

                    {session.description && (
                        <p className="mt-2 text-sm text-slate-600">{session.description}</p>
                    )}

                    {session.status === 'cancelled' && (
                        <p className="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800">
                            Cancelled{session.cancelReason ? `: ${session.cancelReason}` : ''}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 flex-col items-end gap-2">
                    {session.isJoinable && session.status !== 'cancelled' && (
                        <a
                            href={route('classrooms.sessions.join', session.id)}
                            className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                        >
                            {session.mode === 'online' ? 'Join now' : "I'm here"}
                        </a>
                    )}

                    {!isTeacher &&
                        !session.isPast &&
                        session.status !== 'cancelled' &&
                        !session.isJoinable && (
                            <div className="flex gap-1.5">
                                {(['going', 'not_going'] as const).map((response) => (
                                    <button
                                        key={response}
                                        onClick={() =>
                                            router.post(
                                                route('classrooms.sessions.respond', session.id),
                                                { response },
                                                { preserveScroll: true },
                                            )
                                        }
                                        className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                                            session.myResponse === response
                                                ? 'bg-slate-900 text-white'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                    >
                                        {response === 'going' ? 'Going' : "Can't make it"}
                                    </button>
                                ))}
                            </div>
                        )}

                    {isTeacher && !session.isPast && session.status !== 'cancelled' && (
                        <button
                            onClick={() => setCancelling(cancelling === session.id ? null : session.id)}
                            className="text-xs font-medium text-slate-500 hover:text-rose-600"
                        >
                            Cancel
                        </button>
                    )}
                </div>
            </div>

            {cancelling === session.id && (
                <div className="mt-4 space-y-2 rounded-lg bg-slate-50 p-4">
                    <input
                        value={cancel.data.reason}
                        onChange={(e) => cancel.setData('reason', e.target.value)}
                        placeholder="Why? Learners will be told."
                        className="w-full rounded-lg border-slate-300 text-sm"
                    />
                    {session.repeats === 'weekly' && (
                        <label className="flex items-center gap-2 text-xs text-slate-600">
                            <input
                                type="checkbox"
                                checked={cancel.data.whole_series}
                                onChange={(e) => cancel.setData('whole_series', e.target.checked)}
                                className="rounded border-slate-300"
                            />
                            Cancel every remaining session in this series
                        </label>
                    )}
                    <button
                        onClick={() =>
                            cancel.post(route('classrooms.sessions.cancel', session.id), {
                                preserveScroll: true,
                                onSuccess: () => setCancelling(null),
                            })
                        }
                        className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white"
                    >
                        Cancel session
                    </button>
                    {cancel.errors.reason && <p className="text-xs text-rose-600">{cancel.errors.reason}</p>}
                </div>
            )}
        </li>
    );

    return (
        <div className="space-y-5">
            {upcoming.length > 0 && (
                <div>
                    <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Coming up
                    </h3>
                    <ul className="space-y-3">{upcoming.map(card)}</ul>
                </div>
            )}

            {other.length > 0 && (
                <details>
                    <summary className="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Past and cancelled ({other.length})
                    </summary>
                    <ul className="mt-3 space-y-3">{other.map(card)}</ul>
                </details>
            )}
        </div>
    );
}
