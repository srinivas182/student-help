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

            // Two-factor: app first, students never required
            '2fa_primary_method' => 'app',
            '2fa_backup_methods' => ['email'],
            '2fa_required_roles' => ['super_admin', 'admin', 'moderator'],
            '2fa_trusted_device_days' => 30,
            '2fa_bypass_hours' => 24,

            // Gateways are off until DX configures them
            'email_gateway_enabled' => false,
            'email_gateway_provider' => 'smtp',
            'email_from_address' => 'noreply@dxstudenthelp.co.za',
            'email_from_name' => 'DX Student Help',
            'sms_gateway_enabled' => false,
            'sms_gateway_provider' => 'clickatell',
            'sms_sender_id' => 'DXHelp',

            // AI Tutor generation costs and confirmation thresholds
            'ai_tutor_inherit_assistant' => true,
            'ai_tutor_otp_enabled' => true,
            'ai_tutor_otp_cost_threshold' => 2.0,
            'ai_tutor_otp_language_threshold' => 5,
            'ai_tutor_input_cost_per_million' => 3.0,
            'ai_tutor_output_cost_per_million' => 15.0,
            'usd_to_zar' => 18.0,

            // Payments: off until DX adds their PayFast credentials
            'payfast_merchant_id' => '',
            'payfast_merchant_key' => '',
            'payfast_passphrase' => '',
            'payfast_sandbox' => true,
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
