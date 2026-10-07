<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Messaging\Models\GatewayCredential;
use App\Domains\Messaging\Providers;
use App\Domains\Messaging\Services\MessageDispatcher;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GatewayController extends Controller
{
    public function __construct(private readonly MessageDispatcher $dispatcher)
    {
    }

    public function index(): Response
    {
        $saved = GatewayCredential::all()->keyBy(fn ($row) => $row->channel.':'.$row->provider);

        return Inertia::render('Admin/Gateways', [
            'channels' => collect(['email', 'sms', 'whatsapp'])->map(fn (string $channel) => [
                'channel' => $channel,
                'ready' => $this->dispatcher->isChannelReady($channel),
                'active' => $this->dispatcher->activeCredential($channel)?->provider,
                'providers' => collect(Providers::forChannel($channel))
                    ->map(function (array $meta, string $provider) use ($channel, $saved) {
                        $credential = $saved->get("{$channel}:{$provider}");

                        return [
                            'key' => $provider,
                            'name' => $meta['name'],
                            'note' => $meta['note'],
                            'fields' => $meta['fields'],
                            'isActive' => (bool) $credential?->is_active,
                            // Which fields are stored, never the values
                            'filled' => $credential?->filledFields() ?? [],
                            'verifiedAt' => $credential?->verified_at?->toDayDateTimeString(),
                            'lastError' => $credential?->last_error,
                        ];
                    })->values(),
            ]),
            'whatsappPreferred' => (bool) setting('whatsapp_preferred', false),
            'deliveries' => DB::table('message_deliveries')
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(fn ($row) => [
                    'channel' => $row->channel,
                    'provider' => $row->provider,
                    'recipient' => $row->recipient,
                    'purpose' => $row->purpose,
                    'status' => $row->status,
                    'error' => $row->error,
                    'at' => $row->created_at,
                ]),
            'stats' => [
                'sent' => DB::table('message_deliveries')->where('status', 'sent')->count(),
                'failed' => DB::table('message_deliveries')->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'provider' => ['required', 'string', 'max:32'],
            'credentials' => ['required', 'array'],
            'activate' => ['boolean'],
        ]);

        abort_unless(
            Providers::exists($validated['channel'], $validated['provider']),
            422,
            'That provider is not supported.',
        );

        $allowed = array_keys(Providers::fields($validated['channel'], $validated['provider']));

        $credential = GatewayCredential::firstOrNew([
            'channel' => $validated['channel'],
            'provider' => $validated['provider'],
        ]);

        // Blank fields keep whatever is already stored, so saving does not wipe a key
        $existing = $credential->exists ? $credential->secrets() : [];
        $incoming = collect($validated['credentials'])->only($allowed)
            ->map(fn ($value, $key) => filled($value) ? $value : ($existing[$key] ?? null))
            ->all();

        $credential->setSecrets($incoming);
        $credential->last_error = null;
        $credential->save();

        if ($request->boolean('activate')) {
            GatewayCredential::where('channel', $validated['channel'])
                ->where('id', '!=', $credential->id)
                ->update(['is_active' => false]);

            $credential->update(['is_active' => true]);
        }

        audit('gateway.configured', $credential, [
            'channel' => $validated['channel'],
            'provider' => $validated['provider'],
            'activated' => $request->boolean('activate'),
        ]);

        return back()->with('success', Providers::forChannel($validated['channel'])[$validated['provider']]['name'].' saved.');
    }

    public function deactivate(Request $request, GatewayCredential $credential): RedirectResponse
    {
        $credential->update(['is_active' => false]);

        audit('gateway.deactivated', $credential, ['channel' => $credential->channel]);

        return back()->with('success', 'Gateway switched off.');
    }

    /** Proves it works before anyone relies on it for guardian consent. */
    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'to' => ['required', 'string', 'max:120'],
        ]);

        if ($validated['channel'] === 'email') {
            abort_unless($this->dispatcher->isChannelReady('email'), 422, 'Configure and activate an email provider first.');

            $this->dispatcher->applyMailConfig();

            Notification::route('mail', $validated['to'])->notify(
                new \App\Notifications\OneTimeCodeNotification(
                    '000000',
                    'This is a test from DX Student Help. If you received it, your email gateway is working.',
                ),
            );

            DB::table('message_deliveries')->insert([
                'channel' => 'email',
                'provider' => $this->dispatcher->activeCredential('email')?->provider ?? 'none',
                'recipient' => $validated['to'],
                'purpose' => 'test',
                'status' => 'sent',
                'created_at' => now(),
            ]);

            return back()->with('success', "Test email sent to {$validated['to']}.");
        }

        $result = $this->dispatcher->sendOn(
            $validated['channel'],
            $validated['to'],
            'Test from DX Student Help. If you received this, your gateway is working.',
            'test',
        );

        return $result['sent']
            ? back()->with('success', 'Test message sent.')
            : back()->withErrors(['to' => $result['error'] ?? 'The gateway refused the message.']);
    }

    public function preferWhatsApp(Request $request): RedirectResponse
    {
        $validated = $request->validate(['preferred' => ['required', 'boolean']]);

        DB::table('settings')->updateOrInsert(
            ['key' => 'whatsapp_preferred'],
            ['value' => json_encode($validated['preferred']), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget('platform.settings');

        return back()->with('success', $validated['preferred']
            ? 'Short messages will go by WhatsApp where possible.'
            : 'Short messages will go by SMS.');
    }
}
