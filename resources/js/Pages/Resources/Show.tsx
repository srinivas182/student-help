import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({
    resource,
}: {
    resource: {
        id: number;
        title: string;
        typeLabel: string;
        description: string | null;
        uploader: string | null;
        subjects: string[];
        sizeKb: number | null;
        isDownloadable: boolean;
        externalUrl: string | null;
        mimeType: string | null;
        views: number;
        downloads: number;
        addedAt: string | null;
    };
}) {
    const isAudio = resource.mimeType?.startsWith('audio');
    const isImage = resource.mimeType?.startsWith('image');
    const isPdf = resource.mimeType === 'application/pdf';

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">{resource.title}</h2>}>
            <Head title={resource.title} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <Link href={route('resources.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← Study material
                </Link>

                <article className="rounded-xl border border-slate-200 bg-white p-6">
                    <p className="text-xs font-medium uppercase tracking-wide text-indigo-600">
                        {resource.typeLabel}
                    </p>
                    <h1 className="mt-1 text-xl font-semibold text-slate-900">{resource.title}</h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Shared by {resource.uploader} · {resource.addedAt}
                        {resource.sizeKb ? ` · ${resource.sizeKb} KB` : ''}
                    </p>

                    {resource.description && (
                        <p className="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                            {resource.description}
                        </p>
                    )}

                    <div className="mt-4 flex flex-wrap gap-1.5">
                        {resource.subjects.map((subject) => (
                            <span
                                key={subject}
                                className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600"
                            >
                                {subject}
                            </span>
                        ))}
                    </div>

                    {/* RES-05: preview without downloading, which saves data */}
                    {resource.isDownloadable && (isPdf || isImage || isAudio) && (
                        <div className="mt-6 overflow-hidden rounded-lg border border-slate-200">
                            {isPdf && (
                                <iframe
                                    title={resource.title}
                                    src={route('resources.download', resource.id)}
                                    className="h-[60vh] w-full"
                                />
                            )}
                            {isImage && (
                                <img
                                    src={route('resources.download', resource.id)}
                                    alt={resource.title}
                                    className="w-full"
                                />
                            )}
                            {isAudio && (
                                <audio controls className="w-full p-4" preload="none">
                                    <source src={route('resources.download', resource.id)} />
                                </audio>
                            )}
                        </div>
                    )}

                    <div className="mt-6 flex flex-wrap gap-3">
                        {resource.isDownloadable && (
                            <a
                                href={route('resources.download', resource.id)}
                                className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                Download
                            </a>
                        )}
                        {resource.externalUrl && (
                            <a
                                href={resource.externalUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Open link
                            </a>
                        )}
                    </div>

                    <p className="mt-4 text-[11px] text-slate-400">
                        {resource.views} view{resource.views === 1 ? '' : 's'} · {resource.downloads} download
                        {resource.downloads === 1 ? '' : 's'}
                    </p>
                </article>
            </div>
        </AuthenticatedLayout>
    );
}
