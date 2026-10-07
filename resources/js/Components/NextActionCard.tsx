import { Link } from '@inertiajs/react';

export interface NextAction {
    kind: string;
    tone: 'positive' | 'default' | 'muted';
    title: string;
    body: string | null;
    action: string;
    url: string;
}

const TONES: Record<string, string> = {
    positive: 'border-emerald-200 bg-emerald-50',
    default: 'border-slate-200 bg-white',
    muted: 'border-slate-200 bg-slate-50',
};

const BUTTONS: Record<string, string> = {
    positive: 'bg-emerald-600 hover:bg-emerald-500',
    default: 'bg-indigo-600 hover:bg-indigo-500',
    muted: 'bg-slate-700 hover:bg-slate-600',
};

export default function NextActionCard({ action }: { action: NextAction }) {
    return (
        <div
            className={`flex flex-col gap-3 rounded-xl border p-5 sm:flex-row sm:items-center sm:justify-between ${
                TONES[action.tone] ?? TONES.default
            }`}
        >
            <div className="min-w-0">
                <p className="font-medium text-slate-900">{action.title}</p>
                {action.body && (
                    <p className="mt-0.5 truncate text-sm text-slate-600">{action.body}</p>
                )}
            </div>

            <Link
                href={action.url}
                className={`shrink-0 rounded-lg px-5 py-2.5 text-center text-sm font-semibold text-white transition ${
                    BUTTONS[action.tone] ?? BUTTONS.default
                }`}
            >
                {action.action}
            </Link>
        </div>
    );
}
