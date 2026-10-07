import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorSetup({
    secret,
    qrUrl,
    recoveryCodes,
}: {
    secret: string;
    qrUrl: string;
    recoveryCodes: string[];
}) {
    const [saved, setSaved] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('twoFactor.confirm'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Set up two-factor</h2>}>
            <Head title="Set up two-factor" />

            <div className="mx-auto max-w-2xl space-y-5 px-4 py-8 sm:px-6">
                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">1. Scan this with your authenticator app</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Google Authenticator, Microsoft Authenticator, Authy or 1Password all work. The app
                        generates a new code every 30 seconds, and works without signal.
                    </p>

                    <div className="mt-4 flex flex-col items-center gap-4 rounded-xl bg-slate-50 p-5 sm:flex-row">
                        <img
                            alt="Two-factor QR code"
                            src={`https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(qrUrl)}`}
                            className="h-44 w-44 rounded-lg bg-white p-2"
                        />
                        <div className="text-center sm:text-left">
                            <p className="text-xs uppercase tracking-wide text-slate-500">
                                Or type this key in
                            </p>
                            <p className="mt-1 break-all font-mono text-sm font-medium text-slate-900">
                                {secret}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border border-amber-200 bg-amber-50 p-6">
                    <h2 className="font-semibold text-amber-900">2. Save your recovery codes</h2>
                    <p className="mt-1 text-sm text-amber-800">
                        These are the only way back in if you lose your phone. Each one works once. Print them
                        or write them down somewhere that is not your phone — you will not see them again.
                    </p>

                    <div className="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-white p-4">
                        {recoveryCodes.map((code) => (
                            <code key={code} className="font-mono text-sm text-slate-800">
                                {code}
                            </code>
                        ))}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-3">
                        <button
                            onClick={() => window.print()}
                            className="rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-medium text-amber-900"
                        >
                            Print these
                        </button>
                        <label className="flex items-center gap-2 text-sm text-amber-900">
                            <input
                                type="checkbox"
                                checked={saved}
                                onChange={(e) => setSaved(e.target.checked)}
                                className="rounded border-amber-400 text-amber-600"
                            />
                            I have saved my recovery codes
                        </label>
                    </div>
                </section>

                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="font-semibold text-slate-900">3. Enter the code from your app</h2>
                    <input
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                        placeholder="000000"
                        maxLength={6}
                        inputMode="numeric"
                        className="mt-3 block w-40 rounded-lg border-slate-300 text-center font-mono text-lg tracking-widest"
                    />
                    {errors.code && <p className="mt-2 text-sm text-rose-600">{errors.code}</p>}

                    <button
                        type="submit"
                        disabled={processing || !saved || data.code.length !== 6}
                        className="mt-4 rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:bg-slate-300"
                    >
                        Turn on two-factor
                    </button>
                    {!saved && (
                        <p className="mt-2 text-xs text-slate-500">
                            Confirm you have saved your recovery codes first.
                        </p>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
