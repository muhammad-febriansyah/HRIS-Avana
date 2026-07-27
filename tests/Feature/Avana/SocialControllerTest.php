<?php

use App\Models\Employee;
use App\Models\EotmCoreValue;
use App\Models\EotmPeriod;
use App\Models\EotmVote;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostComment;
use App\Models\SocialPostLike;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->employeeUser->employee;
    $this->tenantId = (int) $this->admin->tenant_id;
});

it('renders the sosmed screen with feed, categories and leaderboard', function (): void {
    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'likes_count' => 3,
        'comments_count' => 2,
    ]);

    actingAs($this->admin)
        ->get(route('avana.sosmed'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/sosmed/index', false)
            ->has('posts.data', 1)
            // Seeded by AvanaDemoSeeder, then editable from this screen.
            ->has('categories', 3)
            ->has('leaderboard', 1)
            ->where('leaderboard.0.points', 5 + 3 * 2 + 2 * 1)
            ->where('kpis.posts', 1)
            ->has('weights.post'));
});

it('forbids a plain employee from the moderation screen', function (): void {
    actingAs($this->employeeUser)
        ->get(route('avana.sosmed'))
        ->assertForbidden();
});

it('creates a category with its icon and colour', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sosmed.kategori.store'), [
            'name' => 'Kesehatan',
            'icon' => 'heart',
            'color' => '#DC2626',
            'description' => 'Tips sehat di kantor',
            'status' => 'active',
            'sort_order' => 3,
        ])
        ->assertRedirect();

    $category = SocialCategory::forTenant($this->tenantId)->where('name', 'Kesehatan')->firstOrFail();

    expect($category->slug)->toBe('kesehatan')
        ->and($category->icon)->toBe('heart')
        ->and($category->color)->toBe('#DC2626');
});

it('rejects a duplicate category name and a malformed colour', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sosmed.kategori.store'), [
            'name' => 'Ide Perbaikan',
            'icon' => 'lightbulb',
            'color' => '#F59E0B',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('name');

    actingAs($this->admin)
        ->post(route('avana.sosmed.kategori.store'), [
            'name' => 'Warna Salah',
            'icon' => 'star',
            'color' => 'merah',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('color');
});

it('updates a category', function (): void {
    $category = SocialCategory::forTenant($this->tenantId)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.sosmed.kategori.update', $category), [
            'name' => 'Ide Cemerlang',
            'icon' => 'rocket',
            'color' => '#0EA5E9',
            'status' => 'inactive',
            'sort_order' => 9,
        ])
        ->assertRedirect();

    $category->refresh();

    expect($category->name)->toBe('Ide Cemerlang')
        ->and($category->slug)->toBe('ide-cemerlang')
        ->and($category->status)->toBe('inactive');
});

it('keeps posts when their category is deleted', function (): void {
    $category = SocialCategory::forTenant($this->tenantId)->firstOrFail();

    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'social_category_id' => $category->id,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.sosmed.kategori.destroy', $category))
        ->assertRedirect();

    expect($post->refresh()->social_category_id)->toBeNull()
        ->and(SocialCategory::forTenant($this->tenantId)->count())->toBe(2);
});

it('hides a post from the wall and puts it back', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    actingAs($this->admin)
        ->put(route('avana.sosmed.post.visibility', $post))
        ->assertRedirect();

    expect($post->refresh()->status)->toBe(SocialPost::STATUS_HIDDEN);

    actingAs($this->admin)
        ->put(route('avana.sosmed.post.visibility', $post))
        ->assertRedirect();

    expect($post->refresh()->status)->toBe(SocialPost::STATUS_PUBLISHED);
});

it('deletes a post together with its likes and comments', function (): void {
    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
    ]);

    SocialPostLike::create([
        'social_post_id' => $post->id,
        'employee_id' => $this->employee->id,
        'tenant_id' => $this->tenantId,
    ]);

    SocialPostComment::create([
        'social_post_id' => $post->id,
        'employee_id' => $this->employee->id,
        'tenant_id' => $this->tenantId,
        'body' => 'Mantap',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.sosmed.post.destroy', $post))
        ->assertRedirect();

    expect(SocialPost::whereKey($post->id)->withTrashed()->exists())->toBeFalse()
        ->and(SocialPostLike::where('social_post_id', $post->id)->count())->toBe(0)
        ->and(SocialPostComment::withTrashed()->where('social_post_id', $post->id)->count())->toBe(0);
});

it('never moderates another tenant\'s post', function (): void {
    $otherTenant = Tenant::create([
        'name' => 'PT Tetangga',
        'slug' => 'tetangga',
        'status' => 'active',
    ]);

    $otherEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'TTG-001',
        'full_name' => 'Karyawan Tetangga',
        'status' => 'active',
        'join_date' => '2026-01-01',
    ]);

    $foreign = SocialPost::factory()->create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $otherEmployee->id,
        'body' => 'Punya tenant lain',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.sosmed.post.destroy', $foreign))
        ->assertNotFound();

    expect(SocialPost::whereKey($foreign->id)->exists())->toBeTrue();
});

