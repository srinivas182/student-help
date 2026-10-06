import { Head, useForm } from '@inertiajs/react';

interface Props {
    found: boolean;
    expired: boolean;
    alreadyDecided: boolean;
    status: string | null;
    token: string;
    learner: { name: string; age: number | null } | null;
    guardianName: string | null;
}

function Shell({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
            <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <p className="mb-6 text-sm font-semibold tracking-wide text-indigo-600">DX STUDENT HELP</p>
                {children}
            </div>
        </div>
    );
}

export default function Decide({ found, expired, alreadyDecided, status, token, learner, guardianName }: Props) {
    const { post, processing, setData } = useForm({ decision: 'approve' });

    const decide = (decision: 'approve' | 'decline') => {
        setData('decision', decision);
        post(route('consent.decide', token), { data: { decision } } as never);
    };

    if (!found) {
        return (
            <Shell>
                <Head title="Approval link not found" />
                <h1 className="text-xl font-semibold text-slate-900">We could not find this request</h1>
                <p className="mt-2 text-sm text-slate-600">
                    The link may have been mistyped. Please use the link in the email exactly as it was sent.
                </p>
            </Shell>
        );
    }

    if (alreadyDecided) {
        const approved = status === 'approved';

        return (
            <Shell>
                <Head title="Already answered" />
                <h1 className="text-xl font-semibold text-slate-900">
                    {approved ? 'Thank you, this account is approved' : 'This request was declined'}
                </h1>
                <p className="mt-2 text-sm text-slate-600">
                    {approved
                        ? `${learner?.name} can now ask tutors for help and send messages. All conversations stay inside the platform and our moderators can review them.`
                        : `${learner?.name} can still browse study resources, but cannot message tutors. They can send you the request again from their profile.`}
                </p>
            </Shell>
        );
    }

    if (expired) {
        return (
            <Shell>
                <Head title="Link expired" />
                <h1 className="text-xl font-semibold text-slate-900">This approval link has expired</h1>
                <p className="mt-2 text-sm text-slate-600">
                    Links are valid for 7 days. Ask {learner?.name} to send the request again from their
                    profile and you will receive a fresh email.
                </p>
            </Shell>
        );
    }

    return (
        <Shell>
            <Head title="Approve your child's account" />

            <h1 className="text-xl font-semibold text-slate-900">
                Hi {guardianName}, {learner?.name} needs your approval
            </h1>

            <p className="mt-3 text-sm text-slate-600">
                {learner?.name} ({learner?.age}) has registered on DX Student Help, which connects South
                African students with verified tutors for academic help. Because they are under 18, South
                African law requires your approval before they can message tutors.
            </p>

            <div className="mt-5 space-y-4 rounded-xl bg-slate-50 p-5 text-sm">
                <div>
                    <p className="font-medium text-slate-900">What we collect</p>
                    <p className="mt-1 text-slate-600">
                        Their name, email address, date of birth and the subjects they study.
                    </p>
                </div>
                <div>
                    <p className="font-medium text-slate-900">What we do with it</p>
                    <p className="mt-1 text-slate-600">
                        Match them with tutors who teach those subjects and show relevant study material.
                    </p>
                </div>
                <div>
                    <p className="font-medium text-slate-900">How we keep them safe</p>
                    <ul className="mt-1 list-disc space-y-1 pl-5 text-slate-600">
                        <li>Every tutor is verified by our team before they can help a student</li>
                        <li>All conversations stay inside the platform and moderators can review them</li>
                        <li>Phone numbers, emails and links are removed from messages automatically</li>
                        <li>You can withdraw this approval at any time</li>
                    </ul>
                </div>
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                <button
                    onClick={() => decide('approve')}
                    disabled={processing}
                    className="flex-1 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:bg-slate-300"
                >
                    Approve this account
                </button>
                <button
                    onClick={() => decide('decline')}
                    disabled={processing}
                    className="flex-1 rounded-lg border border-slate-300 px-6 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Decline
                </button>
            </div>

            <p className="mt-4 text-center text-xs text-slate-400">
                If you did not expect this email, you can safely decline or ignore it.
            </p>
        </Shell>
    );
}
