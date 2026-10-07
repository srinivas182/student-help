import { Link, usePage } from '@inertiajs/react';

/**
 * Five tabs, thumb-reachable, visible on every screen.
 *
 * Eleven items in a dropdown is how a teenager decides an app is work. Five
 * fixed destinations is how they decide it is an app.
 */
const TABS = [
    {
        label: 'Home',
        route: 'dashboard',
        match: ['dashboard'],
        icon: 'M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5',
    },
    {
        label: 'Ask',
        route: 'requests.index',
        match: ['requests.*'],
        icon: 'M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12Z',
    },
    {
        label: 'Learn',
        route: 'learn.index',
        match: ['learn.*', 'assessment.*'],
        icon: 'M12 6.5 4 4v14l8 2.5 8-2.5V4l-8 2.5Zm0 0V21',
    },
    {
        label: 'Community',
        route: 'community.index',
        match: ['community.*', 'studyGroups.*', 'classrooms.*'],
        icon: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm13 12v-1a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
    },
    {
        label: 'Me',
        route: 'progress',
        match: ['progress', 'profile.*', 'billing.*', 'notifications.*'],
        icon: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z',
    },
];

export default function MobileTabBar() {
    const page = usePage();
    const unread = (page.props.auth as { unread_notifications?: number } | undefined)?.unread_notifications ?? 0;

    const isActive = (patterns: string[]) => patterns.some((pattern) => route().current(pattern));

    return (
        <nav
            aria-label="Main"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        >
            <ul className="flex">
                {TABS.map((tab) => {
                    const active = isActive(tab.match);

                    return (
                        <li key={tab.label} className="flex-1">
                            <Link
                                href={route(tab.route)}
                                aria-current={active ? 'page' : undefined}
                                className={`flex flex-col items-center gap-0.5 py-2.5 text-[11px] font-medium transition ${
                                    active ? 'text-indigo-600' : 'text-slate-500'
                                }`}
                            >
                                <span className="relative">
                                    <svg
                                        className="h-6 w-6"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth={active ? 2.1 : 1.7}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d={tab.icon} />
                                    </svg>
                                    {tab.label === 'Me' && unread > 0 && (
                                        <span className="absolute -right-1.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
                                            {unread > 9 ? '9+' : unread}
                                        </span>
                                    )}
                                </span>
                                {tab.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
