<?php

namespace Database\Seeders;

use App\Domains\Billing\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'free', 'Free', 'Everything a learner needs to get unstuck.', 0, 'month',
                [
                    'Ask verified tutors for help',
                    'Lessons in your language',
                    'Notes, past papers and solutions',
                    'Study groups and community',
                    'Test yourself at five levels',
                ],
                5, 10, 0,
            ],
            [
                'plus', 'Plus', 'For learners who use DX every day.', 4900, 'month',
                [
                    'Unlimited tutor requests',
                    'More instant answers from the study assistant',
                    'Priority in the tutor queue',
                    'Download lessons for offline revision',
                    'Everything in Free',
                ],
                null, 50, 1,
            ],
            [
                'year', 'Plus, yearly', 'Two months free compared with paying monthly.', 49000, 'year',
                ['Everything in Plus', 'Two months free'],
                null, 50, 2,
            ],
        ];

        foreach ($plans as [$slug, $name, $description, $price, $interval, $features, $requests, $ai, $position]) {
            Plan::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'price_cents' => $price,
                'interval' => $interval,
                'features' => $features,
                'monthly_request_quota' => $requests,
                'monthly_ai_quota' => $ai,
                'is_public' => true,
                'position' => $position,
            ]);
        }
    }
}
