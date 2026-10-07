import GlobalSearch from '@/Components/GlobalSearch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface Group {
    label: string;
    items: { title: string; subtitle: string | null; url: string }[];
}

export default function Search({
    results,
}: {
    results: { term: string; groups: Group[]; total: number };
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Search</h2>}>
            <Head title={results.term ? `Search: ${results.term}` : 'Search'} />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <GlobalSearch compact />

                {results.term && (
                    <p className="text-sm text-slate-600">
                        {results.total === 0
                            ? `Nothing found for "${results.term}".`
                            : `${results.total} result${results.total === 1 ? '' : 's'} for "${results.term}".`}
                    </p>
                )}

                {results.total === 0 && results.term && (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
                        <p className="text-sm text-slate-600">
                            Nothing matched. You could ask a tutor about it instead.
                        </p>
                        <Link
                            href={route('requests.create')}
                            className="mt-4 inline-block rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Ask a question
                        </Link>
                    </div>
                )}

                {results.groups.map((group) => (
                    <section key={group.label} className="rounded-xl border border-slate-200 bg-white">
                        <h2 className="border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {group.label}
                        </h2>
                        <ul className="divide-y divide-slate-100">
                            {group.items.map((item) => (
                                <li key={item.url}>
                                    <Link href={item.url} className="block px-5 py-3 hover:bg-slate-50">
                                        <span className="block text-sm font-medium text-slate-900">
                                            {item.title}
                                        </span>
                                        {item.subtitle && (
                                            <span className="block text-xs text-slate-500">
                                                {item.subtitle}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
