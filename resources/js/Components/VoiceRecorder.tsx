import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    context: 'classroom' | 'request';
    contextId: number;
    maxSeconds?: number;
    onDone?: () => void;
}

/**
 * Records straight in the browser with MediaRecorder — no app needed, and the
 * file never leaves the device until the teacher chooses to send it.
 */
export default function VoiceRecorder({ context, contextId, maxSeconds = 600, onDone }: Props) {
    const [state, setState] = useState<'idle' | 'recording' | 'review' | 'sending'>('idle');
    const [seconds, setSeconds] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [title, setTitle] = useState('');

    const recorder = useRef<MediaRecorder | null>(null);
    const chunks = useRef<Blob[]>([]);
    const blob = useRef<Blob | null>(null);
    const timer = useRef<number | null>(null);

    useEffect(() => () => {
        if (timer.current) window.clearInterval(timer.current);
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    }, [previewUrl]);

    const start = async () => {
        setError(null);

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/mp4';

            chunks.current = [];
            recorder.current = new MediaRecorder(stream, { mimeType: mime });

            recorder.current.ondataavailable = (event) => {
                if (event.data.size > 0) chunks.current.push(event.data);
            };

            recorder.current.onstop = () => {
                blob.current = new Blob(chunks.current, { type: mime });
                setPreviewUrl(URL.createObjectURL(blob.current));
                stream.getTracks().forEach((track) => track.stop());
                setState('review');
            };

            recorder.current.start();
            setSeconds(0);
            setState('recording');

            timer.current = window.setInterval(() => {
                setSeconds((value) => {
                    if (value + 1 >= maxSeconds) stop();
                    return value + 1;
                });
            }, 1000);
        } catch {
            setError('We could not reach your microphone. Check that your browser has permission.');
        }
    };

    const stop = () => {
        if (timer.current) window.clearInterval(timer.current);
        recorder.current?.stop();
    };

    const discard = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        blob.current = null;
        setPreviewUrl(null);
        setSeconds(0);
        setState('idle');
    };

    const send = () => {
        if (!blob.current) return;

        setState('sending');

        const data = new FormData();
        data.append('audio', blob.current, 'voice-note.webm');
        data.append('duration_seconds', String(seconds));
        data.append('context', context);
        data.append('context_id', String(contextId));
        if (title) data.append('title', title);

        router.post(route('voiceNotes.store'), data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                discard();
                onDone?.();
            },
            onError: () => {
                setError('That did not send. Please try again.');
                setState('review');
            },
        });
    };

    const clock = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5">
            <p className="text-sm font-medium text-slate-900">Record a voice note</p>
            <p className="mt-1 text-xs text-slate-500">
                Explain it out loud — often quicker than typing. Up to {Math.round(maxSeconds / 60)} minutes.
                Voice notes are reviewable by moderators, like every message.
            </p>

            {error && <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p>}

            {state === 'idle' && (
                <button
                    onClick={start}
                    className="mt-4 flex items-center gap-2 rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-500"
                >
                    <span className="h-2.5 w-2.5 rounded-full bg-white" />
                    Start recording
                </button>
            )}

            {state === 'recording' && (
                <div className="mt-4 flex items-center gap-4">
                    <span className="flex items-center gap-2 text-sm font-medium text-rose-600">
                        <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-rose-600" />
                        {clock}
                    </span>
                    <button
                        onClick={stop}
                        className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Stop
                    </button>
                </div>
            )}

            {(state === 'review' || state === 'sending') && previewUrl && (
                <div className="mt-4 space-y-3">
                    <audio controls src={previewUrl} className="w-full" />

                    <input
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="What is this about? (optional)"
                        className="block w-full rounded-lg border-slate-300 text-sm"
                    />

                    <div className="flex gap-3">
                        <button
                            onClick={send}
                            disabled={state === 'sending'}
                            className="rounded-lg bg-teal-600 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-500 disabled:bg-slate-300"
                        >
                            {state === 'sending' ? 'Sending…' : `Send (${clock})`}
                        </button>
                        <button
                            onClick={discard}
                            className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        >
                            Discard
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
