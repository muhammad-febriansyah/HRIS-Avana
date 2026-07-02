<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Employee self-service company announcements (published only). */
class AnnouncementController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = Announcement::forTenant($employee->tenant_id)
            ->where('status', 'published')
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'body', 'category', 'pinned', 'published_at'])
            ->map(fn (Announcement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'category' => $a->category,
                'pinned' => (bool) $a->pinned,
                'published_at' => $a->published_at instanceof Carbon ? $a->published_at->toIso8601String() : $a->published_at,
            ]);

        return response()->json(['data' => $data]);
    }
}
