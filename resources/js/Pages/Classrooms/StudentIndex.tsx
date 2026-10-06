import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Item {
    id: number;
    displayName: string;
    school: string | null;
    subject: string | null;
    teacher: string | null;
    students: number;
}

export default function StudentIndex({ classrooms }: { classrooms: Item[] }) {
    const { data, setData, post, processing, errors } = useForm({ join_code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('classrooms.join'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">My classes</h2>}>
            <Head title="My classes" />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-6">
                    <label className="text-sm font-medium text-slate-900">Join a class</label>
                    <p className="mt-1 text-xs text-slate-500">
                        Your teacher will give you a six-character code.
                    </p>
                    <div className="mt-3 flex gap-3">
                        <input
                            value={data.join_code}
                            onChange={(e) => setData('join_code', e.target.value.toUpperCase())}
                            placeholder="ABC123"
                            maxLength={12}
                            className="w-40 rounded-lg border-slate-300 text-center font-mono text-lg tracking-widest uppercase"
                        />
                        <button
                            type="submit"
                            disabled={processing || data.join_code.length < 4}
                            className="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                        >
                            Join
                        </button>
                    </div>
                    {errors.join_code && <p className="mt-2 text-sm text-rose-600">{errors.join_code}</p>}
                </form>

                {classrooms.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-sm text-slate-500">
                        You are not in a class yet. Ask your teacher for a join code.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {classrooms.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={route('classrooms.show', item.id)}
                                    className="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm"
                                >
                                    <p className="font-medium text-slate-900">{item.displayName}</p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {item.teacher}
                                        {item.subject ? ` · ${item.subject}` : ''} · {item.students} student
                                        {item.students === 1 ? '' : 's'}
                                    </p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