it('exposes the EOTM panel with its period, standings and core values', function (): void {
    $period = EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_OPEN,
        'opens_at' => now(),
    ]);

    EotmVote::create([
        'tenant_id' => $this->tenantId,
        'eotm_period_id' => $period->id,
        'voter_employee_id' => $this->employee->id,
        'nominee_employee_id' => Employee::forTenant($this->tenantId)
            ->where('id', '!=', $this->employee->id)->firstOrFail()->id,
    ]);

    actingAs($this->admin)
        ->get(route('avana.sosmed'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('eotm.period.label', 'Juli 2026')
            ->where('eotm.period.is_open', true)
            ->where('eotm.period.total_votes', 1)
            ->has('eotm.standings', 1)
            ->where('eotm.standings.0.percent', 100)
            // Seeded by AvanaDemoSeeder, then editable from this screen.
            ->has('eotm.core_values', 5));
});

it('opens a voting period and closes any period already running', function (): void {
    $running = EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-06',
        'status' => EotmPeriod::STATUS_OPEN,
        'opens_at' => now(),
    ]);

    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.store'), [
            'period' => '2026-07',
            'title' => 'Employee of the Month Juli',
        ])
        ->assertRedirect();

    // Two open periods would let one employee vote twice in the same round.
    expect($running->refresh()->status)->toBe(EotmPeriod::STATUS_CLOSED)
        ->and(EotmPeriod::forTenant($this->tenantId)->open()->count())->toBe(1)
        ->and(EotmPeriod::forTenant($this->tenantId)->open()->first()->period)->toBe('2026-07');
});

it('rejects a malformed month and a duplicate period', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.store'), ['period' => 'Juli 2026'])
        ->assertSessionHasErrors('period');

    EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_CLOSED,
    ]);

    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.store'), ['period' => '2026-07'])
        ->assertSessionHasErrors('period');
});

it('closes a period and stamps the winner', function (): void {
    $period = EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_OPEN,
        'opens_at' => now(),
    ]);

    $nominee = Employee::forTenant($this->tenantId)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    EotmVote::create([
        'tenant_id' => $this->tenantId,
        'eotm_period_id' => $period->id,
        'voter_employee_id' => $this->employee->id,
        'nominee_employee_id' => $nominee->id,
    ]);

    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.close', $period))
        ->assertRedirect();

    $period->refresh();

    expect($period->status)->toBe(EotmPeriod::STATUS_CLOSED)
        ->and($period->winner_employee_id)->toBe($nominee->id)
        ->and($period->winner_votes)->toBe(1);
});

it('manages the core value master', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.value.store'), [
            'name' => 'Inisiatif',
            'icon' => 'rocket',
            'color' => '#0EA5E9',
        ])
        ->assertRedirect();

    $value = EotmCoreValue::forTenant($this->tenantId)->where('name', 'Inisiatif')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.value.store'), [
            'name' => 'Inisiatif',
            'icon' => 'rocket',
            'color' => '#0EA5E9',
        ])
        ->assertSessionHasErrors('name');

    actingAs($this->admin)
        ->delete(route('avana.sosmed.eotm.value.destroy', $value))
        ->assertRedirect();

    expect(EotmCoreValue::forTenant($this->tenantId)->where('name', 'Inisiatif')->exists())->toBeFalse();
});

it('never closes another tenant\'s period', function (): void {
    $otherTenant = Tenant::create([
        'name' => 'PT Seberang',
        'slug' => 'seberang',
        'status' => 'active',
    ]);

    $foreign = EotmPeriod::create([
        'tenant_id' => $otherTenant->id,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_OPEN,
    ]);

    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.close', $foreign))
        ->assertNotFound();

    expect($foreign->refresh()->status)->toBe(EotmPeriod::STATUS_OPEN);
});

it('reopens a closed period and clears the stamped winner', function (): void {
    $nominee = Employee::forTenant($this->tenantId)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    $period = EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_CLOSED,
        'winner_employee_id' => $nominee->id,
        'winner_votes' => 4,
    ]);

    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.reopen', $period))
        ->assertRedirect();

    $period->refresh();

    // The stamp is cleared so closing again recomputes from the votes.
    expect($period->status)->toBe(EotmPeriod::STATUS_OPEN)
        ->and($period->winner_employee_id)->toBeNull()
        ->and($period->winner_votes)->toBe(0);
});

it('never leaves two periods open when one is reopened', function (): void {
    $closed = EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-06',
        'status' => EotmPeriod::STATUS_CLOSED,
    ]);

    $running = EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_OPEN,
    ]);

    actingAs($this->admin)
        ->post(route('avana.sosmed.eotm.reopen', $closed))
        ->assertRedirect();

    expect($closed->refresh()->status)->toBe(EotmPeriod::STATUS_OPEN)
        ->and($running->refresh()->status)->toBe(EotmPeriod::STATUS_CLOSED)
        ->and(EotmPeriod::forTenant($this->tenantId)->open()->count())->toBe(1);
});
