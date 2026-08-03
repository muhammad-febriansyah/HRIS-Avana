<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EotmCoreValue;
use App\Models\EotmPeriod;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostReport;
use App\Services\EotmVoting;
use App\Services\SocialWall;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            // The composer shows the caller's own face next to the prompt, the
            // way the app's wall does.
            'me' => [
                'name' => $employee->full_name,
                'photo' => $this->photoUrl($employee->photo_path),
            ],
            'eotm' => $this->eotmPayload($employee),
            'filters' => ['category' => $categoryId],
        ]);
    }

    /**
     * The Employee of the Month panel: the open period (or the last closed one,
     * so a finished month still shows its winner), the caller's own vote, the
     * core values they may tag it with, and the running tally.
     *
     * @return array<string, mixed>
     */
    private function eotmPayload(Employee $employee): array
    {
        $voting = app(EotmVoting::class);
        $tenantId = (int) $employee->tenant_id;

        $period = $voting->openPeriod($tenantId)
            ?? EotmPeriod::forTenant($tenantId)->latest('period')->first();

        if ($period === null) {
            return ['period' => null, 'my_vote' => null, 'core_values' => [], 'standings' => []];
        }

        $myVote = $voting->voteOf($period, $employee);

        return [
            'period' => [
                'id' => $period->id,
                'label' => $period->label(),
                'title' => $period->title,
                'description' => $period->description,
                'is_open' => $period->isOpen(),
                'closes_at' => $period->closes_at?->toDateTimeString(),
                'winner' => $period->winner?->full_name,
                'winner_votes' => $period->winner_votes,
                'total_votes' => $period->votes()->count(),
            ],
            'my_vote' => $myVote === null ? null : [
                'nominee_employee_id' => $myVote->nominee_employee_id,
                'nominee' => $myVote->nominee?->full_name,
                'core_value_id' => $myVote->eotm_core_value_id,
                'core_value' => $myVote->coreValue?->name,
                'reason' => $myVote->reason,
            ],
            'core_values' => EotmCoreValue::forTenant($tenantId)
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'icon', 'color']),
            'standings' => $voting->standings($period)
                ->map(function (array $row) use ($employee): array {
                    $row['photo'] = $this->photoUrl($row['photo']);
                    $row['is_me'] = (int) $row['employee_id'] === (int) $employee->id;

                    return $row;
                })
                ->all(),
        ];
    }

    /**
     * Colleagues the caller may vote for — themselves excluded, since the
     * service rejects a self-vote anyway.
     */
    public function nominees(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $search = trim((string) $request->string('search'));

        return response()->json([
            'data' => Employee::forTenant($employee->tenant_id)
                ->where('status', 'active')
                ->where('id', '!=', $employee->id)
                ->when(
                    $search !== '',
                    fn ($query) => $query->where(fn ($inner) => $inner
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")),
                )
                ->orderBy('full_name')
                ->take(30)
                ->get(['id', 'full_name', 'employee_number', 'photo_path'])
                ->map(fn (Employee $row): array => [
                    'id' => $row->id,
                    'name' => $row->full_name,
                    'employee_number' => $row->employee_number,
                    'photo' => $this->photoUrl($row->photo_path),
                ]),
        ]);
    }

    /** Cast or change the caller's vote for the open period. */
    public function vote(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);
        $voting = app(EotmVoting::class);

        $period = $voting->openPeriod((int) $employee->tenant_id);

        abort_if($period === null, 404, 'Belum ada periode voting yang dibuka.');

        $data = $request->validate([
            'nominee_employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $employee->tenant_id),
            ],
            'eotm_core_value_id' => [
                'nullable',
                'integer',
                Rule::exists('eotm_core_values', 'id')->where('tenant_id', $employee->tenant_id),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'nominee_employee_id.required' => 'Pilih dulu karyawan yang kamu vote.',
        ]);

        $nominee = Employee::forTenant($employee->tenant_id)
            ->where('id', $data['nominee_employee_id'])
            ->firstOrFail();

        $voting->vote(
            $period,
            $employee,
            $nominee,
            $data['eotm_core_value_id'] ?? null,
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Vote kamu tersimpan');
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
                ? PrivateFile::store($request->file('image'), "social/{$employee->tenant_id}")
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
            isset($data['parent_id'])
                ? SocialPostComment::query()->where('id', $data['parent_id'])->first()
                : null,
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
        return PrivateFile::urlFor($path);
    }

    private function ensureSameTenant(int|string|null $tenantId, int|string|null $employeeTenantId): void
    {
        abort_if((int) $tenantId !== (int) $employeeTenantId, 404);
    }
}
