<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Employee self-service company announcements (published only), with read
 * confirmations and comments so the feed is interactive.
 */
class AnnouncementController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $announcements = Announcement::forTenant($employee->tenant_id)
            ->where('status', 'published')
            ->withCount(['reads', 'comments'])
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $readIds = AnnouncementRead::where('employee_id', $employee->id)
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->pluck('announcement_id')
            ->all();

        $data = $announcements->map(fn (Announcement $a): array => $this->shape($a, in_array($a->id, $readIds, true)));

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, int $announcement): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $model = $this->resolve($employee, $announcement)->loadCount(['reads', 'comments']);

        $isRead = AnnouncementRead::where('announcement_id', $model->id)
            ->where('employee_id', $employee->id)
            ->exists();

        return response()->json(['data' => $this->shape($model, $isRead)]);
    }

    /**
     * Confirm the caller has read an announcement (idempotent).
     */
    public function markRead(Request $request, int $announcement): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $model = $this->resolve($employee, $announcement);

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $model->id, 'employee_id' => $employee->id],
            ['tenant_id' => $employee->tenant_id, 'read_at' => now()],
        );

        return response()->json([
            'message' => 'Ditandai sudah dibaca',
            'data' => [
                'is_read' => true,
                'read_count' => AnnouncementRead::where('announcement_id', $model->id)->count(),
            ],
        ]);
    }

    public function comments(Request $request, int $announcement): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $model = $this->resolve($employee, $announcement);

        $data = $model->comments()
            ->with('employee:id,full_name,photo_path')
            ->orderBy('id')
            ->get()
            ->map(fn (AnnouncementComment $c): array => $this->commentShape($c));

        return response()->json(['data' => $data]);
    }

    public function storeComment(Request $request, int $announcement): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $model = $this->resolve($employee, $announcement);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $comment = AnnouncementComment::create([
            'announcement_id' => $model->id,
            'employee_id' => $employee->id,
            'tenant_id' => $employee->tenant_id,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $this->commentShape($comment->setRelation('employee', $employee))], 201);
    }

    /**
     * Resolve a published announcement within the caller's tenant, 404ing on a
     * draft, another tenant's row, or a missing id.
     */
    private function resolve(Employee $employee, int $id): Announcement
    {
        return Announcement::forTenant($employee->tenant_id)
            ->where('status', 'published')
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Announcement $a, bool $isRead): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'category' => $a->category,
            'pinned' => (bool) $a->pinned,
            'published_at' => $a->published_at instanceof Carbon ? $a->published_at->toIso8601String() : $a->published_at,
            'is_read' => $isRead,
            'read_count' => (int) ($a->reads_count ?? 0),
            'comment_count' => (int) ($a->comments_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentShape(AnnouncementComment $c): array
    {
        $employee = $c->employee;

        return [
            'id' => $c->id,
            'body' => $c->body,
            'author' => [
                'id' => $employee?->id,
                'name' => $employee?->full_name,
                'photo_url' => $employee?->photo_path !== null
                    ? Storage::disk('public')->url($employee->photo_path)
                    : null,
            ],
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
