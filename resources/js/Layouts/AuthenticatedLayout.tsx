import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import NotificationBell from '@/Components/NotificationBell';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

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
                                            href={route('requests.index')}
                                            active={route().current('requests.*')}
                                        >
                                            My requests
                                        </NavLink>
                                        <NavLink
                                            href={route('resources.index')}
                                            active={route().current('resources.index')}
                                        >
                                            Study material
                                        </NavLink>
                                        <NavLink
                                            href={route('classrooms.index')}
                                            active={route().current('classrooms.*')}
                                        >
                                            My classes
                                        </NavLink>
                                        <NavLink
                                            href={route('assistant.index')}
                                            active={route().current('assistant.*')}
                                        >
                                            Assistant
                                        </NavLink>
                                        <NavLink
                                            href={route('community.index')}
                                            active={route().current('community.*')}
                                        >
                                            Community
                                        </NavLink>
                                        <NavLink
                                            href={route('studyGroups.index')}
                                            active={route().current('studyGroups.*')}
                                        >
                                            Study groups
                                        </NavLink>
                                        <NavLink
                                            href={route('progress')}
                                            active={route().current('progress')}
                                        >
                                            Progress
                                        </NavLink>
                                        <NavLink
                                            href={route('announcements.index')}
                                            active={route().current('announcements.index')}
                                        >
                                            Announcements
                                        </NavLink>
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

                                {['admin', 'super_admin', 'moderator'].includes(user.role) && (
                                    <>
                                        <NavLink
                                            href={route('admin.dashboard')}
                                            active={route().current('admin.dashboard')}
                                        >
                                            Overview
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.users.index')}
                                            active={route().current('admin.users.*')}
                                        >
                                            Users
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.verification.index')}
                                            active={route().current('admin.verification.*')}
                                        >
                                            Verification
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.curriculum.index')}
                                            active={route().current('admin.curriculum.*')}
                                        >
                                            Curriculum
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.coverage')}
                                            active={route().current('admin.coverage')}
                                        >
                                            Coverage
                                        </NavLink>
                                        <NavLink
                                            href={route('moderation.index')}
                                            active={route().current('moderation.index')}
                                        >
                                            Moderation
                                        </NavLink>
                                        <NavLink
                                            href={route('moderation.groups')}
                                            active={route().current('moderation.groups*')}
                                        >
                                            Groups
                                        </NavLink>
                                        <NavLink
                                            href={route('moderation.voice')}
                                            active={route().current('moderation.voice*')}
                                        >
                                            Voice
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.schoolLinks.index')}
                                            active={route().current('admin.schoolLinks.*')}
                                        >
                                            Schools
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.announcements.index')}
                                            active={route().current('admin.announcements.*')}
                                        >
                                            Announce
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.resources.index')}
                                            active={route().current('admin.resources.*')}
                                        >
                                            Material
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.topics.index')}
                                            active={route().current('admin.topics.*')}
                                        >
                                            AI Tutor
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.assistant')}
                                            active={route().current('admin.assistant*')}
                                        >
                                            AI
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.settings')}
                                            active={route().current('admin.settings')}
                                        >
                                            Settings
                                        </NavLink>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center sm:gap-2">
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

            <main>{children}</main>
        </div>
    );
}
