<?php

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;
    $this->colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();
});

/** A contract for the given employee. */
function makeOwnContract(Employee $employee, array $overrides = []): EmployeeContract
{
    return EmployeeContract::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'contract_number' => 'PKWT-'.$employee->id.'-'.mt_rand(1000, 9999),
        'contract_type' => 'PKWT',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addMonths(6)->toDateString(),
        'basic_salary' => 9_000_000,
        'status' => 'active',
        ...$overrides,
    ]);
}

/** A review for the given employee in a fresh cycle. */
function makeOwnReview(Employee $employee, array $overrides = []): PerformanceReview
{
    $cycle = PerformanceCycle::firstOrCreate(
        ['tenant_id' => $employee->tenant_id, 'name' => 'Siklus Uji 2026'],
        [
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'active',
        ],
    );

    return PerformanceReview::create([
        'tenant_id' => $employee->tenant_id,
        'cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
        'status' => 'self_review',
        ...$overrides,
    ]);
}

it('lists only the signed-in employee contracts', function (): void {
    EmployeeContract::query()->delete();

    makeOwnContract($this->employee);
    makeOwnContract($this->colleague);

    $this->actingAs($this->user)
        ->get('/avana/saya/kontrak')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/saya/kontrak')
            ->has('contracts', 1)
            ->where('active.status', 'active')
        );
});

it('flags a contract that expires within the month', function (): void {
    EmployeeContract::query()->delete();
    makeOwnContract($this->employee, ['end_date' => now()->addDays(10)->toDateString()]);

    $this->actingAs($this->user)
        ->get('/avana/saya/kontrak')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('active.expiring_soon', true));
});

it('leaves an open-ended contract unflagged', function (): void {
    EmployeeContract::query()->delete();
    makeOwnContract($this->employee, ['end_date' => null]);

    $this->actingAs($this->user)
        ->get('/avana/saya/kontrak')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('active.expiring_soon', false)
            ->where('active.days_to_expiry', null)
        );
});

it('lists only the signed-in employee reviews', function (): void {
    PerformanceReview::query()->delete();

    makeOwnReview($this->employee);
    makeOwnReview($this->colleague);

    $this->actingAs($this->user)
        ->get('/avana/saya/kinerja')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/saya/kinerja')
            ->has('reviews', 1)
            ->where('summary.awaiting_self', 1)
        );
});

it('reports the calibrated score as the effective one', function (): void {
    PerformanceReview::query()->delete();

    makeOwnReview($this->employee, [
        'status' => 'completed',
        'self_score' => 70,
        'manager_score' => 80,
        'final_score' => 85,
        'calibrated_score' => 90,
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/kinerja')
        ->assertOk()
        // Compared loosely: a whole float is serialised to JSON as an int.
        ->assertInertia(fn ($page) => $page
            ->where('reviews.0.effective_score', fn ($score): bool => (float) $score === 90.0)
            ->where('summary.latest_score', fn ($score): bool => (float) $score === 90.0)
        );
});

it('withholds the reviewer identity on feedback', function (): void {
    PerformanceReview::query()->delete();

    $review = makeOwnReview($this->employee, ['status' => 'completed']);
    $review->feedbacks()->create([
        'tenant_id' => $this->employee->tenant_id,
        'reviewer_id' => $this->colleague->id,
        'type' => 'peer',
        'rating' => 88,
        'comment' => 'Kolaborasinya bagus',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/kinerja')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('reviews.0.feedbacks', 1)
            ->where('reviews.0.feedbacks.0.comment', 'Kolaborasinya bagus')
            ->missing('reviews.0.feedbacks.0.reviewer_id')
            ->missing('reviews.0.feedbacks.0.reviewer')
        );
});

it('records a self assessment and hands the review to the manager', function (): void {
    $review = makeOwnReview($this->employee, ['status' => 'self_review']);

    $this->actingAs($this->user)
        ->post("/avana/saya/kinerja/{$review->public_id}/nilai-mandiri", [
            'self_score' => 82.5,
            'notes' => 'Menyelesaikan dua rilis besar',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $review->refresh();

    expect((float) $review->self_score)->toBe(82.5)
        ->and($review->status)->toBe('manager_review')
        ->and($review->notes)->toBe('Menyelesaikan dua rilis besar');
});

it('rejects a self score outside the 0-100 range', function (mixed $score): void {
    $review = makeOwnReview($this->employee, ['status' => 'self_review']);

    $this->actingAs($this->user)
        ->post("/avana/saya/kinerja/{$review->public_id}/nilai-mandiri", ['self_score' => $score])
        ->assertSessionHasErrors('self_score');

    expect($review->refresh()->self_score)->toBeNull()
        ->and($review->status)->toBe('self_review');
})->with([
    'missing' => [null],
    'negative' => [-1],
    'above 100' => [101],
    'not numeric' => ['delapan puluh'],
]);

it('refuses a self assessment once the stage has passed', function (): void {
    $review = makeOwnReview($this->employee, ['status' => 'manager_review']);

    $this->actingAs($this->user)
        ->post("/avana/saya/kinerja/{$review->public_id}/nilai-mandiri", ['self_score' => 90])
        ->assertStatus(422);

    expect($review->refresh()->self_score)->toBeNull();
});

it('refuses a self assessment on somebody else review', function (): void {
    $review = makeOwnReview($this->colleague, ['status' => 'self_review']);

    $this->actingAs($this->user)
        ->post("/avana/saya/kinerja/{$review->public_id}/nilai-mandiri", ['self_score' => 90])
        ->assertNotFound();

    expect($review->refresh()->self_score)->toBeNull();
});

it('refuses both screens to an account with no employee record', function (): void {
    $hrAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $this->actingAs($hrAdmin)->get('/avana/saya/kontrak')->assertForbidden();
    $this->actingAs($hrAdmin)->get('/avana/saya/kinerja')->assertForbidden();
});
