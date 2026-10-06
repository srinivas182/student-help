import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Row {
    id: number;
    name: string | null;
    email: string | null;
    qualification: string | null;
    subjects: string[];
    subjectCount: number;
    documents: number;
    waiting: string | null;
    status: string;
}

export default function Index({
    profiles,
    filters,
    counts,
}: {
    profiles: { data: Row[] };
    filters: { status: string };
    counts: { pending: number; approved: number; rejected: number };
}) {
    const tabs = [
        { value: 'pending', label: `Pending (${counts.pending})` },
        { value: 'approved', label: `Verified (${counts.approved})` },
        { value: 'rejected', label: `Rejected (${counts.rejected})` },
    ];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Tutor verification</h2>}
        >
            <Head title="Tutor verification" />

            <div className="mx-auto max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex gap-2">
                        {tabs.map((tab) => (
                            <button
                                key={tab.value}
                                onClick={() =>
                                    router.get(route('admin.verification.index'), { status: tab.value }, {
                                        preserveState: true,
                                    })
                                }
                                className={`rounded-full px-4 py-1.5 text-sm transition ${
                                    filters.status === tab.value
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <Link
                        href={route('admin.coverage')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    >
                        Subject coverage →
                    </Link>
                </div>

                {profiles.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing here. New tutor applications appear in this queue.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {profiles.data.map((profile) => (
                            <li key={profile.id}>
                                <Link
                                    href={route('admin.verification.show', profile.id)}
                                    className="flex flex-wrap items-center gap-4 px-5 py-4 transition hover:bg-slate-50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">{profile.name}</p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {profile.qualification ?? 'No qualification given'} ·{' '}
                                            {profile.subjectCount} subject
                                            {profile.subjectCount === 1 ? '' : 's'} · waiting {profile.waiting}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {profile.subjects.map((subject) => (
                                                <span
                                                    key={subject}
                                                    className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600"
                                                >
                                                    {subject}
                                                </span>
                                            ))}
                                        </div>
                                    </div>

                                    <span
                                        className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium ${
                                            profile.documents === 3
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-amber-100 text-amber-800'
                                        }`}
                                    >
                                        {profile.documents}/3 documents
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
