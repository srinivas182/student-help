<?php

use App\Domains\Messaging\Models\GatewayCredential;
use App\Domains\Messaging\Providers;
use App\Domains\Messaging\Services\MessageDispatcher;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
});

it('offers South African SMS providers alongside the international one', function () {
    expect(array_keys(Providers::SMS))
        ->toBe(['clickatell', 'smsportal', 'bulksms', 'winsms', 'twilio'])
        ->and(array_keys(Providers::EMAIL))
        ->toBe(['smtp', 'postmark', 'ses', 'resend', 'brevo'])
        ->and(array_keys(Providers::WHATSAPP))
        ->toBe(['meta_cloud', 'twilio_whatsapp']);
});

it('stores credentials encrypted and never exposes them', function () {
    $this->actingAs($this->admin)->post(route('admin.gateways.save'), [
        'channel' => 'sms',
        'provider' => 'clickatell',
        'credentials' => ['api_key' => 'secret-key-value'],
        'activate' => true,
    ])->assertRedirect();

    $credential = GatewayCredential::firstOrFail();

    expect($credential->secrets()['api_key'])->toBe('secret-key-value')
        ->and($credential->getRawOriginal('credentials'))->not->toContain('secret-key-value')
        ->and($credential->toArray())->not->toHaveKey('credentials')
        ->and($credential->filledFields())->toBe(['api_key' => true]);
});

it('keeps an existing secret when the field is left blank', function () {
    $this->actingAs($this->admin)->post(route('admin.gateways.save'), [
        'channel' => 'sms',
        'provider' => 'clickatell',
        'credentials' => ['api_key' => 'original-key'],
        'activate' => true,
    ]);

    $this->actingAs($this->admin)->post(route('admin.gateways.save'), [
        'channel' => 'sms',
        'provider' => 'clickatell',
        'credentials' => ['api_key' => ''],
    ]);

    expect(GatewayCredential::firstOrFail()->secrets()['api_key'])->toBe('original-key');
});

it('activates only one provider per channel', function () {
    foreach (['clickatell', 'bulksms'] as $provider) {
        $this->actingAs($this->admin)->post(route('admin.gateways.save'), [
            'channel' => 'sms',
            'provider' => $provider,
            'credentials' => $provider === 'clickatell'
                ? ['api_key' => 'k']
                : ['token_id' => 'id', 'token_secret' => 's'],
            'activate' => true,
        ]);
    }

    expect(GatewayCredential::where('channel', 'sms')->where('is_active', true)->count())->toBe(1)
        ->and(app(MessageDispatcher::class)->activeCredential('sms')->provider)->toBe('bulksms');
});

it('is not ready until every required field is filled', function () {
    $credential = new GatewayCredential(['channel' => 'sms', 'provider' => 'smsportal', 'is_active' => true]);
    $credential->setSecrets(['client_id' => 'abc', 'api_secret' => '']);
    $credential->save();

    expect(app(MessageDispatcher::class)->isChannelReady('sms'))->toBeFalse();

    $credential->setSecrets(['client_id' => 'abc', 'api_secret' => 'xyz']);
    $credential->save();

    expect(app(MessageDispatcher::class)->isChannelReady('sms'))->toBeTrue();
});

it('rejects a provider that is not on the list', function () {
    $this->actingAs($this->admin)->post(route('admin.gateways.save'), [
        'channel' => 'sms',
        'provider' => 'some-unknown-gateway',
        'credentials' => ['api_key' => 'k'],
    ])->assertStatus(422);
});

it('sends an SMS through the configured gateway and logs it', function () {
    Http::fake(['platform.clickatell.com/*' => Http::response(['messages' => [['apiMessageId' => 'ref-1']]], 202)]);

    $credential = new GatewayCredential(['channel' => 'sms', 'provider' => 'clickatell', 'is_active' => true]);
    $credential->setSecrets(['api_key' => 'k']);
    $credential->save();

    $result = app(MessageDispatcher::class)->sendShortMessage('082 123 4567', 'Test message', 'test');

    expect($result['sent'])->toBeTrue()
        ->and($result['reference'])->toBe('ref-1');

    $log = DB::table('message_deliveries')->first();

    expect($log->status)->toBe('sent')
        ->and($log->provider)->toBe('clickatell')
        // Recipient is partially masked in the log
        ->and($log->recipient)->not->toBe('082 123 4567');

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), '+27821234567'));
});

it('records a failure with the gateway error rather than failing silently', function () {
    Http::fake(['platform.clickatell.com/*' => Http::response(['error' => 'bad key'], 401)]);

    $credential = new GatewayCredential(['channel' => 'sms', 'provider' => 'clickatell', 'is_active' => true]);
    $credential->setSecrets(['api_key' => 'wrong']);
    $credential->save();

    $result = app(MessageDispatcher::class)->sendOn('sms', '0821234567', 'Test', 'test');

    expect($result['sent'])->toBeFalse()
        ->and($result['error'])->toContain('401')
        ->and(DB::table('message_deliveries')->where('status', 'failed')->count())->toBe(1)
        ->and($credential->fresh()->last_error)->toContain('401');
});

it('prefers WhatsApp for short messages only when DX turns it on', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wa-1']]], 200),
        'platform.clickatell.com/*' => Http::response(['messages' => [['apiMessageId' => 'sms-1']]], 202),
    ]);

    $sms = new GatewayCredential(['channel' => 'sms', 'provider' => 'clickatell', 'is_active' => true]);
    $sms->setSecrets(['api_key' => 'k']);
    $sms->save();

    $whatsapp = new GatewayCredential(['channel' => 'whatsapp', 'provider' => 'meta_cloud', 'is_active' => true]);
    $whatsapp->setSecrets(['phone_number_id' => '123', 'token' => 't', 'business_id' => 'b']);
    $whatsapp->save();

    // Off by default: still goes by SMS
    expect(app(MessageDispatcher::class)->sendShortMessage('0821234567', 'Hello')['reference'])->toBe('sms-1');

    $this->actingAs($this->admin)->post(route('admin.gateways.whatsapp'), ['preferred' => true]);

    expect(app(MessageDispatcher::class)->sendShortMessage('0821234567', 'Hello')['reference'])->toBe('wa-1');
});

it('refuses to send when nothing is configured', function () {
    $result = app(MessageDispatcher::class)->sendOn('sms', '0821234567', 'Test');

    expect($result['sent'])->toBeFalse()
        ->and($result['error'])->toContain('No sms gateway is configured');
});

it('keeps gateway settings away from non-administrators', function () {
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)->get(route('admin.gateways'))->assertForbidden();
});
