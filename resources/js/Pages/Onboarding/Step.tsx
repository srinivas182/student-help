import BuiltBy from '@/Components/BuiltBy';
import OptionIcon from '@/Components/Onboarding/OptionIcon';
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

/** Card surface, icon badge and arrow, kept together so they always agree. */
const PALETTE = [
    { card: 'border-sky-200 bg-sky-50/60 hover:border-sky-300', badge: 'bg-sky-100 text-sky-600', arrow: 'bg-sky-500' },
    { card: 'border-emerald-200 bg-emerald-50/60 hover:border-emerald-300', badge: 'bg-emerald-100 text-emerald-600', arrow: 'bg-emerald-500' },
    { card: 'border-violet-200 bg-violet-50/60 hover:border-violet-300', badge: 'bg-violet-100 text-violet-600', arrow: 'bg-violet-500' },
    { card: 'border-amber-200 bg-amber-50/60 hover:border-amber-300', badge: 'bg-amber-100 text-amber-600', arrow: 'bg-amber-500' },
    { card: 'border-rose-200 bg-rose-50/60 hover:border-rose-300', badge: 'bg-rose-100 text-rose-600', arrow: 'bg-rose-500' },
    { card: 'border-teal-200 bg-teal-50/60 hover:border-teal-300', badge: 'bg-teal-100 text-teal-600', arrow: 'bg-teal-500' },
];

/** Roughly how many steps a learner faces, so the bar means something. */
const TYPICAL_STEPS = 5;

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

                {/* A learner who cannot see the end of a wizard assumes it is endless */}
                <div className="mb-6 h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div
                        className="h-full rounded-full bg-indigo-500 transition-all duration-500"
                        style={{ width: `${Math.min((step.step / TYPICAL_STEPS) * 100, 95)}%` }}
                    />
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

                <div
                    className={`grid gap-4 ${
                        step.options.length <= 2
                            ? 'sm:grid-cols-2'
                            : step.options.length === 3
                              ? 'sm:grid-cols-3'
                              : 'sm:grid-cols-2 lg:grid-cols-3'
                    }`}
                >
                    {step.options.map((option, index) => {
                        const tone = PALETTE[index % PALETTE.length];

                        return (
                            <button
                                key={option.id}
                                type="button"
                                disabled={processing}
                                onClick={() => choose(option.id)}
                                className={`group flex flex-col items-center gap-3 rounded-2xl border-2 bg-white p-6 text-center transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 disabled:opacity-60 ${tone.card}`}
                            >
                                <span
                                    className={`flex h-16 w-16 items-center justify-center rounded-full ${tone.badge}`}
                                >
                                    <OptionIcon name={option.name} className="h-8 w-8" />
                                </span>

                                <span className="text-base font-semibold text-slate-900">
                                    {option.name}
                                </span>

                                {option.description && (
                                    <span className="text-sm leading-snug text-slate-600">
                                        {option.description}
                                    </span>
                                )}

                                <span
                                    className={`mt-auto inline-flex h-9 w-9 items-center justify-center rounded-full text-white shadow-sm transition group-hover:translate-x-0.5 ${tone.arrow}`}
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden="true">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                                    </svg>
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="mt-8 flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    <svg className="mt-0.5 h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8} aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path strokeLinecap="round" d="M12 11v5M12 8h.01" />
                    </svg>
                    <p className="text-sm text-slate-600">
                        You can always change this later in your profile settings.
                    </p>
                </div>
            </div>
            <BuiltBy className="mt-8 pb-6" />
        </div>
    );
}
