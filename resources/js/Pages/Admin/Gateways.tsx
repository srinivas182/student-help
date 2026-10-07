import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Provider {
    key: string;
    name: string;
    note: string;
    fields: Record<string, string>;
    isActive: boolean;
    filled: Record<string, boolean>;
    verifiedAt: string | null;
    lastError: string | null;
}

interface Channel {
    channel: string;
    ready: boolean;
    active: string | null;
    providers: Provider[];
}

const CHANNEL_LABEL: Record<string, string> = {
    email: 'Email',
    sms: 'SMS',
    whatsapp: 'WhatsApp',
};

const CHANNEL_NOTE: Record<string, string> = {
    email: 'Guardian consent, invitations, notifications and one-time codes.',
    sms: 'Short messages and backup codes. South African gateways first.',
    whatsapp: 'Off by default. Needs Meta business verification and approved templates.',
};

function ProviderCard({ channel, provider }: { channel: string; provider: Provider }) {
    const [open, setOpen] = useState(false);

    const form = useForm<{ channel: string; provider: string; credentials: Record<string, string>; activate: boolean }>({
        channel,
        provider: provider.key,
        credentials: Object.fromEntries(Object.keys(provider.fields).map((field) => [field, ''])),
        activate: provider.isActive,
    });

    return (
        <div
            className={`rounded-xl border p-5 ${
                provider.isActive ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200 bg-white'
            }`}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <p className="font-medium text-slate-900">{provider.name}</p>
                        {provider.isActive && (
                            <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">
                                In use
                            </span>
                        )}
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">{provider.note}</p>
                    {provider.verifiedAt && (
                        <p className="mt-1 text-xs text-emerald-700">Last successful send {provider.verifiedAt}</p>
                    )}
                    {provider.lastError && (
                        <p className="mt-1 text-xs text-rose-600">Last error: {provider.lastError}</p>
                    )}
                </div>

                <button
                    onClick={() => setOpen(!open)}
                    className="rounded-lg border border-slate-300 px-4 py-1.5 text-xs font-medium text-slate-700 hover:bg-white"
                >
                    {Object.values(provider.filled).some(Boolean) ? 'Edit keys' : 'Set up'}
                </button>
            </div>

            {open && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('admin.gateways.save'), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                    className="mt-4 space-y-3 rounded-lg bg-white p-4 ring-1 ring-slate-200"
                >
                    {Object.entries(provider.fields).map(([field, label]) => (
                        <div key={field}>
                            <label className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                {label}
                            </label>
                            <input
                                type={field.includes('secret') || field.includes('password') || field.includes('token') || field.includes('key') ? 'password' : 'text'}
                                value={form.data.credentials[field] ?? ''}
                                onChange={(e) =>
                                    form.setData('credentials', {
                                        ...form.data.credentials,
                                        [field]: e.target.value,
                                    })
                                }
                                placeholder={provider.filled[field] ? '•••••••• (saved — leave blank to keep)' : ''}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                            />
                        </div>
                    ))}

                    <label className="flex items-center gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.activate}
                            onChange={(e) => form.setData('activate', e.target.checked)}
                            className="rounded border-slate-300 text-indigo-600"
                        />
                        Use this provider for {CHANNEL_LABEL[channel]}
                    </label>

                    <button
                        disabled={form.processing}
                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white"
                    >
                        Save
                    </button>
                </form>
            )}
        </div>
    );
}

export default function Gateways({
    channels,
    whatsappPreferred,
    deliveries,
    stats,
}: {
    channels: Channel[];
    whatsappPreferred: boolean;
    deliveries: {
        channel: string;
        provider: string;
        recipient: string;
        purpose: string | null;
        status: string;
        error: string | null;
        at: string;
    }[];
    stats: { sent: number; failed: number };
}) {
    const test = useForm({ channel: 'email', to: '' });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Gateways</h2>}>
            <Head title="Gateways" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
                {channels.map((channel) => (
                    <section key={channel.channel}>
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 className="font-semibold text-slate-900">
                                    {CHANNEL_LABEL[channel.channel]}
                                    <span
                                        className={`ml-2 rounded-full px-2.5 py-0.5 text-[11px] font-medium ${
                                            channel.ready
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-slate-100 text-slate-500'
                                        }`}
                                    >
                                        {channel.ready ? 'Ready' : 'Not configured'}
                                    </span>
                                </h2>
                                <p className="mt-0.5 text-xs text-slate-500">{CHANNEL_NOTE[channel.channel]}</p>
                            </div>
                        </div>

                        <div className="space-y-3">
                            {channel.providers.map((provider) => (
                                <ProviderCard key={provider.key} channel={channel.channel} provider={provider} />
                            ))}
                        </div>

                        {channel.channel === 'whatsapp' && channel.ready && (
                            <label className="mt-3 flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={whatsappPreferred}
                                    onChange={(e) =>
                                        router.post(route('admin.gateways.whatsapp'), {
                                            preferred: e.target.checked,
                                        })
                                    }
                                    className="rounded border-slate-300 text-indigo-600"
                                />
                                Send short messages by WhatsApp instead of SMS where possible
                            </label>
                        )}
                    </section>
                ))}

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">Send a test</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Prove it works before guardian consent depends on it.
                    </p>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            test.post(route('admin.gateways.test'), { preserveScroll: true });
                        }}
                        className="mt-3 flex flex-wrap gap-3"
                    >
                        <select
                            value={test.data.channel}
                            onChange={(e) => test.setData('channel', e.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        >
                            {channels.map((channel) => (
                                <option key={channel.channel} value={channel.channel}>
                                    {CHANNEL_LABEL[channel.channel]}
                                </option>
                            ))}
                        </select>
                        <input
                            value={test.data.to}
                            onChange={(e) => test.setData('to', e.target.value)}
                            placeholder={test.data.channel === 'email' ? 'you@example.co.za' : '082 123 4567'}
                            className="flex-1 rounded-lg border-slate-300 text-sm"
                        />
                        <button className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white">
                            Send test
                        </button>
                    </form>
                    {test.errors.to && <p className="mt-2 text-sm text-rose-600">{test.errors.to}</p>}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex items-baseline justify-between">
                        <h2 className="font-semibold text-slate-900">Recent deliveries</h2>
                        <p className="text-xs text-slate-500">
                            {stats.sent} sent · {stats.failed} failed
                        </p>
                    </div>

                    {deliveries.length === 0 ? (
                        <p className="mt-3 text-sm text-slate-500">Nothing sent yet.</p>
                    ) : (
                        <ul className="mt-3 divide-y divide-slate-100">
                            {deliveries.map((item, index) => (
                                <li key={index} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                    <span className="text-slate-700">
                                        {item.recipient}
                                        <span className="ml-2 text-xs text-slate-400">
                                            {item.channel} · {item.provider}
                                            {item.purpose && ` · ${item.purpose}`}
                                        </span>
                                    </span>
                                    <span
                                        className={`text-xs ${
                                            item.status === 'sent' ? 'text-emerald-700' : 'text-rose-600'
                                        }`}
                                        title={item.error ?? undefined}
                                    >
                                        {item.status} · {item.at}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
