<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostLike;
use App\Models\SocialPostReport;
use App\Services\SocialWall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The employee social wall as the mobile app sees it: a paginated feed, posting
 * with an optional photo, like toggling, comments, and the contributor
 * leaderboard.
 *
 * Everything is scoped to the caller's tenant. Deleting is allowed on your own
 * post/comment; HR takes anything else down from the web moderation screen.
 */
class SocialController extends Controller
{
    use ResolvesApiEmployee;

    public function categories(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = SocialCategory::forTenant($employee->tenant_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (SocialCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'color' => $category->color,
                'description' => $category->description,
            ]);

        return response()->json(['data' => $data]);
    }

    /**
     * The wall, newest first. `category` filters; `page` paginates.
     */
    public function feed(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $posts = SocialPost::forTenant($employee->tenant_id)
            ->published()
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where('social_category_id', $request->integer('category')),
            )
            ->with(['employee:id,full_name,photo_path', 'category:id,name,icon,color'])
            ->latest('id')
            ->paginate(min(50, max(5, $request->integer('per_page', 15))));

        $likedIds = $this->likedPostIds($employee, collect($posts->items())->pluck('id'));

        return response()->json([
            'data' => collect($posts->items())
                ->map(fn (SocialPost $post): array => $this->transformPost($post, $employee, $likedIds))
                ->all(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'social_category_id' => [
                'nullable',
                'integer',
                Rule::exists('social_categories', 'id')->where('tenant_id', $employee->tenant_id),
            ],
            'body' => ['required', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'body.required' => 'Tulis dulu isi postingannya.',
            'body.max' => 'Maksimal 500 karakter.',
            'image.mimes' => 'Foto harus JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $path = $request->hasFile('image')
            ? $request->file('image')->store("social/{$employee->tenant_id}", 'public')
            : null;

        $post = SocialPost::create([
            'tenant_id' => $employee->tenant_id,
            'social_category_id' => $data['social_category_id'] ?? null,
            'employee_id' => $employee->id,
            'body' => $data['body'],
            'image_path' => $path,
            'status' => SocialPost::STATUS_PUBLISHED,
        ]);

        $post->load(['employee:id,full_name,photo_path', 'category:id,name,icon,color']);

        return response()->json([
            'message' => 'Postingan terkirim',
            'data' => $this->transformPost($post, $employee, collect()),
        ], 201);
    }

    /**
     * One post on its own — the detail screen, and what a comment notification
     * deep-links to.
     */
    public function show(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);
        abort_if($post->status !== SocialPost::STATUS_PUBLISHED, 404);

        $post->load(['employee:id,full_name,photo_path', 'category:id,name,icon,color']);

        return response()->json([
            'data' => $this->transformPost(
                $post,
                $employee,
                $this->likedPostIds($employee, collect([$post->id])),
            ),
        ]);
    }

    /**
     * Edit your own post.
     *
     * Likes and comments survive: the post keeps its id, so nobody loses the
     * conversation because the author fixed a typo. `remove_image` clears the
     * photo without replacing it.
     */
    public function update(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);
        abort_unless((int) $post->employee_id === (int) $employee->id, 403, 'Hanya pemilik postingan yang bisa mengubah.');
        abort_if($post->status !== SocialPost::STATUS_PUBLISHED, 404);

        $data = $request->validate([
            'social_category_id' => [
                'nullable',
                'integer',
                Rule::exists('social_categories', 'id')->where('tenant_id', $employee->tenant_id),
            ],
            'body' => ['required', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'body.required' => 'Tulis dulu isi postingannya.',
            'body.max' => 'Maksimal 500 karakter.',
        ]);

        $attributes = [
            'body' => $data['body'],
            'social_category_id' => $data['social_category_id'] ?? null,
            'edited_at' => now(),
        ];

        if ($request->hasFile('image')) {
            $post->deleteImageFile();
            $attributes['image_path'] = $request->file('image')
                ->store("social/{$employee->tenant_id}", 'public');
        } elseif ($request->boolean('remove_image')) {
            $post->deleteImageFile();
            $attributes['image_path'] = null;
        }

        $post->update($attributes);
        $post->load(['employee:id,full_name,photo_path', 'category:id,name,icon,color']);

        return response()->json([
            'message' => 'Postingan diperbarui',
            'data' => $this->transformPost(
                $post,
                $employee,
                $this->likedPostIds($employee, collect([$post->id])),
            ),
        ]);
    }

    /**
     * Delete your own post. HR removes anyone else's from the web screen.
     */
    public function destroy(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);
        abort_unless((int) $post->employee_id === (int) $employee->id, 403, 'Hanya pemilik postingan yang bisa menghapus.');

        app(SocialWall::class)->deletePost($post);

        return response()->json(['message' => 'Postingan dihapus']);
    }

    public function toggleLike(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);
        abort_if($post->status !== SocialPost::STATUS_PUBLISHED, 404);

        return response()->json(['data' => app(SocialWall::class)->toggleLike($post, $employee)]);
    }

