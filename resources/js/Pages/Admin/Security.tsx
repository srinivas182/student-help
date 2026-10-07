import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Staff {
    id: number;
    name: string;
    email: string;
    role: string;
    enabled: boolean;
    bypassUntil: string | null;
}

export default function Security({
    twoFactor,
    gateways,
    generation,
    emailProviders,
    smsProviders,
    roles,
    staff,
}: {
    twoFactor: {
        primaryMethod: string;
        backupMethods: string[];
        requiredRoles: string[];
        trustedDeviceDays: number;
        bypassHours: number;
    };
    gateways: {
        emailEnabled: boolean;
        emailProvider: string | null;
        emailFrom: { address: string; name: string };
        smsEnabled: boolean;
        smsProvider: string | null;
        smsSender: string;
    };
    generation: {
        otpEnabled: boolean;
        costThreshold: number;
        languageThreshold: number;
        inputCost: number;
        outputCost: number;
        usdToZar: number;
        inheritsAssistant: boolean;
        provider: string;
        model: string;
    };
    emailProviders: Record<string, string>;
    smsProviders: Record<string, string>;
    roles: string[];
    staff: Staff[];
}) {
    const [bypassing, setBypassing] = useState<number | null>(null);
    const bypass = useForm({ reason: '' });

    const { data, setData, put, processing } = useForm({
        primary_method: twoFactor.primaryMethod,
        backup_methods: twoFactor.backupMethods,
        required_roles: twoFactor.requiredRoles,
        trusted_device_days: twoFactor.trustedDeviceDays,
        bypass_hours: twoFactor.bypassHours,

        email_gateway_enabled: gateways.emailEnabled,
        email_gateway_provider: gateways.emailProvider ?? 'smtp',
        email_from_address: gateways.emailFrom.address,
        email_from_name: gateways.emailFrom.name,
        sms_gateway_enabled: gateways.smsEnabled,
        sms_gateway_provider: gateways.smsProvider ?? 'clickatell',
        sms_sender_id: gateways.smsSender,

        ai_tutor_otp_enabled: generation.otpEnabled,
        ai_tutor_otp_cost_threshold: generation.costThreshold,
        ai_tutor_otp_language_threshold: generation.languageThreshold,
        ai_tutor_input_cost_per_million: generation.inputCost,
        ai_tutor_output_cost_per_million: generation.outputCost,
        usd_to_zar: generation.usdToZar,
        ai_tutor_inherit_assistant: generation.inheritsAssistant,
        ai_tutor_provider: generation.provider,
        ai_tutor_model: generation.model,
    });

    const toggleList = (key: 'backup_methods' | 'required_roles', value: string) =>
        setData(
            key,
            data[key].includes(value) ? data[key].filter((v) => v !== value) : [...data[key], value],
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.security.update'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Security</h2>}>
            <Head title="Security" />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-5 px-4 py-8 sm:px-6">
                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">Two-factor authentication</h2>

                    <div className="mt-4">
                        <p className="text-sm font-medium text-slate-700">Primary method</p>
                        <div className="mt-2 flex gap-2">
                            {[
                                ['app', 'Authenticator app'],
                                ['email', 'Email code'],
                                ['sms', 'SMS code'],
                            ].map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setData('primary_method', value)}
                                    className={`rounded-full px-4 py-1.5 text-sm transition ${
                                        data.primary_method === value
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-slate-100 text-slate-700'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        <p className="mt-2 text-xs text-slate-500">
                            An authenticator app is free, works offline and cannot be intercepted. Recovery
                            codes are always available as a backup.
                        </p>
                    </div>

                    <div className="mt-5">
                        <p className="text-sm font-medium text-slate-700">Backup methods allowed</p>
                        <div className="mt-2 flex gap-2">
                            {[
                                ['email', 'Email code'],
                                ['sms', 'SMS code'],
                            ].map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => toggleList('backup_methods', value)}
                                    className={`rounded-full px-4 py-1.5 text-sm transition ${
                                        data.backup_methods.includes(value)
                                            ? 'bg-slate-900 text-white'
                                            : 'bg-slate-100 text-slate-600'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="mt-5">
                        <p className="text-sm font-medium text-slate-700">Required for these roles</p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {roles.map((role) => (
                                <button
                                    key={role}
                                    type="button"
                                    onClick={() => toggleList('required_roles', role)}
                                    className={`rounded-full px-4 py-1.5 text-sm capitalize transition ${
                                        data.required_roles.includes(role)
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-slate-100 text-slate-700'
                                    }`}
                                >
                                    {role.replace('_', ' ')}
                                </button>
                            ))}
                        </div>
                        <p className="mt-2 text-xs text-slate-500">
                            Students are never required, whatever is set here. A locked-out learner the night
                            before an exam helps nobody.
                        </p>
                    </div>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Remember a device for (days)
                            </label>
                            <input
                                type="number"
                                value={data.trusted_device_days}
                                onChange={(e) => setData('trusted_device_days', Number(e.target.value))}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Bypass lasts (hours)
                            </label>
                            <input
                                type="number"
                                value={data.bypass_hours}
                                onChange={(e) => setData('bypass_hours', Number(e.target.value))}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                            />
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">Email</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Guardian consent, invitations and notifications all go through here. One-time codes are
                        only offered when this is on.
                    </p>

                    <label className="mt-4 flex items-center gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.email_gateway_enabled}
                            onChange={(e) => setData('email_gateway_enabled', e.target.checked)}
                            className="rounded border-slate-300 text-indigo-600"
                        />
                        Email sending is enabled
                    </label>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <select
                            value={data.email_gateway_provider}
                            onChange={(e) => setData('email_gateway_provider', e.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        >
                            {Object.entries(emailProviders).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <input
                            value={data.email_from_address}
                            onChange={(e) => setData('email_from_address', e.target.value)}
                            placeholder="From address"
                            className="rounded-lg border-slate-300 text-sm"
                        />
                        <input
                            value={data.email_from_name}
                            onChange={(e) => setData('email_from_name', e.target.value)}
                            placeholder="From name"
                            className="rounded-lg border-slate-300 text-sm"
                        />
                        <button
                            type="button"
                            onClick={() => router.post(route('admin.security.testEmail'))}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Send me a test email
                        </button>
                    </div>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">SMS</h2>
                    <label className="mt-3 flex items-center gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.sms_gateway_enabled}
                            onChange={(e) => setData('sms_gateway_enabled', e.target.checked)}
                            className="rounded border-slate-300 text-indigo-600"
                        />
                        SMS sending is enabled
                    </label>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <select
                            value={data.sms_gateway_provider}
                            onChange={(e) => setData('sms_gateway_provider', e.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        >
                            {Object.entries(smsProviders).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <input
                            value={data.sms_sender_id}
                            onChange={(e) => setData('sms_sender_id', e.target.value)}
                            placeholder="Sender ID"
                            maxLength={11}
                            className="rounded-lg border-slate-300 text-sm"
                        />
                    </div>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">AI Tutor generation</h2>

                    <label className="mt-3 flex items-center gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.ai_tutor_inherit_assistant}
                            onChange={(e) => setData('ai_tutor_inherit_assistant', e.target.checked)}
                            className="rounded border-slate-300 text-indigo-600"
                        />
                        Use the same provider and key as the study assistant
                    </label>

                    {!data.ai_tutor_inherit_assistant && (
                        <div className="mt-3 grid gap-4 sm:grid-cols-2">
                            <input
                                value={data.ai_tutor_provider}
                                onChange={(e) => setData('ai_tutor_provider', e.target.value)}
                                placeholder="Provider"
                                className="rounded-lg border-slate-300 text-sm"
                            />
                            <input
                                value={data.ai_tutor_model}
                                onChange={(e) => setData('ai_tutor_model', e.target.value)}
                                placeholder="Model for lesson generation"
                                className="rounded-lg border-slate-300 text-sm"
                            />
                        </div>
                    )}

                    <p className="mt-4 text-sm font-medium text-slate-700">Cost estimates</p>
                    <div className="mt-2 grid gap-4 sm:grid-cols-3">
                        {[
                            ['ai_tutor_input_cost_per_million', 'Input $/million tokens'],
                            ['ai_tutor_output_cost_per_million', 'Output $/million tokens'],
                            ['usd_to_zar', 'USD to ZAR'],
                        ].map(([key, label]) => (
                            <div key={key}>
                                <label className="text-xs uppercase tracking-wide text-slate-500">{label}</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data[key as keyof typeof data] as number}
                                    onChange={(e) =>
                                        setData(key as never, Number(e.target.value) as never)
                                    }
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                        ))}
                    </div>

                    <label className="mt-5 flex items-center gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.ai_tutor_otp_enabled}
                            onChange={(e) => setData('ai_tutor_otp_enabled', e.target.checked)}
                            className="rounded border-slate-300 text-indigo-600"
                        />
                        Require an emailed code for expensive generations
                    </label>

                    {data.ai_tutor_otp_enabled && (
                        <div className="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="text-xs uppercase tracking-wide text-slate-500">
                                    Ask for a code above ($)
                                </label>
                                <input
                                    type="number"
                                    step="0.5"
                                    value={data.ai_tutor_otp_cost_threshold}
                                    onChange={(e) =>
                                        setData('ai_tutor_otp_cost_threshold', Number(e.target.value))
                                    }
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-xs uppercase tracking-wide text-slate-500">
                                    Or above this many languages
                                </label>
                                <input
                                    type="number"
                                    value={data.ai_tutor_otp_language_threshold}
                                    onChange={(e) =>
                                        setData('ai_tutor_otp_language_threshold', Number(e.target.value))
                                    }
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                                />
                            </div>
                        </div>
                    )}

                    <p className="mt-2 text-xs text-slate-500">
                        Routine generations go straight through after a confirmation box. Only the expensive
                        ones need a code, so nobody learns to click past security prompts.
                    </p>
                </section>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Save security settings
                </button>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">Staff accounts</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        A bypass is temporary and the person must set two-factor up again. Everyone is emailed
                        when one is granted.
                    </p>

                    <ul className="mt-4 divide-y divide-slate-100">
                        {staff.map((person) => (
                            <li key={person.id} className="py-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium text-slate-900">{person.name}</p>
                                        <p className="text-xs text-slate-500">
                                            {person.email} · {person.role.replace('_', ' ')}
                                            {person.bypassUntil && ` · bypass until ${person.bypassUntil}`}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                person.enabled
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-amber-100 text-amber-800'
                                            }`}
                                        >
                                            {person.enabled ? '2FA on' : '2FA off'}
                                        </span>
                                        {person.enabled && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setBypassing(bypassing === person.id ? null : person.id)
                                                }
                                                className="text-xs font-medium text-slate-600 hover:text-slate-900"
                                            >
                                                Grant bypass
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {bypassing === person.id && (
                                    <div className="mt-3 flex gap-2">
                                        <input
                                            value={bypass.data.reason}
                                            onChange={(e) => bypass.setData('reason', e.target.value)}
                                            placeholder="Why is this needed? Goes in the audit log."
                                            className="flex-1 rounded-lg border-slate-300 text-sm"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                bypass.post(route('admin.security.bypass', person.id), {
                                                    preserveScroll: true,
                                                    onSuccess: () => setBypassing(null),
                                                })
                                            }
                                            className="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white"
                                        >
                                            Grant
                                        </button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            </form>
        </AuthenticatedLayout>
    );
}
