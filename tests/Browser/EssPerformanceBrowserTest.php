<?php

use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\PerformanceReviewWorkflow;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

/**
 * UI coverage for "Kinerja Saya".
 *
 * The self-assessment form is driven from the browser on purpose: the endpoint
 * binds on the review's opaque `public_id`, and the page previously posted the
 * numeric `id` into a hand-written URL. Every feature test called the endpoint
 * with the right key directly, so the whole flow 404'd in production while the
 * suite stayed green. Only a test that clicks the actual button catches that.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;

    PerformanceReview::query()->delete();

    $this->cycle = PerformanceCycle::create([
        'tenant_id' => $this->employee->tenant_id,
        'name' => 'Siklus Penilaian Mandiri',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'active',
    ]);

    $this->review = PerformanceReview::create([
        'tenant_id' => $this->employee->tenant_id,
        'cycle_id' => $this->cycle->id,
        'employee_id' => $this->employee->id,
        'status' => 'self_review',
    ]);
});

it('submits a self-assessment from the page and hands the review to the manager', function () {
    actingAs($this->user);

    $page = visit('/avana/saya/kinerja');

    $page->assertSee('Kinerja Saya')
        ->click('Isi Penilaian Mandiri')
        ->assertSee('Nilai Mandiri (0–100)')
        ->fill('input[type=number]', '85')
        ->click('Kirim Penilaian Mandiri')
        ->assertSee('Penilaian mandiri terkirim')
        ->assertNoJavascriptErrors();

    $this->review->refresh();

    expect((float) $this->review->self_score)->toBe(85.0);
    expect($this->review->status)->toBe('manager_review');
});

it('hides the self-assessment button once the cycle is closed', function () {
    $this->review->update(['status' => 'pending']);
    $this->cycle->update(['status' => 'closed']);

    actingAs($this->user);

    visit('/avana/saya/kinerja')
        ->assertSee('Kinerja Saya')
        ->assertDontSee('Isi Penilaian Mandiri')
        ->assertNoJavascriptErrors();
});

it('creates a cycle as a draft and edits it without a status field', function () {
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    actingAs($admin);

    $page = visit('/avana/kinerja');

    $page->click('Tambah Siklus')
        ->assertSee('Siklus baru dibuat sebagai draf')
        // The status select is gone: draft → active → closed is the status
        // endpoint's job, and offering it here promised something the server
        // silently ignored.
        ->assertDontSee('Status *')
        ->assertNoJavascriptErrors();
});

it('marks an uncalibrated legacy review instead of showing it as simply done', function () {
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    PerformanceReview::query()->delete();
    PerformanceReview::factory()->legacyCompleted()->create([
        'tenant_id' => $admin->tenant_id,
        'cycle_id' => $this->cycle->id,
        'employee_id' => $this->employee->id,
    ]);

    actingAs($admin);

    visit('/avana/kinerja')
        ->assertSee('Belum terkalibrasi')
        ->assertNoJavascriptErrors();
});

it('shows the reopen history with its reason on the edit screen', function () {
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $other = User::factory()->create(['tenant_id' => $admin->tenant_id]);

    // beforeEach already seeded a review for this employee in this cycle, and
    // one review per employee per cycle is a database constraint.
    $review = $this->review;
    $review->update(['status' => 'manager_review']);

    $workflow = new PerformanceReviewWorkflow;
    $workflow->submitManagerScore($review, 80.0, now()->toDateString(), $admin);
    $workflow->calibrate($review, 82.0, $other, 'Sesuai');
    $workflow->reopen($review, 'calibration', $admin, 'Realisasi KPI keliru');

    actingAs($admin);

    visit('/avana/kinerja/'.$review->public_id.'/edit')
        ->assertSee('Riwayat Pembukaan Kembali')
        ->assertSee('Realisasi KPI keliru')
        ->assertNoJavascriptErrors();
});