    public function comments(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);

        $data = $post->comments()
            ->with('employee:id,full_name,photo_path')
            ->orderBy('id')
            ->get()
            ->map(fn (SocialPostComment $comment): array => $this->transformComment($comment, $employee));

        return response()->json(['data' => $data]);
    }

    public function storeComment(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);
        abort_if($post->status !== SocialPost::STATUS_PUBLISHED, 404);

        $data = $request->validate(
            ['body' => ['required', 'string', 'max:500']],
            ['body.required' => 'Komentar tidak boleh kosong.'],
        );

        $comment = app(SocialWall::class)->comment($post, $employee, $data['body']);
        $comment->load('employee:id,full_name,photo_path');

        return response()->json([
            'message' => 'Komentar terkirim',
            'data' => $this->transformComment($comment, $employee),
        ], 201);
    }

    public function destroyComment(Request $request, SocialPostComment $comment): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($comment->tenant_id, $employee);
        abort_unless((int) $comment->employee_id === (int) $employee->id, 403, 'Hanya pemilik komentar yang bisa menghapus.');

        app(SocialWall::class)->deleteComment($comment);

        return response()->json(['message' => 'Komentar dihapus']);
    }

    /**
     * Flag a post for HR. Reporting twice is a no-op rather than an error —
     * the employee's intent is already recorded.
     */
    public function report(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        SocialPostReport::updateOrCreate(
            [
                'social_post_id' => $post->id,
                'employee_id' => $employee->id,
            ],
            [
                'tenant_id' => $post->tenant_id,
                'reason' => $data['reason'] ?? null,
                'resolved_at' => null,
            ],
        );

        return response()->json([
            'message' => 'Laporan terkirim. Tim HR akan meninjau postingan ini.',
        ]);
    }

    /**
     * Top contributors. `range` = all|month|week|today.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $range = (string) $request->string('range', 'all');
        $since = match ($range) {
            'today' => Carbon::now()->startOfDay(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            default => null,
        };

        // The screen shows a top-20 by default and asks for the full list only
        // when the employee taps "lihat semua"; 100 is the ceiling either way.
        $limit = min(100, max(5, $request->integer('limit', 20)));

        $rows = app(SocialWall::class)->leaderboard((int) $employee->tenant_id, $since, $limit);

        return response()->json([
            'data' => $rows->map(function (array $row) use ($employee): array {
                $row['photo'] = $row['photo'] !== null
                    ? Storage::disk('public')->url($row['photo'])
                    : null;
                $row['is_me'] = $row['employee_id'] === (int) $employee->id;

                return $row;
            })->all(),
            'meta' => [
                'range' => $range,
                'limit' => $limit,
                'weights' => SocialWall::weights(),
            ],
        ]);
    }

    /**
     * Which of these posts the caller has already liked, in one query.
     *
     * @param  Collection<int, int>  $postIds
     * @return Collection<int, int>
     */
    private function likedPostIds(Employee $employee, Collection $postIds): Collection
    {
        if ($postIds->isEmpty()) {
            return collect();
        }

        return SocialPostLike::query()
            ->where('employee_id', $employee->id)
            ->whereIn('social_post_id', $postIds)
            ->pluck('social_post_id');
    }

    /**
     * @param  Collection<int, int>  $likedIds
     * @return array<string, mixed>
     */
    private function transformPost(SocialPost $post, Employee $viewer, Collection $likedIds): array
    {
        return [
            'id' => $post->id,
            'body' => $post->body,
            'image_url' => $post->imageUrl(),
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'liked' => $likedIds->contains($post->id),
            'is_mine' => (int) $post->employee_id === (int) $viewer->id,
            'author' => $post->employee?->full_name ?? 'Karyawan',
            'author_photo' => $post->employee?->photo_path !== null
                ? Storage::disk('public')->url($post->employee->photo_path)
                : null,
            'category' => $post->category?->name,
            'category_icon' => $post->category?->icon,
            'category_color' => $post->category?->color,
            'created_at' => $post->created_at?->toDateTimeString(),
            'edited' => $post->edited_at !== null,
            'social_category_id' => $post->social_category_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformComment(SocialPostComment $comment, Employee $viewer): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => $comment->employee?->full_name ?? 'Karyawan',
            'author_photo' => $comment->employee?->photo_path !== null
                ? Storage::disk('public')->url($comment->employee->photo_path)
                : null,
            'is_mine' => (int) $comment->employee_id === (int) $viewer->id,
            'created_at' => $comment->created_at?->toDateTimeString(),
        ];
    }

    private function ensureSameTenant(int|string|null $tenantId, Employee $employee): void
    {
        abort_if((int) $tenantId !== (int) $employee->tenant_id, 404);
    }
}
