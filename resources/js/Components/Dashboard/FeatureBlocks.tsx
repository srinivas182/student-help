import { Link } from '@inertiajs/react';

/**
 * The four things the platform does, from the client's feature poster.
 *
 * Shared between the dashboard and the hub so the two cannot drift apart, and
 * written in the app's light palette rather than the poster's dark one.
 *
 * The copy describes what exists. The poster promises video calling inside
 * study groups, which is not built; claiming it here would be the first thing
 * a learner found to be untrue.
 */

export interface FeatureCounts {
    topics: number;
    tutors: number;
    groups: number;
    sessions: number;
}

export default function FeatureBlocks({
    counts,
    canParticipate = true,
}: {
    counts: FeatureCounts;
    canParticipate?: boolean;
}) {
    const features = [
        {
            title: 'Live classes',
            body: 'Join sessions your teacher schedules, online or in person. Miss one and the recording is there afterwards.',
            action: 'See what is on',
            href: route('classrooms.index'),
            badge: 'bg-blue-100 text-blue-600',
            border: 'border-blue-200',
            surface: 'bg-blue-50/50',
            button: 'bg-blue-600 hover:bg-blue-500',
            icon: 'M23 7l-7 5 7 5V7ZM14 5H3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z',
            stat: counts.sessions > 0 ? `${counts.sessions} coming up` : null,
            needsConsent: false,
        },
        {
            title: 'Real teachers answer',
            body: 'Stuck on something? Verified tutors answer your questions, usually within a few hours. Free, and private.',
            action: 'Ask a teacher',
            href: route('requests.create'),
            badge: 'bg-emerald-100 text-emerald-600',
            border: 'border-emerald-200',
            surface: 'bg-emerald-50/50',
            button: 'bg-emerald-600 hover:bg-emerald-500',
            icon: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm14 18v-2a4 4 0 0 0-3-3.9M17 3.1a4 4 0 0 1 0 7.8',
            stat: counts.tutors > 0 ? `${counts.tutors} verified tutors` : null,
            needsConsent: true,
        },
        {
            title: 'Your own study group',
            body: 'Start a group with classmates or join one in your subject. Work through problems with messages and voice notes.',
            action: 'Create a group',
            href: route('studyGroups.index'),
            badge: 'bg-violet-100 text-violet-600',
            border: 'border-violet-200',
            surface: 'bg-violet-50/50',
            button: 'bg-violet-600 hover:bg-violet-500',
            icon: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm13 12v-1a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
            stat: counts.groups > 0 ? `${counts.groups} in your subjects` : null,
            needsConsent: true,
        },
        {
            title: 'AI Tutor, instantly',
            body: 'Lessons in your own language with notes and flashcards, then test yourself at five levels.',
            action: 'Start learning',
            href: route('learn.index'),
            badge: 'bg-teal-100 text-teal-600',
            border: 'border-teal-200',
            surface: 'bg-teal-50/50',
            button: 'bg-teal-600 hover:bg-teal-500',
            icon: 'M12 8V4H8M4 8h16v12H4V8Zm4 4h.01M16 12h.01M9 16h6',
            stat: counts.topics > 0 ? `${counts.topics} lessons ready` : null,
            needsConsent: false,
        },
    ];

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            {features.map((feature) => {
                const locked = feature.needsConsent && ! canParticipate;

                return (
                    <article
                        key={feature.title}
                        className={`flex flex-col rounded-2xl border-2 p-6 ${feature.border} ${feature.surface} ${
                            locked ? 'opacity-70' : ''
                        }`}
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

                            {feature.stat && !locked && (
                                <span className="rounded-full bg-white/80 px-3 py-1 text-[11px] font-medium text-slate-600">
                                    {feature.stat}
                                </span>
                            )}
                        </div>

                        <h3 className="mt-5 text-lg font-bold text-slate-900">{feature.title}</h3>

                        <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-600">
                            {feature.body}
                        </p>

                        {locked ? (
                            <p className="mt-5 rounded-lg bg-white/70 px-4 py-2.5 text-xs text-slate-600">
                                Unlocks once your parent or guardian approves your account.
                            </p>
                        ) : (
                            <Link
                                href={feature.href}
                                className={`mt-5 inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition ${feature.button}`}
                            >
                                {feature.action}
                                <svg
                                    className="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    strokeWidth={2.5}
                                    aria-hidden="true"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </Link>
                        )}
                    </article>
                );
            })}
        </div>
    );
}
