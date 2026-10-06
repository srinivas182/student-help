import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

interface Note {
    id: number;
    title: string | null;
    author: string | null;
    role: string | null;
    duration: string;
    status: string;
    transcript: string | null;
    original: string | null;
    wasMasked: boolean;
    isFlagged: boolean;
    flagReason: string | null;
    context: string | null;
    plays: number;
    createdAt: string | null;
}

export default function VoiceNotes({
    notes,
    filters,
    counts,
    transcriptionEnabled,
}: {
    notes: { data: Note[] };
    filters: { filter: string };
    counts: { flagged: number; untranscribed: number; all: number };
    transcriptionEnabled: boolean;
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Voice notes</h2>}>
            <Head title="Voice note review" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                {!transcriptionEnabled && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <p className="font-medium">Transcription is switched off</p>
                        <p className="mt-1">
                            Voice notes are not being converted to text, so contact details spoken aloud cannot
                            be detected automatically. Review recordings by listening until DX enables a
                            transcription provider.
                        </p>
                    </div>
                )}

                <div className="flex gap-2">
                    {[
                        ['flagged', `Flagged (${counts.flagged})`],
                        ['untranscribed', `No transcript (${counts.untranscribed})`],
                        ['all', `All (${counts.all})`],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            onClick={() => router.get(route('moderation.voice'), { filter: value })}
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

                {notes.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing to review.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {notes.data.map((note) => (
                            <li
                                key={note.id}
                                className={`rounded-xl border bg-white p-5 ${
                                    note.isFlagged ? 'border-rose-300' : 'border-slate-200'
                                }`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">
                                            {note.title ?? 'Voice note'}{' '}
                                            <span className="text-xs font-normal text-slate-400">
                                                {note.duration}
                                            </span>
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {note.author} ({note.role}) · {note.context ?? 'unattached'} ·{' '}
                                            {note.plays} plays · {note.createdAt}
                                        </p>
                                        {note.isFlagged && (
                                            <p className="mt-2 text-xs font-medium text-rose-700">
                                                Flagged:{' '}
                                                {note.flagReason === 'contact_details_spoken'
                                                    ? 'contact details spoken aloud'
                                                    : note.flagReason}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex shrink-0 gap-2">
                                        {note.isFlagged && (
                                            <button
                                                onClick={() =>
                                                    router.post(route('moderation.voice.clear', note.id), {}, {
                                                        preserveScroll: true,
                                                    })
                                                }
                                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-600"
                                            >
                                                Clear flag
                                            </button>
                                        )}
                                        <button
                                            onClick={() =>
                                                router.delete(route('moderation.voice.remove', note.id), {
                                                    preserveScroll: true,
                                                })
                                            }
                                            className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-500"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <audio
                                    controls
                                    preload="none"
                                    src={route('voiceNotes.play', note.id)}
                                    className="mt-3 w-full"
                                />

                                {note.status === 'done' ? (
                                    <div className="mt-3 space-y-2">
                                        <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                                            {note.transcript}
                                        </p>
                                        {note.wasMasked && (
                                            <div className="rounded-lg bg-amber-50 p-3">
                                                <p className="text-xs font-medium text-amber-900">
                                                    What was actually said
                                                </p>
                                                <p className="mt-1 text-sm text-amber-800">{note.original}</p>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <p className="mt-3 text-xs text-slate-500">
                                        No transcript ({note.status}) — listen to review this one.
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
