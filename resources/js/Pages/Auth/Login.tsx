import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const portal = usePage().props.portal as { current: string } | undefined;
    const isStudent = portal?.current !== 'teacher';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const button = isStudent
        ? 'bg-indigo-600 hover:bg-indigo-500 focus:ring-indigo-500'
        : 'bg-teal-600 hover:bg-teal-500 focus:ring-teal-500';

    const link = isStudent ? 'text-indigo-600 hover:text-indigo-500' : 'text-teal-700 hover:text-teal-600';

    return (
        <GuestLayout title="Welcome back" subtitle="Sign in to carry on where you left off.">
            <Head title="Sign in" />

            {status && (
                <div className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-slate-700">
                        Email address
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                        className="mt-1.5 block w-full rounded-lg border-slate-300 px-3.5 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:ring-0"
                        placeholder="you@example.co.za"
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <div className="flex items-baseline justify-between">
                        <label htmlFor="password" className="block text-sm font-medium text-slate-700">
                            Password
                        </label>
                        {canResetPassword && (
                            <Link href={route('password.request')} className={`text-xs font-medium ${link}`}>
                                Forgot it?
                            </Link>
                        )}
                    </div>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                        className="mt-1.5 block w-full rounded-lg border-slate-300 px-3.5 py-2.5 text-slate-900 focus:border-slate-400 focus:ring-0"
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <label className="flex items-center gap-2.5 text-sm text-slate-600">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', (e.target.checked || false) as false)}
                        className="rounded border-slate-300 text-slate-700 focus:ring-slate-400"
                    />
                    Keep me signed in
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    className={`w-full rounded-lg px-6 py-3 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:bg-slate-300 ${button}`}
                >
                    {processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-slate-600">
                {isStudent ? 'New here?' : 'Not registered yet?'}{' '}
                <Link href={route('register')} className={`font-semibold ${link}`}>
                    {isStudent ? 'Create a free account' : 'Apply to tutor'}
                </Link>
            </p>
        </GuestLayout>
    );
}
