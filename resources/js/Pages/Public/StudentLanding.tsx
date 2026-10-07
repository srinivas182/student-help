import PublicLayout, { PortalBrand } from '@/Components/Public/PublicLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const STEPS = [
    ['Tell us what you study', 'Choose your grade or qualification and your subjects. Takes two minutes.'],
    ['Ask your question', 'Describe what you are stuck on. Add a photo of the question if that is easier.'],
    ['A verified tutor answers', 'We match you with someone who teaches that subject. You chat on the platform.'],
    ['Learn it properly', 'Work through lessons in your own language, revise with notes, then test yourself.'],
];

const FEATURES = [
    ['Ask a tutor', 'Real people who teach your subjects, verified by our team before they can help anyone.'],
    ['Lessons in your language', 'Topics explained step by step, with notes and flashcards. Formulas stay in English for the exam.'],
    ['Past papers and memos', 'Notes, past papers and worked solutions for your exact grade and subjects.'],
    ['Study groups', 'Start a group with classmates, or join one in your subject.'],
    ['Test yourself', 'Five levels per topic, from basic to extremely difficult, with explanations for every answer.'],
    ['Works on any phone', 'Save lessons for offline and revise without using data.'],
];

const FAQ = [
    ['Does it cost anything?', 'No. Asking tutors for help is free. Some extras may be paid later, but help from a tutor stays free.'],
    ['Who are the tutors?', 'University students, teachers and graduates. Every one is checked by our team — identity, qualifications and police clearance — before they can speak to a learner.'],
    ['I am under 18. Can I join?', 'Yes, with a parent or guardian\'s approval. We email them to explain what we do and ask permission, as South African law requires.'],
    ['What if nobody answers?', 'If no tutor picks up your question in a few hours, it is escalated to our team, and you can get an instant answer from our study assistant while you wait.'],
    ['Is my conversation private?', 'Conversations stay between you and your tutor, but our moderators can review any conversation. That is how we keep learners safe.'],
    ['Do I need data?', 'Very little. Lessons are text and audio, not video, and you can download them on wifi to use later.'],
];

