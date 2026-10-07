import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

/**
 * Split layout for every signed-out page.
 *
 * The left panel is the reason to sign up, in the portal's own colour, so a
 * student and a teacher arriving at the same form see different, relevant
 * reassurance. On a phone it collapses to a compact header so the form is
 * reachable without scrolling.
 */

interface PortalProps {
    current: 'student' | 'teacher';
    name: string;
    studentUrl: string;
    teacherUrl: string;
}

const PANELS = {
    student: {
        eyebrow: 'Free for South African learners',
        heading: 'Stuck on homework? Ask a real tutor.',
        body: 'Verified tutors in your subjects, lessons in the language you think in, and past papers for your exact grade.',
        points: [
            'Every tutor checked before they can message you',
            'Lessons and notes in 11 languages',
            'Test yourself at five levels, with explanations',
            'Works on any phone, light on data',
        ],
        gradient: 'from-indigo-600 via-indigo-700 to-slate-900',
        accent: 'text-indigo-200',
        dot: 'bg-indigo-300',
        switchLabel: 'Are you a teacher?',
    },
    teacher: {
        eyebrow: 'For teachers, graduates and students who can teach',
        heading: "Twenty minutes of your time changes a learner's week.",
        body: 'Answer questions in the subjects you know, when it suits you. Share your notes once and reach every student studying that subject.',
        points: [
            'Accept only the questions you have time for',
            'Share notes, past papers and voice notes',
            'Host your own class and set work',
            'A contribution certificate that is yours to keep',
        ],
        gradient: 'from-teal-600 via-teal-700 to-slate-900',
        accent: 'text-teal-200',
        dot: 'bg-teal-300',
        switchLabel: 'Are you a student?',
    },
} as const;

export default function Guest({
    children,
    title,
    subtitle,
}: PropsWithChildren<{ title?: string; subtitle?: ReactNode }>) {
    const portal = (usePage().props.portal ?? {
        current: 'student',
        name: 'DX Student Help',
        studentUrl: '/',
        teacherUrl: '/',
    }) as PortalProps;

    const isStudent = portal.current !== 'teacher';
    const panel = isStudent ? PANELS.student : PANELS.teacher;
    const otherUrl = isStudent ? portal.teacherUrl : portal.studentUrl;

    return (
        <div className="min-h-screen bg-white lg:flex">
            {/* Reassurance panel — full height on desktop, a slim header on a phone */}
            <aside
                className={`relative flex flex-col justify-between bg-gradient-to-br px-6 py-8 text-white sm:px-10 lg:w-[46%] lg:px-14 lg:py-14 ${panel.gradient}`}
            >
                <div>
                    <Link href="/" className="inline-flex items-center gap-2.5">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15 text-sm font-bold backdrop-blur">
                            DX
                        </span>
                        <span className="text-base font-semibold">{portal.name}</span>
                    </Link>

                    <div className="mt-10 hidden lg:block">
                        <p className={`text-xs font-semibold uppercase tracking-widest ${panel.accent}`}>
                            {panel.eyebrow}
                        </p>
                        <h2 className="mt-4 text-3xl font-semibold leading-tight xl:text-4xl">
                            {panel.heading}
                        </h2>
                        <p className="mt-4 max-w-md leading-relaxed text-white/80">{panel.body}</p>

                        <ul className="mt-8 space-y-3">
                            {panel.points.map((point) => (
                                <li key={point} className="flex items-start gap-3 text-sm text-white/90">
                                    <span className={`mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full ${panel.dot}`} />
                                    {point}
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Phones get one line rather than the whole pitch */}
                    <p className="mt-4 text-sm text-white/80 lg:hidden">{panel.body}</p>
                </div>

                <p className="mt-8 hidden text-xs text-white/60 lg:block">
                    Every tutor is verified and all conversations are monitored to keep young people safe.
                </p>
            </aside>

            {/* Form side */}
            <main className="flex flex-1 items-center justify-center px-6 py-10 sm:px-10 lg:py-14">
                <div className="w-full max-w-md">
                    {title && (
                        <header className="mb-7">
                            <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
                            {subtitle && <p className="mt-1.5 text-sm text-slate-600">{subtitle}</p>}
                        </header>
                    )}

                    {children}

                    <p className="mt-10 border-t border-slate-100 pt-5 text-center text-xs text-slate-500">
                        {panel.switchLabel}{' '}
                        <a
                            href={otherUrl}
                            className={`font-semibold ${isStudent ? 'text-teal-700' : 'text-indigo-700'}`}
                        >
                            Go to the {isStudent ? 'teacher' : 'student'} site
                        </a>
                    </p>
                </div>
            </main>
        </div>
    );
}
