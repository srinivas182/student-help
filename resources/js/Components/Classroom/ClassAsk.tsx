import { Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Asking, from inside a class, where the context is already known.
 *
 * The global Ask button sends a learner to a blank form where they pick a
 * subject and a topic and wait for any tutor. Inside a class none of that is
 * needed: the subject is the class subject and the tutor is the class teacher.
 */
export default function ClassAsk({
    classroomId,
    teacherName,
    canParticipate,
}: {
    classroomId: number;
    teacherName: string | null;
    canParticipate: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState<'choose' | 'teacher' | 'class'>('choose');

    const form = useForm({ topic: '', description: '' });
    const classForm = useForm({ type: 'question', title: '', body: '' });

    useEffect(() => {
        const escape = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);

        document.addEventListener('keydown', escape);

        return () => document.removeEventListener('keydown', escape);
    }, []);

    const close = () => {
        setOpen(false);
        setMode('choose');
        form.reset();
    };

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
            >
                Ask a question
            </button>

            {open && (
                <div
                    onClick={close}
                    className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 backdrop-blur-sm sm:items-center sm:p-6"
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        className="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl"
                    >
                        {mode === 'choose' ? (
                            <>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    Who should answer this?
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    You are in this class, so we already know the subject.
                                </p>

                                <div className="mt-5 space-y-3">
                                    <button
                                        onClick={() => setMode('teacher')}
                                        disabled={!canParticipate || !teacherName}
                                        className="w-full rounded-xl border border-slate-200 p-4 text-left transition hover:border-indigo-300 hover:bg-indigo-50/50 disabled:opacity-50"
                                    >
                                        <span className="block text-sm font-medium text-slate-900">
                                            {teacherName ? `Ask ${teacherName}` : 'Ask your teacher'}
                                        </span>
                                        <span className="mt-0.5 block text-xs text-slate-500">
                                            Private. Only you and your teacher see it.
                                        </span>
                                    </button>

                                    <button
                                        onClick={() => setMode('class')}
                                        className="w-full rounded-xl border border-slate-200 p-4 text-left transition hover:border-teal-300 hover:bg-teal-50/50"
                                    >
                                        <span className="block text-sm font-medium text-slate-900">
                                            Ask the class
                                        </span>
                                        <span className="mt-0.5 block text-xs text-slate-500">
                                            Everyone in this class sees it and can answer.
                                        </span>
                                    </button>

                                    <Link
                                        href={route('assistant.index')}
                                        className="block w-full rounded-xl border border-slate-200 p-4 text-left transition hover:border-slate-400 hover:bg-slate-50"
                                    >
                                        <span className="block text-sm font-medium text-slate-900">
                                            Ask the study assistant
                                        </span>
                                        <span className="mt-0.5 block text-xs text-slate-500">
                                            Instant, AI. Good while you wait for a person.
                                        </span>
                                    </Link>
                                </div>

                                {!canParticipate && (
                                    <p className="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                                        Messaging unlocks once your parent or guardian approves your
                                        account. You can still ask the class and the study assistant.
                                    </p>
                                )}
                            </>
                        ) : mode === 'class' ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    classForm.post(route('classrooms.posts.store', classroomId), {
                                        onSuccess: close,
                                    });
                                }}
                                className="space-y-4"
                            >
                                <div>
                                    <h2 className="text-lg font-semibold text-slate-900">Ask the class</h2>
                                    <p className="mt-1 text-sm text-slate-600">
                                        Your teacher and everyone in this class will see it.
                                    </p>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-slate-700">
                                        What are you stuck on?
                                    </label>
                                    <input
                                        value={classForm.data.title}
                                        onChange={(e) => classForm.setData('title', e.target.value)}
                                        placeholder="Factorising when a is not 1"
                                        autoFocus
                                        className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"
                                    />
                                    {classForm.errors.title && (
                                        <p className="mt-1 text-xs text-rose-600">{classForm.errors.title}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-slate-700">
                                        Explain it in your own words
                                    </label>
                                    <textarea
                                        value={classForm.data.body}
                                        onChange={(e) => classForm.setData('body', e.target.value)}
                                        rows={4}
                                        placeholder="I get the first step but not what happens after that."
                                        className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"
                                    />
                                    {classForm.errors.body && (
                                        <p className="mt-1 text-xs text-rose-600">{classForm.errors.body}</p>
                                    )}
                                </div>

                                <div className="flex gap-3 border-t border-slate-100 pt-4">
                                    <button
                                        disabled={classForm.processing}
                                        className="flex-1 rounded-lg bg-teal-600 px-6 py-3 text-sm font-semibold text-white hover:bg-teal-500 disabled:bg-slate-300"
                                    >
                                        {classForm.processing ? 'Posting…' : 'Post to the class'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setMode('choose')}
                                        className="rounded-lg border border-slate-300 px-5 py-3 text-sm text-slate-600"
                                    >
                                        Back
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post(route('classrooms.askTeacher', classroomId), {
                                        onSuccess: close,
                                    });
                                }}
                                className="space-y-4"
                            >
                                <div>
                                    <h2 className="text-lg font-semibold text-slate-900">
                                        Ask {teacherName}
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-600">
                                        Goes straight to them. No waiting to be matched.
                                    </p>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-slate-700">
                                        What is it about?
                                    </label>
                                    <input
                                        value={form.data.topic}
                                        onChange={(e) => form.setData('topic', e.target.value)}
                                        placeholder="Question 4 on the past paper"
                                        autoFocus
                                        className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"
                                    />
                                    {form.errors.topic && (
                                        <p className="mt-1 text-xs text-rose-600">{form.errors.topic}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-slate-700">
                                        Explain what you are stuck on
                                    </label>
                                    <textarea
                                        value={form.data.description}
                                        onChange={(e) => form.setData('description', e.target.value)}
                                        rows={4}
                                        placeholder="I tried factorising but the brackets do not match."
                                        className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"
                                    />
                                    {form.errors.description && (
                                        <p className="mt-1 text-xs text-rose-600">
                                            {form.errors.description}
                                        </p>
                                    )}
                                </div>

                                <div className="flex gap-3 border-t border-slate-100 pt-4">
                                    <button
                                        disabled={form.processing}
                                        className="flex-1 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                                    >
                                        {form.processing ? 'Sending…' : 'Send to my teacher'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setMode('choose')}
                                        className="rounded-lg border border-slate-300 px-5 py-3 text-sm text-slate-600"
                                    >
                                        Back
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
