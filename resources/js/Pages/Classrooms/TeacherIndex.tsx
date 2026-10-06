import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Item {
    id: number;
    name: string;
    displayName: string;
    type: string;
    school: string | null;
    pendingSchool: string | null;
    subject: string | null;
    students: number;
    capacity: number | null;
    joinCode: string;
    joinCodeActive: boolean;
    schoolLinkStatus: string | null;
    schoolLinkNotes: string | null;
    isArchived: boolean;
}

export default function TeacherIndex({
    classrooms,
    institutions,
    subjects,
    canCreate,
}: {
    classrooms: Item[];
    institutions: { id: number; name: string; type: string; city: string | null }[];
    subjects: { id: number; name: string }[];
    canCreate: boolean;
}) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        type: 'personal',
        institution_id: '',
        curriculum_item_id: '',
        capacity: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('classrooms.store'), {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">My classes</h2>}>
            <Head title="My classes" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-600">
                        Host a group of students, share material with them and set work.
                    </p>
                    {canCreate && (
                        <button
                            onClick={() => setOpen((v) => !v)}
                            className="rounded-lg bg-teal-600 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-500"
                        >
                            Create a class
                        </button>
                    )}
                </div>

                {open && (
                    <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium text-slate-700">Class name</label>
                                <input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Grade 11 Mathematics"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                                {errors.name && <p className="mt-1 text-xs text-rose-600">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="text-sm font-medium text-slate-700">Subject (optional)</label>
                                <select
                                    value={data.curriculum_item_id}
                                    onChange={(e) => setData('curriculum_item_id', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                >
                                    <option value="">No specific subject</option>
                                    {subjects.map((subject) => (
                                        <option key={subject.id} value={subject.id}>
                                            {subject.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-slate-700">What is this class for?</label>
                            <textarea
                                rows={2}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                            />
                        </div>

                        {/* Both kinds, with the trade-off stated plainly */}
                        <div>
                            <label className="text-sm font-medium text-slate-700">Type of class</label>
                            <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => setData('type', 'personal')}
                                    className={`rounded-xl border p-4 text-left transition ${
                                        data.type === 'personal'
                                            ? 'border-teal-500 bg-teal-50'
                                            : 'border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    <p className="font-medium text-slate-900">My own group</p>
                                    <p className="mt-1 text-xs text-slate-600">
                                        Any students you invite. Works immediately.
                                    </p>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setData('type', 'school')}
                                    className={`rounded-xl border p-4 text-left transition ${
                                        data.type === 'school'
                                            ? 'border-teal-500 bg-teal-50'
                                            : 'border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    <p className="font-medium text-slate-900">Linked to a school</p>
                                    <p className="mt-1 text-xs text-slate-600">
                                        Carries the institution's name once DX approves the link. The class
                                        works while you wait.
                                    </p>
                                </button>
                            </div>
                        </div>

                        {data.type === 'school' && (
                            <div>
                                <label className="text-sm font-medium text-slate-700">Institution</label>
                                <select
                                    value={data.institution_id}
                                    onChange={(e) => setData('institution_id', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                >
                                    <option value="">Choose…</option>
                                    {institutions.map((institution) => (
                                        <option key={institution.id} value={institution.id}>
                                            {institution.name}
                                            {institution.city ? ` — ${institution.city}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.institution_id && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.institution_id}</p>
                                )}
                            </div>
                        )}

                        <div>
                            <label className="text-sm font-medium text-slate-700">Maximum students (optional)</label>
                            <input
                                type="number"
                                value={data.capacity}
                                onChange={(e) => setData('capacity', e.target.value)}
                                placeholder="e.g. 40"
                                className="mt-1 block w-full max-w-xs rounded-lg border-slate-300 text-sm"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-500"
                        >
                            Create class
                        </button>
                    </form>
                )}

                {classrooms.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        You have not created a class yet.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {classrooms.map((item) => (
                            <li key={item.id} className="rounded-xl border border-slate-200 bg-white p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={route('classrooms.show', item.id)}
                                            className="font-medium text-slate-900 hover:text-teal-700"
                                        >
                                            {item.displayName}
                                        </Link>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {item.subject ? `${item.subject} · ` : ''}
                                            {item.students} student{item.students === 1 ? '' : 's'}
                                            {item.capacity ? ` of ${item.capacity}` : ''}
                                        </p>

                                        {item.pendingSchool && (
                                            <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                                Waiting for DX to approve the link to {item.pendingSchool}. The
                                                class works now; the school name appears after approval.
                                            </p>
                                        )}
                                        {item.schoolLinkStatus === 'rejected' && item.schoolLinkNotes && (
                                            <p className="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                                School link declined: {item.schoolLinkNotes}
                                            </p>
                                        )}
                                    </div>

                                    <div className="text-right">
                                        <p className="font-mono text-lg font-semibold tracking-widest text-slate-900">
                                            {item.joinCode}
                                        </p>
                                        <button
                                            onClick={() =>
                                                router.post(route('classrooms.code', item.id), {}, {
                                                    preserveScroll: true,
                                                })
                                            }
                                            className="mt-1 text-xs text-slate-500 hover:text-slate-700"
                                        >
                                            New code
                                        </button>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
