import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface Item {
    id: number;
    title: string;
    body: string;
    priority: string;
    author: string | null;
    publishedAt: string | null;
}

export default function Index({ announcements }: { announcements: Item[] }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Announcements</h2>}>
            <Head title="Announcements" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-8 sm:px-6">
                {announcements.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        No announcements right now. Important notices from DX will appear here.
                    </p>
                ) : (
                    announcements.map((item) => (
                        <article
                            key={item.id}
                            className={`rounded-xl border bg-white p-6 ${
                                item.priority === 'important' ? 'border-amber-300' : 'border-slate-200'
                            }`}
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                {item.priority === 'important' && (
                                    <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                                        Important
                                    </span>
                                )}
                                <h2 className="font-semibold text-slate-900">{item.title}</h2>
                            </div>

                            <p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                                {item.body}
                            </p>

                            <p className="mt-4 text-xs text-slate-400">
                                {item.author} · {item.publishedAt}
                            </p>
                        </article>
                    ))
                )}
            </div>
        </AuthenticatedLayout>
    );
}
