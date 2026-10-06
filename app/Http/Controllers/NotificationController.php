<?php

namespace App\Http\Controllers;

use App\Domains\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', [
            'notifications' => $request->user()->notifications()->paginate(20)->through(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'readAt' => $n->read_at?->diffForHumans(),
                'createdAt' => $n->created_at->diffForHumans(),
            ]),
            'preferences' => app(NotificationPreferences::class)->all($request->user()),
        ]);
    }

    /** Polled by the header bell. */
    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
            'items' => $request->user()->unreadNotifications()->limit(5)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'createdAt' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $request->user()->unreadNotifications()->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All caught up.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.database' => ['required', 'boolean'],
            'preferences.*.mail' => ['required', 'boolean'],
        ]);

        $allowed = array_keys(NotificationPreferences::EVENTS);

        $request->user()->update([
            'notification_preferences' => collect($validated['preferences'])
                ->only($allowed)
                ->map(fn ($p) => ['database' => (bool) $p['database'], 'mail' => (bool) $p['mail']])
                ->all(),
        ]);

        return back()->with('success', 'Notification preferences saved.');
    }
}
