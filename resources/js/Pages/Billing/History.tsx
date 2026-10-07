import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function History({
    payments,
}: {
    payments: {
        reference: string;
        amount: string;
        status: string;
        paidAt: string | null;
        createdAt: string | null;
    }[];
}) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Payments</h2>}>
            <Head title="Payment history" />

            <div className="mx-auto max-w-2xl space-y-4 px-4 py-8 sm:px-6">
                <Link href={route('billing.plans')} className="text-sm text-slate-500 hover:text-slate-800">
                    ← Plans
                </Link>

                {payments.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-sm text-slate-500">
                        No payments yet.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        {payments.map((payment) => (
                            <li key={payment.reference} className="flex items-center justify-between px-5 py-3.5">
                                <div>
                                    <p className="text-sm font-medium text-slate-900">{payment.amount}</p>
                                    <p className="font-mono text-xs text-slate-400">{payment.reference}</p>
                                </div>
                                <div className="text-right">
                                    <p
                                        className={`text-sm font-medium ${
                                            payment.status === 'complete' ? 'text-emerald-700' : 'text-slate-500'
                                        }`}
                                    >
                                        {payment.status}
                                    </p>
                                    <p className="text-xs text-slate-400">
                                        {payment.paidAt ?? payment.createdAt}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
