import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Message {
    id: number;
    author: string | null;
    isMinor: boolean;
    body: string;
    original: string;
    wasMasked: boolean;
    isFlagged: boolean;
    isRemoved: boolean;
    sentAt: string | null;
}

export default function StudyGroupThread({
    group,
    messages,
    members,
}: {
    group: {
        id: number;
        name: string;
        subject: string | null;
        owner: string | null;
        reports: number;
        isLocked: boolean;
    };
    messages: Message[];
    members: { id: number; name: string; isMinor: boolean }[];
}) {
    const minors = members.filter((member) => member.isMinor).length;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Group review</h2>}>
            <Head title={`Review: ${group.name}`} />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-8 sm:px-6">
                <Link href={route('moderation.groups')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← Study groups
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-5">
                    <h1 className="font-semibold text-slate-900">{group.name}</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {group.subject ? `${group.subject} · ` : ''}owner {group.owner} · {members.length}{' '}
                        members
                        {minors > 0 && (
                            <span className="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">
                                {minors} under 18
                            </span>
                        )}
                    </p>
                    <p className="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                        You are reading the unmasked thread. This access has been recorded in the audit log.
                    </p>
                </section>

                <ul className="space-y-2">
                    {messages.map((message) => (
                        <li
                            key={message.id}
                            className={`rounded-xl border bg-white p-4 ${
                                message.isFlagged ? 'border-rose-300' : 'border-slate-200'
                            }`}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-medium text-slate-900">
                                    {message.author}
                                    {message.isMinor && (
                                        <span className="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] text-rose-800">
                                            Minor
                                        </span>
                                    )}
                                </p>
                                <span className="text-xs text-slate-400">{message.sentAt}</span>
                            </div>

                            <p className="mt-2 text-sm text-slate-700">
                                {message.isRemoved ? '[removed by a moderator]' : message.body}
                            </p>

                            {message.wasMasked && (
                                <div className="mt-2 rounded-lg bg-amber-50 p-3">
                                    <p className="text-xs font-medium text-amber-900">What was actually sent</p>
                                    <p className="mt-1 text-sm text-amber-800">{message.original}</p>
                                </div>
                            )}

                            {!message.isRemoved && (
                                <button
                                    onClick={() =>
                                        router.post(route('moderation.groups.message.remove', message.id), {}, {
                                            preserveScroll: true,
                                        })
                                    }
                                    className="mt-2 text-xs text-slate-500 hover:text-rose-600"
                                >
                                    Remove this message
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
