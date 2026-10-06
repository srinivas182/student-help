<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Platform settings, all editable from the admin console (SRS: ADM-05).
 * Defaults follow the Sprint 1 decision register.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'minimum_registration_age' => 13,              // D-01
            'request_escalation_hours' => 24,              // D-10
            'max_open_requests_per_student' => 3,          // D-13
            'auto_close_hours_after_resolved' => 72,       // D-14
            'minimum_tutors_per_subject' => 3,             // D-08
            'monetisation_mode' => 'free',                 // D-19: free|freemium
            'free_monthly_request_allowance' => 5,         // D-20, applies only in freemium
            'grandfather_existing_users' => true,          // D-21
            'max_attachment_mb' => 10,
            'max_attachments_per_request' => 5,
            'prohibited_words' => [],
            'policy_version' => '1.0',

            // AI study assistant — off until DX configures a provider
            'ai_mode' => 'off',
            'ai_provider' => 'anthropic',
            'ai_model' => '',
            'ai_fallback_after_hours' => 2,
            'ai_monthly_quota_per_student' => 10,
            'ai_daily_quota_per_student' => 5,
            'ai_monthly_budget_usd' => 50,
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
