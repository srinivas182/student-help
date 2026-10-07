import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Doc {
    id: number;
    type: string;
    label: string;
    name: string;
    sizeKb: number;
    uploadedAt: string | null;
}

export default function Show({
    profile,
    documents,
    missing,
}: {
    profile: {
        id: number;
        name: string | null;
        email: string | null;
        registered: string | null;
        bio: string | null;
        qualification: string | null;
        institution: string | null;
        languages: string[];
        status: string;
        notes: string | null;
        reviewedBy: string | null;
        reviewedAt: string | null;
        subjects: string[];
    };
    documents: Doc[];
    missing: Record<string, string>;
}) {
    const [rejecting, setRejecting] = useState(false);
    const approve = useForm({});
    const reject = useForm({ reason: '' });

    const complete = Object.keys(missing).length === 0;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Review tutor</h2>}
        >
            <Head title={`Review ${profile.name}`} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <Link
                    href={route('admin.verification.index')}
                    className="text-sm text-slate-500 hover:text-slate-800"
                >
                    ← Verification queue
                </Link>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h1 className="text-lg font-semibold text-slate-900">{profile.name}</h1>
                    <p className="text-sm text-slate-500">
                        {profile.email} · registered {profile.registered}
                    </p>

                    <dl className="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-400">Qualification</dt>
                            <dd className="mt-1 text-slate-800">{profile.qualification ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-400">Institution</dt>
                            <dd className="mt-1 text-slate-800">{profile.institution ?? '—'}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-xs uppercase tracking-wide text-slate-400">Bio</dt>
                            <dd className="mt-1 text-slate-700">{profile.bio ?? '—'}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-xs uppercase tracking-wide text-slate-400">Subjects</dt>
                            <dd className="mt-2 flex flex-wrap gap-1.5">
                                {profile.subjects.map((subject) => (
                                    <span
                                        key={subject}
                                        className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700"
                                    >
                                        {subject}
                                    </span>
                                ))}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">Documents</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Opening a document is recorded in the audit log.
                    </p>

                    <ul className="mt-4 space-y-2">
                        {documents.map((doc) => (
                            <li
                                key={doc.id}
                                className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-4"
                            >
                                <div>
                                    <p className="text-sm font-medium text-slate-900">{doc.label}</p>
                                    <p className="text-xs text-slate-500">
                                        {doc.name} · {doc.sizeKb} KB · {doc.uploadedAt}
                                    </p>
                                </div>
                                <a
                                    href={route('admin.documents.show', doc.id)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    View
                                </a>
                            </li>
                        ))}
                    </ul>

                    {!complete && (
                        <p className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                            Missing: {Object.values(missing).join(', ')}. This tutor cannot be approved yet.
                        </p>
                    )}
                </section>

                {profile.status === 'pending' ? (
                    <section className="rounded-xl border border-slate-200 bg-white p-6">
                        <h2 className="font-semibold text-slate-900">Decision</h2>

                        <div className="mt-4 flex flex-wrap gap-3">
                            <button
                                onClick={() => approve.post(route('admin.verification.approve', profile.id))}
                                disabled={!complete || approve.processing}
                                className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                            >
                                Approve tutor
                            </button>
                            <button
                                onClick={() => setRejecting((value) => !value)}
                                className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Reject
                            </button>
                        </div>

                        {rejecting && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    reject.post(route('admin.verification.reject', profile.id));
                                }}
                                className="mt-4 space-y-3 rounded-lg bg-slate-50 p-4"
                            >
                                <textarea
                                    rows={3}
                                    value={reject.data.reason}
                                    onChange={(e) => reject.setData('reason', e.target.value)}
                                    placeholder="Explain what the tutor needs to fix. They receive this by email."
                                    className="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                {reject.errors.reason && (
                                    <p className="text-sm text-rose-600">{reject.errors.reason}</p>
                                )}
                                <button
                                    type="submit"
                                    disabled={reject.processing}
                                    className="rounded-lg bg-rose-600 px-5 py-2 text-sm font-semibold text-white hover:bg-rose-500"
                                >
                                    Send rejection
                                </button>
                            </form>
                        )}
                    </section>
                ) : (
                    <section className="rounded-xl border border-slate-200 bg-white p-6 text-sm">
                        <p className="font-medium capitalize text-slate-900">{profile.status}</p>
                        {profile.reviewedBy && (
                            <p className="mt-1 text-slate-500">
                                by {profile.reviewedBy} · {profile.reviewedAt}
                            </p>
                        )}
                        {profile.notes && <p className="mt-3 text-slate-600">{profile.notes}</p>}
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
