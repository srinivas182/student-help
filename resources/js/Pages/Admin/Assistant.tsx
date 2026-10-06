import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Settings {
    mode: string;
    provider: string;
    model: string;
    hasApiKey: boolean;
    fallbackAfterHours: number;
    monthlyQuota: number;
    dailyQuota: number;
    monthlyBudgetUsd: number;
}

const MODES = [
    ['off', 'Off', 'No AI answers at all. Students only ask tutors.'],
    ['fallback', 'Fallback only', 'The AI is offered only when no tutor has accepted a request in time. Protects tutor engagement and costs least.'],
    ['always', 'Always available', 'Students can ask the AI directly at any time, alongside asking a tutor.'],
];

export default function Assistant({
    settings,
    providers,
    usage,
}: {
    settings: Settings;
    providers: Record<string, string>;
    usage: {
        answersThisMonth: number;
        refusalsThisMonth: number;
        escalations: number;
        spendUsd: number;
        budgetUsd: number;
        budgetUsedPercent: number;
        helpfulRate: number | null;
        topSubjects: { subject: string; total: number }[];
    };
}) {
    const { data, setData, put, processing, errors } = useForm({
        mode: settings.mode,
        provider: settings.provider,
        model: settings.model,
        api_key: '',
        fallback_after_hours: settings.fallbackAfterHours,
        monthly_quota_per_student: settings.monthlyQuota,
        daily_quota_per_student: settings.dailyQuota,
        monthly_budget_usd: settings.monthlyBudgetUsd,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.assistant.update'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Study assistant</h2>}>
            <Head title="Study assistant settings" />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <div className="grid gap-4 sm:grid-cols-4">
                    {[
                        ['Answers this month', usage.answersThisMonth],
                        ['Refused (assessment)', usage.refusalsThisMonth],
                        ['Sent to a human', usage.escalations],
                        ['Rated helpful', usage.helpfulRate !== null ? `${usage.helpfulRate}%` : '—'],
                    ].map(([label, value]) => (
                        <div key={label as string} className="rounded-xl border border-slate-200 bg-white p-4">
                            <p className="text-xs uppercase tracking-wide text-slate-500">{label as string}</p>
                            <p className="mt-1 text-xl font-semibold text-slate-900">{value as string}</p>
                        </div>
                    ))}
                </div>

                <section className="rounded-xl border border-slate-200 bg-white p-5">
                    <div className="flex items-baseline justify-between">
                        <h3 className="font-semibold text-slate-900">Spend this month</h3>
                        <p className="text-sm text-slate-600">
                            ${usage.spendUsd} of ${usage.budgetUsd}
                        </p>
                    </div>
                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className={`h-full rounded-full ${
                                usage.budgetUsedPercent > 85 ? 'bg-rose-500' : 'bg-emerald-500'
                            }`}
                            style={{ width: `${Math.min(usage.budgetUsedPercent, 100)}%` }}
                        />
                    </div>
                    <p className="mt-2 text-xs text-slate-500">
                        The assistant switches itself off for everyone when the budget is reached, and comes
                        back at the start of the next month.
                    </p>
                </section>

                <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
                    <div>
                        <h3 className="font-semibold text-slate-900">When students can use it</h3>
                        <div className="mt-3 space-y-2">
                            {MODES.map(([value, label, help]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setData('mode', value)}
                                    className={`block w-full rounded-xl border p-4 text-left transition ${
                                        data.mode === value
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    <p className="text-sm font-medium text-slate-900">{label}</p>
                                    <p className="mt-0.5 text-xs text-slate-600">{help}</p>
                                </button>
                            ))}
                        </div>
                    </div>

                    {data.mode === 'fallback' && (
                        <div>
                            <label className="text-sm font-medium text-slate-700">
                                Offer the AI after a request has waited (hours)
                            </label>
                            <input
                                type="number"
                                value={data.fallback_after_hours}
                                onChange={(e) => setData('fallback_after_hours', Number(e.target.value))}
                                className="mt-1 block w-32 rounded-lg border-slate-300 text-sm"
                            />
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="text-sm font-medium text-slate-700">Provider</label>
                            <select
                                value={data.provider}
                                onChange={(e) => setData('provider', e.target.value)}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                            >
                                {Object.entries(providers).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-slate-700">Model</label>
                            <input
                                value={data.model}
                                onChange={(e) => setData('model', e.target.value)}
                                placeholder="Model name from your provider"
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="text-sm font-medium text-slate-700">API key</label>
                        <input
                            type="password"
                            value={data.api_key}
                            onChange={(e) => setData('api_key', e.target.value)}
                            placeholder={settings.hasApiKey ? '•••••••• (saved — leave blank to keep)' : 'Paste your key'}
                            className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            Stored encrypted. Leaving this blank keeps the key you already saved.
                        </p>
                    </div>

                    <div>
                        <h3 className="font-semibold text-slate-900">Limits</h3>
                        <p className="mt-1 text-xs text-slate-500">
                            Each answer costs money, so students get an allowance. When it runs out they are
                            pointed back to a tutor, which is free and unlimited.
                        </p>

                        <div className="mt-3 grid gap-4 sm:grid-cols-3">
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Per student, per month
                                </label>
                                <input
                                    type="number"
                                    value={data.monthly_quota_per_student}
                                    onChange={(e) => setData('monthly_quota_per_student', Number(e.target.value))}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                                {errors.monthly_quota_per_student && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.monthly_quota_per_student}</p>
                                )}
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Per student, per day
                                </label>
                                <input
                                    type="number"
                                    value={data.daily_quota_per_student}
                                    onChange={(e) => setData('daily_quota_per_student', Number(e.target.value))}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Platform budget (USD/month)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.monthly_budget_usd}
                                    onChange={(e) => setData('monthly_budget_usd', Number(e.target.value))}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Save settings
                    </button>
                </form>

                {usage.topSubjects.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 className="font-semibold text-slate-900">Where the AI is being used</h3>
                        <p className="mt-1 text-xs text-slate-500">
                            High usage in a subject usually means tutor coverage there is too thin.
                        </p>
                        <ul className="mt-3 space-y-2 text-sm">
                            {usage.topSubjects.map((row) => (
                                <li key={row.subject} className="flex justify-between">
                                    <span className="text-slate-700">{row.subject}</span>
                                    <span className="font-medium text-slate-900">{row.total}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
