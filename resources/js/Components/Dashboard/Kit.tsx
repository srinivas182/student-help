import { Link } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

/**
 * The shared language for every dashboard.
 *
 * The three dashboards were built at different times and did not agree with
 * each other or with the client's designs. Keeping the pieces here means they
 * cannot drift apart again, and any page added later inherits the look.
 */

export type Tone = 'indigo' | 'emerald' | 'amber' | 'rose' | 'violet' | 'teal' | 'slate';

const TONES: Record<Tone, { badge: string; border: string; surface: string; text: string }> = {
    indigo: { badge: 'bg-indigo-100 text-indigo-600', border: 'border-indigo-200', surface: 'bg-indigo-50/50', text: 'text-indigo-700' },
    emerald: { badge: 'bg-emerald-100 text-emerald-600', border: 'border-emerald-200', surface: 'bg-emerald-50/50', text: 'text-emerald-700' },
    amber: { badge: 'bg-amber-100 text-amber-600', border: 'border-amber-200', surface: 'bg-amber-50/50', text: 'text-amber-700' },
    rose: { badge: 'bg-rose-100 text-rose-600', border: 'border-rose-200', surface: 'bg-rose-50/50', text: 'text-rose-700' },
    violet: { badge: 'bg-violet-100 text-violet-600', border: 'border-violet-200', surface: 'bg-violet-50/50', text: 'text-violet-700' },
    teal: { badge: 'bg-teal-100 text-teal-600', border: 'border-teal-200', surface: 'bg-teal-50/50', text: 'text-teal-700' },
    slate: { badge: 'bg-slate-100 text-slate-600', border: 'border-slate-200', surface: 'bg-white', text: 'text-slate-700' },
};

/** Line icons, drawn once so every dashboard uses the same set. */
export const ICONS = {
    question: 'M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12Z',
    book: 'M12 6.5 4 4v14l8 2.5 8-2.5V4l-8 2.5Zm0 0V21',
    people: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm13 12v-1a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
    check: 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
    clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 4v5l3 2',
    shield: 'M12 3l8 3v6c0 4.4-3.4 8.3-8 9-4.6-.7-8-4.6-8-9V6l8-3Z',
    spark: 'M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8',
    flame: 'M12 3s4 4 4 8a4 4 0 0 1-8 0c0-1.5.7-2.8 1.4-3.7C10 9.5 12 8 12 3Z',
    chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    calendar: 'M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
    warning: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
    star: 'm12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z',
} as const;

export function Icon({ path, className = 'h-5 w-5' }: { path: string; className?: string }) {
    return (
        <svg
            className={className}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.7}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={path} />
        </svg>
    );
}

/** The band at the top of a dashboard: who you are and what matters today. */
export function PageHero({
    eyebrow,
    title,
    subtitle,
    tone = 'indigo',
    children,
}: PropsWithChildren<{
    eyebrow?: string;
    title: string;
    subtitle?: ReactNode;
    tone?: Tone;
}>) {
    const gradients: Record<Tone, string> = {
        indigo: 'from-indigo-600 to-indigo-800',
        emerald: 'from-emerald-600 to-emerald-800',
        amber: 'from-amber-500 to-amber-700',
        rose: 'from-rose-600 to-rose-800',
        violet: 'from-violet-600 to-violet-800',
        teal: 'from-teal-600 to-teal-800',
        slate: 'from-slate-700 to-slate-900',
    };

    return (
        <section
            className={`rounded-2xl bg-gradient-to-br px-6 py-7 text-white sm:px-8 ${gradients[tone]}`}
        >
            {eyebrow && (
                <p className="text-xs font-semibold uppercase tracking-widest text-white/70">
                    {eyebrow}
                </p>
            )}

            <h1 className="mt-1.5 text-2xl font-semibold sm:text-3xl">{title}</h1>

            {subtitle && <p className="mt-2 max-w-2xl text-sm text-white/80">{subtitle}</p>}

            {children && <div className="mt-5">{children}</div>}
        </section>
    );
}

/** One number, with an icon so it reads at a glance rather than being parsed. */
export function StatTile({
    label,
    value,
    tone = 'slate',
    icon,
    hint,
    href,
}: {
    label: string;
    value: number | string;
    tone?: Tone;
    icon?: string;
    hint?: string;
    href?: string;
}) {
    const t = TONES[tone];

    const inner = (
        <div
            className={`flex h-full items-start gap-3 rounded-xl border p-4 transition ${t.border} ${t.surface} ${
                href ? 'hover:shadow-md' : ''
            }`}
        >
            {icon && (
                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${t.badge}`}>
                    <Icon path={icon} />
                </span>
            )}

            <div className="min-w-0">
                <p className="text-2xl font-semibold leading-tight text-slate-900">{value}</p>
                <p className="mt-0.5 text-xs font-medium text-slate-600">{label}</p>
                {hint && <p className="mt-0.5 text-xs text-slate-500">{hint}</p>}
            </div>
        </div>
    );

    return href ? (
        <Link href={href} className="block h-full">
            {inner}
        </Link>
    ) : (
        inner
    );
}

/** A titled block, optionally with a link in the corner. */
export function SectionCard({
    title,
    description,
    action,
    children,
    className = '',
}: PropsWithChildren<{
    title: string;
    description?: string;
    action?: { label: string; href: string };
    className?: string;
}>) {
    return (
        <section className={`rounded-xl border border-slate-200 bg-white p-5 sm:p-6 ${className}`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-slate-900">{title}</h2>
                    {description && <p className="mt-0.5 text-sm text-slate-600">{description}</p>}
                </div>

                {action && (
                    <Link
                        href={action.href}
                        className="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        {action.label}
                    </Link>
                )}
            </div>

            <div className="mt-4">{children}</div>
        </section>
    );
}

/** Says what to do, rather than just that there is nothing here. */
export function EmptyState({
    title,
    body,
    action,
    icon = ICONS.spark,
}: {
    title: string;
    body: string;
    action?: { label: string; href: string };
    icon?: string;
}) {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 px-6 py-10 text-center">
            <span className="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm">
                <Icon path={icon} className="h-5 w-5" />
            </span>

            <p className="mt-3 font-medium text-slate-800">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-slate-600">{body}</p>

            {action && (
                <Link
                    href={action.href}
                    className="mt-4 inline-block rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    {action.label}
                </Link>
            )}
        </div>
    );
}

/** A row that needs attention, used on the admin dashboard. */
export function AttentionRow({
    label,
    count,
    href,
    tone = 'amber',
    icon = ICONS.warning,
}: {
    label: string;
    count: number;
    href: string;
    tone?: Tone;
    icon?: string;
}) {
    const t = TONES[tone];

    return (
        <Link
            href={href}
            className={`flex items-center justify-between gap-3 rounded-lg border p-3.5 transition hover:shadow-sm ${t.border} ${t.surface}`}
        >
            <span className="flex min-w-0 items-center gap-3">
                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${t.badge}`}>
                    <Icon path={icon} className="h-4 w-4" />
                </span>
                <span className="truncate text-sm font-medium text-slate-800">{label}</span>
            </span>

            <span className={`shrink-0 text-lg font-semibold ${t.text}`}>{count}</span>
        </Link>
    );
}
