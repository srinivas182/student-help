import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export interface SessionMaterial {
    id: number;
    title: string;
    isLink: boolean;
    url: string | null;
    size: string;
}

/**
 * Everything that hangs off a session once it exists: the recording, the
 * materials, and the register.
 *
 * Live is the product and the recording is the safety net, so this sits below
 * the join button rather than competing with it.
 */
export default function SessionExtras({
    sessionId,
    isTeacher,
    isPast,
    hasRecording,
    recordingNote,
    materials,
}: {
    sessionId: number;
    isTeacher: boolean;
    isPast: boolean;
    hasRecording: boolean;
    recordingNote: string | null;
    materials: SessionMaterial[];
}) {
    const [panel, setPanel] = useState<'none' | 'recording' | 'material'>('none');

    const recording = useForm<{
        recording_url: string;
        recording_file: File | null;
        recording_note: string;
    }>({ recording_url: '', recording_file: null, recording_note: '' });

    const material = useForm<{ title: string; external_url: string; file: File | null }>({
        title: '',
        external_url: '',
        file: null,
    });

    const nothingToShow = !hasRecording && materials.length === 0 && !isTeacher;

    if (nothingToShow) {
        return null;
    }

    return (
        <div className="mt-4 space-y-3 border-t border-slate-200 pt-4">
            {/* The recording */}
            {hasRecording ? (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3">
                    <div className="min-w-0">
                        <p className="text-sm font-medium text-slate-900">Recording available</p>
                        {recordingNote && (
                            <p className="mt-0.5 text-xs text-slate-600">{recordingNote}</p>
                        )}
                    </div>

                    <div className="flex items-center gap-3">
                        <a
                            href={route('classrooms.sessions.watch', sessionId)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700"
                        >
                            <svg className="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                            Watch it
                        </a>

                        {isTeacher && (
                            <button
                                onClick={() =>
                                    router.delete(route('classrooms.sessions.recording.remove', sessionId), {
                                        preserveScroll: true,
                                    })
                                }
                                className="text-xs text-slate-400 hover:text-rose-600"
                            >
                                Remove
                            </button>
                        )}
                    </div>
                </div>
            ) : (
                isTeacher &&
                isPast && (
                    <button
                        onClick={() => setPanel(panel === 'recording' ? 'none' : 'recording')}
                        className="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        + Add the recording
                    </button>
                )
            )}

            {panel === 'recording' && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        recording.post(route('classrooms.sessions.recording', sessionId), {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                recording.reset();
                                setPanel('none');
                            },
                        });
                    }}
                    className="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
                >
                    <div>
                        <label className="text-xs font-medium text-slate-700">
                            Link to the recording
                        </label>
                        <input
                            value={recording.data.recording_url}
                            onChange={(e) => recording.setData('recording_url', e.target.value)}
                            placeholder="YouTube (unlisted), Google Drive or Zoom Cloud"
                            className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            A link is better than a file. It costs your learners less data and does not
                            fill the server.
                        </p>
                        {recording.errors.recording_url && (
                            <p className="mt-1 text-xs text-rose-600">{recording.errors.recording_url}</p>
                        )}
                    </div>

                    <details>
                        <summary className="cursor-pointer text-xs text-slate-500">
                            Or upload the file instead
                        </summary>
                        <input
                            type="file"
                            accept="video/mp4,video/webm,video/quicktime"
                            onChange={(e) =>
                                recording.setData('recording_file', e.target.files?.[0] ?? null)
                            }
                            className="mt-2 block w-full text-xs text-slate-600"
                        />
                        <p className="mt-1 text-xs text-slate-500">MP4, WebM or MOV, up to 500 MB.</p>
                        {recording.errors.recording_file && (
                            <p className="mt-1 text-xs text-rose-600">{recording.errors.recording_file}</p>
                        )}
                    </details>

                    <input
                        value={recording.data.recording_note}
                        onChange={(e) => recording.setData('recording_note', e.target.value)}
                        placeholder="Anything learners should know about it (optional)"
                        className="block w-full rounded-lg border-slate-300 text-sm"
                    />

                    <div className="flex gap-2">
                        <button
                            disabled={recording.processing}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white disabled:bg-slate-300"
                        >
                            {recording.processing ? 'Saving…' : 'Add recording'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setPanel('none')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-xs text-slate-600"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            )}

            {/* Materials */}
            {materials.length > 0 && (
                <ul className="space-y-1.5">
                    {materials.map((item) => (
                        <li key={item.id}>
                            <a
                                href={item.url ?? '#'}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 text-sm text-slate-700 hover:text-indigo-700"
                            >
                                <svg className="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8} aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6" />
                                </svg>
                                {item.title}
                                {item.size && <span className="text-xs text-slate-400">{item.size}</span>}
                            </a>
                        </li>
                    ))}
                </ul>
            )}

            {isTeacher && (
                <div className="flex flex-wrap items-center gap-4">
                    <button
                        onClick={() => setPanel(panel === 'material' ? 'none' : 'material')}
                        className="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        + Add slides or notes
                    </button>

                    <Link
                        href={route('classrooms.sessions.register', sessionId)}
                        className="text-xs font-medium text-slate-500 hover:text-slate-800"
                    >
                        Attendance register
                    </Link>
                </div>
            )}

            {panel === 'material' && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        material.post(route('classrooms.sessions.materials', sessionId), {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                material.reset();
                                setPanel('none');
                            },
                        });
                    }}
                    className="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
                >
                    <input
                        value={material.data.title}
                        onChange={(e) => material.setData('title', e.target.value)}
                        placeholder="What is it? e.g. Slides from today"
                        className="block w-full rounded-lg border-slate-300 text-sm"
                    />
                    {material.errors.title && (
                        <p className="text-xs text-rose-600">{material.errors.title}</p>
                    )}

                    <input
                        type="file"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg,.jpeg"
                        onChange={(e) => material.setData('file', e.target.files?.[0] ?? null)}
                        className="block w-full text-xs text-slate-600"
                    />

                    <input
                        value={material.data.external_url}
                        onChange={(e) => material.setData('external_url', e.target.value)}
                        placeholder="Or paste a link"
                        className="block w-full rounded-lg border-slate-300 text-sm"
                    />

                    <div className="flex gap-2">
                        <button
                            disabled={material.processing}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white disabled:bg-slate-300"
                        >
                            {material.processing ? 'Adding…' : 'Add'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setPanel('none')}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-xs text-slate-600"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
