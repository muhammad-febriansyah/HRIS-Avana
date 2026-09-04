<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostCommentMention;
use App\Models\SocialPostLike;
use App\Models\SocialPostMention;
use App\Models\SocialPostReport;
use App\Services\SocialWall;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /**
     * How far back "trending" looks. A week is long enough for a Friday post to
     * still rank on Monday, short enough that the tab keeps changing.
     */
    private const TRENDING_DAYS = 7;

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

        $trending = $request->string('sort')->toString() === 'trending';

        $posts = SocialPost::forTenant($employee->tenant_id)
            ->published()
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where('social_category_id', $request->integer('category')),
            )
            // Trending is the last week only: without the window a post that
            // once went viral would sit at the top of the wall forever.
            ->when($trending, fn ($query) => $query
                ->where('created_at', '>=', Carbon::now()->subDays(self::TRENDING_DAYS))
                ->orderByRaw('(likes_count + comments_count) desc'))
            ->latest('id')
            ->with([
                'employee:id,full_name,photo_path',
                'category:id,name,icon,color',
                'mentions.employee:id,full_name,photo_path',
            ])
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
                'sort' => $trending ? 'trending' : 'latest',
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
            'mentioned_employee_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_employee_ids.*' => $this->mentionRule($employee),
        ], [
            'body.required' => 'Tulis dulu isi postingannya.',
            'body.max' => 'Maksimal 500 karakter.',
            'image.mimes' => 'Foto harus JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto maksimal 5 MB.',
            'mentioned_employee_ids.max' => 'Maksimal 20 orang yang bisa ditag.',
        ]);

        $path = $request->hasFile('image')
            ? PrivateFile::store($request->file('image'), "social/{$employee->tenant_id}")
            : null;

        $post = app(SocialWall::class)->createPost(
            $employee,
            [
                'social_category_id' => $data['social_category_id'] ?? null,
                'body' => $data['body'],
                'image_path' => $path,
            ],
            $data['mentioned_employee_ids'] ?? [],
        );

        $post->load(['employee:id,full_name,photo_path', 'category:id,name,icon,color', 'mentions.employee:id,full_name,photo_path']);

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

        $post->load(['employee:id,full_name,photo_path', 'category:id,name,icon,color', 'mentions.employee:id,full_name,photo_path']);

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
            'mentioned_employee_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_employee_ids.*' => $this->mentionRule($employee),
        ], [
            'body.required' => 'Tulis dulu isi postingannya.',
            'body.max' => 'Maksimal 500 karakter.',
            'image.mimes' => 'Foto harus JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto maksimal 5 MB.',
            'mentioned_employee_ids.max' => 'Maksimal 20 orang yang bisa ditag.',
        ]);

        $attributes = [
            'body' => $data['body'],
            'social_category_id' => $data['social_category_id'] ?? null,
            'edited_at' => now(),
        ];

        $imageAttributes = [];

        if ($request->hasFile('image')) {
            $post->deleteImageFile();
            $imageAttributes['image_path'] = $request->file('image')
                ->store("social/{$employee->tenant_id}", PrivateFile::DISK);
        } elseif ($request->boolean('remove_image')) {
            $post->deleteImageFile();
            $imageAttributes['image_path'] = null;
        }

        $post = app(SocialWall::class)->updatePost(
            $post,
            $employee,
            $attributes,
            $request->has('mentioned_employee_ids') ? ($data['mentioned_employee_ids'] ?? []) : null,
            $imageAttributes,
        );
        $post->load(['employee:id,full_name,photo_path', 'category:id,name,icon,color', 'mentions.employee:id,full_name,photo_path']);

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
            ->topLevel()
            ->with([
                'employee:id,full_name,photo_path',
                'mentions.employee:id,full_name,photo_path',
                'replies' => fn ($query) => $query
                    ->with(['employee:id,full_name,photo_path', 'replyTo:id,full_name', 'mentions.employee:id,full_name,photo_path'])
                    ->orderBy('id'),
            ])
            ->orderBy('id')
            ->get()
            ->map(function (SocialPostComment $comment) use ($employee): array {
                $row = $this->transformComment($comment, $employee);
                $row['replies'] = $comment->replies
                    ->map(fn (SocialPostComment $reply): array => $this->transformComment($reply, $employee))
                    ->all();

                return $row;
            });

        return response()->json(['data' => $data]);
    }

    public function storeComment(Request $request, SocialPost $post): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureSameTenant($post->tenant_id, $employee);
        abort_if($post->status !== SocialPost::STATUS_PUBLISHED, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'parent_id' => [
                'nullable',
                'integer',
                // Scoped to this post: a parent from another post would graft
                // the thread onto the wrong conversation.
                Rule::exists('social_post_comments', 'id')
                    ->where('social_post_id', $post->id)
                    ->whereNull('deleted_at'),
            ],
            'mentioned_employee_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_employee_ids.*' => $this->mentionRule($employee),
        ], [
            'body.required' => 'Komentar tidak boleh kosong.',
            'mentioned_employee_ids.max' => 'Maksimal 20 orang yang bisa ditag.',
        ]);

        $parent = isset($data['parent_id'])
            ? SocialPostComment::find($data['parent_id'])
            : null;

        $comment = app(SocialWall::class)->comment(
            $post,
            $employee,
            $data['body'],
            $parent,
            $data['mentioned_employee_ids'] ?? [],
        );
        $comment->load(['employee:id,full_name,photo_path', 'mentions.employee:id,full_name,photo_path']);

        return response()->json([
            'message' => $parent === null ? 'Komentar terkirim' : 'Balasan terkirim',
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
                    ? PrivateFile::urlFor($row['photo'])
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
                ? PrivateFile::urlFor($post->employee->photo_path)
                : null,
            'category' => $post->category?->name,
            'category_icon' => $post->category?->icon,
            'category_color' => $post->category?->color,
            'created_at' => $post->created_at?->toDateTimeString(),
            'edited' => $post->edited_at !== null,
            'social_category_id' => $post->social_category_id,
            'tagged' => $this->transformMentions($post->mentions),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, SocialPostMention>|\Illuminate\Database\Eloquent\Collection<int, SocialPostCommentMention>  $mentions
     * @return array<int, array<string, mixed>>
     */
    private function transformMentions(\Illuminate\Database\Eloquent\Collection $mentions): array
    {
        return $mentions
            ->map(fn ($mention): array => [
                'id' => $mention->employee_id,
                'name' => $mention->employee?->full_name ?? 'Karyawan',
                'photo' => $mention->employee?->photo_path !== null
                    ? PrivateFile::urlFor($mention->employee->photo_path)
                    : null,
            ])
            ->all();
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
                ? PrivateFile::urlFor($comment->employee->photo_path)
                : null,
            'is_mine' => (int) $comment->employee_id === (int) $viewer->id,
            'parent_id' => $comment->parent_id,
            'reply_to' => $comment->replyTo?->full_name,
            'created_at' => $comment->created_at?->toDateTimeString(),
            'tagged' => $this->transformMentions($comment->mentions),
        ];
    }

    private function ensureSameTenant(int|string|null $tenantId, Employee $employee): void
    {
        abort_if((int) $tenantId !== (int) $employee->tenant_id, 404);
    }

    /**
     * Validation rules for one id in `mentioned_employee_ids`: only an active
     * colleague in the caller's own tenant can be tagged.
     *
     * @return array<int, mixed>
     */
    private function mentionRule(Employee $employee): array
    {
        return [
            'integer',
            Rule::exists('employees', 'id')
                ->where('tenant_id', $employee->tenant_id)
                ->where('status', 'active'),
        ];
    }
}
