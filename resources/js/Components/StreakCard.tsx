export interface Streak {
    days: number;
    activeToday: boolean;
    atRisk: boolean;
    best: number;
    message: string;
}

/** Encouraging, never scolding: a missed day is not a failure worth a red badge. */
export default function StreakCard({ streak }: { streak: Streak }) {
    return (
        <div className="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <div
                className={`flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-full ${
                    streak.activeToday ? 'bg-amber-100' : 'bg-slate-100'
                }`}
            >
                <span className="text-lg font-semibold text-slate-900">{streak.days}</span>
                <span className="text-[10px] uppercase tracking-wide text-slate-500">
                    {streak.days === 1 ? 'day' : 'days'}
                </span>
            </div>

            <div className="min-w-0">
                <p className="text-sm font-medium text-slate-900">{streak.message}</p>
                {streak.best > streak.days && (
                    <p className="mt-0.5 text-xs text-slate-500">Your best so far is {streak.best} days.</p>
                )}
            </div>
        </div>
    );
}
