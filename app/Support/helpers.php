<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('setting')) {
    /** Platform settings, admin-editable (SRS: ADM-05). */
    function setting(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('platform.settings', 300, function () {
            return DB::table('settings')->pluck('value', 'key')
                ->map(fn ($value) => json_decode($value, true))
                ->all();
        });

        return $settings[$key] ?? $default;
    }
}

if (! function_exists('audit')) {
    /** Append-only audit trail (SRS: NFR-16). */
    function audit(string $action, mixed $subject = null, array $context = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'context' => json_encode($context),
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}

if (! function_exists('user')) {
    function user(): ?User
    {
        return auth()->user();
    }
}
