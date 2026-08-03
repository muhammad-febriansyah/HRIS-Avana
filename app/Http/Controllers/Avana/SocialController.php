<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\EotmCoreValue;
use App\Models\EotmPeriod;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostReport;
use App\Models\User;
use App\Services\EotmVoting;
use App\Services\SocialWall;
use App\Support\Notifier;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant admin side of the employee social wall: the category master, the
 * moderation feed, and the contributor leaderboard.
 *
 * Employees post from the mobile app; nothing is created here. HR's job is to
 * curate the categories and take down anything that should not stay up —
 * posts publish immediately, so moderation is after the fact.
 */
class SocialController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'social';

    /**
     * How many closed periods the roll of honour shows.
     */
    private const EOTM_HISTORY_MONTHS = 12;

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $posts = SocialPost::forTenant($tenantId)
            ->with(['employee:id,full_name,photo_path', 'category:id,name,icon,color'])
            ->withCount(['reports as open_reports_count' => fn ($query) => $query->whereNull('resolved_at')])
            // A reported post is the one HR needs to see first.
            ->when(
                $request->boolean('reported'),
                fn ($query) => $query->has('reports'),
            )
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where('social_category_id', $request->integer('category')),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SocialPost $post): array => $this->transformPost($post));

        $categories = SocialCategory::forTenant($tenantId)
            ->withCount('posts')
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
                'status' => $category->status,
                'sort_order' => $category->sort_order,
                'posts_count' => $category->posts_count,
            ]);

        return Inertia::render('avana/sosmed/index', [
            'posts' => $posts,
            'categories' => $categories,
            'filters' => $request->only(['category', 'status', 'reported']),
            'leaderboard' => app(SocialWall::class)->leaderboard($tenantId)
                ->map(function (array $row): array {
                    // The service returns the stored path; the podium needs a
                    // URL it can put in an <img>.
                    $row['photo'] = $row['photo'] !== null
                        ? PrivateFile::urlFor($row['photo'])
                        : null;

                    return $row;
                })
                ->all(),
            'weights' => SocialWall::weights(),
            'eotm' => $this->eotmPayload($tenantId),
            'kpis' => [
                'posts' => SocialPost::forTenant($tenantId)->published()->count(),
                'hidden' => SocialPost::forTenant($tenantId)->where('status', SocialPost::STATUS_HIDDEN)->count(),
                'categories' => $categories->count(),
                'contributors' => SocialPost::forTenant($tenantId)->published()->distinct('employee_id')->count('employee_id'),
                'this_month' => SocialPost::forTenant($tenantId)->published()
                    ->where('created_at', '>=', Carbon::now()->startOfMonth())
                    ->count(),
                'reported' => SocialPostReport::forTenant($tenantId)->open()->distinct('social_post_id')->count('social_post_id'),
            ],
        ]);
    }

    /**
     * Hide a post from the wall, or put a hidden one back.
     */
    public function toggleVisibility(Request $request, SocialPost $post): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $post);

        $hiding = $post->status === SocialPost::STATUS_PUBLISHED;

        $post->update([
            'status' => $hiding ? SocialPost::STATUS_HIDDEN : SocialPost::STATUS_PUBLISHED,
        ]);

        // HR has now looked at it either way, so the queue stops flagging it.
        SocialPostReport::where('social_post_id', $post->id)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        return back()->with('success', $hiding ? 'Post disembunyikan' : 'Post ditayangkan kembali');
    }

    public function destroyPost(Request $request, SocialPost $post): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $post);

        app(SocialWall::class)->deletePost($post);

        return back()->with('success', 'Post dihapus');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;
        $data = $request->validate($this->categoryRules($tenantId), $this->messages());

        SocialCategory::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon' => $data['icon'],
            'color' => $data['color'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Kategori dibuat');
    }

    public function updateCategory(Request $request, SocialCategory $socialCategory): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $socialCategory);

        $tenantId = (int) $request->user()->tenant_id;
        $data = $request->validate(
            $this->categoryRules($tenantId, (int) $socialCategory->getKey()),
            $this->messages(),
        );

        $socialCategory->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon' => $data['icon'],
            'color' => $data['color'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Kategori diperbarui');
    }

    /**
     * Soft delete a category; its posts stay up, uncategorised.
     */
    public function destroyCategory(Request $request, SocialCategory $socialCategory): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $socialCategory);

        SocialPost::forTenant((int) $request->user()->tenant_id)
            ->where('social_category_id', $socialCategory->id)
            ->update(['social_category_id' => null]);

        $socialCategory->delete();

        return back()->with('success', 'Kategori dihapus');
    }

    /**
     * Open a voting period for a month. Only one period exists per month, and
     * only one may be open at a time — opening a new one closes the previous.
     */
    public function storePeriod(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m', Rule::unique('eotm_periods', 'period')->where('tenant_id', $tenantId)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'closes_at' => ['nullable', 'date'],
        ], [
            'period.required' => 'Bulan voting wajib diisi.',
            'period.date_format' => 'Format bulan harus YYYY-MM, contoh 2026-07.',
            'period.unique' => 'Periode untuk bulan ini sudah ada.',
        ]);

        // Two open periods would let one employee vote twice in the same round.
        EotmPeriod::forTenant($tenantId)->open()->update(['status' => EotmPeriod::STATUS_CLOSED]);

        $period = EotmPeriod::create([
            'tenant_id' => $tenantId,
            'period' => $data['period'],
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => EotmPeriod::STATUS_OPEN,
            'opens_at' => now(),
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        Notifier::eotmPeriodOpened($period);

        return back()->with('success', 'Periode voting dibuka');
    }

    public function updatePeriod(Request $request, EotmPeriod $eotmPeriod): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        abort_if((int) $eotmPeriod->tenant_id !== (int) $request->user()->tenant_id, 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $eotmPeriod->update([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        return back()->with('success', 'Periode diperbarui');
    }

    /**
     * Close voting and stamp the winner onto the period.
     */
    public function closePeriod(Request $request, EotmPeriod $eotmPeriod): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        abort_if((int) $eotmPeriod->tenant_id !== (int) $request->user()->tenant_id, 404);

        $closed = app(EotmVoting::class)->close($eotmPeriod);

        return back()->with(
            'success',
            $closed->winner_employee_id !== null
                ? 'Voting ditutup. Pemenang: '.($closed->winner?->full_name ?? '-')
                : 'Voting ditutup tanpa suara masuk.',
        );
    }

    /**
     * Reopen a closed period.
     *
     * Exists because closing is one click and a month cannot be re-created —
     * `(tenant, period)` is unique. Reopening clears the stamped winner: the
     * result is recomputed from the votes when it is closed again.
     */
    public function reopenPeriod(Request $request, EotmPeriod $eotmPeriod): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        abort_if((int) $eotmPeriod->tenant_id !== (int) $request->user()->tenant_id, 404);

        $tenantId = (int) $request->user()->tenant_id;

        // Same rule as opening a new one: never two open at once.
        EotmPeriod::forTenant($tenantId)
            ->open()
            ->whereKeyNot($eotmPeriod->getKey())
            ->update(['status' => EotmPeriod::STATUS_CLOSED]);

        $eotmPeriod->update([
            'status' => EotmPeriod::STATUS_OPEN,
            'winner_employee_id' => null,
            'winner_votes' => 0,
        ]);

        return back()->with('success', 'Voting dibuka kembali');
    }

    public function storeCoreValue(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('eotm_core_values', 'name')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at'))],
            'icon' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.required' => 'Nama core value wajib diisi.',
            'name.unique' => 'Core value ini sudah ada.',
        ]);

        EotmCoreValue::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'icon' => $data['icon'],
            'color' => $data['color'],
            'status' => 'active',
            'sort_order' => EotmCoreValue::forTenant($tenantId)->count(),
        ]);

        return back()->with('success', 'Core value dibuat');
    }

    public function destroyCoreValue(Request $request, EotmCoreValue $eotmCoreValue): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        abort_if((int) $eotmCoreValue->tenant_id !== (int) $request->user()->tenant_id, 404);

        $eotmCoreValue->delete();

        return back()->with('success', 'Core value dihapus');
    }

    /**
     * The Employee of the Month panel: the latest period, its live standings,
     * and the core values a voter picks from.
     *
     * @return array<string, mixed>
     */
    private function eotmPayload(int $tenantId): array
    {
        $voting = app(EotmVoting::class);

        $period = $voting->openPeriod($tenantId)
            ?? EotmPeriod::forTenant($tenantId)->latest('period')->first();

        return [
            'period' => $period === null ? null : [
                'id' => $period->id,
                'period' => $period->period,
                'label' => $period->label(),
                'title' => $period->title,
                'description' => $period->description,
                'status' => $period->status,
                'is_open' => $period->isOpen(),
                'closes_at' => $period->closes_at?->toDateString(),
                'winner' => $period->winner?->full_name,
                'winner_votes' => $period->winner_votes,
                'total_votes' => $period->votes()->count(),
            ],
            'standings' => $period === null ? [] : $voting->standings($period)->all(),
            'core_values' => EotmCoreValue::forTenant($tenantId)
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'icon', 'color'])
                ->all(),
            'history' => EotmPeriod::forTenant($tenantId)
                ->where('status', EotmPeriod::STATUS_CLOSED)
                ->with('winner:id,full_name')
                ->latest('period')
                // A year of history is what a roll of honour needs; older
                // periods stay in the table and are reachable by report.
                ->take(self::EOTM_HISTORY_MONTHS)
                ->get()
                ->map(fn (EotmPeriod $row): array => [
                    'id' => $row->id,
                    'label' => $row->label(),
                    'winner' => $row->winner?->full_name,
                    'winner_votes' => $row->winner_votes,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPost(SocialPost $post): array
    {
        return [
            'id' => $post->id,
            'body' => $post->body,
            'image_url' => $post->imageUrl(),
            'status' => $post->status,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'reports_count' => (int) ($post->open_reports_count ?? 0),
            'author' => $post->employee?->full_name ?? 'Karyawan',
            'author_photo' => $post->employee?->photo_path,
            'category' => $post->category?->name,
            'category_icon' => $post->category?->icon,
            'category_color' => $post->category?->color,
            'created_at' => $post->created_at?->toDateTimeString(),
            'created_for_humans' => $post->created_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(int $tenantId, ?int $recordId = null): array
    {
        $name = Rule::unique('social_categories', 'name')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at'));

        if ($recordId !== null) {
            $name->ignore($recordId);
        }

        return [
            'name' => ['required', 'string', 'max:100', $name],
            'icon' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Kategori dengan nama ini sudah ada.',
            'icon.required' => 'Ikon wajib dipilih.',
            'color.required' => 'Warna wajib dipilih.',
            'color.regex' => 'Warna harus berupa kode heks, contoh #2F54C9.',
            'status.required' => 'Status wajib dipilih.',
        ];
    }

    private function ensureTenantOwnership(Request $request, SocialPost|SocialCategory $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}
