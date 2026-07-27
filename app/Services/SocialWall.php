<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostLike;
use App\Support\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes and scoring for the employee social wall.
 *
 * Likes and comments both mutate a counter cache on the post, so every write
 * goes through here rather than through the models directly — that keeps the
 * cached counts and the rows they summarise in one transaction.
 */
final class SocialWall
{
    /**
     * Leaderboard weights: authoring an idea is worth more than reacting to
     * one, and a like on your post is worth more than a comment (a comment can
     * be a question, a like is an endorsement).
     */
    private const POINTS_PER_POST = 5;

    private const POINTS_PER_LIKE_RECEIVED = 2;

    private const POINTS_PER_COMMENT_RECEIVED = 1;

    /**
     * Toggle an employee's like. Returns the post's state afterwards.
     *
     * @return array{liked: bool, likes_count: int}
     */
    public function toggleLike(SocialPost $post, Employee $employee): array
    {
        return DB::transaction(function () use ($post, $employee): array {
            $existing = SocialPostLike::query()
                ->where('social_post_id', $post->id)
                ->where('employee_id', $employee->id)
                ->lockForUpdate()
                ->first();

            // `increment`/`decrement` already write the new value back onto the
            // model, so read it straight off rather than adjusting it again.
            if ($existing !== null) {
                $existing->delete();
                $post->decrement('likes_count');

                return ['liked' => false, 'likes_count' => max(0, (int) $post->likes_count)];
            }

            SocialPostLike::create([
                'social_post_id' => $post->id,
                'employee_id' => $employee->id,
                'tenant_id' => $post->tenant_id,
            ]);
            $post->increment('likes_count');

            return ['liked' => true, 'likes_count' => (int) $post->likes_count];
        });
    }

    public function comment(SocialPost $post, Employee $employee, string $body): SocialPostComment
    {
        return DB::transaction(function () use ($post, $employee, $body): SocialPostComment {
            $comment = SocialPostComment::create([
                'social_post_id' => $post->id,
                'employee_id' => $employee->id,
                'tenant_id' => $post->tenant_id,
                'body' => $body,
            ]);

            $post->increment('comments_count');

            Notifier::socialPostCommented($post, $employee);

            return $comment;
        });
    }

    public function deleteComment(SocialPostComment $comment): void
    {
        DB::transaction(function () use ($comment): void {
            $post = $comment->post()->first();

            $comment->delete();

            if ($post !== null && $post->comments_count > 0) {
                $post->decrement('comments_count');
            }
        });
    }

    /**
     * Delete a post and everything hanging off it, photo included.
     */
    public function deletePost(SocialPost $post): void
    {
        DB::transaction(function () use ($post): void {
            $post->deleteImageFile();
            $post->likes()->delete();
            $post->comments()->forceDelete();
            $post->forceDelete();
        });
    }

    /**
     * Top idea contributors, scored from what their posts earned.
     *
     * `$since` limits the window (this week / this month); null = all time.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(int $tenantId, ?Carbon $since = null, int $limit = 20): Collection
    {
        $posts = SocialPost::query()
            ->forTenant($tenantId)
            ->published()
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since))
            ->with('employee:id,full_name,photo_path')
            ->get(['id', 'employee_id', 'likes_count', 'comments_count']);

        return $posts->groupBy('employee_id')
            ->map(function (Collection $rows): array {
                $likes = (int) $rows->sum('likes_count');
                $comments = (int) $rows->sum('comments_count');
                $employee = $rows->first()->employee;

                return [
                    'employee_id' => (int) $rows->first()->employee_id,
                    'name' => $employee?->full_name ?? 'Karyawan',
                    'photo' => $employee?->photo_path,
                    'posts' => $rows->count(),
                    'likes' => $likes,
                    'comments' => $comments,
                    'points' => $rows->count() * self::POINTS_PER_POST
                        + $likes * self::POINTS_PER_LIKE_RECEIVED
                        + $comments * self::POINTS_PER_COMMENT_RECEIVED,
                ];
            })
            ->sortByDesc('points')
            ->values()
            ->take($limit)
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    /**
     * How the points are made up, so the UI can explain the ranking instead of
     * showing an unexplained number.
     *
     * @return array{post: int, like: int, comment: int}
     */
    public static function weights(): array
    {
        return [
            'post' => self::POINTS_PER_POST,
            'like' => self::POINTS_PER_LIKE_RECEIVED,
            'comment' => self::POINTS_PER_COMMENT_RECEIVED,
        ];
    }
}
