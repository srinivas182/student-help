import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface Row {
    id: number;
    name: string;
    context: string;
    tutors: number;
    students: number;
    requests: number;
    isLive: boolean;
    shortfall: number;
}

export default function Coverage({
    subjects,
    minimum,
    summary,
}: {
    subjects: Row[];
    minimum: number;
    summary: { live: number; belowThreshold: number; noTutors: number; verifiedTutors: number };
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Subject coverage</h2>}>
            <Head title="Subject coverage" />

            <div className="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
                <p className="text-sm text-slate-600">
                    A subject goes live for students once it has {minimum} verified tutors. Use this to see
                    exactly where to recruit before launch.
                </p>

                <div className="grid gap-4 sm:grid-cols-4">
                    {[
                        ['Live subjects', summary.live, 'text-emerald-700'],
                        ['Below threshold', summary.belowThreshold, 'text-amber-700'],
                        ['No tutors at all', summary.noTutors, 'text-rose-700'],
                        ['Verified tutors', summary.verifiedTutors, 'text-slate-900'],
                    ].map(([label, value, tone]) => (
                        <div key={label as string} className="rounded-xl border border-slate-200 bg-white p-5">
                            <p className="text-xs uppercase tracking-wide text-slate-500">{label as string}</p>
                            <p className={`mt-1 text-2xl font-semibold ${tone as string}`}>{value as number}</p>
                        </div>
                    ))}
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-5 py-3">Subject</th>
                                <th className="px-5 py-3 text-center">Tutors</th>
                                <th className="px-5 py-3 text-center">Students</th>
                                <th className="px-5 py-3 text-center">Requests</th>
                                <th className="px-5 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {subjects.map((subject) => (
                                <tr key={subject.id} className={subject.tutors === 0 ? 'bg-rose-50/40' : ''}>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-slate-900">{subject.name}</p>
                                        <p className="text-xs text-slate-400">{subject.context}</p>
                                    </td>
                                    <td className="px-5 py-3 text-center font-medium text-slate-900">
                                        {subject.tutors}
                                    </td>
                                    <td className="px-5 py-3 text-center text-slate-600">{subject.students}</td>
                                    <td className="px-5 py-3 text-center text-slate-600">{subject.requests}</td>
                                    <td className="px-5 py-3 text-center">
                                        {subject.isLive ? (
                                            <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-800">
                                                Live
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
                                                Need {subject.shortfall} more
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Link
                    href={route('admin.verification.index')}
                    className="inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500"
                >
                    ← Verification queue
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
