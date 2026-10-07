import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function AcceptInvitation({
    valid,
    token,
    name,
    email,
    role,
}: {
    valid: boolean;
    token: string;
    name: string | null;
    email: string | null;
    role: string | null;
}) {
    const [firstName, ...rest] = (name ?? '').split(' ');

    const { data, setData, post, processing, errors } = useForm({
        first_name: firstName ?? '',
        last_name: rest.join(' '),
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitations.accept', token));
    };

    if (!valid) {
        return (
            <GuestLayout
            title="Set up your account"
            subtitle="You have been invited to join the DX team."
        >
                <Head title="Invitation not valid" />
                <h1 className="text-lg font-semibold text-slate-900">This invitation is no longer valid</h1>
                <p className="mt-2 text-sm text-slate-600">
                    It may have expired or already been used. Ask an administrator to send a new one.
                </p>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <Head title="Set up your account" />

            <h1 className="text-lg font-semibold text-slate-900">Set up your DX Student Help account</h1>
            <p className="mt-1 text-sm text-slate-600">
                {email} · <span className="capitalize">{role?.replace('_', ' ')}</span>
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="first_name" value="First name" />
                        <TextInput
                            id="first_name"
                            value={data.first_name}
                            className="mt-1 block w-full"
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
                            onChange={(e) => setData('last_name', e.target.value)}
                            required
                        />
                        <InputError message={errors.last_name} className="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <p className="mt-1 text-xs text-slate-500">
                        At least 12 characters, with upper and lower case, a number and a symbol.
                    </p>
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                </div>

                <PrimaryButton disabled={processing}>Create my account</PrimaryButton>
            </form>
        </GuestLayout>
    );
}
