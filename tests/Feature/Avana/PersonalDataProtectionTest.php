<?php

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\EmployeeIdentity;
use App\Support\Pii;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->firstOrFail();
});

test('a NIK is stored encrypted but reads back in the clear', function (): void {
    $this->employee->forceFill(['nik' => '3273010101900001'])->save();

    $raw = DB::table('employees')->where('id', $this->employee->id)->value('nik');

    expect($raw)->not->toBe('3273010101900001')
        ->and(base64_decode((string) $raw, true))->toContain('"iv"')
        ->and($this->employee->fresh()->nik)->toBe('3273010101900001');
});

test('the lookup hash is kept in step with the NIK', function (): void {
    $this->employee->forceFill(['nik' => '3273010101900002'])->save();

    expect(DB::table('employees')->where('id', $this->employee->id)->value('nik_hash'))
        ->toBe(Pii::hash('3273010101900002'));

    $this->employee->forceFill(['nik' => '3273010101900003'])->save();

    expect(DB::table('employees')->where('id', $this->employee->id)->value('nik_hash'))
        ->toBe(Pii::hash('3273010101900003'));
});

test('an encrypted NIK can still be found, so duplicates are still caught', function (): void {
    $this->employee->forceFill(['nik' => '3273010101900004'])->save();

    $holder = EmployeeIdentity::employeeHolding('3273010101900004', (int) $this->tenant->id);

    expect($holder?->id)->toBe($this->employee->id);

    $other = Employee::forTenant($this->tenant->id)->whereKeyNot($this->employee->id)->firstOrFail();

    $validator = Validator::make(
        ['nik' => '3273010101900004'],
        ['nik' => EmployeeIdentity::nikRules((int) $this->tenant->id, (int) $other->id)],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('nik'))->toContain('sudah dipakai karyawan lain');
});

test('a bank account number is encrypted at rest', function (): void {
    $account = EmployeeBankAccount::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'Uji Coba',
        'is_primary' => true,
    ]);

    expect(DB::table('employee_bank_accounts')->where('id', $account->id)->value('account_number'))
        ->not->toBe('1234567890')
        ->and($account->fresh()->account_number)->toBe('1234567890');
});

test('an empty identifier is stored as null, not as an encrypted blank', function (): void {
    $this->employee->forceFill(['nik' => ''])->save();

    expect(DB::table('employees')->where('id', $this->employee->id)->value('nik'))->toBeNull()
        ->and(DB::table('employees')->where('id', $this->employee->id)->value('nik_hash'))->toBeNull();
});

test('identifiers are masked for a viewer who cannot edit employee records', function (): void {
    expect(Pii::maskNik('3273010101900005'))->toBe('••••••••••••0005')
        ->and(Pii::maskAccount('1234567890'))->toBe('••••••7890');

    $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(Pii::visibleTo($outsider, $this->employee))->toBeFalse()
        ->and(Pii::visibleTo($this->admin, $this->employee))->toBeTrue();
});

test('an employee always sees their own identifiers in full', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->whereNotNull('user_id')->firstOrFail();
    $self = User::findOrFail($employee->user_id);

    expect(Pii::visibleTo($self, $employee))->toBeTrue();
});

test('personal data can be exported and the download is audited', function (): void {
    $this->employee->forceFill(['nik' => '3273010101900006'])->save();

    $response = actingAs($this->admin)
        ->get(route('avana.employees.personal-data.export', $this->employee));

    $response->assertOk()
        ->assertJsonPath('identitas.nik', '3273010101900006');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    expect(AuditLog::where('action', 'data_exported')->where('auditable_id', $this->employee->id)->exists())->toBeTrue()
        ->and(UserActivityLog::where('event', 'data_exported')->exists())->toBeTrue();
});

test('an active employee cannot have their personal data erased', function (): void {
    $this->employee->forceFill(['status' => 'active', 'resign_date' => null])->save();

    actingAs($this->admin)
        ->delete(route('avana.employees.personal-data.erase', $this->employee), [
            'confirm_name' => $this->employee->full_name,
        ])
        ->assertRedirect();

    expect($this->employee->fresh()->anonymized_at)->toBeNull();
});

test('erasure needs the employee name typed back', function (): void {
    $this->employee->forceFill(['status' => 'inactive', 'resign_date' => now()->subMonth()])->save();

    actingAs($this->admin)
        ->delete(route('avana.employees.personal-data.erase', $this->employee), [
            'confirm_name' => 'Nama Yang Salah',
        ])
        ->assertRedirect();

    expect($this->employee->fresh()->anonymized_at)->toBeNull();
});

test('erasure scrubs the identifiers but keeps the payroll history', function (): void {
    $employee = $this->employee;
    $employee->forceFill([
        'status' => 'inactive',
        'resign_date' => now()->subMonth(),
        'nik' => '3273010101900007',
        'address' => 'Jalan Uji Coba 1',
    ])->save();

    EmployeeBankAccount::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'Uji Coba',
        'is_primary' => true,
    ]);

    $name = $employee->full_name;

    actingAs($this->admin)
        ->delete(route('avana.employees.personal-data.erase', $employee), ['confirm_name' => $name])
        ->assertRedirect(route('avana.employees.index'));

    $fresh = $employee->fresh();

    expect($fresh->anonymized_at)->not->toBeNull()
        ->and($fresh->nik)->toBeNull()
        ->and($fresh->nik_hash)->toBeNull()
        ->and($fresh->address)->toBeNull()
        ->and($fresh->full_name)->toContain('Data Dihapus')
        ->and(EmployeeBankAccount::where('employee_id', $employee->id)->count())->toBe(0)
        // The row itself survives, so payroll rows pointing at it still resolve.
        ->and(Employee::withTrashed()->whereKey($employee->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'data_erased')->where('auditable_id', $employee->id)->exists())->toBeTrue();
});

test('the retention command drops trails past their window', function (): void {
    config([
        'security.retention.activity_log_days' => 30,
        'security.retention.audit_log_days' => 0,
    ]);

    UserActivityLog::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'event' => 'login',
        'description' => 'lama',
        'created_at' => now()->subDays(60),
    ]);

    UserActivityLog::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'event' => 'login',
        'description' => 'baru',
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('avana:prune-security-data')->assertSuccessful();

    expect(UserActivityLog::where('description', 'lama')->exists())->toBeFalse()
        ->and(UserActivityLog::where('description', 'baru')->exists())->toBeTrue();
});

test('the bank account number snapshotted onto a settlement is encrypted at rest', function (): void {
    $settlement = Settlement::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'number' => 'STL-TEST-0001',
        'title' => 'Uji Coba',
        'category' => 'perjalanan',
        'destination' => 'Jakarta',
        'subtotal' => 100000,
        'tax_amount' => 0,
        'total' => 100000,
        'bank_name' => 'BCA',
        'bank_account_number' => '9988776655',
        'bank_account_holder' => 'Uji Coba',
        'submission_date' => now(),
        'status' => 'submitted',
    ]);

    expect(DB::table('settlements')->where('id', $settlement->id)->value('bank_account_number'))
        ->not->toBe('9988776655')
        ->and($settlement->fresh()->bank_account_number)->toBe('9988776655');
});
