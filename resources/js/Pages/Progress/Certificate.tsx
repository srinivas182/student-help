import { Head, Link } from '@inertiajs/react';

/**
 * Printable record of contribution. Deliberately plain so it prints cleanly
 * and reads as a document rather than a web page.
 */
export default function Certificate({
    name,
    stats,
    subjects,
    since,
    issuedOn,
    reference,
}: {
    name: string;
    stats: { studentsHelped: number; resolved: number; rating: string | null; resourcesShared: number };
    subjects: string[];
    since: string | null;
    issuedOn: string;
    reference: string;
}) {
    return (
        <div className="min-h-screen bg-slate-100 py-10 print:bg-white print:py-0">
            <Head title="Contribution certificate" />

            <div className="mx-auto max-w-2xl px-4 print:px-0">
                <div className="mb-4 flex justify-between print:hidden">
                    <Link href={route('progress')} className="text-sm text-slate-600 hover:text-slate-900">
                        ← Back
                    </Link>
                    <button
                        onClick={() => window.print()}
                        className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white"
                    >
                        Print or save as PDF
                    </button>
                </div>

                <article className="border-8 border-double border-teal-700 bg-white p-12 text-center print:border-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.3em] text-teal-700">
                        DX Student Help
                    </p>
                    <h1 className="mt-8 text-sm uppercase tracking-widest text-slate-500">
                        Certificate of contribution
                    </h1>

                    <p className="mt-8 text-3xl font-semibold text-slate-900">{name}</p>

                    <p className="mx-auto mt-6 max-w-md text-sm leading-relaxed text-slate-700">
                        has served as a verified tutor on DX Student Help
                        {since ? `, since ${since},` : ','} giving academic support to South African learners.
                    </p>

                    <dl className="mt-10 grid grid-cols-3 gap-6 border-y border-slate-200 py-6">
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Students helped</dt>
                            <dd className="mt-1 text-2xl font-semibold text-slate-900">
                                {stats.studentsHelped}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">
                                Questions answered
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold text-slate-900">{stats.resolved}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Average rating</dt>
                            <dd className="mt-1 text-2xl font-semibold text-slate-900">
                                {stats.rating ? `★ ${stats.rating}` : '—'}
                            </dd>
                        </div>
                    </dl>

                    {subjects.length > 0 && (
                        <p className="mt-6 text-sm text-slate-600">
                            Subjects supported: {subjects.slice(0, 8).join(', ')}
                        </p>
                    )}

                    <div className="mt-12 flex items-end justify-between text-left text-xs text-slate-500">
                        <div>
                            <p className="border-t border-slate-400 pt-2">Issued {issuedOn}</p>
                        </div>
                        <div className="text-right">
                            <p className="border-t border-slate-400 pt-2">Reference {reference}</p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    );
}
