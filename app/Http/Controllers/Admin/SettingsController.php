<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform settings (SRS: ADM-05). Every rule the business might want to change
 * without a release lives here.
 */
class SettingsController extends Controller
{
    private const EDITABLE = [
        'minimum_registration_age' => ['label' => 'Minimum registration age', 'type' => 'integer', 'help' => 'Learners younger than this cannot register.'],
        'request_escalation_hours' => ['label' => 'Escalate unaccepted requests after (hours)', 'type' => 'integer', 'help' => 'Requests no tutor accepts in this time are escalated to administrators.'],
        'max_open_requests_per_student' => ['label' => 'Maximum open requests per student', 'type' => 'integer', 'help' => 'Protects tutor capacity.'],
        'auto_close_hours_after_resolved' => ['label' => 'Auto-close resolved requests after (hours)', 'type' => 'integer', 'help' => 'Closes a request if the student does not confirm.'],
        'minimum_tutors_per_subject' => ['label' => 'Minimum tutors before a subject goes live', 'type' => 'integer', 'help' => 'Subjects below this are hidden from students.'],
        'max_attachment_mb' => ['label' => 'Maximum attachment size (MB)', 'type' => 'integer'],
        'max_attachments_per_request' => ['label' => 'Maximum attachments per request', 'type' => 'integer'],
        'prohibited_words' => ['label' => 'Prohibited words', 'type' => 'list', 'help' => 'Messages containing these are flagged for a moderator. One word or phrase per line.'],
        'policy_version' => ['label' => 'Policy version', 'type' => 'string', 'help' => 'Recorded against each consent so you can prove which version was agreed.'],
        'monetisation_mode' => ['label' => 'Monetisation', 'type' => 'string', 'help' => 'free or freemium. While free, nobody is limited or charged.'],
        'free_monthly_request_allowance' => ['label' => 'Free plan: requests per month', 'type' => 'integer'],
        'payfast_merchant_id' => ['label' => 'PayFast merchant ID', 'type' => 'string'],
        'payfast_merchant_key' => ['label' => 'PayFast merchant key', 'type' => 'string'],
        'payfast_passphrase' => ['label' => 'PayFast passphrase', 'type' => 'string', 'help' => 'Set this in your PayFast dashboard and paste it here. Payments are rejected without a matching signature.'],
    ];

    public function edit(): Response
    {
        $stored = DB::table('settings')->pluck('value', 'key')
            ->map(fn ($value) => json_decode($value, true));

        return Inertia::render('Admin/Settings', [
            'settings' => collect(self::EDITABLE)
                ->map(fn ($meta, $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'],
                    'help' => $meta['help'] ?? null,
                    'value' => $stored[$key] ?? null,
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.minimum_registration_age' => ['required', 'integer', 'between:5,21'],
            'settings.request_escalation_hours' => ['required', 'integer', 'between:1,168'],
            'settings.max_open_requests_per_student' => ['required', 'integer', 'between:1,50'],
            'settings.auto_close_hours_after_resolved' => ['required', 'integer', 'between:1,336'],
            'settings.minimum_tutors_per_subject' => ['required', 'integer', 'between:1,20'],
            'settings.max_attachment_mb' => ['required', 'integer', 'between:1,50'],
            'settings.max_attachments_per_request' => ['required', 'integer', 'between:1,20'],
            'settings.prohibited_words' => ['array'],
            'settings.prohibited_words.*' => ['string', 'max:60'],
            'settings.policy_version' => ['required', 'string', 'max:16'],
            'settings.monetisation_mode' => ['nullable', 'in:free,freemium'],
            'settings.free_monthly_request_allowance' => ['nullable', 'integer', 'between:0,100'],
            'settings.payfast_merchant_id' => ['nullable', 'string', 'max:32'],
            'settings.payfast_merchant_key' => ['nullable', 'string', 'max:64'],
            'settings.payfast_passphrase' => ['nullable', 'string', 'max:64'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            if (! array_key_exists($key, self::EDITABLE)) {
                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        Cache::forget('platform.settings');

        audit('settings.updated', null, ['keys' => array_keys($validated['settings'])]);

        return back()->with('success', 'Settings saved. Changes take effect immediately.');
    }
}
