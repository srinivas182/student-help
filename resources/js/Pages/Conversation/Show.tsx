import StatusBadge from '@/Components/StatusBadge';
import VoiceNotePlayer from '@/Components/VoiceNotePlayer';
import VoiceRecorder from '@/Components/VoiceRecorder';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface ChatMessage {
    id: number;
    body: string;
    sender: string | null;
    isMine: boolean;
    sentAt: string | null;
    sentOn: string | null;
    readAt: string | null;
}

interface Props {
    request: {
        id: number;
        topic: string;
        subject: string | null;
        description: string;
        status: string;
        isOpen: boolean;
    };
    counterpart: { name: string; initial: string } | null;
    messages: ChatMessage[];
    isTutor: boolean;
    canPost: boolean;
    rating: { stars: number; comment: string | null } | null;
    voiceNotes: {
        id: number;
        title: string | null;
        author: string | null;
        duration: string;
        transcript: string | null;
        status: string;
        createdAt: string | null;
    }[];
}

const REPORT_REASONS = [
    { value: 'inappropriate', label: 'Inappropriate content' },
    { value: 'contact_details', label: 'Asking to move off the platform' },
    { value: 'academic_dishonesty', label: 'Asking me to do their assessment' },
    { value: 'harassment', label: 'Harassment or bullying' },
    { value: 'spam', label: 'Spam or advertising' },
    { value: 'other', label: 'Something else' },
];

