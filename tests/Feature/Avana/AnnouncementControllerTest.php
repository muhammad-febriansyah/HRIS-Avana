<?php

use App\Models\Announcement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create an announcement for the given tenant.
 */
function makeAnnouncement(int $tenantId, array $overrides = []): Announcement
{
    return Announcement::create(array_merge([
        'tenant_id' => $tenantId,
        'title' => fake()->sentence(4),
        'body' => fake()->paragraph(),
        'category' => 'Umum',
        'status' => 'draft',
        'pinned' => false,
    ], $overrides));
}

it('renders the announcement index with the expected props', function (): void {
    makeAnnouncement($this->tenant->id, ['status' => 'published', 'published_at' => now()]);

    actingAs($this->admin)
        ->get(route('avana.pengumuman'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/pengumuman/index', false)
            ->has('announcements.0', fn (Assert $row) => $row
                ->has('id')
                ->has('title')
                ->has('body')
                ->has('category')
                ->has('status')
                ->has('pinned')
                ->has('published_at')
                ->has('created_at'))
            ->has('kpis'));
});

it('orders the feed pinned first, then published, then draft', function (): void {
    $draft = makeAnnouncement($this->tenant->id, ['title' => 'Draft', 'status' => 'draft']);
    $published = makeAnnouncement($this->tenant->id, ['title' => 'Published', 'status' => 'published', 'published_at' => now()->subDay()]);
    $pinned = makeAnnouncement($this->tenant->id, ['title' => 'Pinned', 'status' => 'draft', 'pinned' => true]);

    actingAs($this->admin)
        ->get(route('avana.pengumuman'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('announcements.0.id', $pinned->id)
            ->where('announcements.1.id', $published->id)
            ->where('announcements.2.id', $draft->id));
});

it('only lists announcements that belong to the current tenant', function (): void {
    makeAnnouncement($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeAnnouncement($otherTenant->id);

    $tenantTotal = Announcement::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.pengumuman'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('announcements', $tenantTotal));
});

it('creates a draft announcement scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pengumuman.store'), [
            'title' => 'Libur Idul Adha',
            'body' => 'Kantor tutup pada tanggal tersebut.',
            'category' => 'Libur',
            'pinned' => true,
        ])
        ->assertRedirect(route('avana.pengumuman'))
        ->assertSessionHas('success');

    $announcement = Announcement::where('title', 'Libur Idul Adha')->firstOrFail();

    expect($announcement->tenant_id)->toBe($this->tenant->id);
    expect($announcement->status)->toBe('draft');
    expect($announcement->pinned)->toBeTrue();
    expect($announcement->published_at)->toBeNull();
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pengumuman.store'), [
            'title' => '',
            'body' => '',
        ])
        ->assertSessionHasErrors(['title', 'body']);
});

it('updates an existing announcement', function (): void {
    $announcement = makeAnnouncement($this->tenant->id, ['title' => 'Old', 'pinned' => false]);

    actingAs($this->admin)
        ->put(route('avana.pengumuman.update', $announcement), [
            'title' => 'New Title',
            'body' => 'Isi baru',
            'category' => 'Penting',
            'pinned' => true,
        ])
        ->assertRedirect(route('avana.pengumuman'))
        ->assertSessionHas('success');

    $announcement->refresh();

    expect($announcement->title)->toBe('New Title');
    expect($announcement->pinned)->toBeTrue();
});

it('publishes an announcement', function (): void {
    $announcement = makeAnnouncement($this->tenant->id, ['status' => 'draft']);

    actingAs($this->admin)
        ->post(route('avana.pengumuman.publish', $announcement))
        ->assertSessionHas('success');

    $announcement->refresh();

    expect($announcement->status)->toBe('published');
    expect($announcement->published_at)->not->toBeNull();
});

it('deletes an announcement', function (): void {
    $announcement = makeAnnouncement($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.pengumuman.destroy', $announcement))
        ->assertSessionHas('success');

    expect(Announcement::find($announcement->id))->toBeNull();
});

it('returns 404 when updating an announcement from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeAnnouncement($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.pengumuman.update', $foreign), [
            'title' => 'Hack',
            'body' => 'Hack body',
        ])
        ->assertNotFound();
});

it('returns 404 when publishing an announcement from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeAnnouncement($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.pengumuman.publish', $foreign))
        ->assertNotFound();
});

it('returns 404 when deleting an announcement from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeAnnouncement($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.pengumuman.destroy', $foreign))
        ->assertNotFound();

    expect(Announcement::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from managing announcements', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.pengumuman'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.pengumuman.store'), [
            'title' => 'Tidak Boleh',
            'body' => 'Isi',
        ])
        ->assertForbidden();
});
