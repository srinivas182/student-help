<?php

use Illuminate\Support\Facades\File;

/**
 * Attribution must appear for every role on every page, so it belongs in the
 * layouts rather than being pasted in page by page.
 */
it('shows attribution in all three layouts', function () {
    foreach ([
        'Layouts/AuthenticatedLayout.tsx',
        'Layouts/GuestLayout.tsx',
        'Components/Public/PublicLayout.tsx',
    ] as $layout) {
        // toContain takes further arguments as extra needles, not as a message
        expect(str_contains(File::get(resource_path("js/{$layout}")), 'BuiltBy'))
            ->toBeTrue("{$layout} is missing the attribution");
    }
});

it('shows attribution on pages that sit outside a layout', function () {
    foreach ([
        'Pages/Consent/Decide.tsx',
        'Pages/Onboarding/Step.tsx',
        'Pages/Onboarding/Subjects.tsx',
        'Pages/Billing/Checkout.tsx',
        'Pages/Progress/Certificate.tsx',
    ] as $page) {
        expect(str_contains(File::get(resource_path("js/{$page}")), 'BuiltBy'))
            ->toBeTrue("{$page} is missing the attribution");
    }
});

it('links to the right address, safely', function () {
    $component = File::get(resource_path('js/Components/BuiltBy.tsx'));

    expect($component)->toContain('https://www.mayuraconsultancy.com')
        ->and($component)->toContain('Mayura Consultancy Services')
        // Opens in a new tab, so it must not hand over window.opener
        ->and($component)->toContain('noopener noreferrer');
});
