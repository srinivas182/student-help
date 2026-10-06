import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Subject {
    id: number;
    name: string;
    code: string | null;
}

export default function Subjects({
    subjects,
    breadcrumb,
    selected,
    isTutor,
}: {
    subjects: Subject[];
    breadcrumb: string[];
    selected: number[];
    isTutor: boolean;
}) {
    const { data, setData, post, processing, errors } = useForm<{ subject_ids: number[] }>({
        subject_ids: selected ?? [],
    });

    const chosen = data.subject_ids;

    const toggle = (id: number) =>
        setData(
            'subject_ids',
            chosen.includes(id) ? chosen.filter((x) => x !== id) : [...chosen, id],
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.subjects.store'));
    };

    const chosenSubjects = subjects.filter((s) => chosen.includes(s.id));

    return (
        <div className="min-h-screen bg-slate-50 pb-32 pt-10">
            <Head title="Select your subjects" />

            <form onSubmit={submit} className="mx-auto max-w-4xl px-4">
                <nav className="mb-6 flex flex-wrap gap-2">
                    {breadcrumb.map((crumb) => (
                        <span
                            key={crumb}
                            className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-500 shadow-sm ring-1 ring-slate-200"
                        >
                            {crumb}
                        </span>
                    ))}
                </nav>

                <header className="mb-6 text-center">
                    <h1 className="text-2xl font-semibold text-slate-900 sm:text-3xl">
                        {isTutor ? 'Which subjects can you help with?' : 'Select your subjects'}
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        {isTutor
                            ? 'Students will be matched to you based on these subjects.'
                            : 'Choose the subjects you find challenging. You can select more than one.'}
                    </p>
                </header>

                {errors.subject_ids && (
                    <p className="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                        {errors.subject_ids}
                    </p>
                )}

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {subjects.map((subject) => {
                        const active = chosen.includes(subject.id);

                        return (
                            <button
                                key={subject.id}
                                type="button"
                                aria-pressed={active}
                                onClick={() => toggle(subject.id)}
                                className={`flex items-center justify-between gap-3 rounded-xl border p-4 text-left transition ${
                                    active
                                        ? 'border-indigo-400 bg-indigo-50 shadow-sm'
                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm'
                                }`}
                            >
                                <span className="text-sm font-medium text-slate-800">{subject.name}</span>
                                <span
                                    className={`flex h-5 w-5 shrink-0 items-center justify-center rounded border text-xs ${
                                        active
                                            ? 'border-indigo-500 bg-indigo-500 text-white'
                                            : 'border-slate-300 bg-white text-transparent'
                                    }`}
                                >
                                    ✓
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Selection tracker, as in the client's concept designs */}
                <div className="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur">
                    <div className="mx-auto flex max-w-4xl flex-wrap items-center gap-3 px-4 py-4">
                        <span className="text-sm font-medium text-slate-700">
                            {chosen.length} subject{chosen.length === 1 ? '' : 's'} selected
                        </span>
                        <div className="flex flex-1 flex-wrap gap-2">
                            {chosenSubjects.slice(0, 4).map((s) => (
                                <span
                                    key={s.id}
                                    className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600"
                                >
                                    {s.name}
                                </span>
                            ))}
                            {chosenSubjects.length > 4 && (
                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                                    +{chosenSubjects.length - 4} more
                                </span>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={processing || chosen.length === 0}
                            className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            Continue →
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}
