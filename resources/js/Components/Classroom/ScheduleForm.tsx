import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** Scheduling a session: when, how long, online with a link or in a room. */
export default function ScheduleForm({ classroomId }: { classroomId: number }) {
    const [open, setOpen] = useState(false);

    const form = useForm({
        title: '',
        description: '',
        mode: 'online' as 'online' | 'in_person',
        meeting_url: '',
        location: '',
        starts_at: '',
        duration_minutes: 60,
        repeats: '' as '' | 'weekly',
        repeats_until: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // An empty string is not a valid repeat value; send null instead
        form.transform((data) => ({ ...data, repeats: data.repeats || null }));

        form.post(route('classrooms.sessions.store', classroomId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <button
                onClick={() => setOpen(true)}
                className="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-500"
            >
                Schedule a session
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <div>
                <label className="text-sm font-medium text-slate-700">What is the session?</label>
                <input
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="Revision: factorising trinomials"
                    className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"
                />
                {form.errors.title && <p className="mt-1 text-xs text-rose-600">{form.errors.title}</p>}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label className="text-sm font-medium text-slate-700">When</label>
                    <input
                        type="datetime-local"
                        value={form.data.starts_at}
                        onChange={(e) => form.setData('starts_at', e.target.value)}
                        className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"
                    />
                    {form.errors.starts_at && (
                        <p className="mt-1 text-xs text-rose-600">{form.errors.starts_at}</p>
                    )}
                </div>

                <div>
                    <label className="text-sm font-medium text-slate-700">How long (minutes)</label>
                    <input
                        type="number"
                        min={15}
                        max={300}
                        step={15}
                        value={form.data.duration_minutes}
                        onChange={(e) => form.setData('duration_minutes', Number(e.target.value))}
                        className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"
                    />
                </div>
            </div>

            <div>
                <p className="text-sm font-medium text-slate-700">Where</p>
                <div className="mt-2 flex gap-2">
                    {(
                        [
                            ['online', 'Online'],
                            ['in_person', 'In person'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => form.setData('mode', value)}
                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                form.data.mode === value
                                    ? 'bg-teal-600 text-white'
                                    : 'bg-slate-100 text-slate-700'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {form.data.mode === 'online' ? (
                    <>
                        <input
                            value={form.data.meeting_url}
                            onChange={(e) => form.setData('meeting_url', e.target.value)}
                            placeholder="https://meet.google.com/… or your Zoom link"
                            className="mt-3 block w-full rounded-lg border-slate-300 text-sm"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            Learners see this link 15 minutes before the session starts, not before.
                        </p>
                        {form.errors.meeting_url && (
                            <p className="mt-1 text-xs text-rose-600">{form.errors.meeting_url}</p>
                        )}
                    </>
                ) : (
                    <>
                        <input
                            value={form.data.location}
                            onChange={(e) => form.setData('location', e.target.value)}
                            placeholder="Room 12, or the school library"
                            className="mt-3 block w-full rounded-lg border-slate-300 text-sm"
                        />
                        {form.errors.location && (
                            <p className="mt-1 text-xs text-rose-600">{form.errors.location}</p>
                        )}
                    </>
                )}
            </div>

            <div>
                <label className="flex items-center gap-2.5 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={form.data.repeats === 'weekly'}
                        onChange={(e) => form.setData('repeats', e.target.checked ? 'weekly' : '')}
                        className="rounded border-slate-300 text-teal-600"
                    />
                    Repeat weekly at this time
                </label>

                {form.data.repeats === 'weekly' && (
                    <div className="mt-2">
                        <label className="text-xs text-slate-500">Until</label>
                        <input
                            type="date"
                            value={form.data.repeats_until}
                            onChange={(e) => form.setData('repeats_until', e.target.value)}
                            className="mt-1 block w-full rounded-lg border-slate-300 text-sm sm:w-56"
                        />
                        {form.errors.repeats_until && (
                            <p className="mt-1 text-xs text-rose-600">{form.errors.repeats_until}</p>
                        )}
                    </div>
                )}
            </div>

            <textarea
                value={form.data.description}
                onChange={(e) => form.setData('description', e.target.value)}
                rows={2}
                placeholder="What should learners bring or prepare? (optional)"
                className="block w-full rounded-lg border-slate-300 text-sm"
            />

            <div className="flex gap-3">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded-lg bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-500 disabled:bg-slate-300"
                >
                    {form.processing ? 'Scheduling…' : 'Schedule it'}
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="rounded-lg border border-slate-300 px-5 py-2 text-sm text-slate-600"
                >
                    Cancel
                </button>
            </div>
        </form>
    );
}
