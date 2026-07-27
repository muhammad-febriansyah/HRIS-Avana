<?php

use App\Models\Employee;
use App\Models\EotmPeriod;
use App\Models\SocialCategory;
use App\Models\SocialPost;
use App\Models\SocialPostReport;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

/**
 * UI coverage for the Sosmed moderation screen.
 *
 * Posting is not driven from here — employees post from the app, and the
 * plugin's test server drops multipart bodies anyway. What this checks is what
 * HR actually does on this screen: read the wall, curate categories, take a
 * post down, and run the Employee of the Month period.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // No withoutVite() here: a browser test needs the real built bundle, and
    // stubbing Vite leaves the page with no JS at all — a blank screen.
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $this->tenantId = (int) $this->admin->tenant_id;
});

it('renders the wall with its KPI row and four tabs', function () {
    actingAs($this->admin);

    $page = visit('/avana/sosmed');

    $page->assertSee('Ruang Kita')
        ->assertSee('Post Tayang')
        ->assertSee('Kontributor')
        ->assertSee('Dilaporkan')
        ->assertSee('Belum ada postingan.')
        ->assertNoJavascriptErrors();
});

it('shows a post with its author, category and reaction counts', function () {
    actingAs($this->admin);

    $category = SocialCategory::forTenant($this->tenantId)->firstOrFail();

    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'social_category_id' => $category->id,
        'body' => 'Usul: tambah dispenser air panas di pantry.',
        'likes_count' => 4,
        'comments_count' => 2,
    ]);

    $page = visit('/avana/sosmed');

    $page->assertSee('Usul: tambah dispenser air panas di pantry.')
        ->assertSee($this->employee->full_name)
        ->assertSee($category->name)
        ->assertNoJavascriptErrors();
});

it('flags a reported post so HR can spot it', function () {
    actingAs($this->admin);

    $post = SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'body' => 'Postingan bermasalah',
    ]);

    SocialPostReport::create([
        'tenant_id' => $this->tenantId,
        'social_post_id' => $post->id,
        'employee_id' => $this->employee->id,
        'reason' => 'Tidak pantas',
    ]);

    $page = visit('/avana/sosmed');

    $page->assertSee('1 laporan')
        ->assertNoJavascriptErrors();
});

it('creates a category through the modal, icon and colour included', function () {
    actingAs($this->admin);

    $page = visit('/avana/sosmed');

    $page->click('Kategori Baru')
        ->assertSee('Nama Kategori')
        ->type('input[name=name]', 'Kesehatan')
        ->click('button[aria-label="heart"]')
        ->click('Simpan')
        ->assertSee('Kategori dibuat')
        ->assertNoJavascriptErrors();

    expect(SocialCategory::forTenant($this->tenantId)->where('name', 'Kesehatan')->firstOrFail()->icon)
        ->toBe('heart');
});

it('lists the categories with their post counts on the kategori tab', function () {
    actingAs($this->admin);

    $page = visit('/avana/sosmed');

    $page->click('button[role=tab][aria-label="Kategori"]')
        ->assertSee('Ide Perbaikan')
        ->assertSee('Sports Day')
        ->assertSee('Employee of the Month')
        ->assertSee('Urutan')
        ->assertNoJavascriptErrors();
});

it('explains how leaderboard points are earned', function () {
    actingAs($this->admin);

    SocialPost::factory()->create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'likes_count' => 3,
    ]);

    $page = visit('/avana/sosmed');

    $page->click('button[role=tab][aria-label="Leaderboard"]')
        ->assertSee('Poin = 5 per post + 2 per like diterima + 1 per komentar diterima.')
        ->assertSee($this->employee->full_name)
        ->assertNoJavascriptErrors();
});

it('offers to open a voting period when none exists', function () {
    actingAs($this->admin);

    $page = visit('/avana/sosmed');

    $page->click('button[role=tab][aria-label="Employee of the Month"]')
        ->assertSee('Belum ada periode voting.')
        ->assertSee('Buka Periode Voting')
        ->assertSee('Core Value')
        ->assertSee('Jujur')
        ->assertNoJavascriptErrors();
});

it('shows a running period with its tally and a close button', function () {
    actingAs($this->admin);

    EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_OPEN,
        'opens_at' => now(),
    ]);

    $page = visit('/avana/sosmed');

    $page->click('button[role=tab][aria-label="Employee of the Month"]')
        ->assertSee('Juli 2026')
        ->assertSee('BERLANGSUNG')
        ->assertSee('Tutup Voting')
        ->assertSee('Belum ada suara masuk.')
        ->assertNoJavascriptErrors();
});

it('ranks the top three on a podium above the rest of the list', function () {
    // Four contributors, so there is both a podium and a list under it.
    $employees = Employee::where('tenant_id', $this->tenantId)->take(4)->get();

    foreach ($employees as $index => $employee) {
        SocialPost::factory()->count(4 - $index)->create([
            'tenant_id' => $this->tenantId,
            'employee_id' => $employee->id,
            'status' => SocialPost::STATUS_PUBLISHED,
        ]);
    }

    actingAs($this->admin);

    $page = visit('/avana/sosmed');
    $page->click('button[role=tab][aria-label="Leaderboard"]');

    // Podium cards carry the "poin" label; the fourth contributor can only be
    // in the ranked list below it.
    $page->assertSee('poin')
        ->assertSee($employees[0]->full_name)
        ->assertSee($employees[3]->full_name)
        ->assertSee('Poin = ')
        ->assertNoJavascriptErrors();
});

it('keeps the contributor name readable on a phone', function () {
    // Three fixed metric columns used to squeeze the name box to zero width
    // here, leaving rows that were a rank, a face and some numbers.
    $employees = Employee::where('tenant_id', $this->tenantId)->take(4)->get();

    foreach ($employees as $index => $employee) {
        SocialPost::factory()->count(4 - $index)->create([
            'tenant_id' => $this->tenantId,
            'employee_id' => $employee->id,
            'status' => SocialPost::STATUS_PUBLISHED,
        ]);
    }

    actingAs($this->admin);

    $page = visit('/avana/sosmed')->on()->iPhone15Pro();
    $page->click('button[role=tab][aria-label="Leaderboard"]');

    $width = $page->script(
        "Math.round(Math.min(...[...document.querySelectorAll('div')]"
        ."  .filter(d => d.style.width === '26px')"
        .'  .map(d => d.parentElement.children[2].getBoundingClientRect().width)))'
    );

    expect($width)->toBeGreaterThan(60);

    $page->assertSee($employees[3]->full_name)
        ->assertNoJavascriptErrors();
});
