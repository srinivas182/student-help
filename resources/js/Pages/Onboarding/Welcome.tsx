import BuiltBy from '@/Components/BuiltBy';
import { Head, Link, router } from '@inertiajs/react';

/**
 * Shown once, straight after onboarding.
 *
 * A learner used to finish the wizard and land on a dashboard with no idea
 * what the platform could do. This is the only moment they are guaranteed to
 * be paying attention, so it is the right place to say it.
 */

interface Feature {
    title: string;
    body: string;
    action: string;
    href: string;
    tone: string;
    badge: string;
    icon: string;
}

export default function Welcome({
    firstName,
    subjects,
    canParticipate,
    topicCount,
}: {
    firstName: string;
    subjects: string[];
    canParticipate: boolean;
    topicCount: number;
}) {
    const features: Feature[] = [
        {
            title: 'Ask a real tutor',
            body: 'Stuck on something? A verified tutor answers, usually within a few hours. It is free, and private between the two of you.',
            action: 'Ask a question',
            href: route('requests.create'),
            tone: 'border-indigo-200 bg-indigo-50/50',
            badge: 'bg-indigo-100 text-indigo-600',
            icon: 'M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12Z',
        },
        {
            title: 'Lessons in your language',
            body: topicCount > 0
                ? `${topicCount} lesson${topicCount === 1 ? '' : 's'} ready for your subjects, in 11 South African languages. Formulas stay in English, because your exam is.`
                : 'Lessons in 11 South African languages, with notes and flashcards. Formulas stay in English, because your exam is.',
            action: 'Start learning',
            href: route('learn.index'),
            tone: 'border-emerald-200 bg-emerald-50/50',
            badge: 'bg-emerald-100 text-emerald-600',
            icon: 'M12 6.5 4 4v14l8 2.5 8-2.5V4l-8 2.5Zm0 0V21',
        },
        {
            title: 'Test yourself',
            body: 'Five levels per topic, from basic to extremely difficult. Get one wrong and we explain the actual mistake, not just that it was wrong.',
            action: 'See how it works',
            href: route('learn.index'),
            tone: 'border-amber-200 bg-amber-50/50',
            badge: 'bg-amber-100 text-amber-600',
            icon: 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
        },
        {
            title: 'Study with others',
            body: 'Join a class your teacher set up, start a study group with classmates, or answer questions on the community boards.',
            action: 'Find your people',
            href: route('community.index'),
            tone: 'border-violet-200 bg-violet-50/50',
            badge: 'bg-violet-100 text-violet-600',
            icon: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm13 12v-1a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        },
    ];

    return (
        <div className="min-h-screen bg-slate-50 py-10">
            <Head title="Welcome to DX Student Help" />

            <div className="mx-auto max-w-4xl px-4">
                <header className="text-center">
                    <span className="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-indigo-700">
                        You are all set
                    </span>

                    <h1 className="mt-5 text-3xl font-semibold text-slate-900 sm:text-4xl">
                        Welcome, {firstName}
                    </h1>

                    <p className="mx-auto mt-3 max-w-xl text-slate-600">
                        {subjects.length > 0 ? (
                            <>
                                We have set you up for{' '}
                                <span className="font-medium text-slate-900">
                                    {subjects.slice(0, 3).join(', ')}
                                    {subjects.length > 3 && ` and ${subjects.length - 3} more`}
                                </span>
                                . Here is what you can do.
                            </>
                        ) : (
                            <>Here is what you can do.</>
                        )}
                    </p>
                </header>

                {!canParticipate && (
                    <div className="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <p className="font-medium text-amber-900">
                            We have emailed your parent or guardian
                        </p>
                        <p className="mt-1 text-sm text-amber-800">
                            Lessons, notes and self-testing are open to you right now. Messaging a tutor
                            and posting in the community unlock as soon as they approve your account.
                        </p>
                    </div>
                )}

                <div className="mt-8 grid gap-4 sm:grid-cols-2">
                    {features.map((feature) => (
                        <article
                            key={feature.title}
                            className={`flex flex-col rounded-2xl border-2 p-6 ${feature.tone}`}
                        >
                            <span
                                className={`flex h-12 w-12 items-center justify-center rounded-xl ${feature.badge}`}
                            >
                                <svg
                                    className="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    strokeWidth={1.7}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d={feature.icon} />
                                </svg>
                            </span>

                            <h2 className="mt-4 font-semibold text-slate-900">{feature.title}</h2>
                            <p className="mt-1.5 flex-1 text-sm leading-relaxed text-slate-600">
                                {feature.body}
                            </p>

                            <Link
                                href={feature.href}
                                className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-900 hover:gap-2.5"
                            >
                                {feature.action}
                                <span aria-hidden="true">→</span>
                            </Link>
                        </article>
                    ))}
                </div>

                <div className="mt-8 rounded-xl border border-slate-200 bg-white p-5">
                    <p className="text-sm font-medium text-slate-900">One thing worth knowing</p>
                    <p className="mt-1 text-sm text-slate-600">
                        Every tutor is checked — identity, qualifications and police clearance — before
                        they can message you. Conversations stay on the platform and are monitored, and
                        you can report anything with one tap.
                    </p>
                </div>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <button
                        onClick={() => router.visit(route('dashboard'))}
                        className="rounded-lg bg-indigo-600 px-8 py-3 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Take me to my dashboard
                    </button>

                    <Link
                        href={route('explore')}
                        className="rounded-lg border border-slate-300 bg-white px-8 py-3 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Show me everything first
                    </Link>
                </div>
            </div>

            <BuiltBy className="mt-10 pb-6" />
        </div>
    );
}
