import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Subject {
    id: number;
    name: string;
    tutors: number;
    typicalHours: number | null;
}

export default function Create({
    subjects,
    maxOpen,
    openCount,
}: {
    subjects: Subject[];
    maxOpen: number;
    openCount: number;
}) {
    const { data, setData, post, processing, errors } = useForm({
        subject_id: '',
        topic: '',
        description: '',
        academic_honesty: false as boolean,
    });

    const chosen = subjects.find((s) => String(s.id) === data.subject_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('requests.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Ask for help</h2>}>
            <Head title="Ask for help" />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                <p className="mb-6 text-sm text-slate-500">
                    You have {openCount} of {maxOpen} open requests. Give your tutor enough detail to help
                    first time.
                </p>

                {(errors as Record<string, string>).consent && (
                    <p className="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {(errors as Record<string, string>).consent}
                    </p>
                )}
                {(errors as Record<string, string>).limit && (
                    <p className="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {(errors as Record<string, string>).limit}
                    </p>
                )}

                <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
                    <div>
                        <InputLabel htmlFor="subject_id" value="Subject" />
                        <select
                            id="subject_id"
                            value={data.subject_id}
                            onChange={(e) => setData('subject_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Choose a subject…</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.subject_id} className="mt-2" />

                        {chosen && (
                            <p className="mt-2 text-xs text-slate-500">
                                {chosen.tutors} tutor{chosen.tutors === 1 ? '' : 's'} cover this subject
                                {chosen.typicalHours !== null
                                    ? ` · usually answered within ${chosen.typicalHours} hours`
                                    : ''}
                            </p>
                        )}
                    </div>

                    <div>
                        <InputLabel htmlFor="topic" value="What is the topic?" />
                        <input
                            id="topic"
                            value={data.topic}
                            onChange={(e) => setData('topic', e.target.value)}
                            placeholder="e.g. Factorising trinomials"
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError message={errors.topic} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="description" value="Explain where you are stuck" />
                        <textarea
                            id="description"
                            rows={6}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Tell the tutor what you have tried and exactly which step confuses you."
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div className="mt-1 flex justify-between text-xs text-slate-400">
                            <span>At least 20 characters</span>
                            <span>{data.description.length} / 2000</span>
                        </div>
                        <InputError message={errors.description} className="mt-2" />
                    </div>

                    {/* D-17: academic honesty confirmation */}
                    <label className="flex items-start gap-3 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                        <input
                            type="checkbox"
                            checked={data.academic_honesty}
                            onChange={(e) => setData('academic_honesty', e.target.checked)}
                            className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>
                            I am asking for help to understand this work. Tutors explain methods, they do not
                            complete assignments or assessments.
                        </span>
                    </label>
                    <InputError message={errors.academic_honesty} />

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:bg-slate-300"
                    >
                        Send to tutors
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
