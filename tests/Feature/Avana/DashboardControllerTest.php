<?php

use App\Http\Controllers\Avana\DashboardController;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // The /dashboard route still points at the static inertia view until the
    // orchestrator switches it, so expose the controller via a throwaway route.
    Route::get('test-dashboard', [DashboardController::class, 'index'])
        ->middleware(['web', 'auth'])
        ->name('test-dashboard');
});

it('renders the dashboard with real tenant-scoped data', function (): void {
    $activeCount = Employee::forTenant($this->tenant->id)->where('status', 'active')->count();
    $pendingCount = LeaveRequest::forTenant($this->tenant->id)->where('status', 'pending')->count();

    expect($activeCount)->toBeGreaterThan(0);
    expect($pendingCount)->toBeGreaterThan(0);

    actingAs($this->admin)
        ->get('/test-dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('kpis', 4)
            ->has('kpis.0', fn (Assert $kpi) => $kpi
                ->where('label', 'Total Karyawan')
                ->where('value', number_format($activeCount, 0, ',', '.'))
                ->has('icon')
                ->has('iconBg')
                ->has('iconColor')
                ->has('delta')
                ->has('deltaIcon')
                ->has('deltaColor'))
            ->where('kpis.2.label', 'Pending Approval')
            ->where('kpis.2.value', (string) $pendingCount)
            ->has('activities')
            ->has('activities.0', fn (Assert $activity) => $activity
                ->has('icon')
                ->has('bg')
                ->has('color')
                ->has('text')
                ->has('time'))
            ->has('approvals')
            ->has('approvals.0', fn (Assert $approval) => $approval
                ->has('id')
                ->has('ini')
                ->has('avBg')
                ->has('name')
                ->has('type'))
            ->has('headcount.labels', 6)
            ->has('headcount.values', 6)
            ->has('attendanceWeek.labels', 5)
            ->has('attendanceWeek.values', 5)
            ->where('userName', 'Rina')
            ->has('today'));
});

it('scopes the headcount and approvals to the current tenant only', function (): void {
    $activeCount = Employee::forTenant($this->tenant->id)->where('status', 'active')->count();
    $pendingCount = LeaveRequest::forTenant($this->tenant->id)->where('status', 'pending')->count();

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $branch = Branch::create(['tenant_id' => $otherTenant->id, 'code' => 'X', 'name' => 'X', 'status' => 'active']);
    $foreign = Employee::create([
        'tenant_id' => $otherTenant->id,
        'branch_id' => $branch->id,
        'employee_number' => 'EMP-7777',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    LeaveRequest::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreign->id,
        'branch_id' => $branch->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'total_days' => 2,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->get('/test-dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.0.value', number_format($activeCount, 0, ',', '.'))
            ->where('kpis.2.value', (string) $pendingCount)
            ->has('approvals', min(4, $pendingCount)));
});
