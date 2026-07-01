<?php

use App\Models\CustomField;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('creates a custom field definition with a slugged key', function (): void {
    actingAs($this->admin)
        ->post(route('avana.custom-fields.store'), [
            'label' => 'Nomor BPJS Kesehatan',
            'type' => 'text',
            'is_required' => false,
        ])
        ->assertSessionHas('success');

    $field = CustomField::forTenant($this->tenant->id)->firstOrFail();

    expect($field->key)->toBe('nomor_bpjs_kesehatan');
    expect($field->label)->toBe('Nomor BPJS Kesehatan');
});

it('parses select options from a comma string', function (): void {
    actingAs($this->admin)
        ->post(route('avana.custom-fields.store'), [
            'label' => 'Ukuran Seragam',
            'type' => 'select',
            'options' => 'S, M, L, XL',
        ])
        ->assertSessionHas('success');

    $field = CustomField::forTenant($this->tenant->id)->firstOrFail();

    expect($field->options)->toBe(['S', 'M', 'L', 'XL']);
});

it('stores custom_data when creating an employee', function (): void {
    CustomField::create([
        'tenant_id' => $this->tenant->id, 'entity' => 'employee',
        'key' => 'nomor_bpjs', 'label' => 'Nomor BPJS', 'type' => 'text',
        'is_required' => false, 'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Budi Custom',
            'employment_status' => 'permanent',
            'status' => 'active',
            'custom_data' => ['nomor_bpjs' => '000123456'],
        ])
        ->assertSessionHas('success');

    $employee = Employee::forTenant($this->tenant->id)->where('full_name', 'Budi Custom')->firstOrFail();

    expect($employee->custom_data['nomor_bpjs'])->toBe('000123456');
});

it('rejects an employee missing a required custom field', function (): void {
    CustomField::create([
        'tenant_id' => $this->tenant->id, 'entity' => 'employee',
        'key' => 'nomor_bpjs', 'label' => 'Nomor BPJS', 'type' => 'text',
        'is_required' => true, 'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Tanpa BPJS',
            'employment_status' => 'permanent',
            'status' => 'active',
            'custom_data' => [],
        ])
        ->assertSessionHasErrors('custom_data.nomor_bpjs');
});
