import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

interface Item {
    id: string;
    title: string;
    body: string;
    url: string | null;
    readAt: string | null;
    createdAt: string;
}

type Preferences = Record<string, { label: string; database: boolean; mail: boolean }>;

export default function Index({
    notifications,
    preferences,
}: {
    notifications: { data: Item[] };
    preferences: Preferences;
}) {
    const { data, setData, put, processing } = useForm<{ preferences: Preferences }>({ preferences });

    const toggle = (event: string, channel: 'database' | 'mail') =>
        setData('preferences', {
            ...data.preferences,
            [event]: { ...data.preferences[event], [channel]: !data.preferences[event][channel] },
        });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Notifications</h2>}>
            <Head title="Notifications" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <header className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h3 className="font-semibold text-slate-900">Recent</h3>
                        <button
                            onClick={() => router.post(route('notifications.readAll'))}
                            className="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                        >
                            Mark all as read
                        </button>
                    </header>

                    {notifications.data.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-slate-500">
                            Nothing yet. We will let you know when a tutor replies or your request is picked up.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {notifications.data.map((item) => (
                                <li
                                    key={item.id}
                                    className={`px-5 py-4 ${item.readAt ? 'bg-white' : 'bg-indigo-50/40'}`}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <p className="font-medium text-slate-900">{item.title}</p>
                                            <p className="mt-0.5 text-sm text-slate-600">{item.body}</p>
                                            <p className="mt-1 text-xs text-slate-400">{item.createdAt}</p>
                                        </div>
                                        {item.url && (
                                            <a
                                                href={item.url}
                                                onClick={() => router.post(route('notifications.read', item.id))}
                                                className="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-500"
                                            >
                                                Open
                                            </a>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 className="font-semibold text-slate-900">How you want to hear from us</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        Account, security and guardian approval emails are always sent.
                    </p>

                    <table className="mt-4 w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                                <th className="pb-2">Event</th>
                                <th className="pb-2 text-center">In app</th>
                                <th className="pb-2 text-center">Email</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {Object.entries(data.preferences).map(([event, value]) => (
                                <tr key={event}>
                                    <td className="py-3 text-slate-700">{value.label}</td>
                                    {(['database', 'mail'] as const).map((channel) => (
                                        <td key={channel} className="py-3 text-center">
                                            <input
                                                type="checkbox"
                                                checked={value[channel]}
                                                onChange={() => toggle(event, channel)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <button
                        onClick={() => put(route('notifications.preferences'))}
                        disabled={processing}
                        className="mt-4 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Save preferences
                    </button>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
