const STYLES: Record<string, string> = {
    open: 'bg-amber-100 text-amber-800',
    escalated: 'bg-rose-100 text-rose-800',
    assigned: 'bg-sky-100 text-sky-800',
    resolved: 'bg-emerald-100 text-emerald-800',
    closed: 'bg-slate-100 text-slate-600',
    cancelled: 'bg-slate-100 text-slate-500',
};

const LABELS: Record<string, string> = {
    open: 'Waiting for a tutor',
    escalated: 'Escalated to admin',
    assigned: 'Tutor helping',
    resolved: 'Awaiting your confirmation',
    closed: 'Closed',
    cancelled: 'Cancelled',
};

export default function StatusBadge({ status, plain = false }: { status: string; plain?: boolean }) {
    return (
        <span
            className={`inline-flex shrink-0 rounded-full px-3 py-1 text-xs font-medium ${
                STYLES[status] ?? 'bg-slate-100 text-slate-600'
            }`}
        >
            {plain ? status : (LABELS[status] ?? status)}
        </span>
    );
}
