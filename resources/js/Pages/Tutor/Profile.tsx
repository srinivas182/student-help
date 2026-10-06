import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Doc {
    id: number;
    type: string;
    label: string;
    name: string;
    uploadedAt: string | null;
}

interface Props {
    profile: {
        bio: string | null;
        highestQualification: string | null;
        institutionName: string | null;
        languages: string[];
        availability: Record<string, string>;
        status: string;
        notes: string | null;
        isAvailable: boolean;
    };
    subjects: number[];
    subjectNames: string[];
    documents: Doc[];
    requiredDocuments: Record<string, string>;
    missingDocuments: Record<string, string>;
    canSubmit: boolean;
}

const STATUS_BANNER: Record<string, { tone: string; title: string; body: string }> = {
    pending: {
        tone: 'border-amber-200 bg-amber-50 text-amber-900',
        title: 'Awaiting verification',
        body: 'A DX administrator will review your documents, usually within 48 hours. You will start receiving student requests once approved.',
    },
    approved: {
        tone: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        title: 'You are a verified DX tutor',
        body: 'Students in your subjects can now be matched to you.',
    },
    rejected: {
        tone: 'border-rose-200 bg-rose-50 text-rose-900',
        title: 'We need more information',
        body: 'Please review the note below, update your documents and resubmit.',
    },
};

export default function Profile({
    profile,
    subjects,
    subjectNames,
    documents,
    requiredDocuments,
    missingDocuments,
    canSubmit,
}: Props) {
    const banner = STATUS_BANNER[profile.status];
    const [uploading, setUploading] = useState<string | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        bio: profile.bio ?? '',
        highest_qualification: profile.highestQualification ?? '',
        institution_name: profile.institutionName ?? '',
        languages: profile.languages ?? [],
        availability: profile.availability ?? {},
        subject_ids: subjects,
    });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('tutor.profile.update'), { preserveScroll: true });
    };

    const upload = (type: string, file: File) => {
        setUploading(type);
        router.post(
            route('tutor.documents.upload'),
            { document_type: type, file },
            { forceFormData: true, preserveScroll: true, onFinish: () => setUploading(null) },
        );
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Tutor profile</h2>}>
            <Head title="Tutor profile" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
                {banner && (
                    <div className={`rounded-xl border p-5 ${banner.tone}`} role="status">
                        <p className="font-semibold">{banner.title}</p>
                        <p className="mt-1 text-sm">{banner.body}</p>
                        {profile.notes && (
                            <p className="mt-3 rounded-lg bg-white/70 p-3 text-sm">{profile.notes}</p>
                        )}
                    </div>
                )}

                <form onSubmit={save} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
                    <h3 className="font-semibold text-slate-900">About you</h3>

                    <div>
                        <InputLabel htmlFor="bio" value="How do you help students?" />
                        <textarea
                            id="bio"
                            rows={4}
                            value={data.bio}
                            onChange={(e) => setData('bio', e.target.value)}
                            placeholder="Students see this on your profile. Mention your teaching style and experience."
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError message={errors.bio} className="mt-2" />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="qualification" value="Highest qualification" />
                            <input
                                id="qualification"
                                value={data.highest_qualification}
                                onChange={(e) => setData('highest_qualification', e.target.value)}
                                placeholder="e.g. BSc Mathematics, UKZN"
                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError message={errors.highest_qualification} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="institution" value="Institution (optional)" />
                            <input
                                id="institution"
                                value={data.institution_name}
                                onChange={(e) => setData('institution_name', e.target.value)}
                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Subjects you teach" />
                        <div className="mt-2 flex flex-wrap gap-2">
                            {subjectNames.length === 0 ? (
                                <p className="text-sm text-slate-500">
                                    No subjects yet. Complete onboarding to choose the subjects you can help with.
                                </p>
                            ) : (
                                subjectNames.map((name) => (
                                    <span
                                        key={name}
                                        className="rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-700"
                                    >
                                        {name}
                                    </span>
                                ))
                            )}
                        </div>
                        <InputError message={errors.subject_ids} className="mt-2" />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                    >
                        Save profile
                    </button>
                </form>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h3 className="font-semibold text-slate-900">Verification documents</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        Because you will be helping learners under 18, DX checks every tutor. Documents are
                        stored privately and seen only by DX administrators.
                    </p>

                    <ul className="mt-4 space-y-3">
                        {Object.entries(requiredDocuments).map(([type, label]) => {
                            const existing = documents.find((d) => d.type === type);

                            return (
                                <li
                                    key={type}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-4"
                                >
                                    <div>
                                        <p className="text-sm font-medium text-slate-900">{label}</p>
                                        {existing ? (
                                            <p className="mt-0.5 text-xs text-emerald-700">
                                                {existing.name} · uploaded {existing.uploadedAt}
                                            </p>
                                        ) : (
                                            <p className="mt-0.5 text-xs text-slate-400">
                                                PDF, JPG or PNG, up to 10 MB
                                            </p>
                                        )}
                                    </div>

                                    <label className="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        {uploading === type ? 'Uploading…' : existing ? 'Replace' : 'Upload'}
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            className="hidden"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) upload(type, file);
                                            }}
                                        />
                                    </label>
                                </li>
                            );
                        })}
                    </ul>

                    {Object.keys(missingDocuments).length > 0 && (
                        <p className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                            Still needed: {Object.values(missingDocuments).join(', ')}
                        </p>
                    )}

                    {profile.status !== 'approved' && (
                        <button
                            onClick={() => router.post(route('tutor.submit'))}
                            disabled={!canSubmit}
                            className="mt-4 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            Submit for review
                        </button>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
