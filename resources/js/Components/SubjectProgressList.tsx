import { Link } from '@inertiajs/react';

export interface SubjectProgress {
    id: number;
    name: string;
    total: number;
    completed: number;
    mastered: number;
    percent: number;
    hasContent: boolean;
}

export default function SubjectProgressList({ subjects }: { subjects: SubjectProgress[] }) {
    if (subjects.length === 0) {
        return null;
    }

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5">
            <div className="flex items-baseline justify-between">
                <h2 className="font-semibold text-slate-900">Your subjects</h2>
                <Link href={route('learn.index')} className="text-xs font-medium text-indigo-600">
                    All lessons
                </Link>
            </div>

            <ul className="mt-4 space-y-4">
                {subjects.map((subject) => (
                    <li key={subject.id}>
                        <div className="flex items-baseline justify-between gap-3">
                            <span className="truncate text-sm font-medium text-slate-800">
                                {subject.name}
                            </span>
                            <span className="shrink-0 text-xs text-slate-500">
                                {subject.hasContent
                                    ? `${subject.completed} of ${subject.total} lessons`
                                    : 'Lessons coming soon'}
                            </span>
                        </div>

                        {subject.hasContent && (
                            <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-full rounded-full bg-indigo-500 transition-all"
                                    style={{ width: `${Math.max(subject.percent, 2)}%` }}
                                />
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