export default function Show({ request, counterpart, messages, isTutor, canPost, rating, voiceNotes }: Props) {
    const [thread, setThread] = useState<ChatMessage[]>(messages);
    const [reportingId, setReportingId] = useState<number | null>(null);
    const bottom = useRef<HTMLDivElement>(null);

    const { data, setData, post, processing, reset } = useForm({ body: '' });

    // MSG-02: poll so new messages appear without a reload.
    useEffect(() => {
        if (!request.isOpen) return;

        const timer = setInterval(async () => {
            try {
                const response = await window.axios.get(route('conversations.poll', request.id));
                setThread(response.data.messages);
            } catch {
                // Offline or server hiccup — the next tick retries.
            }
        }, 5000);

        return () => clearInterval(timer);
    }, [request.id, request.isOpen]);

    useEffect(() => {
        bottom.current?.scrollIntoView({ behavior: 'smooth' });
    }, [thread.length]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('conversations.store', request.id), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    const report = (messageId: number, reason: string) => {
        router.post(
            route('conversations.report', [request.id, messageId]),
            { reason },
            { preserveScroll: true, onFinish: () => setReportingId(null) },
        );
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">{request.topic}</h2>}
        >
            <Head title={request.topic} />

            <div className="mx-auto max-w-3xl px-4 py-6 sm:px-6">
                <Link
                    href={isTutor ? route('tutor.queue') : route('requests.index')}
                    className="text-sm text-slate-500 hover:text-slate-800"
                >
                    ← {isTutor ? 'My queue' : 'My questions'}
                </Link>

                <div className="mt-4 flex items-center justify-between gap-4 rounded-t-xl border border-b-0 border-slate-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        {counterpart && (
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700">
                                {counterpart.initial}
                            </div>
                        )}
                        <div>
                            <p className="font-medium text-slate-900">{counterpart?.name ?? 'Waiting for a tutor'}</p>
                            <p className="text-xs text-slate-500">{request.subject}</p>
                        </div>
                    </div>
                    <StatusBadge status={request.status} />
                </div>

                <div className="h-[55vh] space-y-4 overflow-y-auto border-x border-slate-200 bg-slate-50 p-4">
                    <div className="rounded-xl bg-white p-4 text-sm text-slate-700 shadow-sm">
                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-slate-400">
                            The question
                        </p>
                        {request.description}
                    </div>

                    {thread.map((message) => (
                        <div key={message.id} className={`flex ${message.isMine ? 'justify-end' : 'justify-start'}`}>
                            <div className="max-w-[80%]">
                                <div
                                    className={`rounded-2xl px-4 py-2.5 text-sm ${
                                        message.isMine
                                            ? 'rounded-br-sm bg-indigo-600 text-white'
                                            : 'rounded-bl-sm bg-white text-slate-800 shadow-sm'
                                    }`}
                                >
                                    {message.body}
                                </div>
                                <div
                                    className={`mt-1 flex items-center gap-2 text-[11px] text-slate-400 ${
                                        message.isMine ? 'justify-end' : ''
                                    }`}
                                >
                                    <span>{message.sentAt}</span>
                                    {message.isMine && message.readAt && <span>· Read</span>}
                                    {!message.isMine && (
                                        <button
                                            onClick={() => setReportingId(message.id)}
                                            className="underline hover:text-rose-600"
                                        >
                                            Report
                                        </button>
                                    )}
                                </div>

                                {reportingId === message.id && (
                                    <div className="mt-2 rounded-lg border border-rose-200 bg-rose-50 p-3">
                                        <p className="mb-2 text-xs font-medium text-rose-900">
                                            Why are you reporting this?
                                        </p>
                                        <div className="space-y-1">
                                            {REPORT_REASONS.map((reason) => (
                                                <button
                                                    key={reason.value}
                                                    onClick={() => report(message.id, reason.value)}
                                                    className="block w-full rounded px-2 py-1 text-left text-xs text-rose-800 hover:bg-rose-100"
                                                >
                                                    {reason.label}
                                                </button>
                                            ))}
                                            <button
                                                onClick={() => setReportingId(null)}
                                                className="block w-full rounded px-2 py-1 text-left text-xs text-slate-500 hover:bg-slate-100"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}

                    <div ref={bottom} />
                </div>

                <div className="rounded-b-xl border border-slate-200 bg-white p-4">
                    {canPost ? (
                        <form onSubmit={submit} className="flex gap-3">
                            <input
                                value={data.body}
                                onChange={(e) => setData('body', e.target.value)}
                                placeholder="Type your message…"
                                className="flex-1 rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <button
                                type="submit"
                                disabled={processing || data.body.trim() === ''}
                                className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                            >
                                Send
                            </button>
                        </form>
                    ) : (
                        <p className="text-center text-sm text-slate-500">
                            {request.isOpen
                                ? 'A parent or guardian needs to approve your account before you can send messages.'
                                : 'This conversation is closed.'}
                        </p>
                    )}

                    <p className="mt-3 text-center text-[11px] text-slate-400">
                        Keep conversations here. Phone numbers, emails and links are removed automatically,
                        and moderators can review any conversation to keep learners safe.
                    </p>
                </div>

                {voiceNotes.length > 0 && (
                    <section className="mt-5 space-y-3">
                        <h2 className="text-sm font-semibold text-slate-900">Voice notes</h2>
                        {voiceNotes.map((note) => (
                            <VoiceNotePlayer key={note.id} note={note} />
                        ))}
                    </section>
                )}

                {canPost && (
                    <div className="mt-5">
                        <VoiceRecorder context="request" contextId={request.id} maxSeconds={300} />
                    </div>
                )}

                {!isTutor && !rating && ['resolved', 'closed'].includes(request.status) && (
                    <RatingForm requestId={request.id} />
                )}

                {rating && (
                    <div className="mt-5 rounded-xl border border-slate-200 bg-white p-5 text-sm">
                        <p className="font-medium text-slate-900">Your rating</p>
                        <p className="mt-1 text-amber-500">{'★'.repeat(rating.stars)}{'☆'.repeat(5 - rating.stars)}</p>
                        {rating.comment && <p className="mt-2 text-slate-600">{rating.comment}</p>}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function RatingForm({ requestId }: { requestId: number }) {
    const { data, setData, post, processing } = useForm({ stars: 0, comment: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('ratings.store', requestId), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-5 rounded-xl border border-slate-200 bg-white p-5">
            <p className="font-medium text-slate-900">How was the help you received?</p>

            <div className="mt-3 flex gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        type="button"
                        onClick={() => setData('stars', star)}
                        aria-label={`${star} star${star === 1 ? '' : 's'}`}
                        className={`text-3xl transition ${
                            star <= data.stars ? 'text-amber-400' : 'text-slate-200 hover:text-amber-200'
                        }`}
                    >
                        ★
                    </button>
                ))}
            </div>

            <textarea
                rows={2}
                value={data.comment}
                onChange={(e) => setData('comment', e.target.value)}
                placeholder="Anything you would like to add? (optional)"
                className="mt-3 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            />

            <button
                type="submit"
                disabled={processing || data.stars === 0}
                className="mt-3 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
            >
                Submit rating
            </button>
        </form>
    );
}
