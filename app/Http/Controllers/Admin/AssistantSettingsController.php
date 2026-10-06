<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Assistant\Models\AiAnswer;
use App\Domains\Assistant\Services\AssistantSettings;
use App\Domains\Assistant\Services\QuotaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssistantSettingsController extends Controller
{
    private const PROVIDERS = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI (GPT)',
        'google' => 'Google (Gemini)',
    ];

    public function edit(AssistantSettings $settings, QuotaService $quota): Response
    {
        $spend = $quota->platformSpendThisMonth();

        return Inertia::render('Admin/Assistant', [
            'settings' => [
                'mode' => $settings->mode(),
                'provider' => $settings->provider(),
                'model' => $settings->model(),
                'hasApiKey' => filled($settings->apiKey()),
                'fallbackAfterHours' => $settings->fallbackAfterHours(),
                'monthlyQuota' => $settings->monthlyQuota(),
                'dailyQuota' => $settings->dailyQuota(),
                'monthlyBudgetUsd' => $settings->monthlyBudgetUsd(),
            ],
            'providers' => self::PROVIDERS,
            'usage' => [
                'answersThisMonth' => AiAnswer::where('created_at', '>=', now()->startOfMonth())
                    ->where('refused', false)->count(),
                'refusalsThisMonth' => AiAnswer::where('created_at', '>=', now()->startOfMonth())
                    ->where('refused', true)->count(),
                'escalations' => AiAnswer::where('escalated', true)->count(),
                'spendUsd' => round($spend, 2),
                'budgetUsd' => $settings->monthlyBudgetUsd(),
                'budgetUsedPercent' => $settings->monthlyBudgetUsd() > 0
                    ? (int) round($spend / $settings->monthlyBudgetUsd() * 100)
                    : 0,
                'helpfulRate' => $this->helpfulRate(),
                'topSubjects' => AiAnswer::select('curriculum_item_id', DB::raw('count(*) as total'))
                    ->whereNotNull('curriculum_item_id')
                    ->with('subject:id,name')
                    ->groupBy('curriculum_item_id')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get()
                    ->map(fn (AiAnswer $row) => [
                        'subject' => $row->subject?->name ?? 'Unknown',
                        'total' => (int) $row->total,
                    ]),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in([
                AssistantSettings::MODE_OFF,
                AssistantSettings::MODE_FALLBACK,
                AssistantSettings::MODE_ALWAYS,
            ])],
            'provider' => ['required', Rule::in(array_keys(self::PROVIDERS))],
            'model' => ['nullable', 'string', 'max:64'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'fallback_after_hours' => ['required', 'integer', 'between:0,72'],
            'monthly_quota_per_student' => ['required', 'integer', 'between:0,500'],
            'daily_quota_per_student' => ['required', 'integer', 'between:0,100'],
            'monthly_budget_usd' => ['required', 'numeric', 'between:0,10000'],
        ]);

        $map = [
            'ai_mode' => $validated['mode'],
            'ai_provider' => $validated['provider'],
            'ai_model' => $validated['model'] ?? '',
            'ai_fallback_after_hours' => $validated['fallback_after_hours'],
            'ai_monthly_quota_per_student' => $validated['monthly_quota_per_student'],
            'ai_daily_quota_per_student' => $validated['daily_quota_per_student'],
            'ai_monthly_budget_usd' => $validated['monthly_budget_usd'],
        ];

        // Only overwrite the key when a new one is supplied, so saving other
        // settings does not wipe it.
        if (filled($validated['api_key'] ?? null)) {
            $map['ai_api_key'] = encrypt($validated['api_key']);
        }

        foreach ($map as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        Cache::forget('platform.settings');

        audit('ai.settings_updated', null, [
            'mode' => $validated['mode'],
            'provider' => $validated['provider'],
        ]);

        return back()->with('success', 'Study assistant settings saved.');
    }

    private function helpfulRate(): ?int
    {
        $rated = AiAnswer::whereNotNull('helpful')->count();

        if ($rated === 0) {
            return null;
        }

        return (int) round(AiAnswer::where('helpful', 1)->count() / $rated * 100);
    }
}
