import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface RequestRow {
    id: number;
    topic: string;
    subject: string | null;
    status: string;
    tutor: string | null;
    createdAt: string | null;
    updatedAt: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const FILTERS = [
    { value: '', label: 'All' },
    { value: 'open', label: 'Waiting' },
    { value: 'assigned', label: 'In progress' },
    { value: 'resolved', label: 'To confirm' },
    { value: 'closed', label: 'Closed' },
];

export default function Index({
    requests,
    filters,
    canRaise,
}: {
    requests: Paginated<RequestRow>;
    filters: { status: string };
    canRaise: boolean;
}) {
    const filter = (status: string) =>
        router.get(route('requests.index'), status ? { status } : {}, {
            preserveState: true,
            replace: true,
        });

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">My help requests</h2>}
        >
            <Head title="My help requests" />

            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                        {FILTERS.map((item) => (
                            <button
                                key={item.label}
                                onClick={() => filter(item.value)}
                                className={`rounded-full px-4 py-1.5 text-sm transition ${
                                    filters.status === item.value
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                                }`}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>

                    {canRaise && (
                        <Link
                            href={route('requests.create')}
                            className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                        >
                            Ask for help
                        </Link>
                    )}
                </div>

                {requests.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <p className="text-sm text-slate-600">
                            Nothing here yet. When a topic has you stuck, ask a tutor and track it from this page.
                        </p>
                        {canRaise && (
                            <Link
                                href={route('requests.create')}
                                className="mt-4 inline-block rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                Ask your first question
                            </Link>
                        )}
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {requests.data.map((request) => (
                            <li key={request.id}>
                                <Link
                                    href={route('requests.show', request.id)}
                                    className="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium text-slate-900">{request.topic}</p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {request.subject}
                                            {request.tutor ? ` · ${request.tutor}` : ''}
                                            {request.updatedAt ? ` · ${request.updatedAt}` : ''}
                                        </p>
                                    </div>
                                    <StatusBadge status={request.status} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
