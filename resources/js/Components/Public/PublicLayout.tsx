import { Link } from '@inertiajs/react';
import { PropsWithChildren, useState } from 'react';

export interface PortalBrand {
    key: 'student' | 'teacher';
    name: string;
    otherName: string;
    otherUrl: string;
}

const MENUS = {
    student: [
        { label: 'How it works', href: '#how' },
        { label: 'Subjects', href: '#subjects' },
        { label: 'Staying safe', href: '#safety' },
        { label: 'For parents', href: '/parents' },
        { label: 'Questions', href: '#faq' },
    ],
    teacher: [
        { label: 'How it works', href: '#how' },
        { label: 'Why volunteer', href: '#why' },
        { label: 'Getting verified', href: '#verify' },
        { label: 'Questions', href: '#faq' },
    ],
};

export default function PublicLayout({
    brand,
    children,
}: PropsWithChildren<{ brand: PortalBrand }>) {
    const [open, setOpen] = useState(false);
    const isStudent = brand.key === 'student';
    const accent = isStudent ? 'indigo' : 'teal';
    const menu = MENUS[brand.key];

    return (
        <div className="min-h-screen bg-white">
            <header className="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5 sm:px-6">
                    <Link href="/" className="flex items-center gap-2">
                        <span
                            className={`flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold text-white ${
                                isStudent ? 'bg-indigo-600' : 'bg-teal-600'
                            }`}
                        >
                            DX
                        </span>
                        <span className="text-base font-semibold text-slate-900">{brand.name}</span>
                    </Link>

                    <nav className="hidden items-center gap-6 md:flex">
                        {menu.map((item) => (
                            <a
                                key={item.label}
                                href={item.href}
                                className="text-sm text-slate-600 transition hover:text-slate-900"
                            >
                                {item.label}
                            </a>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-3 md:flex">
                        <Link href={route('login')} className="text-sm font-medium text-slate-700 hover:text-slate-900">
                            Sign in
                        </Link>
                        <Link
                            href={route('register')}
                            className={`rounded-lg px-4 py-2 text-sm font-semibold text-white transition ${
                                isStudent ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-teal-600 hover:bg-teal-500'
                            }`}
                        >
                            {isStudent ? 'Join free' : 'Apply to tutor'}
                        </Link>
                    </div>

                    <button
                        onClick={() => setOpen(!open)}
                        aria-label="Menu"
                        className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden"
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path
                                strokeLinecap="round"
                                d={open ? 'M6 18L18 6M6 6l12 12' : 'M4 7h16M4 12h16M4 17h16'}
                            />
                        </svg>
                    </button>
                </div>

                {open && (
                    <nav className="border-t border-slate-200 bg-white px-4 py-4 md:hidden">
                        {menu.map((item) => (
                            <a
                                key={item.label}
                                href={item.href}
                                onClick={() => setOpen(false)}
                                className="block py-2.5 text-sm text-slate-700"
                            >
                                {item.label}
                            </a>
                        ))}
                        <div className="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3">
                            <Link href={route('login')} className="py-2 text-sm font-medium text-slate-700">
                                Sign in
                            </Link>
                            <Link
                                href={route('register')}
                                className={`rounded-lg px-4 py-2.5 text-center text-sm font-semibold text-white ${
                                    isStudent ? 'bg-indigo-600' : 'bg-teal-600'
                                }`}
                            >
                                {isStudent ? 'Join free' : 'Apply to tutor'}
                            </Link>
                        </div>
                    </nav>
                )}
            </header>

            <main>{children}</main>

            <footer className="border-t border-slate-200 bg-slate-50">
                <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="font-semibold text-slate-900">{brand.name}</p>
                            <p className="mt-2 text-sm text-slate-600">
                                Academic help for South African learners, from verified tutors.
                            </p>
                        </div>

                        <div>
                            <p className="text-sm font-semibold text-slate-900">Platform</p>
                            <ul className="mt-3 space-y-2 text-sm text-slate-600">
                                {menu.map((item) => (
                                    <li key={item.label}>
                                        <a href={item.href} className="hover:text-slate-900">
                                            {item.label}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div>
                            <p className="text-sm font-semibold text-slate-900">Safety and privacy</p>
                            <ul className="mt-3 space-y-2 text-sm text-slate-600">
                                <li><a href="/safety" className="hover:text-slate-900">Keeping learners safe</a></li>
                                <li><a href="/privacy" className="hover:text-slate-900">Privacy and POPIA</a></li>
                                <li><a href="/terms" className="hover:text-slate-900">Terms of use</a></li>
                                <li><a href="/report" className="hover:text-slate-900">Report a concern</a></li>
                            </ul>
                        </div>

                        <div>
                            <p className="text-sm font-semibold text-slate-900">
                                {brand.key === 'student' ? 'Are you a teacher?' : 'Are you a student?'}
                            </p>
                            <p className="mt-2 text-sm text-slate-600">
                                {brand.otherName} is the other side of the platform.
                            </p>
                            <a
                                href={brand.otherUrl}
                                className={`mt-2 inline-block text-sm font-semibold ${
                                    isStudent ? 'text-teal-700' : 'text-indigo-700'
                                }`}
                            >
                                Go to {brand.otherName} →
                            </a>
                        </div>
                    </div>

                    <p className="mt-10 border-t border-slate-200 pt-6 text-xs text-slate-500">
                        © {new Date().getFullYear()} The X Student Help (PTY) LTD. Built for South African
                        learners. Every tutor is verified, and all conversations are monitored to keep young
                        people safe.
                    </p>
                </div>
            </footer>
        </div>
    );
}
