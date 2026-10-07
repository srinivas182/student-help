import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Result({
    outcome,
    subscription,
}: {
    outcome: 'returned' | 'cancelled';
    subscription: boolean;
}) {
    const cancelled = outcome === 'cancelled';

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Payment</h2>}>
            <Head title="Payment" />

            <div className="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
                <p className="text-5xl">{cancelled ? '↩' : subscription ? '✓' : '⏳'}</p>

                <h1 className="mt-5 text-2xl font-semibold text-slate-900">
                    {cancelled
                        ? 'Payment cancelled'
                        : subscription
                          ? 'You are all set'
                          : 'Thanks — we are confirming your payment'}
                </h1>

                <p className="mt-3 text-sm leading-relaxed text-slate-600">
                    {cancelled
                        ? 'Nothing was charged. You can pick a plan again whenever you are ready.'
                        : subscription
                          ? 'Your plan is active. Ask away.'
                          : 'PayFast confirms payments to us separately, which usually takes a minute. Your plan will activate automatically — there is nothing more for you to do.'}
                </p>

                <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <Link
                        href={route('dashboard')}
                        className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Back to my dashboard
                    </Link>
                    <Link
                        href={route('billing.plans')}
                        className="rounded-lg border border-slate-300 px-6 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        View plans
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
