import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Item {
    id: string;
    title: string;
    body: string;
    url: string | null;
    createdAt: string;
}

export default function NotificationBell() {
    const [count, setCount] = useState(0);
    const [items, setItems] = useState<Item[]>([]);
    const [open, setOpen] = useState(false);
    const wrapper = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const load = async () => {
            try {
                const response = await window.axios.get(route('notifications.unread'));
                setCount(response.data.count);
                setItems(response.data.items);
            } catch {
                // Ignore; the next poll retries.
            }
        };

        load();
        const timer = setInterval(load, 30000);

        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (wrapper.current && !wrapper.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    return (
        <div ref={wrapper} className="relative">
            <button
                onClick={() => setOpen((value) => !value)}
                aria-label={`Notifications${count > 0 ? `, ${count} unread` : ''}`}
                className="relative rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M14.857 17.082a23.8 23.8 0 005.454-1.31A8.97 8.97 0 0118 9.75V9A6 6 0 006 9v.75a8.97 8.97 0 01-2.311 6.022c1.733.64 3.56 1.085 5.455 1.31m5.713 0a24.3 24.3 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                    />
                </svg>

                {count > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[11px] font-semibold text-white">
                        {count > 9 ? '9+' : count}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    <div className="border-b border-slate-100 px-4 py-3">
                        <p className="text-sm font-semibold text-slate-900">Notifications</p>
                    </div>

                    {items.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-500">You are all caught up.</p>
                    ) : (
                        <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                            {items.map((item) => (
                                <li key={item.id}>
                                    <button
                                        onClick={() => {
                                            router.post(route('notifications.read', item.id), {}, {
                                                preserveScroll: true,
                                                onFinish: () => item.url && router.visit(item.url),
                                            });
                                        }}
                                        className="block w-full px-4 py-3 text-left transition hover:bg-slate-50"
                                    >
                                        <p className="text-sm font-medium text-slate-900">{item.title}</p>
                                        <p className="mt-0.5 line-clamp-2 text-xs text-slate-600">{item.body}</p>
                                        <p className="mt-1 text-[11px] text-slate-400">{item.createdAt}</p>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <Link
                        href={route('notifications.index')}
                        className="block border-t border-slate-100 px-4 py-3 text-center text-xs font-medium text-indigo-600 hover:bg-slate-50"
                    >
                        See all notifications
                    </Link>
                </div>
            )}
        </div>
    );
}
