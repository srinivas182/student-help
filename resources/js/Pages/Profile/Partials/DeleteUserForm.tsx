import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm({
    className = '',
}: {
    className?: string;
}) {
    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="text-lg font-medium text-slate-900">Delete account</h2>

                <p className="mt-1 text-sm text-slate-600">
                    Closing an account removes your questions, progress and certificates permanently.
                </p>
            </header>

            <div className="rounded-xl border border-slate-200 bg-slate-50 p-5">
                <button
                    type="button"
                    disabled
                    aria-disabled="true"
                    className="cursor-not-allowed rounded-lg bg-slate-300 px-5 py-2.5 text-sm font-semibold text-white"
                >
                    Delete account
                </button>

                <p className="mt-3 text-sm text-slate-600">
                    Not available yet. If you need your account closed, contact DX Student Help and we
                    will do it for you, so nothing is lost by accident.
                </p>
            </div>
        </section>
    );
}
