import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface ModeratorMessage {
    id: number;
    sender: string | null;
    role: string | null;
    body: string;
    original: string;
    wasMasked: boolean;
    isFlagged: boolean;
    sentAt: string | null;
}

export default function Conversation({
    request,
    messages,
}: {
    request: {
        id: number;
        topic: string;
        status: string;
        student: string | null;
        studentIsMinor: boolean;
        tutor: string | null;
    };
    messages: ModeratorMessage[];
}) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Conversation review</h2>}
        >
            <Head title={`Review: ${request.topic}`} />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-8 sm:px-6">
                <Link href={route('moderation.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← Moderation queue
                </Link>

                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <h1 className="font-semibold text-slate-900">{request.topic}</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {request.student} {request.studentIsMinor && (
                            <span className="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">
                                Minor
                            </span>
                        )}{' '}
                        · {request.tutor ?? 'unassigned'}
                    </p>
                    <p className="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                        You are viewing the unmasked conversation. This access has been recorded in the audit
                        log.
                    </p>
                </div>

                <ul className="space-y-3">
                    {messages.map((message) => (
                        <li
                            key={message.id}
                            className={`rounded-xl border bg-white p-4 ${
                                message.isFlagged ? 'border-rose-300' : 'border-slate-200'
                            }`}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-medium text-slate-900">
                                    {message.sender}{' '}
                                    <span className="text-xs font-normal capitalize text-slate-400">
                                        {message.role}
                                    </span>
                                </p>
                                <span className="text-xs text-slate-400">{message.sentAt}</span>
                            </div>

                            <p className="mt-2 text-sm text-slate-700">{message.body}</p>

                            {message.wasMasked && (
                                <div className="mt-3 rounded-lg bg-amber-50 p-3">
                                    <p className="text-xs font-medium text-amber-900">Original text</p>
                                    <p className="mt-1 text-sm text-amber-800">{message.original}</p>
                                </div>
                            )}

                            {message.isFlagged && (
                                <p className="mt-2 text-xs font-medium text-rose-700">
                                    Flagged by the content filter
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
