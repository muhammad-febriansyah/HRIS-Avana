<?php

use App\Models\JournalEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
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
 * Create a payroll run with balanced totals (gross = deduction + tax + net).
 */
function makePayrollRun(int $tenantId, array $overrides = []): PayrollRun
{
    $period = PayrollPeriod::create([
        'tenant_id' => $tenantId,
        'code' => 'TST-'.fake()->unique()->numerify('####'),
        'name' => 'Periode Uji',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'pay_date' => '2026-07-25',
        'status' => 'draft',
    ]);

    return PayrollRun::create(array_merge([
        'tenant_id' => $tenantId,
        'payroll_period_id' => $period->id,
        'status' => 'draft',
        'total_gross' => 100_000_000,
        'total_deduction' => 8_000_000,
        'total_tax' => 2_000_000,
        'total_net' => 90_000_000,
        'employee_count' => 10,
    ], $overrides));
}

it('renders the journal index with the expected props', function (): void {
    makePayrollRun($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.jurnal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/jurnal/index', false)
            ->has('entries')
            ->has('latestRun')
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->has('total_entries')
                ->has('total_debit')
                ->has('total_credit')
                ->has('balanced')));
});

it('generates a balanced journal from the latest payroll run', function (): void {
    makePayrollRun($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.jurnal.generate'))
        ->assertRedirect(route('avana.jurnal'))
        ->assertSessionHas('success');

    $entries = JournalEntry::where('tenant_id', $this->tenant->id)->get();

    expect($entries)->toHaveCount(3);
    expect((float) $entries->sum('debit'))->toBe(100_000_000.0);
    expect((float) $entries->sum('credit'))->toBe(100_000_000.0);
    expect((float) $entries->sum('debit'))->toBe((float) $entries->sum('credit'));

    $expense = $entries->firstWhere('account_code', '5101');
    expect((float) $expense->debit)->toBe(100_000_000.0);

    $liability = $entries->firstWhere('account_code', '2101');
    expect((float) $liability->credit)->toBe(10_000_000.0);

    $cash = $entries->firstWhere('account_code', '1101');
    expect((float) $cash->credit)->toBe(90_000_000.0);
});

it('scopes the generated journal to the acting tenant', function (): void {
    makePayrollRun($this->tenant->id);

    actingAs($this->admin)->post(route('avana.jurnal.generate'));

    expect(JournalEntry::where('tenant_id', $this->tenant->id)->count())->toBe(3);
});

it('only lists journal entries that belong to the current tenant', function (): void {
    JournalEntry::create([
        'tenant_id' => $this->tenant->id,
        'entry_date' => '2026-07-01',
        'account_code' => '5101',
        'account_name' => 'Beban Gaji',
        'debit' => 1000,
        'credit' => 0,
    ]);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    JournalEntry::create([
        'tenant_id' => $otherTenant->id,
        'entry_date' => '2026-07-01',
        'account_code' => '5101',
        'account_name' => 'Beban Gaji',
        'debit' => 9999,
        'credit' => 0,
    ]);

    actingAs($this->admin)
        ->get(route('avana.jurnal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('entries', 1));
});

it('deletes a journal entry', function (): void {
    $entry = JournalEntry::create([
        'tenant_id' => $this->tenant->id,
        'entry_date' => '2026-07-01',
        'account_code' => '1101',
        'account_name' => 'Kas/Bank',
        'debit' => 0,
        'credit' => 500,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.jurnal.destroy', $entry))
        ->assertSessionHas('success');

    expect(JournalEntry::find($entry->id))->toBeNull();
});

it('returns 404 when deleting a journal entry from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = JournalEntry::create([
        'tenant_id' => $otherTenant->id,
        'entry_date' => '2026-07-01',
        'account_code' => '1101',
        'account_name' => 'Kas/Bank',
        'debit' => 0,
        'credit' => 500,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.jurnal.destroy', $foreign))
        ->assertNotFound();

    expect(JournalEntry::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from viewing or generating the journal', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.jurnal'))->assertForbidden();
    actingAs($staff)->post(route('avana.jurnal.generate'))->assertForbidden();
});
