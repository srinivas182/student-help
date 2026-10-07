import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface PlanCard {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    price: string;
    priceCents: number;
    interval: string;
    features: string[];
    isFree: boolean;
    isCurrent: boolean;
}

export default function Plans({
    plans,
    monetisationOn,
    grandfathered,
    freeForLife,
    paymentsReady,
    subscription,
}: {
    plans: PlanCard[];
    monetisationOn: boolean;
    grandfathered: boolean;
    freeForLife: boolean;
    paymentsReady: boolean;
    subscription: { plan: string | null; status: string; endsAt: string | null; cancelled: boolean } | null;
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Plans</h2>}>
            <Head title="Plans" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
                {!monetisationOn && (
                    <p className="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
                        Everything on DX Student Help is free at the moment. These plans are here so you can see
                        what is coming — nothing is charged today.
                    </p>
                )}

                {grandfathered && (
                    <p className="rounded-xl border border-indigo-200 bg-indigo-50 p-5 text-sm text-indigo-900">
                        You joined before we introduced plans, so you keep unlimited access for free. That does
                        not change.
                    </p>
                )}

                {freeForLife && (
                    <p className="rounded-xl border border-indigo-200 bg-indigo-50 p-5 text-sm text-indigo-900">
                        Your account has unlimited access, free for life.
                    </p>
                )}

                {subscription && (
                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <p className="font-medium text-slate-900">
                            You are on {subscription.plan}
                            {subscription.cancelled && ' (cancelled)'}
                        </p>
                        <p className="mt-1 text-sm text-slate-600">
                            {subscription.cancelled
                                ? `You keep access until ${subscription.endsAt}.`
                                : `Renews on ${subscription.endsAt}.`}
                        </p>
                        {!subscription.cancelled && (
                            <button
                                onClick={() => router.post(route('billing.cancelSubscription'))}
                                className="mt-3 text-xs font-medium text-slate-500 hover:text-rose-600"
                            >
                                Cancel subscription
                            </button>
                        )}
                    </div>
                )}

                <div className="grid gap-5 sm:grid-cols-3">
                    {plans.map((plan) => (
                        <div
                            key={plan.id}
                            className={`flex flex-col rounded-2xl border p-6 ${
                                plan.isCurrent
                                    ? 'border-indigo-400 bg-indigo-50/40'
                                    : 'border-slate-200 bg-white'
                            }`}
                        >
                            <p className="font-semibold text-slate-900">{plan.name}</p>
                            <p className="mt-1 text-sm text-slate-600">{plan.description}</p>

                            <p className="mt-4">
                                <span className="text-3xl font-semibold text-slate-900">{plan.price}</span>
                                {!plan.isFree && (
                                    <span className="text-sm text-slate-500">
                                        {plan.interval === 'year' ? '/year' : '/month'}
                                    </span>
                                )}
                            </p>

                            <ul className="mt-5 space-y-2 text-sm text-slate-600">
                                {plan.features.map((feature) => (
                                    <li key={feature} className="flex gap-2">
                                        <span className="text-emerald-600">✓</span>
                                        {feature}
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-auto pt-6">
                                {plan.isCurrent ? (
                                    <p className="rounded-lg bg-slate-100 py-2.5 text-center text-sm font-medium text-slate-600">
                                        Your plan
                                    </p>
                                ) : plan.isFree ? (
                                    <p className="py-2.5 text-center text-sm text-slate-400">Always available</p>
                                ) : paymentsReady ? (
                                    <Link
                                        href={route('billing.checkout', plan.id)}
                                        className="block rounded-lg bg-indigo-600 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-500"
                                    >
                                        Choose {plan.name}
                                    </Link>
                                ) : (
                                    <p className="rounded-lg bg-slate-100 py-2.5 text-center text-sm text-slate-500">
                                        Coming soon
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <p className="text-center text-xs text-slate-500">
                    Asking a verified tutor for help is free on every plan. Paid plans lift the limits on how
                    many questions you can ask each month.{' '}
                    <Link href={route('billing.history')} className="underline">
                        Payment history
                    </Link>
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
