<?php

namespace App\Http\Controllers;

use App\Domains\Engagement\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search)
    {
    }

    /** Used by the header search box as the person types. */
    public function quick(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return response()->json(
            $this->search->search($request->user(), $validated['q'] ?? '', 4),
        );
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return Inertia::render('Search', [
            'results' => $this->search->search($request->user(), $validated['q'] ?? '', 12),
        ]);
    }
}
