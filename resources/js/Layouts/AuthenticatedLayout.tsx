import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavGroup from '@/Components/NavGroup';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import NotificationBell from '@/Components/NotificationBell';
import BuiltBy from '@/Components/BuiltBy';
import GlobalSearch from '@/Components/GlobalSearch';
import MobileTabBar from '@/Components/MobileTabBar';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

interface StaffSection {
    label: string;
    items: { label: string; href: string; pattern: string; description?: string }[];
}

/**
 * Staff navigation, shared by the desktop dropdowns and the mobile menu.
 * Filtered by permission, so a moderator never sees a link to a page the
 * server would refuse them.
 */
function staffSections(can: Record<string, boolean>): StaffSection[] {
    const sections: StaffSection[] = [
        {
            label: 'People',
            items: [
                can['users.manage'] && { label: 'Users', href: route('admin.users.index'), pattern: 'admin.users.*', description: 'Accounts, suspensions, invitations' },
                can['tutors.verify'] && { label: 'Tutor verification', href: route('admin.verification.index'), pattern: 'admin.verification.*', description: 'Documents awaiting review' },
                can['tutors.verify'] && { label: 'Subject coverage', href: route('admin.coverage'), pattern: 'admin.coverage', description: 'Where tutors are thin' },
                can['roles.manage'] && { label: 'Roles and permissions', href: route('admin.roles.index'), pattern: 'admin.roles.*', description: 'Who can do what' },
            ].filter(Boolean) as StaffSection['items'],
        },
        {
            label: 'Content',
            items: [
                can['topics.manage'] && { label: 'AI Tutor topics', href: route('admin.topics.index'), pattern: 'admin.topics.*', description: 'Generate and publish lessons' },
                can['resources.review'] && { label: 'Study material', href: route('admin.resources.index'), pattern: 'admin.resources.*', description: 'Uploads awaiting approval' },
                can['curriculum.manage'] && { label: 'Curriculum', href: route('admin.curriculum.index'), pattern: 'admin.curriculum.*', description: 'Grades, subjects and topics' },
                can['announcements.manage'] && { label: 'Announcements', href: route('admin.announcements.index'), pattern: 'admin.announcements.*', description: 'Messages to students and tutors' },
                can['tutors.verify'] && { label: 'School links', href: route('admin.schoolLinks.index'), pattern: 'admin.schoolLinks.*', description: 'Classes claiming a school name' },
            ].filter(Boolean) as StaffSection['items'],
        },
        {
            label: 'Safeguarding',
            items: [
                can['moderation.queue'] && { label: 'Reports queue', href: route('moderation.index'), pattern: 'moderation.index', description: 'Reported messages and posts' },
                can['moderation.groups'] && { label: 'Study groups', href: route('moderation.groups'), pattern: 'moderation.groups*', description: 'Flagged group conversations' },
                can['moderation.voice'] && { label: 'Voice notes', href: route('moderation.voice'), pattern: 'moderation.voice*', description: 'Flagged or untranscribed audio' },
                can['audit.view'] && { label: 'Audit log', href: route('admin.audit'), pattern: 'admin.audit', description: 'Who did what, and when' },
            ].filter(Boolean) as StaffSection['items'],
        },
        {
            label: 'Settings',
            items: [
                can['settings.manage'] && { label: 'Platform settings', href: route('admin.settings'), pattern: 'admin.settings', description: 'Limits, consent, policy version' },
                can['assistant.configure'] && { label: 'Study assistant', href: route('admin.assistant'), pattern: 'admin.assistant*', description: 'AI mode, quotas and spend' },
                can['settings.manage'] && { label: 'Gateways', href: route('admin.gateways'), pattern: 'admin.gateways*', description: 'Email, SMS and WhatsApp' },
                can['settings.manage'] && { label: 'Security', href: route('admin.security'), pattern: 'admin.security*', description: 'Two-factor and staff accounts' },
            ].filter(Boolean) as StaffSection['items'],
        },
    ];

    // A group with nothing in it should not appear at all
    return sections.filter((section) => section.items.length > 0);
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    // Staff work on desktop and need the full menu; the tab bar is for learners
    const isStaff = ['admin', 'super_admin', 'moderator'].includes(user.role);
    const can = ((user as unknown as { can?: Record<string, boolean> }).can ?? {}) as Record<string, boolean>;
    const STAFF_SECTIONS = isStaff ? staffSections(can) : [];

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>

                                {user.role === 'student' && (
                                    <>
                                        <NavLink
                                            href={route('dashboard')}
                                            active={route().current('dashboard')}
                                        >
                                            Home
                                        </NavLink>
                                        <NavLink
                                            href={route('requests.index')}
                                            active={route().current('requests.*')}
                                        >
                                            My questions
                                        </NavLink>
                                        <NavLink
                                            href={route('learn.index')}
                                            active={route().current('learn.*') || route().current('assessment.*')}
                                        >
                                            Learn
                                        </NavLink>

                                        <NavGroup
                                            label="Study"
                                            items={[
                                                {
                                                    label: 'Study material',
                                                    href: route('resources.index'),
                                                    pattern: 'resources.*',
                                                    description: 'Notes, past papers and solutions',
                                                },
                                                {
                                                    label: 'Study assistant',
                                                    href: route('assistant.index'),
                                                    pattern: 'assistant.*',
                                                    description: 'An instant explanation while you wait',
                                                },
                                                {
                                                    label: 'My classes',
                                                    href: route('classrooms.index'),
                                                    pattern: 'classrooms.*',
                                                    description: 'Classes your teacher set up',
                                                },
                                            ]}
                                        />

                                        <NavGroup
                                            label="Community"
                                            items={[
                                                {
                                                    label: 'Subject boards',
                                                    href: route('community.index'),
                                                    pattern: 'community.*',
                                                    description: 'Ask and answer in your subjects',
                                                },
                                                {
                                                    label: 'Study groups',
                                                    href: route('studyGroups.index'),
                                                    pattern: 'studyGroups.*',
                                                    description: 'Work through things together',
                                                },
                                                {
                                                    label: 'Announcements',
                                                    href: route('announcements.index'),
                                                    pattern: 'announcements.*',
                                                    description: 'News from DX',
                                                },
                                            ]}
                                        />

                                        <NavGroup
                                            label="Me"
                                            items={[
                                                {
                                                    label: 'My progress',
                                                    href: route('progress'),
                                                    pattern: 'progress',
                                                    description: 'Streak, subjects and stats',
                                                },
                                                {
                                                    label: 'Plans',
                                                    href: route('billing.plans'),
                                                    pattern: 'billing.*',
                                                    description: 'What is free and what is not',
                                                },
                                            ]}
                                        />
                                    </>
                                )}

                                {user.role === 'tutor' && (
                                    <>
                                        <NavLink
                                            href={route('tutor.home')}
                                            active={route().current('tutor.home')}
                                        >
                                            Home
                                        </NavLink>
                                        <NavLink
                                            href={route('tutor.queue')}
                                            active={route().current('tutor.queue')}
                                        >
                                            Queue
                                        </NavLink>
                                        <NavLink
                                            href={route('classrooms.index')}
                                            active={route().current('classrooms.*')}
                                        >
                                            Classes
                                        </NavLink>
                                        <NavLink
                                            href={route('resources.mine')}
                                            active={route().current('resources.*')}
                                        >
                                            Material
                                        </NavLink>
                                        <NavLink
                                            href={route('progress')}
                                            active={route().current('progress*')}
                                        >
                                            My impact
                                        </NavLink>
                                        <NavLink
                                            href={route('tutor.profile')}
                                            active={route().current('tutor.profile')}
                                        >
                                            My profile
                                        </NavLink>
                                    </>
                                )}

                                {user.can_review && (
                                    <NavLink
                                        href={route('review.index')}
                                        active={route().current('review.*')}
                                    >
                                        Review
                                    </NavLink>
                                )}

                                {isStaff && (
                                    <>
                                        <NavLink
                                            href={route('admin.dashboard')}
                                            active={route().current('admin.dashboard')}
                                        >
                                            Overview
                                        </NavLink>

                                        {STAFF_SECTIONS.map((section) => (
                                            <NavGroup
                                                key={section.label}
                                                label={section.label}
                                                items={section.items}
                                            />
                                        ))}
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center sm:gap-2">
                            <div className="hidden lg:block">
                                <GlobalSearch />
                            </div>

                            <NotificationBell />
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>

                        {/* Staff have no tab bar, so every destination must be
                            reachable here or it cannot be reached on a phone. */}
                        {isStaff &&
                            STAFF_SECTIONS.map((section) => (
                                <div key={section.label} className="pt-2">
                                    <p className="px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                        {section.label}
                                    </p>
                                    {section.items.map((item) => (
                                        <ResponsiveNavLink
                                            key={item.href}
                                            href={item.href}
                                            active={route().current(item.pattern)}
                                        >
                                            {item.label}
                                        </ResponsiveNavLink>
                                    ))}
                                </div>
                            ))}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {/* Padding keeps the last line of content clear of the tab bar */}
            <main className="pb-20 md:pb-0">{children}</main>

            <footer className="border-t border-slate-200 bg-white px-4 py-6 pb-24 md:pb-6">
                <BuiltBy />
            </footer>

            {!isStaff && <MobileTabBar />}
        </div>
    );
}