export default function StudentLanding({ brand, stats }: { brand: PortalBrand; stats: { tutors: number; subjects: number; topics: number } }) {
    const [openFaq, setOpenFaq] = useState<number | null>(0);

    return (
        <PublicLayout brand={brand}>
            <Head title={`${brand.name} — free help with your schoolwork`} />

            <section className="bg-gradient-to-b from-indigo-50 to-white">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
                    <div className="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-widest text-indigo-600">
                                Free for South African learners
                            </p>
                            <h1 className="mt-4 text-4xl font-semibold leading-tight text-slate-900 sm:text-5xl">
                                Stuck on homework? Ask a real tutor.
                            </h1>
                            <p className="mt-5 text-lg leading-relaxed text-slate-600">
                                Get help in your subjects from verified tutors, learn topics in the language you
                                think in, and practise with past papers. Grade 8 to university.
                            </p>

                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={route('register')}
                                    className="rounded-lg bg-indigo-600 px-7 py-3.5 text-center text-sm font-semibold text-white transition hover:bg-indigo-500"
                                >
                                    Join free
                                </Link>
                                <a
                                    href="#how"
                                    className="rounded-lg border border-slate-300 px-7 py-3.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                >
                                    See how it works
                                </a>
                            </div>

                            <dl className="mt-10 grid grid-cols-3 gap-4 border-t border-slate-200 pt-6">
                                {[
                                    ['Verified tutors', stats.tutors],
                                    ['Subjects covered', stats.subjects],
                                    ['Lessons available', stats.topics],
                                ].map(([label, value]) => (
                                    <div key={label as string}>
                                        <dt className="text-xs uppercase tracking-wide text-slate-500">
                                            {label as string}
                                        </dt>
                                        <dd className="mt-1 text-2xl font-semibold text-slate-900">
                                            {value as number}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="space-y-3">
                                <div className="max-w-[85%] rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-3 text-sm text-slate-800">
                                    I don't understand why the sign flips when I divide by a negative number
                                </div>
                                <div className="ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-indigo-600 px-4 py-3 text-sm text-white">
                                    Good question. Picture a number line: 2 is less than 4. Multiply both by −1
                                    and you get −2 and −4 — the order mirrors. Shall we try one together?
                                </div>
                                <div className="max-w-[85%] rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-3 text-sm text-slate-800">
                                    Yes please 🙏
                                </div>
                            </div>
                            <p className="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                                Nomsa M., BSc Mathematics · verified tutor · usually replies within 2 hours
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="how" className="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <h2 className="text-3xl font-semibold text-slate-900">How it works</h2>
                <div className="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    {STEPS.map(([title, body], index) => (
                        <div key={title}>
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                                {index + 1}
                            </span>
                            <h3 className="mt-4 font-semibold text-slate-900">{title}</h3>
                            <p className="mt-1.5 text-sm leading-relaxed text-slate-600">{body}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section id="subjects" className="bg-slate-50 py-20">
                <div className="mx-auto max-w-6xl px-4 sm:px-6">
                    <h2 className="text-3xl font-semibold text-slate-900">Everything you get</h2>
                    <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map(([title, body]) => (
                            <div key={title} className="rounded-xl border border-slate-200 bg-white p-6">
                                <h3 className="font-semibold text-slate-900">{title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
                            </div>
                        ))}
                    </div>

                    <p className="mt-8 text-sm text-slate-600">
                        We cover CAPS Grades 8–12, NCV and NATED college programmes, and university
                        qualifications.
                    </p>
                </div>
            </section>

            <section id="safety" className="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <div className="grid gap-10 lg:grid-cols-2">
                    <div>
                        <h2 className="text-3xl font-semibold text-slate-900">Built to keep you safe</h2>
                        <p className="mt-4 text-slate-600">
                            Most of this platform is about learning. This part is about making sure young
                            people are protected while they do it.
                        </p>
                    </div>

                    <ul className="space-y-4">
                        {[
                            ['Every tutor is checked', 'Identity, qualifications and police clearance, reviewed by our team before they can message a learner.'],
                            ['Conversations stay here', 'Phone numbers, email addresses and links are removed from messages automatically.'],
                            ['Moderators can see everything', 'Any conversation can be reviewed. Anything involving a learner under 18 is treated as a priority.'],
                            ['Parents are asked first', 'If you are under 18, a parent or guardian approves your account before you can message anyone.'],
                            ['Report anything', 'One tap on any message. A moderator reviews it, usually within 24 hours.'],
                        ].map(([title, body]) => (
                            <li key={title} className="flex gap-3">
                                <span className="mt-0.5 text-emerald-600">✓</span>
                                <div>
                                    <p className="font-medium text-slate-900">{title}</p>
                                    <p className="mt-0.5 text-sm text-slate-600">{body}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            <section id="faq" className="bg-slate-50 py-20">
                <div className="mx-auto max-w-3xl px-4 sm:px-6">
                    <h2 className="text-3xl font-semibold text-slate-900">Common questions</h2>
                    <div className="mt-8 divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {FAQ.map(([question, answer], index) => (
                            <div key={question}>
                                <button
                                    onClick={() => setOpenFaq(openFaq === index ? null : index)}
                                    className="flex w-full items-center justify-between px-5 py-4 text-left"
                                >
                                    <span className="text-sm font-medium text-slate-900">{question}</span>
                                    <span className="text-slate-400">{openFaq === index ? '−' : '+'}</span>
                                </button>
                                {openFaq === index && (
                                    <p className="px-5 pb-4 text-sm leading-relaxed text-slate-600">{answer}</p>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="bg-indigo-600 py-16">
                <div className="mx-auto max-w-3xl px-4 text-center sm:px-6">
                    <h2 className="text-3xl font-semibold text-white">Your next question is free</h2>
                    <p className="mt-3 text-indigo-100">
                        Join in two minutes and ask a tutor tonight.
                    </p>
                    <Link
                        href={route('register')}
                        className="mt-7 inline-block rounded-lg bg-white px-8 py-3.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50"
                    >
                        Create my free account
                    </Link>
                </div>
            </section>
        </PublicLayout>
    );
}
