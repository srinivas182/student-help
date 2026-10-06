import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Option {
    id: number;
    name: string;
    description: string | null;
    icon: string | null;
}

interface StepData {
    type: string;
    title: string;
    subtitle: string;
    options: Option[];
    breadcrumb: string[];
    step: number;
}

const PALETTE = [
    'from-sky-50 to-sky-100 border-sky-200 text-sky-700',
    'from-emerald-50 to-emerald-100 border-emerald-200 text-emerald-700',
    'from-violet-50 to-violet-100 border-violet-200 text-violet-700',
    'from-amber-50 to-amber-100 border-amber-200 text-amber-700',
    'from-rose-50 to-rose-100 border-rose-200 text-rose-700',
    'from-teal-50 to-teal-100 border-teal-200 text-teal-700',
];

export default function Step({ step, canGoBack }: { step: StepData; canGoBack: boolean }) {
    const { setData, post, processing } = useForm<{ curriculum_item_id: number | null }>({
        curriculum_item_id: null,
    });

    const choose = (id: number) => {
        setData('curriculum_item_id', id);
        router.post(route('onboarding.store'), { curriculum_item_id: id }, { preserveScroll: true });
    };

    const goBack: FormEventHandler = (e) => {
        e.preventDefault();
        router.post(route('onboarding.back'));
    };

    return (
        <div className="min-h-screen bg-slate-50 py-10">
            <Head title={step.title} />

            <div className="mx-auto max-w-4xl px-4">
                <div className="mb-6 flex items-center justify-between">
                    {canGoBack ? (
                        <button
                            onClick={goBack}
                            className="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-100"
                        >
                            ← Back
                        </button>
                    ) : (
                        <span />
                    )}
                    <span className="text-xs font-medium uppercase tracking-widest text-slate-400">
                        Step {step.step}
                    </span>
                </div>

                {step.breadcrumb.length > 0 && (
                    <nav className="mb-6 flex flex-wrap gap-2" aria-label="Your selections so far">
                        {step.breadcrumb.map((crumb) => (
                            <span
                                key={crumb}
                                className="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-500 shadow-sm ring-1 ring-slate-200"
                            >
                                {crumb}
                            </span>
                        ))}
                    </nav>
                )}

                <header className="mb-8 text-center">
                    <h1 className="text-2xl font-semibold text-slate-900 sm:text-3xl">{step.title}</h1>
                    <p className="mt-2 text-sm text-slate-500">{step.subtitle}</p>
                </header>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {step.options.map((option, index) => (
                        <button
                            key={option.id}
                            type="button"
                            disabled={processing}
                            onClick={() => choose(option.id)}
                            className={`group flex flex-col items-start gap-3 rounded-2xl border bg-gradient-to-br p-5 text-left transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 ${
                                PALETTE[index % PALETTE.length]
                            }`}
                        >
                            <span className="text-base font-semibold text-slate-900">{option.name}</span>
                            {option.description && (
                                <span className="text-sm text-slate-600">{option.description}</span>
                            )}
                            <span className="mt-auto inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-sm shadow-sm transition group-hover:translate-x-1">
                                →
                            </span>
                        </button>
                    ))}
                </div>

                <p className="mt-8 text-center text-xs text-slate-400">
                    You can always change this later in your profile settings.
                </p>
            </div>
        </div>
    );
}
