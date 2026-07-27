<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostReport;
use App\Services\SocialWall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Sosmed" for an employee on the web — the same wall the mobile app shows, for
 * people who work at a desk.
 *
 * Read, post, like, comment, reply and report. Moderation stays on the HR
 * screen; an employee may only delete their own post or comment.
 */
class EssSocialController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $categoryId = $request->integer('category') ?: null;

        $posts = SocialPost::forTenant($employee->tenant_id)
            ->published()
            ->when($categoryId !== null, fn ($query) => $query->where('social_category_id', $categoryId))
            ->with(['employee:id,full_name,photo_path', 'category:id,name,icon,color'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        // One query for the caller's likes on this page, rather than one per row.
        $likedIds = $employee->socialLikes()
            ->whereIn('social_post_id', collect($posts->items())->pluck('id'))
            ->pluck('social_post_id');

        return Inertia::render('avana/saya/sosmed', [
            'posts' => $posts->through(fn (SocialPost $post): array => [
                'id' => $post->id,
                'body' => $post->body,
                'image_url' => $post->imageUrl(),
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'liked' => $likedIds->contains($post->id),
                'is_mine' => (int) $post->employee_id === (int) $employee->id,
                'author' => $post->employee?->full_name ?? 'Karyawan',
                'author_photo' => $this->photoUrl($post->employee?->photo_path),
                'category' => $post->category?->name,
                'category_color' => $post->category?->color,
                'created_at' => $post->created_at?->toDateTimeString(),
                'edited' => $post->edited_at !== null,
            ]),
            'categories' => SocialCategory::forTenant($employee->tenant_id)
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'icon', 'color']),
            'leaderboard' => app(SocialWall::class)
                ->leaderboard((int) $employee->tenant_id)
                ->map(function (array $row) use ($employee): array {
                    $row['photo'] = $this->photoUrl($row['photo']);
                    $row['is_me'] = $row['employee_id'] === (int) $employee->id;

                    return $row;
                })
                ->all(),
            'weights' => SocialWall::weights(),
            'filters' => ['category' => $categoryId],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'social_category_id' => [
                'nullable',
                'integer',
                Rule::exists('social_categories', 'id')->where('tenant_id', $employee->tenant_id),
            ],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'body.required' => 'Tulis dulu isi postingannya.',
            'body.max' => 'Maksimal 500 karakter.',
            'image.mimes' => 'Foto harus JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        SocialPost::create([
            'tenant_id' => $employee->tenant_id,
            'social_category_id' => $data['social_category_id'] ?? null,
            'employee_id' => $employee->id,
            'body' => $data['body'],
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store("social/{$employee->tenant_id}", 'public')
                : null,
            'status' => SocialPost::STATUS_PUBLISHED,
        ]);

        return back()->with('success', 'Postingan terkirim');
    }

    public function destroy(Request $request, SocialPost $post): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee->tenant_id);
        abort_unless((int) $post->employee_id === (int) $employee->id, 403, 'Hanya pemilik postingan yang bisa menghapus.');

        app(SocialWall::class)->deletePost($post);

        return back()->with('success', 'Postingan dihapus');
    }

    public function toggleLike(Request $request, SocialPost $post): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee->tenant_id);
        abort_if($post->status !== SocialPost::STATUS_PUBLISHED, 404);

        app(SocialWall::class)->toggleLike($post, $employee);

        return back();
    }

    /**
     * The thread for one post, fetched on demand so the feed does not carry
     * every comment of every post it lists.
     */
    public function comments(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee->tenant_id);

        $data = $post->comments()
            ->topLevel()
            ->with([
                'employee:id,full_name,photo_path',
                'replies' => fn ($query) => $query->with('employee:id,full_name,photo_path')->orderBy('id'),
            ])
            ->orderBy('id')
            ->get()
            ->map(function (SocialPostComment $comment) use ($employee): array {
                $row = $this->transformComment($comment, (int) $employee->id);
                $row['replies'] = $comment->replies
                    ->map(fn (SocialPostComment $reply): array => $this->transformComment($reply, (int) $employee->id))
                    ->all();

                return $row;
            });

        return response()->json(['data' => $data]);
    }

    public function storeComment(Request $request, SocialPost $post): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee->tenant_id);
        abort_if($post->status !== SocialPost::STATUS_PUBLISHED, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'parent_id' => [
                'nullable',
                'integer',
                // Scoped to this post, or a reply would graft onto another thread.
                Rule::exists('social_post_comments', 'id')
                    ->where('social_post_id', $post->id)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'body.required' => 'Komentar tidak boleh kosong.',
        ]);

        app(SocialWall::class)->comment(
            $post,
            $employee,
            $data['body'],
            isset($data['parent_id']) ? SocialPostComment::find($data['parent_id']) : null,
        );

        return back();
    }

    public function destroyComment(Request $request, SocialPostComment $comment): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($comment->tenant_id, $employee->tenant_id);
        abort_unless((int) $comment->employee_id === (int) $employee->id, 403, 'Hanya pemilik komentar yang bisa menghapus.');

        app(SocialWall::class)->deleteComment($comment);

        return back();
    }

    /**
     * Flag a post for HR. Reporting twice is a no-op — the intent is recorded.
     */
    public function report(Request $request, SocialPost $post): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee->tenant_id);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        SocialPostReport::updateOrCreate(
            ['social_post_id' => $post->id, 'employee_id' => $employee->id],
            [
                'tenant_id' => $post->tenant_id,
                'reason' => $data['reason'] ?? null,
                'resolved_at' => null,
            ],
        );

        return back()->with('success', 'Laporan terkirim. Tim HR akan meninjau.');
    }

    /**
     * @return array<string, mixed>
     */
    private function transformComment(SocialPostComment $comment, int $viewerId): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => $comment->employee?->full_name ?? 'Karyawan',
            'author_photo' => $this->photoUrl($comment->employee?->photo_path),
            'is_mine' => (int) $comment->employee_id === $viewerId,
            'parent_id' => $comment->parent_id,
            'created_at' => $comment->created_at?->toDateTimeString(),
        ];
    }

    private function photoUrl(?string $path): ?string
    {
        return $path !== null ? Storage::disk('public')->url($path) : null;
    }

    private function ensureSameTenant(int|string|null $tenantId, int|string|null $employeeTenantId): void
    {
        abort_if((int) $tenantId !== (int) $employeeTenantId, 404);
    }
}
