import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Reviews({
    due,
    intervals,
}: {
    due: {
        topicId: number;
        title: string | null;
        subject: string | null;
        level: string | null;
        lastSeen: string | null;
        stage: number;
    }[];
    intervals: number[];
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Review</h2>}>
            <Head title="Review" />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <p className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
                    Topics come back after {intervals.join(', ')} days. A few minutes on something you learned
                    last week does more than an hour of rereading.
                </p>

                {due.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        Nothing due today. Come back when a topic is ready for review.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {due.map((item) => (
                            <li key={item.topicId} className="rounded-xl border border-slate-200 bg-white p-5">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-slate-900">{item.title}</p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {item.subject} · last tested {item.lastSeen}
                                            {item.level && ` · ${item.level} level`}
                                        </p>
                                    </div>

                                    <Link
                                        href={route('assessment.start', [
                                            item.topicId,
                                            item.level ?? 'basic',
                                        ])}
                                        data={{ review: 1 }}
                                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                    >
                                        Review now
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
