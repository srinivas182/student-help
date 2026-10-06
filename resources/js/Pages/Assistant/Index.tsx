import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Answer {
    id: number;
    question: string;
    answer: string;
    refused: boolean;
    helpful: number | null;
    askedAt: string | null;
}

interface Quota {
    enabled: boolean;
    mode: string;
    canAskDirectly: boolean;
    limit: number;
    used: number;
    remaining: number;
    usedToday: number;
    dailyLimit: number;
    unlimited: boolean;
    resetsOn: string;
    allowed: boolean;
    message: string | null;
}

export default function Index({ quota, history }: { quota: Quota; history: Answer[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ question: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('assistant.ask'), { onSuccess: () => reset('question') });
    };

    const lastOne = !quota.unlimited && quota.remaining === 1;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Study assistant</h2>}>
            <Head title="Study assistant" />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                {!quota.enabled ? (
                    <div className="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center">
                        <p className="text-sm text-slate-600">
                            The study assistant is not switched on yet. Ask a verified tutor instead — it is
                            free and unlimited.
                        </p>
                        <Link
                            href={route('requests.create')}
                            className="mt-4 inline-block rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white"
                        >
                            Ask a tutor
                        </Link>
                    </div>
                ) : (
                    <>
                        <div
                            className={`rounded-xl border p-4 text-sm ${
                                !quota.allowed
                                    ? 'border-amber-200 bg-amber-50 text-amber-900'
                                    : lastOne
                                      ? 'border-amber-200 bg-amber-50 text-amber-900'
                                      : 'border-slate-200 bg-white text-slate-600'
                            }`}
                        >
                            {quota.unlimited ? (
                                <p>You have unlimited instant answers.</p>
                            ) : !quota.allowed ? (
                                <p className="font-medium">{quota.message}</p>
                            ) : lastOne ? (
                                <p className="font-medium">
                                    This is your last instant answer this month. It resets on {quota.resetsOn}.
                                </p>
                            ) : (
                                <p>
                                    <span className="font-medium text-slate-900">
                                        {quota.remaining} of {quota.limit}
                                    </span>{' '}
                                    instant answers left this month · {quota.usedToday} of {quota.dailyLimit}{' '}
                                    used today · resets {quota.resetsOn}
                                </p>
                            )}
                        </div>

                        <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-6">
                            <label className="text-sm font-medium text-slate-900">
                                What are you stuck on?
                            </label>
                            <p className="mt-1 text-xs text-slate-500">
                                The assistant explains the method so you can do it yourself. It will not
                                complete homework or tests.
                            </p>
                            <textarea
                                rows={4}
                                value={data.question}
                                onChange={(e) => setData('question', e.target.value)}
                                disabled={!quota.allowed}
                                placeholder="e.g. How do I know when to use the sine rule instead of the cosine rule?"
                                className="mt-3 block w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-50"
                            />
                            {errors.question && <p className="mt-2 text-sm text-rose-600">{errors.question}</p>}

                            <div className="mt-4 flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    disabled={processing || !quota.allowed || data.question.trim().length < 10}
                                    className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                                >
                                    {processing ? 'Thinking…' : 'Get an instant answer'}
                                </button>
                                <Link
                                    href={route('requests.create')}
                                    className="rounded-lg border border-slate-300 px-6 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Ask a tutor instead
                                </Link>
                            </div>
                        </form>
                    </>
                )}

                {history.map((item) => (
                    <article key={item.id} className="rounded-xl border border-slate-200 bg-white p-5">
                        <p className="text-sm font-medium text-slate-900">{item.question}</p>
                        <p className="mt-1 text-xs text-slate-400">{item.askedAt}</p>

                        <div
                            className={`mt-3 rounded-lg p-4 text-sm leading-relaxed ${
                                item.refused ? 'bg-amber-50 text-amber-900' : 'bg-slate-50 text-slate-700'
                            }`}
                        >
                            <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                AI study assistant
                            </p>
                            <p className="whitespace-pre-line">{item.answer}</p>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-3 text-xs">
                            {item.helpful === null && !item.refused && (
                                <>
                                    <span className="text-slate-500">Did this help?</span>
                                    {[
                                        [true, 'Yes'],
                                        [false, 'Not really'],
                                    ].map(([value, label]) => (
                                        <button
                                            key={String(value)}
                                            onClick={() =>
                                                router.post(
                                                    route('assistant.feedback', item.id),
                                                    { helpful: value },
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-600 hover:bg-slate-50"
                                        >
                                            {label as string}
                                        </button>
                                    ))}
                                </>
                            )}

                            <button
                                onClick={() => router.post(route('assistant.escalate', item.id))}
                                className="font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                I still need a person →
                            </button>
                        </div>
                    </article>
                ))}

                <p className="text-center text-[11px] text-slate-400">
                    The study assistant is an AI, not a person. For anything personal, or if something is
                    worrying you, speak to a tutor or an adult you trust.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
