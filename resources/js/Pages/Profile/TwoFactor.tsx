import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function TwoFactor({
    enabled,
    required,
    recoveryRemaining,
    primaryMethod,
    backupMethods,
    trustedDeviceDays,
    bypassActive,
}: {
    enabled: boolean;
    required: boolean;
    recoveryRemaining: number;
    primaryMethod: string;
    backupMethods: string[];
    trustedDeviceDays: number;
    bypassActive: boolean;
}) {
    const flash = usePage().props.flash as { recoveryCodes?: string[] } | undefined;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Two-factor authentication</h2>}
        >
            <Head title="Two-factor authentication" />

            <div className="mx-auto max-w-2xl space-y-5 px-4 py-8 sm:px-6">
                {bypassActive && (
                    <p className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        An administrator gave you temporary access without two-factor. Set it up again now —
                        the bypass expires shortly.
                    </p>
                )}

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slate-900">
                                {enabled ? 'Two-factor is on' : 'Two-factor is off'}
                            </h2>
                            <p className="mt-1 text-sm text-slate-600">
                                {enabled
                                    ? `You have ${recoveryRemaining} recovery codes left. Devices stay trusted for ${trustedDeviceDays} days.`
                                    : required
                                      ? 'Your role requires two-factor authentication. Set it up to keep your account.'
                                      : 'Add a second step to your sign-in. Strongly recommended.'}
                            </p>
                        </div>

                        <span
                            className={`rounded-full px-3 py-1 text-xs font-medium ${
                                enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                            }`}
                        >
                            {enabled ? 'Protected' : required ? 'Required' : 'Optional'}
                        </span>
                    </div>

                    <div className="mt-5 flex flex-wrap gap-3">
                        {!enabled ? (
                            <Link
                                href={route('twoFactor.enrol')}
                                className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                Set up with an app
                            </Link>
                        ) : (
                            <>
                                <button
                                    onClick={() => router.post(route('twoFactor.recovery'))}
                                    className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    New recovery codes
                                </button>
                                <button
                                    onClick={() => router.delete(route('twoFactor.devices'))}
                                    className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Forget trusted devices
                                </button>
                                {!required && (
                                    <button
                                        onClick={() => router.delete(route('twoFactor.disable'))}
                                        className="rounded-lg px-5 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"
                                    >
                                        Turn off
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                </section>

                {flash?.recoveryCodes && (
                    <section className="rounded-xl border border-amber-200 bg-amber-50 p-6">
                        <h3 className="font-semibold text-amber-900">Your new recovery codes</h3>
                        <p className="mt-1 text-sm text-amber-800">
                            The old ones no longer work. Save these now.
                        </p>
                        <div className="mt-3 grid grid-cols-2 gap-2 rounded-lg bg-white p-4">
                            {flash.recoveryCodes.map((code) => (
                                <code key={code} className="font-mono text-sm text-slate-800">
                                    {code}
                                </code>
                            ))}
                        </div>
                    </section>
                )}

                <section className="rounded-xl border border-slate-200 bg-white p-6 text-sm">
                    <h3 className="font-semibold text-slate-900">How you sign in</h3>
                    <ul className="mt-3 space-y-2 text-slate-600">
                        <li>
                            <span className="font-medium text-slate-800">Primary:</span>{' '}
                            {primaryMethod === 'app' ? 'Authenticator app' : primaryMethod}
                        </li>
                        <li>
                            <span className="font-medium text-slate-800">If you lose your phone:</span>{' '}
                            recovery codes
                            {backupMethods.length > 0 && `, or a code by ${backupMethods.join(' or ')}`}
                        </li>
                    </ul>
                    <p className="mt-3 text-xs text-slate-500">
                        Recovery codes work even if your email is compromised, which is why they matter most.
                    </p>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
