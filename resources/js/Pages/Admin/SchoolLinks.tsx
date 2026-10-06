import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    name: string;
    teacher: string | null;
    teacherEmail: string | null;
    institution: string | null;
    institutionType: string | null;
    city: string | null;
    subject: string | null;
    students: number;
    notes: string | null;
    requestedAt: string | null;
}

export default function SchoolLinks({
    classrooms,
    filters,
    counts,
}: {
    classrooms: { data: Row[] };
    filters: { status: string };
    counts: { pending: number; approved: number; rejected: number };
}) {
    const [rejecting, setRejecting] = useState<number | null>(null);
    const reject = useForm({ reason: '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">School links</h2>}>
            <Head title="School links" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <p className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Approving a link lets a teacher use a real institution's name in front of learners. Check
                    that the teacher genuinely teaches there before approving.
                </p>

                <div className="flex gap-2">
                    {[
                        ['pending', `Pending (${counts.pending})`],
                        ['approved', `Approved (${counts.approved})`],
                        ['rejected', `Rejected (${counts.rejected})`],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            onClick={() => router.get(route('admin.schoolLinks.index'), { status: value })}
                            className={`rounded-full px-4 py-1.5 text-sm transition ${
                                filters.status === value
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {classrooms.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing here.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {classrooms.data.map((item) => (
                            <li key={item.id} className="rounded-xl border border-slate-200 bg-white p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">
                                            {item.institution} · {item.name}
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {item.teacher} ({item.teacherEmail}) ·{' '}
                                            {item.subject ?? 'no subject'} · {item.students} student
                                            {item.students === 1 ? '' : 's'} · requested {item.requestedAt}
                                        </p>
                                        <p className="mt-1 text-xs capitalize text-slate-400">
                                            {item.institutionType}
                                            {item.city ? ` · ${item.city}` : ''}
                                        </p>
                                        {item.notes && (
                                            <p className="mt-2 rounded bg-slate-50 p-2 text-xs text-slate-600">
                                                {item.notes}
                                            </p>
                                        )}
                                    </div>

                                    {filters.status === 'pending' && (
                                        <div className="flex shrink-0 gap-2">
                                            <button
                                                onClick={() =>
                                                    router.post(route('admin.schoolLinks.approve', item.id), {}, {
                                                        preserveScroll: true,
                                                    })
                                                }
                                                className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                                            >
                                                Approve link
                                            </button>
                                            <button
                                                onClick={() => setRejecting(rejecting === item.id ? null : item.id)}
                                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-600"
                                            >
                                                Reject
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {rejecting === item.id && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            reject.post(route('admin.schoolLinks.reject', item.id), {
                                                preserveScroll: true,
                                                onSuccess: () => setRejecting(null),
                                            });
                                        }}
                                        className="mt-3 flex gap-2"
                                    >
                                        <input
                                            value={reject.data.reason}
                                            onChange={(e) => reject.setData('reason', e.target.value)}
                                            placeholder="Why — the teacher sees this, and the class continues as their own group"
                                            className="flex-1 rounded-lg border-slate-300 text-sm"
                                        />
                                        <button className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white">
                                            Confirm
                                        </button>
                                    </form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
