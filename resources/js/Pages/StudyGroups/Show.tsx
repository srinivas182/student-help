import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface Message {
    id: number;
    body: string;
    author: string | null;
    isMine: boolean;
    isRemoved: boolean;
    sentAt: string | null;
}

const REASONS = [
    ['inappropriate', 'Inappropriate content'],
    ['contact_details', 'Sharing contact details'],
    ['harassment', 'Harassment or bullying'],
    ['academic_dishonesty', 'Cheating'],
    ['spam', 'Spam'],
    ['other', 'Something else'],
];

export default function Show({
    group,
    messages,
    members,
    canPost,
}: {
    group: {
        id: number;
        name: string;
        description: string | null;
        subject: string | null;
        owner: string | null;
        joinCode: string | null;
        isOwner: boolean;
        isLocked: boolean;
        lockedReason: string | null;
        capacity: number;
    };
    messages: Message[];
    members: { id: number; name: string; role: string }[];
    canPost: boolean;
}) {
    const [thread, setThread] = useState(messages);
    const [reporting, setReporting] = useState<number | null>(null);
    const bottom = useRef<HTMLDivElement>(null);

    const { data, setData, post, processing, reset } = useForm({ body: '' });

    useEffect(() => {
        if (group.isLocked) return;

        const timer = setInterval(async () => {
            try {
                const response = await window.axios.get(route('studyGroups.poll', group.id));
                setThread(response.data.messages);
            } catch {
                // retried on the next tick
            }
        }, 6000);

        return () => clearInterval(timer);
    }, [group.id, group.isLocked]);

    useEffect(() => {
        bottom.current?.scrollIntoView({ behavior: 'smooth' });
    }, [thread.length]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('studyGroups.post', group.id), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">{group.name}</h2>}>
            <Head title={group.name} />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6">
                <Link href={route('studyGroups.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← All groups
                </Link>

                {group.isLocked && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                        <p className="font-medium">This group has been closed by a moderator</p>
                        {group.lockedReason && <p className="mt-1">{group.lockedReason}</p>}
                        <p className="mt-1">You can still read it, but nobody can post.</p>
                    </div>
                )}

                <section className="rounded-xl border border-slate-200 bg-white p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="font-semibold text-slate-900">{group.name}</h1>
                            <p className="mt-0.5 text-xs text-slate-500">
                                {group.subject ? `${group.subject} · ` : ''}
                                {members.length} of {group.capacity} members · started by {group.owner}
                            </p>
                            {group.description && (
                                <p className="mt-2 text-sm text-slate-700">{group.description}</p>
                            )}
                        </div>

                        {group.joinCode && (
                            <div className="rounded-lg bg-slate-50 px-4 py-2 text-center">
                                <p className="text-[11px] uppercase tracking-wide text-slate-500">Code</p>
                                <p className="font-mono font-semibold tracking-widest text-slate-900">
                                    {group.joinCode}
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {members.map((member) => (
                            <span
                                key={member.id}
                                className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700"
                            >
                                {member.name}
                                {member.role === 'owner' && ' ★'}
                                {group.isOwner && member.role !== 'owner' && (
                                    <button
                                        onClick={() =>
                                            router.post(
                                                route('studyGroups.members.remove', [group.id, member.id]),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="ml-2 text-slate-400 hover:text-rose-600"
                                    >
                                        ×
                                    </button>
                                )}
                            </span>
                        ))}
                    </div>

                    <button
                        onClick={() => router.post(route('studyGroups.leave', group.id))}
                        className="mt-4 text-xs text-slate-500 hover:text-rose-600"
                    >
                        Leave this group
                    </button>
                </section>

                <div className="h-[50vh] space-y-3 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-4">
                    {thread.length === 0 ? (
                        <p className="py-16 text-center text-sm text-slate-500">
                            No messages yet. Say hello and set a study goal for the week.
                        </p>
                    ) : (
                        thread.map((message) => (
                            <div key={message.id} className={`flex ${message.isMine ? 'justify-end' : ''}`}>
                                <div className="max-w-[80%]">
                                    {!message.isMine && (
                                        <p className="mb-0.5 text-[11px] font-medium text-slate-500">
                                            {message.author}
                                        </p>
                                    )}
                                    <div
                                        className={`rounded-2xl px-4 py-2 text-sm ${
                                            message.isRemoved
                                                ? 'bg-slate-200 italic text-slate-500'
                                                : message.isMine
                                                  ? 'rounded-br-sm bg-indigo-600 text-white'
                                                  : 'rounded-bl-sm bg-white text-slate-800 shadow-sm'
                                        }`}
                                    >
                                        {message.body}
                                    </div>
                                    <div
                                        className={`mt-1 flex gap-2 text-[11px] text-slate-400 ${
                                            message.isMine ? 'justify-end' : ''
                                        }`}
                                    >
                                        <span>{message.sentAt}</span>
                                        {!message.isMine && !message.isRemoved && (
                                            <button
                                                onClick={() => setReporting(message.id)}
                                                className="underline hover:text-rose-600"
                                            >
                                                Report
                                            </button>
                                        )}
                                    </div>

                                    {reporting === message.id && (
                                        <div className="mt-2 rounded-lg border border-rose-200 bg-rose-50 p-2">
                                            {REASONS.map(([value, label]) => (
                                                <button
                                                    key={value}
                                                    onClick={() =>
                                                        router.post(
                                                            route('studyGroups.report', [group.id, message.id]),
                                                            { reason: value },
                                                            {
                                                                preserveScroll: true,
                                                                onFinish: () => setReporting(null),
                                                            },
                                                        )
                                                    }
                                                    className="block w-full rounded px-2 py-1 text-left text-xs text-rose-800 hover:bg-rose-100"
                                                >
                                                    {label}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                    <div ref={bottom} />
                </div>

                {canPost ? (
                    <form onSubmit={submit} className="flex gap-3">
                        <input
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Message the group…"
                            className="flex-1 rounded-lg border-slate-300 text-sm"
                        />
                        <button
                            disabled={processing || data.body.trim() === ''}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                        >
                            Send
                        </button>
                    </form>
                ) : (
                    <p className="text-center text-sm text-slate-500">
                        {group.isLocked
                            ? 'This group is closed.'
                            : 'A parent or guardian needs to approve your account before you can post.'}
                    </p>
                )}

                <p className="text-center text-[11px] text-slate-400">
                    Group messages are filtered the same way as private messages, and moderators can review any
                    group. Report anything that worries you.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
