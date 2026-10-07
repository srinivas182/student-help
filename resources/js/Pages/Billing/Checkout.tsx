import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Posts straight to PayFast. The signature is calculated on the server, so
 * nothing here can be tampered with to change the amount.
 */
export default function Checkout({
    plan,
    url,
    fields,
    sandbox,
}: {
    plan: { name: string; price: string; interval: string };
    url: string;
    fields: Record<string, string>;
    sandbox: boolean;
}) {
    const form = useRef<HTMLFormElement>(null);

    useEffect(() => {
        const timer = setTimeout(() => form.current?.submit(), 1200);

        return () => clearTimeout(timer);
    }, []);

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
            <Head title="Taking you to PayFast" />

            <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center">
                <p className="text-sm font-semibold uppercase tracking-widest text-indigo-600">
                    DX Student Help
                </p>
                <h1 className="mt-4 text-xl font-semibold text-slate-900">Taking you to PayFast</h1>
                <p className="mt-2 text-sm text-slate-600">
                    {plan.name} · {plan.price}
                    {plan.interval === 'year' ? ' per year' : ' per month'}
                </p>

                {sandbox && (
                    <p className="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                        Sandbox mode — no real money moves.
                    </p>
                )}

                <form ref={form} action={url} method="post" className="mt-6">
                    {Object.entries(fields).map(([name, value]) => (
                        <input key={name} type="hidden" name={name} value={value} />
                    ))}

                    <button
                        type="submit"
                        className="w-full rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Continue to PayFast
                    </button>
                </form>

                <p className="mt-4 text-xs text-slate-400">
                    If nothing happens in a few seconds, press the button.
                </p>
            </div>
        </div>
    );
}
