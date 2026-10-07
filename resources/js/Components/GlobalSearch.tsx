import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Group {
    label: string;
    items: { title: string; subtitle: string | null; url: string }[];
}

/** One box for lessons, study material, community answers and your own questions. */
export default function GlobalSearch({ compact = false }: { compact?: boolean }) {
    const [term, setTerm] = useState('');
    const [groups, setGroups] = useState<Group[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (term.trim().length < 2) {
            setGroups([]);

            return;
        }

        setLoading(true);
        const timer = setTimeout(async () => {
            try {
                const response = await window.axios.get(route('search.quick'), { params: { q: term } });
                setGroups(response.data.groups ?? []);
                setOpen(true);
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => clearTimeout(timer);
    }, [term]);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (box.current && !box.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    return (
        <div ref={box} className={`relative ${compact ? 'w-full' : 'w-full max-w-sm'}`}>
            <div className="relative">
                <svg
                    className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                    aria-hidden="true"
                >
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-3.5-3.5" strokeLinecap="round" />
                </svg>
                <input
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                    onFocus={() => groups.length > 0 && setOpen(true)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && term.trim().length >= 2) {
                            setOpen(false);
                            router.get(route('search'), { q: term });
                        }

                        if (e.key === 'Escape') {
                            setOpen(false);
                        }
                    }}
                    placeholder="Search lessons, notes, questions…"
                    aria-label="Search"
                    className="w-full rounded-lg border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:ring-indigo-400"
                />
            </div>

            {open && (
                <div className="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    {groups.length === 0 ? (
                        <p className="px-4 py-6 text-center text-sm text-slate-500">
                            {loading ? 'Searching…' : `Nothing found for "${term}".`}
                        </p>
                    ) : (
                        <>
                            {groups.map((group) => (
                                <div key={group.label} className="border-b border-slate-100 last:border-0">
                                    <p className="px-4 pt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                        {group.label}
                                    </p>
                                    <ul className="py-1">
                                        {group.items.map((item) => (
                                            <li key={item.url}>
                                                <button
                                                    onClick={() => {
                                                        setOpen(false);
                                                        router.visit(item.url);
                                                    }}
                                                    className="block w-full px-4 py-2 text-left hover:bg-slate-50"
                                                >
                                                    <span className="block text-sm text-slate-800">
                                                        {item.title}
                                                    </span>
                                                    {item.subtitle && (
                                                        <span className="block text-xs text-slate-500">
                                                            {item.subtitle}
                                                        </span>
                                                    )}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                            <button
                                onClick={() => {
                                    setOpen(false);
                                    router.get(route('search'), { q: term });
                                }}
                                className="w-full bg-slate-50 px-4 py-2.5 text-center text-xs font-medium text-indigo-700 hover:bg-slate-100"
                            >
                                See all results
                            </button>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
