import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

interface Role {
    value: string;
    label: string;
}

export default function Register({ minimumAge, roles }: { minimumAge: number; roles: Role[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '',
        last_name: '',
        email: '',
        date_of_birth: '',
        role: 'student',
        mobile: '',
        password: '',
        password_confirmation: '',
        guardian_name: '',
        guardian_email: '',
        guardian_mobile: '',
        terms: false as boolean,
    });

    // CON-01: guardian fields appear as soon as the date of birth shows a minor.
    const isMinor = useMemo(() => {
        if (!data.date_of_birth) return false;

        const dob = new Date(data.date_of_birth);
        const eighteenth = new Date(dob.getFullYear() + 18, dob.getMonth(), dob.getDate());

        return eighteenth > new Date();
    }, [data.date_of_birth]);

    const portal = usePage().props.portal as { current: string } | undefined;
    const isStudent = portal?.current !== 'teacher';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const button = isStudent
        ? 'bg-indigo-600 hover:bg-indigo-500 focus:ring-indigo-500'
        : 'bg-teal-600 hover:bg-teal-500 focus:ring-teal-500';

    const link = isStudent ? 'text-indigo-600 hover:text-indigo-500' : 'text-teal-700 hover:text-teal-600';

    return (
        <GuestLayout
            title={isStudent ? 'Create your free account' : 'Apply to tutor'}
            subtitle={
                isStudent
                    ? 'Two minutes, and you can ask a tutor tonight.'
                    : 'Tell us what you teach. We usually review applications within 48 hours.'
            }
        >
            <Head title={isStudent ? 'Create your account' : 'Apply to tutor'} />

            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="first_name" value="First name" />
                        <TextInput
                            id="first_name"
                            value={data.first_name}
                            className="mt-1 block w-full"
                            autoComplete="given-name"
                            isFocused
                            onChange={(e) => setData('first_name', e.target.value)}
                            required
                        />
                        <InputError message={errors.first_name} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="last_name" value="Last name" />
                        <TextInput
                            id="last_name"
                            value={data.last_name}
                            className="mt-1 block w-full"
                            autoComplete="family-name"
                            onChange={(e) => setData('last_name', e.target.value)}
                            required
                        />
                        <InputError message={errors.last_name} className="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email address" />
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="date_of_birth" value="Date of birth" />
                        <TextInput
                            id="date_of_birth"
                            type="date"
                            value={data.date_of_birth}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                            required
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            You must be at least {minimumAge} years old.
                        </p>
                        <InputError message={errors.date_of_birth} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="role" value="Account type" />
                        <select
                            id="role"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.role} className="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="mobile" value="Mobile number (optional)" />
                    <TextInput
                        id="mobile"
                        value={data.mobile}
                        className="mt-1 block w-full"
                        placeholder="+27 82 000 0000"
                        onChange={(e) => setData('mobile', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-slate-500">
                        Used later for SMS and WhatsApp notifications.
                    </p>
                    <InputError message={errors.mobile} className="mt-2" />
                </div>

                {isMinor && (
                    <fieldset className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <legend className="px-1 text-sm font-semibold text-amber-900">
                            Parent or guardian details
                        </legend>
                        <p className="mb-3 text-xs text-amber-800">
                            Because you are under 18, we need a parent or guardian to approve your account
                            before you can ask tutors for help or message anyone.
                        </p>

                        <div className="space-y-3">
                            <div>
                                <InputLabel htmlFor="guardian_name" value="Guardian full name" />
                                <TextInput
                                    id="guardian_name"
                                    value={data.guardian_name}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('guardian_name', e.target.value)}
                                />
                                <InputError message={errors.guardian_name} className="mt-2" />
                            </div>

                            <div>
                                <InputLabel htmlFor="guardian_email" value="Guardian email address" />
                                <TextInput
                                    id="guardian_email"
                                    type="email"
                                    value={data.guardian_email}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('guardian_email', e.target.value)}
                                />
                                <InputError message={errors.guardian_email} className="mt-2" />
                            </div>

                            <div>
                                <InputLabel htmlFor="guardian_mobile" value="Guardian mobile number" />
                                <TextInput
                                    id="guardian_mobile"
                                    value={data.guardian_mobile}
                                    className="mt-1 block w-full"
                                    placeholder="+27 82 000 0000"
                                    onChange={(e) => setData('guardian_mobile', e.target.value)}
                                />
                                <InputError message={errors.guardian_mobile} className="mt-2" />
                            </div>
                        </div>
                    </fieldset>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="password" value="Password" />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>
                </div>

                <label className="flex items-start gap-3 text-sm text-slate-600">
                    <input
                        type="checkbox"
                        checked={data.terms}
                        onChange={(e) => setData('terms', e.target.checked)}
                        className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span>I accept the terms of use and privacy policy.</span>
                </label>
                <InputError message={errors.terms} />

                <button
                    type="submit"
                    disabled={processing}
                    className={`w-full rounded-lg px-6 py-3 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:bg-slate-300 ${button}`}
                >
                    {processing
                        ? 'Creating your account…'
                        : isStudent
                          ? 'Create my free account'
                          : 'Submit my application'}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-slate-600">
                Already have an account?{' '}
                <Link href={route('login')} className={`font-semibold ${link}`}>
                    Sign in
                </Link>
            </p>
        </GuestLayout>
    );
}
