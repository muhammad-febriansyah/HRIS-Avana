<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee tagged on a social post comment (or reply). Deleted along with
 * the comment (cascade).
 */
final class SocialPostCommentMention extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<SocialPostComment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(SocialPostComment::class, 'social_post_comment_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
