import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface RequestDetail {
    id: number;
    topic: string;
    subject: string | null;
    status: string;
    tutor: string | null;
    description: string;
    tutorBio: string | null;
    tutorRating: string | null;
    offersSent: number;
    createdAt: string | null;
    updatedAt: string | null;
}

export default function Show({ request }: { request: RequestDetail }) {
    const act = (name: string) => router.post(route(name, request.id));

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Help request</h2>}>
            <Head title={request.topic} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <Link href={route('requests.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← All requests
                </Link>

                <article className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-indigo-600">
                                {request.subject}
                            </p>
                            <h1 className="mt-1 text-xl font-semibold text-slate-900">{request.topic}</h1>
                            <p className="mt-1 text-xs text-slate-500">Asked {request.createdAt}</p>
                        </div>
                        <StatusBadge status={request.status} />
                    </div>

                    <p className="mt-5 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                        {request.description}
                    </p>
                </article>

                {request.status === 'open' && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                        <p className="font-medium">Waiting for a tutor to accept</p>
                        <p className="mt-1 text-amber-800">
                            Sent to {request.offersSent} tutor{request.offersSent === 1 ? '' : 's'} who teach
                            this subject. If nobody accepts within 24 hours, an administrator will assign
                            someone.
                        </p>
                    </div>
                )}

                {request.status === 'escalated' && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-900">
                        <p className="font-medium">An administrator is finding you a tutor</p>
                        <p className="mt-1 text-rose-800">
                            No tutor picked this up in time, so the DX team has been alerted.
                        </p>
                    </div>
                )}

                {request.tutor && (
                    <section className="rounded-xl border border-slate-200 bg-white p-6">
                        <h2 className="text-sm font-semibold text-slate-900">Your tutor</h2>
                        <div className="mt-3 flex items-start gap-4">
                            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700">
                                {request.tutor.charAt(0)}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium text-slate-900">
                                    {request.tutor}
                                    {request.tutorRating && (
                                        <span className="ml-2 text-xs text-amber-600">
                                            ★ {request.tutorRating}
                                        </span>
                                    )}
                                </p>
                                {request.tutorBio && (
                                    <p className="mt-1 text-sm text-slate-600">{request.tutorBio}</p>
                                )}
                            </div>
                        </div>

                        <p className="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                            Messaging opens in the next release. All conversations stay inside DX Student Help
                            and are visible to moderators to keep learners safe.
                        </p>
                    </section>
                )}

                <div className="flex flex-wrap gap-3">
                    {request.status === 'resolved' && (
                        <>
                            <button
                                onClick={() => act('requests.confirm')}
                                className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                            >
                                This helped, close it
                            </button>
                            <button
                                onClick={() => act('requests.reopen')}
                                className="rounded-lg border border-slate-300 bg-white px-5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                I still need help
                            </button>
                        </>
                    )}

                    {['open', 'escalated', 'assigned'].includes(request.status) && (
                        <button
                            onClick={() => act('requests.cancel')}
                            className="rounded-lg border border-slate-300 bg-white px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        >
                            Cancel request
                        </button>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
