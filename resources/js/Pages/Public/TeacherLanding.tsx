import PublicLayout, { PortalBrand } from '@/Components/Public/PublicLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const STEPS = [
    ['Apply in ten minutes', 'Tell us what you teach and upload your ID, qualification and police clearance.'],
    ['We verify you', 'Our team checks everything, usually within 48 hours. You will hear either way.'],
    ['Answer what suits you', 'Questions arrive in your subjects. Accept the ones you have time for.'],
    ['Build your record', 'Students rate your help. Your contribution certificate is yours to keep.'],
];

const REASONS = [
    ['Teach on your own time', 'No timetable, no minimum hours. Accept a question when you have twenty minutes free.'],
    ['Reach learners who need it', 'Many students have nobody at home who can help with Grade 12 Maths.'],
    ['A record you can use', 'A printable certificate showing students helped, questions answered and your rating — useful on a CV or bursary application.'],
    ['Share your material once', 'Upload notes, past papers and voice notes. One upload reaches every student in that subject.'],
    ['Host your own class', 'Create a class, invite your learners, set work and track who has done it.'],
    ['You are protected too', 'Conversations stay on the platform and are monitored. That protects you as much as the learner.'],
];

const FAQ = [
    ['Do I get paid?', 'Not at launch. Tutoring on DX is voluntary. You receive recognition, a public rating and a contribution certificate. Paid opportunities may follow as the platform grows.'],
    ['How much time does it take?', 'Entirely up to you. Some tutors answer one question a week, others several a day. You accept only what you choose.'],
    ['What do I need to apply?', 'Your ID, your highest qualification certificate, and a police clearance certificate. We check all three because you will be working with learners under 18.'],
    ['Who can become a tutor?', 'University students, teachers, graduates and working professionals. What matters is that you know the subject and can explain it patiently.'],
    ['Can I teach in isiZulu or Afrikaans?', 'Yes. Many learners understand better in their home language, and we need tutors who can do that.'],
    ['What are the rules?', 'Help students understand the method. Never complete an assessment for them, keep conversations on the platform, and treat every learner with respect.'],
];

export default function TeacherLanding({
    brand,
    stats,
}: {
    brand: PortalBrand;
    stats: { students: number; openRequests: number; subjects: number };
}) {
    const [openFaq, setOpenFaq] = useState<number | null>(0);

    return (
        <PublicLayout brand={brand}>
            <Head title={`${brand.name} — volunteer as a verified tutor`} />

            <section className="bg-gradient-to-b from-teal-50 to-white">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
                    <div className="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-widest text-teal-700">
                                For teachers, graduates and students who can teach
                            </p>
                            <h1 className="mt-4 text-4xl font-semibold leading-tight text-slate-900 sm:text-5xl">
                                Twenty minutes of your time changes a learner's week.
                            </h1>
                            <p className="mt-5 text-lg leading-relaxed text-slate-600">
                                Answer questions in the subjects you know, when it suits you. Share your notes
                                once and reach every student studying that subject.
                            </p>

                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={route('register')}
                                    className="rounded-lg bg-teal-600 px-7 py-3.5 text-center text-sm font-semibold text-white transition hover:bg-teal-500"
                                >
                                    Apply to tutor
                                </Link>
                                <a
                                    href="#how"
                                    className="rounded-lg border border-slate-300 px-7 py-3.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                >
                                    What is involved
                                </a>
                            </div>

                            <dl className="mt-10 grid grid-cols-3 gap-4 border-t border-slate-200 pt-6">
                                {[
                                    ['Learners registered', stats.students],
                                    ['Questions waiting', stats.openRequests],
                                    ['Subjects needing tutors', stats.subjects],
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
                            <p className="text-xs uppercase tracking-wide text-slate-500">
                                Waiting in your subjects
                            </p>
                            <ul className="mt-4 space-y-3">
                                {[
                                    ['Grade 11 Mathematics', 'Factorising when a is not 1', '18 min'],
                                    ['Physical Sciences', 'Balancing redox equations', '41 min'],
                                    ['Grade 12 Accounting', 'Bank reconciliation', '2 hrs'],
                                ].map(([subject, topic, waiting]) => (
                                    <li
                                        key={topic}
                                        className="flex items-center justify-between rounded-lg border border-slate-200 p-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-slate-900">{topic}</p>
                                            <p className="text-xs text-slate-500">{subject}</p>
                                        </div>
                                        <span className="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white">
                                            Accept
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-4 text-xs text-slate-500">
                                Accept only what you have time for. Nothing is assigned to you.
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
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-teal-100 text-sm font-semibold text-teal-700">
                                {index + 1}
                            </span>
                            <h3 className="mt-4 font-semibold text-slate-900">{title}</h3>
                            <p className="mt-1.5 text-sm leading-relaxed text-slate-600">{body}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section id="why" className="bg-slate-50 py-20">
                <div className="mx-auto max-w-6xl px-4 sm:px-6">
                    <h2 className="text-3xl font-semibold text-slate-900">Why tutors stay</h2>
                    <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {REASONS.map(([title, body]) => (
                            <div key={title} className="rounded-xl border border-slate-200 bg-white p-6">
                                <h3 className="font-semibold text-slate-900">{title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section id="verify" className="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <div className="grid gap-10 lg:grid-cols-2">
                    <div>
                        <h2 className="text-3xl font-semibold text-slate-900">Getting verified</h2>
                        <p className="mt-4 text-slate-600">
                            You will be talking to learners as young as 13, so we check every tutor properly.
                            It takes about ten minutes to apply and we usually review within 48 hours.
                        </p>
                        <p className="mt-4 text-sm text-slate-500">
                            Your documents are stored privately, seen only by DX administrators, and every time
                            one is opened it is recorded.
                        </p>
                    </div>

                    <ol className="space-y-4">
                        {[
                            ['Identity document', 'Your ID book, card or passport.'],
                            ['Highest qualification', 'Degree, diploma or teaching certificate.'],
                            ['Police clearance', 'A SAPS clearance certificate. Required because you will work with minors.'],
                            ['Subjects you teach', 'Choose from the curriculum. You only receive questions in these.'],
                        ].map(([title, body], index) => (
                            <li key={title} className="flex gap-4 rounded-xl border border-slate-200 bg-white p-4">
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                    {index + 1}
                                </span>
                                <div>
                                    <p className="font-medium text-slate-900">{title}</p>
                                    <p className="mt-0.5 text-sm text-slate-600">{body}</p>
                                </div>
                            </li>
                        ))}
                    </ol>
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

            <section className="bg-teal-700 py-16">
                <div className="mx-auto max-w-3xl px-4 text-center sm:px-6">
                    <h2 className="text-3xl font-semibold text-white">
                        Somebody is stuck on your subject right now
                    </h2>
                    <p className="mt-3 text-teal-100">
                        Apply tonight. Most applications are reviewed within 48 hours.
                    </p>
                    <Link
                        href={route('register')}
                        className="mt-7 inline-block rounded-lg bg-white px-8 py-3.5 text-sm font-semibold text-teal-800 transition hover:bg-teal-50"
                    >
                        Apply to become a tutor
                    </Link>
                </div>
            </section>
        </PublicLayout>
    );
}
