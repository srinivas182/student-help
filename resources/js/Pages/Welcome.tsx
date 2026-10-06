import { Head, Link } from '@inertiajs/react';

interface Props {
    portal: 'student' | 'teacher';
    name: string;
    tagline: string;
    otherPortal: { key: string; name: string; url: string };
}

const CONTENT = {
    student: {
        heading: 'Stuck on something? Ask a verified tutor.',
        points: [
            'Ask a question in your subject and get matched with a tutor who teaches it',
            'Study notes, past papers and solutions for your exact grade and subjects',
            'Safe by design: every tutor is verified and all conversations are monitored',
        ],
        cta: 'Create a student account',
        switchPrompt: 'Are you a teacher or tutor?',
    },
    teacher: {
        heading: 'Help the students who need you most.',
        points: [
            'Answer questions from students studying the subjects you teach',
            'Share notes, past papers, solutions and voice note lessons',
            'Your time is tracked and recognised, with ratings and a contribution record',
        ],
        cta: 'Apply to become a tutor',
        switchPrompt: 'Are you a student?',
    },
};

export default function Welcome({ portal, name, tagline, otherPortal }: Props) {
    const content = CONTENT[portal];
    const accent = portal === 'student' ? 'indigo' : 'teal';

    return (
        <div className="min-h-screen bg-slate-50">
            <Head title={name} />

            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
                    <span className="text-lg font-semibold text-slate-900">{name}</span>

                    <nav className="flex items-center gap-4 text-sm">
                        <Link href={route('login')} className="text-slate-600 hover:text-slate-900">
                            Sign in
                        </Link>
                        <Link
                            href={route('register')}
                            className={`rounded-lg px-4 py-2 font-medium text-white ${
                                accent === 'indigo' ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-teal-600 hover:bg-teal-500'
                            }`}
                        >
                            Get started
                        </Link>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-4 py-16 sm:px-6">
                <p
                    className={`text-sm font-semibold uppercase tracking-widest ${
                        accent === 'indigo' ? 'text-indigo-600' : 'text-teal-600'
                    }`}
                >
                    {tagline}
                </p>

                <h1 className="mt-4 max-w-2xl text-4xl font-semibold leading-tight text-slate-900 sm:text-5xl">
                    {content.heading}
                </h1>

                <ul className="mt-8 max-w-2xl space-y-3">
                    {content.points.map((point) => (
                        <li key={point} className="flex gap-3 text-slate-700">
                            <span
                                className={accent === 'indigo' ? 'text-indigo-600' : 'text-teal-600'}
                                aria-hidden
                            >
                                ✓
                            </span>
                            <span>{point}</span>
                        </li>
                    ))}
                </ul>

                <Link
                    href={route('register')}
                    className={`mt-10 inline-block rounded-lg px-7 py-3 text-sm font-semibold text-white ${
                        accent === 'indigo' ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-teal-600 hover:bg-teal-500'
                    }`}
                >
                    {content.cta}
                </Link>

                {/* The other front door — nobody should get stuck on the wrong site */}
                <div className="mt-16 rounded-xl border border-slate-200 bg-white p-6">
                    <p className="text-sm font-medium text-slate-900">{content.switchPrompt}</p>
                    <p className="mt-1 text-sm text-slate-600">
                        {otherPortal.name} is where you belong. Same platform, your own space.
                    </p>
                    <a
                        href={otherPortal.url}
                        className="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                    >
                        Go to {otherPortal.name} →
                    </a>
                </div>
            </main>

            <footer className="border-t border-slate-200 py-8 text-center text-xs text-slate-400">
                {name} · a safe space for South African learners
            </footer>
        </div>
    );
}
