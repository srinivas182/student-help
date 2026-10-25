import BuiltBy from '@/Components/BuiltBy';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * What the platform can do, in one screen.
 *
 * Built from the client's feature poster, in the light palette the rest of the
 * app uses rather than the poster's dark one — so it belongs to the product
 * instead of sitting beside it.
 *
 * The copy describes what exists. The poster promises in-app video calling in
 * study groups and auto-saving video lessons; neither is built, and claiming
 * them here would be the first thing a learner found to be untrue.
 */

interface Feature {
    title: string;
    body: string;
    action: string;
    href: string;
    badge: string;
    border: string;
    surface: string;
    button: string;
    icon: string;
    stat?: string;
}

export default function Explore({
    liveSessions,
    counts,
    canParticipate,
}: {
    liveSessions: {
        id: number;
        title: string;
        classroom: string | null;
        classroomId: number;
        whenLabel: string;
        mode: string;
        isJoinable: boolean;
        isLive: boolean;
    }[];
    counts: {
        topics: number;
        tutors: number;
        groups: number;
        resources: number;
    };
    canParticipate: boolean;
}) {
    const features: Feature[] = [
        {
            title: 'Live classes',
            body: 'Join sessions your teacher schedules, online or in person. The join button appears just before it starts, and the recording is there afterwards if you miss it.',
            action: 'See what is on',
            href: route('classrooms.index'),
            badge: 'bg-blue-100 text-blue-600',
            border: 'border-blue-200',
            surface: 'bg-blue-50/50',
            button: 'bg-blue-600 hover:bg-blue-500',
            icon: 'M23 7l-7 5 7 5V7ZM14 5H3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z',
            stat: liveSessions.length > 0 ? `${liveSessions.length} coming up` : undefined,
        },
        {
            title: 'Real teachers answer',
            body: 'Stuck on something? Verified tutors answer your questions, usually within a few hours. Free, and private between you and them.',
            action: 'Ask a teacher',
            href: route('requests.create'),
            badge: 'bg-emerald-100 text-emerald-600',
            border: 'border-emerald-200',
            surface: 'bg-emerald-50/50',
            button: 'bg-emerald-600 hover:bg-emerald-500',
            icon: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm14 18v-2a4 4 0 0 0-3-3.9M17 3.1a4 4 0 0 1 0 7.8',
            stat: counts.tutors > 0 ? `${counts.tutors} verified tutors` : undefined,
        },
        {
            title: 'Your own study group',
            body: 'Start a group with classmates or join one in your subject. Work through problems together with messages and voice notes.',
            action: 'Create a group',
            href: route('studyGroups.index'),
            badge: 'bg-violet-100 text-violet-600',
            border: 'border-violet-200',
            surface: 'bg-violet-50/50',
            button: 'bg-violet-600 hover:bg-violet-500',
            icon: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm13 12v-1a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
            stat: counts.groups > 0 ? `${counts.groups} groups in your subjects` : undefined,
        },
        {
            title: 'AI Tutor, instantly',
            body: 'Lessons in your own language, with notes and flashcards. Then test yourself at five levels, and get told the actual mistake when you slip.',
            action: 'Start learning',
            href: route('learn.index'),
            badge: 'bg-teal-100 text-teal-600',
            border: 'border-teal-200',
            surface: 'bg-teal-50/50',
            button: 'bg-teal-600 hover:bg-teal-500',
            icon: 'M12 8V4H8M4 8h16v12H4V8Zm4 4h.01M16 12h.01M9 16h6',
            stat: counts.topics > 0 ? `${counts.topics} lessons ready` : undefined,
        },
    ];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">What you can do</h2>}
        >
            <Head title="What you can do" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
                <section className="rounded-2xl border border-slate-200 bg-white px-6 py-8 text-center sm:px-8">
                    <span className="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-indigo-700">
                        Built for South African learners
                    </span>

                    <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Study smarter. Learn deeper. Achieve more.
                    </h1>

                    <p className="mx-auto mt-2.5 max-w-xl text-slate-600">
                        Everything here is free, and every tutor is verified before they can message you.
                    </p>
                </section>

                {/* What is actually happening, before what is possible */}
                {liveSessions.length > 0 && (
                    <section className="rounded-2xl border border-blue-200 bg-blue-50/50 p-6">
                        <h2 className="font-semibold text-slate-900">
                            {liveSessions.some((s) => s.isLive) ? 'Happening now' : 'Your next live classes'}
                        </h2>

                        <ul className="mt-4 space-y-2.5">
                            {liveSessions.map((session) => (
                                <li
                                    key={session.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4"
                                >
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 text-sm font-medium text-slate-900">
                                            {session.title}
                                            {session.isLive && (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700">
                                                    <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-500" />
                                                    Live
                                                </span>
                                            )}
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {session.classroom} · {session.whenLabel}
                                        </p>
                                    </div>

                                    {session.isJoinable ? (
                                        <a
                                            href={route('classrooms.sessions.join', session.id)}
                                            className="rounded-lg bg-emerald-600 px-5 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                                        >
                                            {session.mode === 'online' ? 'Join now' : "I'm here"}
                                        </a>
                                    ) : (
                                        <Link
                                            href={route('classrooms.show', session.classroomId)}
                                            className="text-xs font-medium text-blue-700"
                                        >
                                            Details
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="grid gap-5 sm:grid-cols-2">
                    {features.map((feature) => (
                        <article
                            key={feature.title}
                            className={`flex flex-col rounded-2xl border-2 p-6 ${feature.border} ${feature.surface}`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <span
                                    className={`flex h-14 w-14 items-center justify-center rounded-2xl ${feature.badge}`}
                                >
                                    <svg
                                        className="h-7 w-7"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth={1.6}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d={feature.icon} />
                                    </svg>
                                </span>

                                {feature.stat && (
                                    <span className="rounded-full bg-white/80 px-3 py-1 text-[11px] font-medium text-slate-600">
                                        {feature.stat}
                                    </span>
                                )}
                            </div>

                            <h2 className="mt-5 text-lg font-bold text-slate-900">{feature.title}</h2>

                            <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-600">
                                {feature.body}
                            </p>

                            <Link
                                href={feature.href}
                                className={`mt-5 inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition ${feature.button}`}
                            >
                                {feature.action}
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </Link>
                        </article>
                    ))}
                </div>

                {!canParticipate && (
                    <p className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                        Lessons and self-testing are open to you now. Asking a teacher, groups and the
                        community unlock once your parent or guardian approves your account.
                    </p>
                )}

                {/* Offline study, which is real, in place of the poster's
                    auto-saving video lessons, which are not */}
                <section className="rounded-2xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-wrap items-start gap-5">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.6} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
                            </svg>
                        </span>

                        <div className="min-w-0 flex-1">
                            <h2 className="font-semibold text-slate-900">Study without data</h2>
                            <p className="mt-1 text-sm leading-relaxed text-slate-600">
                                Lessons are text and audio rather than video, so they are light on data.
                                Open one on wifi and it stays available to read later, and your place in
                                it is saved as you go.
                            </p>
                        </div>
                    </div>

                    <ul className="mt-5 grid gap-3 border-t border-slate-100 pt-5 text-xs text-slate-600 sm:grid-cols-4">
                        {[
                            ['Safe and checked', 'M12 3l8 3v6c0 4.4-3.4 8.3-8 9-4.6-.7-8-4.6-8-9V6l8-3Z'],
                            ['Learn anywhere', 'M12 3 2 8l10 5 10-5-10-5ZM6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5'],
                            ['Track progress', 'M4 20V10M10 20V4M16 20v-7M22 20H2'],
                            ['Reach your goals', 'm12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z'],
                        ].map(([label, path]) => (
                            <li key={label} className="flex items-center gap-2">
                                <svg className="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.7} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                    <path d={path} />
                                </svg>
                                {label}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <BuiltBy className="pb-6" />
        </AuthenticatedLayout>
    );
}
