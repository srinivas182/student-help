<?php

namespace App\Http\Controllers;

use App\Domains\Content\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** What a student or teacher sees: only announcements aimed at them. */
class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $announcements = Announcement::live()
            ->with('author:id,first_name,last_name')
            ->latest('publish_at')
            ->get()
            ->filter(fn (Announcement $a) => $a->appliesTo($user))
            ->take(50)
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'priority' => $a->priority,
                'author' => $a->author?->name,
                'publishedAt' => $a->publish_at?->diffForHumans(),
            ])
            ->values();

        return Inertia::render('Announcements/Index', [
            'announcements' => $announcements,
        ]);
    }
}
