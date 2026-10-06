import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import { Head, router } from '@inertiajs/react';

interface Offer {
    id: number;
    topic: string;
    description: string;
    subject: string | null;
    student: string | null;
    waiting: string | null;
    isEscalated: boolean;
}

interface Active {
    id: number;
    topic: string;
    subject: string | null;
    student: string | null;
    status: string;
    updatedAt: string | null;
}

export default function Queue({
    available,
    active,
    isAvailable,
    isVerified,
    stats,
}: {
    available: Offer[];
    active: Active[];
    isAvailable: boolean;
    isVerified: boolean;
    stats: { resolved: number; rating: string | null };
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Tutor queue</h2>}>
            <Head title="Tutor queue" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {!isVerified && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5" role="status">
                        <p className="font-semibold text-amber-900">Your account is awaiting verification</p>
                        <p className="mt-1 text-sm text-amber-800">
                            A DX administrator is reviewing your documents. You will start receiving student
                            requests as soon as you are approved.
                        </p>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5">
                    <div className="flex gap-8">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-500">Students helped</p>
                            <p className="text-2xl font-semibold text-slate-900">{stats.resolved}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-500">Rating</p>
                            <p className="text-2xl font-semibold text-slate-900">
                                {stats.rating ? `★ ${stats.rating}` : '—'}
                            </p>
                        </div>
                    </div>

                    <button
                        onClick={() => router.post(route('tutor.availability'))}
                        className={`rounded-full px-5 py-2 text-sm font-medium transition ${
                            isAvailable
                                ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                : 'bg-slate-200 text-slate-600 hover:bg-slate-300'
                        }`}
                    >
                        {isAvailable ? 'Available for requests' : 'Not available'}
                    </button>
                </div>

                <section>
                    <h3 className="mb-3 font-semibold text-slate-900">
                        New requests for you ({available.length})
                    </h3>

                    {available.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                            No new requests right now. We will notify you when a student needs help in your
                            subjects.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {available.map((offer) => (
                                <li key={offer.id} className="rounded-xl border border-slate-200 bg-white p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium uppercase tracking-wide text-indigo-600">
                                                {offer.subject}
                                            </p>
                                            <p className="mt-1 font-medium text-slate-900">{offer.topic}</p>
                                            <p className="mt-1 text-sm text-slate-600">{offer.description}</p>
                                            <p className="mt-2 text-xs text-slate-400">
                                                {offer.student} · waiting {offer.waiting}
                                            </p>
                                        </div>
                                        {offer.isEscalated && (
                                            <span className="rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-800">
                                                Urgent
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-4 flex gap-3">
                                        <button
                                            onClick={() => router.post(route('tutor.requests.accept', offer.id))}
                                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                        >
                                            Accept
                                        </button>
                                        <button
                                            onClick={() => router.post(route('tutor.requests.decline', offer.id))}
                                            className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                                        >
                                            Decline
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section>
                    <h3 className="mb-3 font-semibold text-slate-900">Active ({active.length})</h3>

                    {active.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                            Nothing in progress.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                            {active.map((item) => (
                                <li key={item.id} className="flex flex-wrap items-center gap-4 px-5 py-4">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium text-slate-900">{item.topic}</p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {item.subject} · {item.student} · {item.updatedAt}
                                        </p>
                                    </div>
                                    <StatusBadge status={item.status} />
                                    {item.status === 'assigned' && (
                                        <button
                                            onClick={() => router.post(route('tutor.requests.resolve', item.id))}
                                            className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500"
                                        >
                                            Mark resolved
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
