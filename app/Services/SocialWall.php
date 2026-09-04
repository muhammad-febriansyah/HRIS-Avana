<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostLike;
use App\Support\Notifier;
use Carbon\CarbonInterface;
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
     * Publish a post, tagging whoever the author selected.
     *
     * @param  array{social_category_id: int|null, body: string, image_path: string|null}  $attributes
     * @param  array<int, int>  $mentionedEmployeeIds
     */
    public function createPost(Employee $employee, array $attributes, array $mentionedEmployeeIds = []): SocialPost
    {
        return DB::transaction(function () use ($employee, $attributes, $mentionedEmployeeIds): SocialPost {
            $post = SocialPost::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'social_category_id' => $attributes['social_category_id'],
                'body' => $attributes['body'],
                'image_path' => $attributes['image_path'],
                'status' => SocialPost::STATUS_PUBLISHED,
            ]);

            $tagged = $this->syncPostMentions($post, $employee, $mentionedEmployeeIds);

            Notifier::socialPostTagged($post, $employee, $tagged);

            return $post;
        });
    }

    /**
     * Edit a post, optionally replacing its tag list. Only employees newly
     * added to the set are notified — an existing tag does not re-announce
     * itself every time the caption is touched.
     *
     * `$mentionedEmployeeIds` is null when the caller did not send the field
     * at all (the mobile edit sheet does not touch tags today) — the existing
     * tag set is then left exactly as it was, rather than being wiped.
     *
     * @param  array{social_category_id: int|null, body: string, edited_at: CarbonInterface}  $attributes
     * @param  array<int, int>|null  $mentionedEmployeeIds
     * @param  array<string, mixed>  $imageAttributes  image_path when it changed, empty when untouched
     */
    public function updatePost(SocialPost $post, Employee $employee, array $attributes, ?array $mentionedEmployeeIds, array $imageAttributes = []): SocialPost
    {
        return DB::transaction(function () use ($post, $employee, $attributes, $mentionedEmployeeIds, $imageAttributes): SocialPost {
            $post->update($attributes + $imageAttributes);

            if ($mentionedEmployeeIds !== null) {
                $before = $post->mentions()->pluck('employee_id')->map(fn ($id): int => (int) $id)->all();
                $tagged = $this->syncPostMentions($post, $employee, $mentionedEmployeeIds);

                Notifier::socialPostTagged($post, $employee, array_values(array_diff($tagged, $before)));
            }

            return $post;
        });
    }

    /**
     * Replace a post's tag set wholesale — simpler than diffing adds/removes,
     * and a post is tagged by one person at a time so there is no concurrent
     * edit to preserve. Tagging yourself is dropped here, not just at the
     * notify step — a self-tag has no reader to reach, so it is not worth
     * keeping around as a row.
     *
     * @param  array<int, int>  $mentionedEmployeeIds
     * @return array<int, int> the tag set actually saved (deduped, self excluded)
     */
    private function syncPostMentions(SocialPost $post, Employee $employee, array $mentionedEmployeeIds): array
    {
        $ids = $this->dedupeMentionIds($mentionedEmployeeIds, $employee->id);

        $post->mentions()->delete();

        if ($ids !== []) {
            $post->mentions()->createMany(array_map(
                fn (int $id): array => ['employee_id' => $id, 'tenant_id' => $post->tenant_id],
                $ids,
            ));
        }

        return $ids;
    }

    /**
     * Clean a raw id list into a tag set: deduped and excluding the tagger
     * themselves.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function dedupeMentionIds(array $ids, int $excludeId): array
    {
        return array_values(array_unique(array_filter(
            $ids,
            fn ($id): bool => (int) $id !== $excludeId,
        )));
    }

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

    /**
     * @param  array<int, int>  $mentionedEmployeeIds
     */
    public function comment(
        SocialPost $post,
        Employee $employee,
        string $body,
        ?SocialPostComment $parent = null,
        array $mentionedEmployeeIds = [],
    ): SocialPostComment {
        return DB::transaction(function () use ($post, $employee, $body, $parent, $mentionedEmployeeIds): SocialPostComment {
            // One level only: replying to a reply lands under the same
            // top-level comment, so a thread never nests off the screen.
            $parentId = $parent?->parent_id ?? $parent?->id;

            // Name the person only when the indent cannot: a reply to a reply
            // sits beside the one it answers, so without this the reader has to
            // guess which of the two it belongs to.
            $replyToId = $parent?->parent_id !== null ? $parent?->employee_id : null;

            $comment = SocialPostComment::create([
                'social_post_id' => $post->id,
                'parent_id' => $parentId,
                'reply_to_employee_id' => $replyToId,
                'employee_id' => $employee->id,
                'tenant_id' => $post->tenant_id,
                'body' => $body,
            ]);

            $post->increment('comments_count');

            if ($parent !== null) {
                Notifier::socialCommentReplied($parent, $employee, $post);
            }

            Notifier::socialPostCommented($post, $employee);

            $ids = $this->dedupeMentionIds($mentionedEmployeeIds, $employee->id);

            if ($ids !== []) {
                $comment->mentions()->createMany(array_map(
                    fn (int $id): array => ['employee_id' => $id, 'tenant_id' => $comment->tenant_id],
                    $ids,
                ));

                Notifier::socialCommentTagged($comment, $employee, $ids);
            }

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
        // Grouped and ranked in SQL, not in PHP. Pulling every post into memory
        // to sum it here is fine on a demo wall and ruinous on a real one — the
        // cost would grow with the tenant's entire post history on every open.
        $rows = SocialPost::query()
            ->forTenant($tenantId)
            ->published()
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since))
            ->groupBy('employee_id')
            ->orderByDesc('points')
            ->limit($limit)
            ->get([
                'employee_id',
                DB::raw('count(*) as posts'),
                DB::raw('coalesce(sum(likes_count), 0) as likes'),
                DB::raw('coalesce(sum(comments_count), 0) as comments'),
                DB::raw(sprintf(
                    'count(*) * %d + coalesce(sum(likes_count), 0) * %d + coalesce(sum(comments_count), 0) * %d as points',
                    self::POINTS_PER_POST,
                    self::POINTS_PER_LIKE_RECEIVED,
                    self::POINTS_PER_COMMENT_RECEIVED,
                )),
            ]);

        // One extra query for the names, over the winners only.
        $employees = Employee::query()
            ->whereIn('id', $rows->pluck('employee_id'))
            ->get(['id', 'full_name', 'photo_path'])
            ->keyBy('id');

        return $rows->values()->map(function ($row, int $index) use ($employees): array {
            $employee = $employees->get($row->employee_id);

            return [
                'rank' => $index + 1,
                'employee_id' => (int) $row->employee_id,
                'name' => $employee?->full_name ?? 'Karyawan',
                'photo' => $employee?->photo_path,
                'posts' => (int) $row->posts,
                'likes' => (int) $row->likes,
                'comments' => (int) $row->comments,
                'points' => (int) $row->points,
            ];
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
