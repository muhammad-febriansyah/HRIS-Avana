<?php

use App\Concerns\HasPublicId;
use App\Models\Applicant;
use App\Models\CashAdvance;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\Loan;
use App\Models\OffboardingCase;
use App\Models\PayrollRunItem;
use App\Models\PerformanceReview;
use App\Models\Reimbursement;
use App\Models\Settlement;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Every model whose URL must not be countable. Their primary keys stay put —
 * only what the router binds on changed.
 *
 * @return array<int, class-string<Model>>
 */
function opaqueModels(): array
{
    return [
        Employee::class,
        EmployeeContract::class,
        PayrollRunItem::class,
        Loan::class,
        CashAdvance::class,
        Claim::class,
        Reimbursement::class,
        Settlement::class,
        EmployeeDocument::class,
        Applicant::class,
        PerformanceReview::class,
        OffboardingCase::class,
    ];
}

/** A contract for the demo tenant's first employee. */
function makeOpaqueContract(): EmployeeContract
{
    $employee = Employee::query()->firstOrFail();

    return EmployeeContract::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'contract_number' => 'PKWT-UJI-'.$employee->id,
        'contract_type' => 'PKWT',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addMonths(6)->toDateString(),
        'basic_salary' => 9_000_000,
        'status' => 'active',
    ]);
}

it('binds every sensitive model on its opaque key, never the primary key', function (string $model): void {
    expect((new $model)->getRouteKeyName())->toBe('public_id');
})->with(opaqueModels());

it('carries the trait that stamps the key, so no model can be added without one', function (string $model): void {
    expect(class_uses_recursive($model))->toContain(HasPublicId::class);
})->with(opaqueModels());

it('stamps a ULID on create rather than leaving it to the caller', function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $contract = makeOpaqueContract();

    expect($contract->public_id)->toBeString()
        ->and(strlen((string) $contract->public_id))->toBe(26)
        ->and($contract->getRouteKey())->toBe($contract->public_id)
        // The primary key is untouched: every foreign key still points at it.
        ->and($contract->id)->toBeInt();
});

it('leaves an explicitly given key alone', function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $employee = Employee::query()->firstOrFail();
    $chosen = (string) Str::ulid();

    $contract = EmployeeContract::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'public_id' => $chosen,
        'contract_number' => 'PKWT-PILIH',
        'contract_type' => 'PKWT',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    expect($contract->public_id)->toBe($chosen);
});

it('backfilled every row that already existed', function (string $model): void {
    $this->seed(AvanaDemoSeeder::class);

    expect($model::query()->whereNull('public_id')->count())->toBe(0);
})->with(opaqueModels());

it('keeps the primary key out of the contract URL', function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $contract = makeOpaqueContract();

    // The number that used to address it now addresses nothing.
    $this->actingAs($admin)
        ->get("/avana/kontrak/{$contract->id}/edit")
        ->assertNotFound();

    $this->actingAs($admin)
        ->get("/avana/kontrak/{$contract->public_id}/edit")
        ->assertOk();
});

it('hands the frontend the opaque key, not the id, to build links from', function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $contract = makeOpaqueContract();

    $this->actingAs($admin)
        ->get("/avana/kontrak/{$contract->public_id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('contract.route_key', $contract->public_id),
        );
});

it('keeps the mobile API bound to the primary key, so installed apps keep working', function (string $uri, string $param): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($candidate): bool => $candidate->uri() === $uri);

    expect($route)->not->toBeNull("route [{$uri}] is gone")
        // Without the `:id` the router would bind on public_id, and every
        // phone already carrying the app sends the number.
        ->and($route->bindingFieldFor($param))->toBe('id');
})->with([
    ['api/v1/me/payslips/{item}', 'item'],
    ['api/v1/me/payslips/{item}/pdf', 'item'],
    ['api/v1/me/cash-advances/{cashAdvance}', 'cashAdvance'],
    ['api/v1/me/settlements/{settlement}', 'settlement'],
    ['api/v1/finance/reimbursements/{claim}/pay', 'claim'],
]);

it('refuses a well-formed key belonging to nothing', function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $this->actingAs($admin)
        ->get('/avana/kontrak/'.Str::ulid().'/edit')
        ->assertNotFound();
});
