import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface QueueItem {
    id: number;
    topic: string | null;
    subject: string | null;
    language: string | null;
    code: string | null;
    status: string;
    segments: number;
    questions: number;
    generatedAt: string | null;
    notes: string | null;
}

export default function Index({
    queue,
    published,
    scopes,
}: {
    queue: QueueItem[];
    published: { id: number; topic: string | null; language: string | null; reviewedAt: string | null }[];
    scopes: { subject: string; language: string }[];
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Lesson review</h2>}>
            <Head title="Lesson review" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-6">
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <p className="text-sm text-slate-700">
                        Lessons are written by AI and checked by you before any student sees them. Read every
                        segment, fix anything wrong, and put your name to it.
                    </p>
                    {scopes.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            <span className="text-xs text-slate-500">You review:</span>
                            {scopes.map((scope, index) => (
                                <span
                                    key={index}
                                    className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-600"
                                >
                                    {scope.subject} · {scope.language}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                <section>
                    <h3 className="mb-3 font-semibold text-slate-900">Waiting for you ({queue.length})</h3>

                    {queue.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center text-sm text-slate-500">
                            Nothing waiting. New lessons appear here once they are generated.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {queue.map((item) => (
                                <li key={item.id}>
                                    <Link
                                        href={route('review.show', item.id)}
                                        className="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium text-slate-900">{item.topic}</p>
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    {item.subject} · {item.language} · {item.segments} segments ·{' '}
                                                    {item.questions} questions · {item.generatedAt}
                                                </p>
                                                {item.status === 'rejected' && item.notes && (
                                                    <p className="mt-2 rounded bg-rose-50 p-2 text-xs text-rose-800">
                                                        Previously sent back: {item.notes}
                                                    </p>
                                                )}
                                            </div>

                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-medium ${
                                                    item.status === 'rejected'
                                                        ? 'bg-rose-100 text-rose-800'
                                                        : 'bg-amber-100 text-amber-800'
                                                }`}
                                            >
                                                {item.status === 'rejected' ? 'regenerated' : 'needs review'}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {published.length > 0 && (
                    <section>
                        <h3 className="mb-3 font-semibold text-slate-900">Approved by you</h3>
                        <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                            {published.map((item) => (
                                <li key={item.id} className="flex items-center justify-between px-5 py-3 text-sm">
                                    <span className="text-slate-700">
                                        {item.topic}{' '}
                                        <span className="text-xs text-slate-400">{item.language}</span>
                                    </span>
                                    <span className="text-xs text-slate-400">{item.reviewedAt}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
