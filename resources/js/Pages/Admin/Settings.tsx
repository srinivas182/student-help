import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

interface Setting {
    key: string;
    label: string;
    type: 'integer' | 'string' | 'list';
    help: string | null;
    value: number | string | string[] | null;
}

export default function Settings({ settings }: { settings: Setting[] }) {
    const initial = Object.fromEntries(
        settings.map((setting) => [
            setting.key,
            setting.type === 'list' ? ((setting.value as string[]) ?? []) : setting.value,
        ]),
    );

    type SettingValue = number | string | string[];

    const { data, setData, put, processing, errors } = useForm<{
        settings: Record<string, SettingValue>;
    }>({ settings: initial as Record<string, SettingValue> });

    const update = (key: string, value: SettingValue) =>
        setData('settings', { ...data.settings, [key]: value });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Platform settings</h2>}>
            <Head title="Settings" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    put(route('admin.settings.update'));
                }}
                className="mx-auto max-w-3xl space-y-4 px-4 py-8 sm:px-6"
            >
                <p className="text-sm text-slate-600">
                    These rules govern how the platform behaves. Changes apply immediately to everyone.
                </p>

                {settings.map((setting) => (
                    <div key={setting.key} className="rounded-xl border border-slate-200 bg-white p-5">
                        <label htmlFor={setting.key} className="block text-sm font-medium text-slate-900">
                            {setting.label}
                        </label>
                        {setting.help && <p className="mt-1 text-xs text-slate-500">{setting.help}</p>}

                        {setting.type === 'list' ? (
                            <textarea
                                id={setting.key}
                                rows={4}
                                value={((data.settings[setting.key] as string[]) ?? []).join('\n')}
                                onChange={(e) =>
                                    update(
                                        setting.key,
                                        e.target.value.split('\n').map((line) => line.trim()).filter(Boolean),
                                    )
                                }
                                className="mt-3 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        ) : (
                            <input
                                id={setting.key}
                                type={setting.type === 'integer' ? 'number' : 'text'}
                                value={String(data.settings[setting.key] ?? '')}
                                onChange={(e) =>
                                    update(
                                        setting.key,
                                        setting.type === 'integer' ? Number(e.target.value) : e.target.value,
                                    )
                                }
                                className="mt-3 block w-full max-w-xs rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        )}

                        {errors[`settings.${setting.key}` as keyof typeof errors] && (
                            <p className="mt-2 text-xs text-rose-600">
                                {errors[`settings.${setting.key}` as keyof typeof errors] as string}
                            </p>
                        )}
                    </div>
                ))}

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Save settings
                </button>
            </form>
        </AuthenticatedLayout>
    );
}
