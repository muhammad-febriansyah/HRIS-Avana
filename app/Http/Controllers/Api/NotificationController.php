<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Employee self-service notifications (scoped to the authenticated user). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $data = Notification::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at'])
            ->map(fn (Notification $n): array => [
                'id' => $n->id,
                'type' => $n->type,
                'payload' => array_merge(
                    ['title' => $n->title, 'message' => $n->body],
                    is_array($n->data) ? $n->data : [],
                ),
                'is_read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $data,
            'meta' => ['unread' => Notification::where('user_id', $userId)->whereNull('read_at')->count()],
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if((int) $notification->user_id !== (int) $request->user()->id, 404);

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Ditandai dibaca']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua ditandai dibaca']);
    }
}
