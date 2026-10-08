import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * One tap to ask, from anywhere.
 *
 * A learner stuck inside a class page had to work out for themselves whether
 * to ask the class, ask a tutor privately, or try the assistant — and the
 * routes to each were in three different places. This puts all three in reach
 * and says plainly who will see each one.
 */
export default function AskFab() {
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);

    const page = usePage().props as {
        auth?: { user?: { role?: string; can_participate?: boolean } };
    };

    const user = page.auth?.user;

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

    // Only learners ask; staff and tutors answer
    if (user?.role !== 'student') {
        return null;
    }

    const options = [
        {
            label: 'Ask a tutor',
            detail: 'Private. Only you and the tutor see it.',
            href: route('requests.create'),
            tone: 'bg-indigo-600 hover:bg-indigo-500',
            available: user?.can_participate !== false,
        },
        {
            label: 'Ask the community',
            detail: 'Public in your subject. Anyone can answer.',
            href: route('community.index'),
            tone: 'bg-teal-600 hover:bg-teal-500',
            available: user?.can_participate !== false,
        },
        {
            label: 'Ask the study assistant',
            detail: 'Instant, AI. Good while you wait.',
            href: route('assistant.index'),
            tone: 'bg-slate-800 hover:bg-slate-700',
            available: true,
        },
    ].filter((option) => option.available);

    return (
        <div
            ref={box}
            className="fixed bottom-24 right-4 z-40 flex flex-col items-end gap-3 md:bottom-6 md:right-6"
        >
            {open && (
                <div className="w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                    {options.map((option) => (
                        <Link
                            key={option.href}
                            href={option.href}
                            onClick={() => setOpen(false)}
                            className="block border-b border-slate-100 px-5 py-3.5 last:border-0 hover:bg-slate-50"
                        >
                            <span className="block text-sm font-medium text-slate-900">
                                {option.label}
                            </span>
                            <span className="mt-0.5 block text-xs text-slate-500">{option.detail}</span>
                        </Link>
                    ))}
                </div>
            )}

            <button
                onClick={() => setOpen(!open)}
                aria-expanded={open}
                aria-label={open ? 'Close ask menu' : 'Ask a question'}
                className={`flex h-14 items-center gap-2 rounded-full px-5 font-semibold text-white shadow-lg transition ${
                    open ? 'bg-slate-700' : 'bg-indigo-600 hover:bg-indigo-500'
                }`}
            >
                <svg
                    className={`h-5 w-5 transition-transform ${open ? 'rotate-45' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2.2}
                    aria-hidden="true"
                >
                    <path strokeLinecap="round" d="M12 5v14M5 12h14" />
                </svg>
                <span className="text-sm">Ask</span>
            </button>
        </div>
    );
}
