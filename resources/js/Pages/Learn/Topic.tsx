import Markdown from '@/Components/Markdown';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface Segment {
    title?: string;
    narration?: string;
    visual?: string;
    check?: { question?: string; answer?: string };
}

interface Props {
    topic: { id: number; title: string; subject: string | null; summary: string | null; objectives: string[] };
    version: {
        id: number;
        segments: Segment[];
        notes: string | null;
        flashcards: { front: string; back: string }[];
        language: { code: string | null; name: string | null; ttsSupported: boolean };
        provenance: {
            aiGenerated: boolean;
            reviewer: string | null;
            reviewerQualification: string | null;
            reviewedAt: string | null;
        };
    };
    availableLanguages: { code: string | null; name: string | null; ttsSupported: boolean }[];
    requestedLanguageMissing: boolean;
    requestedLanguage: string | null;
    progress: { lastSegment: number; segmentsDone: number[]; completed: boolean };
}

export default function Topic({
    topic,
    version,
    availableLanguages,
    requestedLanguageMissing,
    requestedLanguage,
    progress,
}: Props) {
    const [current, setCurrent] = useState(progress.lastSegment ?? 0);
    const [done, setDone] = useState<number[]>(progress.segmentsDone ?? []);
    const [revealed, setRevealed] = useState(false);
    const [speaking, setSpeaking] = useState(false);
    const [tab, setTab] = useState<'lesson' | 'notes' | 'cards'>('lesson');
    const [savedOffline, setSavedOffline] = useState(false);
    const [cardIndex, setCardIndex] = useState(0);
    const [cardFlipped, setCardFlipped] = useState(false);

    const started = useRef(Date.now());
    const segments = version.segments;
    const segment = segments[current];
    const total = segments.length;

    const percent = useMemo(
        () => (total === 0 ? 0 : Math.round((done.length / total) * 100)),
        [done.length, total],
    );

    // Narration uses the device's own voice: no data cost, works offline.
    const speak = () => {
        if (!('speechSynthesis' in window) || !segment?.narration) return;

        window.speechSynthesis.cancel();

        const utterance = new SpeechSynthesisUtterance(segment.narration);
        utterance.lang = version.language.code === 'en' ? 'en-ZA' : (version.language.code ?? 'en');
        utterance.rate = 0.95;
        utterance.onend = () => setSpeaking(false);

        setSpeaking(true);
        window.speechSynthesis.speak(utterance);
    };

    const stopSpeaking = () => {
        window.speechSynthesis?.cancel();
        setSpeaking(false);
    };

    useEffect(() => () => window.speechSynthesis?.cancel(), []);

    useEffect(() => {
        setRevealed(false);
        stopSpeaking();
    }, [current]);

    const markDone = async (index: number) => {
        const updated = Array.from(new Set([...done, index]));
        setDone(updated);

        try {
            await window.axios.post(route('learn.progress', topic.id), {
                segment: index,
                total_segments: total,
                seconds: Math.round((Date.now() - started.current) / 1000),
            });
            started.current = Date.now();
        } catch {
            // Progress saves again on the next segment.
        }
    };

    const next = () => {
        markDone(current);
        if (current < total - 1) setCurrent(current + 1);
    };

    const saveOffline = () => {
        try {
            localStorage.setItem(
                `dx.lesson.${topic.id}.${version.language.code}`,
                JSON.stringify({ topic, version, savedAt: Date.now() }),
            );
            setSavedOffline(true);
        } catch {
            setSavedOffline(false);
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">{topic.title}</h2>}>
            <Head title={topic.title} />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6">
                <Link href={route('learn.index')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← All lessons
                </Link>

                {requestedLanguageMissing && (
                    <p className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        This lesson is not available in {requestedLanguage} yet, so you are seeing the English
                        version.
                    </p>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                        {availableLanguages.map((language) => (
                            <button
                                key={language.code ?? ''}
                                onClick={() =>
                                    router.get(route('learn.topic', topic.id), { lang: language.code })
                                }
                                className={`rounded-full px-3 py-1 text-xs transition ${
                                    language.code === version.language.code
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }`}
                            >
                                {language.name}
                            </button>
                        ))}
                    </div>

                    <button
                        onClick={saveOffline}
                        className="rounded-lg border border-slate-300 px-4 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    >
                        {savedOffline ? 'Saved for offline ✓' : 'Save for offline'}
                    </button>
                </div>

                <div className="flex gap-2">
                    {[
                        ['lesson', 'Lesson'],
                        ['notes', 'Revision notes'],
                        ['cards', `Flashcards (${version.flashcards.length})`],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            onClick={() => setTab(value as typeof tab)}
                            className={`rounded-lg px-4 py-2 text-sm transition ${
                                tab === value
                                    ? 'bg-white font-medium text-slate-900 shadow-sm ring-1 ring-slate-200'
                                    : 'text-slate-600 hover:bg-white/60'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'lesson' && segment && (
                    <>
                        <div className="h-1.5 overflow-hidden rounded-full bg-slate-200">
                            <div
                                className="h-full rounded-full bg-indigo-500 transition-all"
                                style={{ width: `${percent}%` }}
                            />
                        </div>

                        <article className="rounded-xl border border-slate-200 bg-white p-6">
                            <p className="text-xs uppercase tracking-wide text-slate-400">
                                Part {current + 1} of {total}
                            </p>
                            <h2 className="mt-1 text-lg font-semibold text-slate-900">{segment.title}</h2>

                            {segment.visual && (
                                <div className="mt-4 rounded-xl bg-gradient-to-br from-indigo-50 to-violet-50 p-5">
                                    <p className="text-xs font-medium uppercase tracking-wide text-indigo-700">
                                        What to picture
                                    </p>
                                    <p className="mt-1 text-sm text-indigo-900">{segment.visual}</p>
                                </div>
                            )}

                            <p className="mt-4 whitespace-pre-line text-base leading-relaxed text-slate-700">
                                {segment.narration}
                            </p>

                            {version.language.ttsSupported && 'speechSynthesis' in window && (
                                <button
                                    onClick={speaking ? stopSpeaking : speak}
                                    className="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    {speaking ? '■ Stop' : '▶ Listen to this part'}
                                </button>
                            )}

                            {segment.check?.question && (
                                <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                                    <p className="text-sm font-medium text-amber-900">
                                        Pause and think: {segment.check.question}
                                    </p>
                                    {revealed ? (
                                        <p className="mt-2 text-sm text-amber-800">{segment.check.answer}</p>
                                    ) : (
                                        <button
                                            onClick={() => setRevealed(true)}
                                            className="mt-2 text-xs font-semibold text-amber-900 underline"
                                        >
                                            Show the answer
                                        </button>
                                    )}
                                </div>
                            )}

                            <div className="mt-6 flex items-center justify-between">
                                <button
                                    onClick={() => setCurrent(Math.max(0, current - 1))}
                                    disabled={current === 0}
                                    className="rounded-lg border border-slate-300 px-5 py-2 text-sm text-slate-700 disabled:opacity-40"
                                >
                                    Back
                                </button>

                                {current < total - 1 ? (
                                    <button
                                        onClick={next}
                                        className="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                    >
                                        Next part
                                    </button>
                                ) : (
                                    <button
                                        onClick={() => {
                                            markDone(current);
                                            router.visit(route('assessment.index', topic.id));
                                        }}
                                        className="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                                    >
                                        Finish and test yourself
                                    </button>
                                )}
                            </div>
                        </article>

                        <div className="flex flex-wrap gap-1.5">
                            {segments.map((item, index) => (
                                <button
                                    key={index}
                                    onClick={() => setCurrent(index)}
                                    title={item.title}
                                    className={`h-2 flex-1 rounded-full transition ${
                                        done.includes(index)
                                            ? 'bg-emerald-500'
                                            : index === current
                                              ? 'bg-indigo-500'
                                              : 'bg-slate-200 hover:bg-slate-300'
                                    }`}
                                />
                            ))}
                        </div>
                    </>
                )}

                {tab === 'notes' && (
                    <article className="rounded-xl border border-slate-200 bg-white p-6">
                        {version.notes ? (
                            <Markdown source={version.notes} />
                        ) : (
                            <p className="text-sm text-slate-500">
                                No revision notes for this lesson yet.
                            </p>
                        )}
                    </article>
                )}

                {tab === 'cards' && version.flashcards.length > 0 && (
                    <div className="space-y-3">
                        <button
                            onClick={() => setCardFlipped(!cardFlipped)}
                            className="flex min-h-48 w-full items-center justify-center rounded-xl border border-slate-200 bg-white p-8 text-center"
                        >
                            <p className="text-lg text-slate-800">
                                {cardFlipped
                                    ? version.flashcards[cardIndex].back
                                    : version.flashcards[cardIndex].front}
                            </p>
                        </button>

                        <div className="flex items-center justify-between">
                            <button
                                onClick={() => {
                                    setCardIndex(Math.max(0, cardIndex - 1));
                                    setCardFlipped(false);
                                }}
                                disabled={cardIndex === 0}
                                className="rounded-lg border border-slate-300 px-5 py-2 text-sm disabled:opacity-40"
                            >
                                Back
                            </button>
                            <p className="text-xs text-slate-500">
                                {cardIndex + 1} of {version.flashcards.length} · tap the card to flip
                            </p>
                            <button
                                onClick={() => {
                                    setCardIndex(Math.min(version.flashcards.length - 1, cardIndex + 1));
                                    setCardFlipped(false);
                                }}
                                disabled={cardIndex === version.flashcards.length - 1}
                                className="rounded-lg border border-slate-300 px-5 py-2 text-sm disabled:opacity-40"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}

                <p className="rounded-xl bg-slate-50 p-4 text-center text-xs text-slate-500">
                    {version.provenance.aiGenerated && 'This lesson was written with AI'}
                    {version.provenance.reviewer && (
                        <>
                            {' '}
                            and checked by{' '}
                            <span className="font-medium text-slate-700">{version.provenance.reviewer}</span>
                            {version.provenance.reviewerQualification &&
                                `, ${version.provenance.reviewerQualification}`}
                            {version.provenance.reviewedAt && ` on ${version.provenance.reviewedAt}`}
                        </>
                    )}
                    . Something wrong? Ask a tutor and we will fix it.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
