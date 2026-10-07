import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export interface NavItem {
    label: string;
    href: string;
    pattern: string;
    description?: string;
}

/**
 * A grouped dropdown for staff navigation.
 *
 * A super administrator has seventeen destinations. In one horizontal row they
 * overflow the screen and the ones past the edge simply cannot be reached.
 * Five groups fit, and each one says what is inside it.
 */
export default function NavGroup({ label, items }: { label: string; items: NavItem[] }) {
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);

    const active = items.some((item) => route().current(item.pattern));

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (box.current && !box.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const escape = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', escape);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', escape);
        };
    }, []);

    return (
        <div ref={box} className="relative inline-flex h-16 items-center">
            <button
                onClick={() => setOpen(!open)}
                aria-expanded={open}
                aria-haspopup="true"
                className={`inline-flex h-16 items-center gap-1.5 border-b-2 px-1 text-sm font-medium transition focus:outline-none ${
                    active
                        ? 'border-indigo-400 text-slate-900'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
                }`}
            >
                {label}
                <svg
                    className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                    aria-hidden="true"
                >
                    <path d="m6 9 6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && (
                <div className="absolute left-0 top-16 z-50 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={() => setOpen(false)}
                            className={`block px-4 py-2.5 transition hover:bg-slate-50 ${
                                route().current(item.pattern) ? 'bg-slate-50' : ''
                            }`}
                        >
                            <span
                                className={`block text-sm ${
                                    route().current(item.pattern)
                                        ? 'font-semibold text-indigo-700'
                                        : 'text-slate-800'
                                }`}
                            >
                                {item.label}
                            </span>
                            {item.description && (
                                <span className="mt-0.5 block text-xs text-slate-500">
                                    {item.description}
                                </span>
                            )}
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
