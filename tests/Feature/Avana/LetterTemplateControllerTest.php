<?php

use App\Models\Employee;
use App\Models\GeneratedLetter;
use App\Models\LetterTemplate;
use App\Models\Position;
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
 * Create a letter template for the given tenant.
 */
function makeSuratTemplate(int $tenantId, array $overrides = []): LetterTemplate
{
    return LetterTemplate::create(array_merge([
        'tenant_id' => $tenantId,
        'name' => 'Surat Keterangan Kerja',
        'type' => 'referensi',
        'body' => 'Yang bertanda tangan menerangkan {{nama}}.',
        'is_active' => true,
    ], $overrides));
}

/**
 * Create an employee under the given tenant.
 */
function makeSuratEmployee(int $tenantId, array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_number' => 'TST-'.uniqid(),
        'full_name' => 'Joko Widodo',
        'gender' => 'unspecified',
        'employment_status' => 'permanent',
        'status' => 'active',
    ], $overrides));
}

/**
 * Create a generated letter under the given tenant.
 */
function makeSuratGeneratedLetter(int $tenantId, array $overrides = []): GeneratedLetter
{
    return GeneratedLetter::create(array_merge([
        'tenant_id' => $tenantId,
        'title' => 'Surat - Joko Widodo',
        'body' => 'Isi surat tergenerate.',
        'generated_at' => '2026-07-01',
    ], $overrides));
}

it('renders the surat index with the expected props', function (): void {
    makeSuratTemplate($this->tenant->id);
    makeSuratGeneratedLetter($this->tenant->id, [
        'employee_id' => makeSuratEmployee($this->tenant->id)->id,
        'letter_number' => '001/HR/2026',
    ]);

    actingAs($this->admin)
        ->get(route('avana.surat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/surat/index', false)
            ->has('templates.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('type')
                ->has('type_label')
                ->has('is_active')
                ->has('updated_at'))
            ->has('generatedLetters.0', fn (Assert $row) => $row
                ->has('id')
                ->has('title')
                ->has('employee_name')
                ->has('letter_number')
                ->has('generated_at'))
            ->has('employees')
            ->has('templateOptions')
            ->has('types'));
});

it('only lists templates that belong to the current tenant', function (): void {
    makeSuratTemplate($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeSuratTemplate($otherTenant->id);

    $tenantTotal = LetterTemplate::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.surat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', $tenantTotal));
});

it('creates a template scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.surat.store'), [
            'name' => 'Surat Paklaring',
            'type' => 'paklaring',
            'body' => 'Dengan ini menerangkan {{nama}} dari {{perusahaan}}.',
            'is_active' => true,
        ])
        ->assertRedirect(route('avana.surat'))
        ->assertSessionHas('success');

    $template = LetterTemplate::where('name', 'Surat Paklaring')->firstOrFail();

    expect($template->tenant_id)->toBe($this->tenant->id);
    expect($template->type)->toBe('paklaring');
    expect($template->is_active)->toBeTrue();
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.surat.store'), [
            'name' => '',
            'type' => 'invalid',
            'body' => '',
        ])
        ->assertSessionHasErrors(['name', 'type', 'body']);
});

it('updates an existing template', function (): void {
    $template = makeSuratTemplate($this->tenant->id, ['name' => 'Lama']);

    actingAs($this->admin)
        ->put(route('avana.surat.update', $template), [
            'name' => 'Baru',
            'type' => 'sk',
            'body' => 'Isi baru {{jabatan}}.',
            'is_active' => false,
        ])
        ->assertRedirect(route('avana.surat'))
        ->assertSessionHas('success');

    $template->refresh();

    expect($template->name)->toBe('Baru');
    expect($template->type)->toBe('sk');
    expect($template->is_active)->toBeFalse();
});

it('deletes a template', function (): void {
    $template = makeSuratTemplate($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.surat.destroy', $template))
        ->assertSessionHas('success');

    expect(LetterTemplate::find($template->id))->toBeNull();
});

it('generates a letter with placeholders replaced', function (): void {
    $position = Position::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'TST-JAB'],
        ['name' => 'Software Engineer', 'status' => 'active'],
    );

    $template = makeSuratTemplate($this->tenant->id, [
        'name' => 'Surat Referensi',
        'body' => 'Karyawan {{NAMA}} menjabat sebagai {{jabatan}} di {{perusahaan}}.',
    ]);

    $employee = makeSuratEmployee($this->tenant->id, [
        'full_name' => 'Budi Hartono',
        'position_id' => $position->id,
    ]);

    actingAs($this->admin)
        ->post(route('avana.surat.generate'), [
            'letter_template_id' => $template->id,
            'employee_id' => $employee->id,
            'letter_number' => '010/HR/2026',
            'generated_at' => '2026-07-01',
        ])
        ->assertRedirect(route('avana.surat'))
        ->assertSessionHas('success');

    $letter = GeneratedLetter::where('letter_template_id', $template->id)->firstOrFail();

    expect($letter->tenant_id)->toBe($this->tenant->id);
    expect($letter->employee_id)->toBe($employee->id);
    expect($letter->title)->toBe('Surat Referensi - Budi Hartono');
    expect($letter->body)->toContain('Budi Hartono');
    expect($letter->body)->toContain('Software Engineer');
    expect($letter->body)->not->toContain('{{NAMA}}');
    expect($letter->body)->not->toContain('{{jabatan}}');
});

it('validates the generate request against tenant-scoped records', function (): void {
    actingAs($this->admin)
        ->post(route('avana.surat.generate'), [
            'letter_template_id' => 999999,
            'employee_id' => 999999,
        ])
        ->assertSessionHasErrors(['letter_template_id', 'employee_id']);
});

it('renders the printable letter page', function (): void {
    $letter = makeSuratGeneratedLetter($this->tenant->id, [
        'employee_id' => makeSuratEmployee($this->tenant->id)->id,
        'letter_number' => '777/HR/2026',
    ]);

    actingAs($this->admin)
        ->get(route('avana.surat.print', $letter))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/surat/print', false)
            ->where('letter.id', $letter->id)
            ->has('letter.title')
            ->has('letter.body')
            ->has('company.name'));
});

it('deletes a generated letter', function (): void {
    $letter = makeSuratGeneratedLetter($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.surat.generated.destroy', $letter))
        ->assertSessionHas('success');

    expect(GeneratedLetter::find($letter->id))->toBeNull();
});

it('returns 404 when editing a template from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeSuratTemplate($otherTenant->id);

    actingAs($this->admin)
        ->get(route('avana.surat.edit', $foreign))
        ->assertNotFound();
});

it('returns 404 when printing a letter from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeSuratGeneratedLetter($otherTenant->id);

    actingAs($this->admin)
        ->get(route('avana.surat.print', $foreign))
        ->assertNotFound();
});

it('forbids a plain employee from creating templates', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.surat'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.surat.store'), [
            'name' => 'Tidak Boleh',
            'type' => 'custom',
            'body' => 'x',
            'is_active' => true,
        ])
        ->assertForbidden();
});
