interface Props {
    note: {
        id: number;
        title: string | null;
        author: string | null;
        duration: string;
        transcript: string | null;
        status: string;
        createdAt: string | null;
    };
    onDelete?: () => void;
}

export default function VoiceNotePlayer({ note, onDelete }: Props) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-slate-900">
                        🎧 {note.title ?? 'Voice note'}
                        <span className="ml-2 text-xs font-normal text-slate-400">{note.duration}</span>
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {note.author} · {note.createdAt}
                    </p>
                </div>

                {onDelete && (
                    <button onClick={onDelete} className="text-xs text-slate-400 hover:text-rose-600">
                        Remove
                    </button>
                )}
            </div>

            <audio controls preload="none" src={route('voiceNotes.play', note.id)} className="mt-3 w-full" />

            {note.transcript && (
                <details className="mt-3">
                    <summary className="cursor-pointer text-xs font-medium text-indigo-600">
                        Read the transcript
                    </summary>
                    <p className="mt-2 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        {note.transcript}
                    </p>
                </details>
            )}
        </div>
    );
}
