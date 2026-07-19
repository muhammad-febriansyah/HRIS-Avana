<?php

use App\Jobs\SendBrandedNotificationJob;
use App\Mail\BrandedNotification;
use App\Models\Claim;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WebsiteSetting;
use App\Support\MailBranding;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeTenantWithCompany(): Tenant
{
    $tenant = Tenant::create([
        'name' => 'Acme Corp',
        'slug' => Str::slug('acme-'.fake()->unique()->numberBetween(1, 99999)),
    ]);

    Company::create([
        'tenant_id' => $tenant->id,
        'name' => 'Acme Indonesia',
        'legal_name' => 'PT Acme Indonesia',
        'email' => 'hr@acme.test',
        'phone' => '021-555-1234',
        'address' => 'Jl. Sudirman No. 1, Jakarta',
        'logo_path' => 'logos/acme.png',
        'status' => 'active',
    ]);

    return $tenant;
}

it('uses the tenant company branding for a tenant scope', function () {
    $tenant = makeTenantWithCompany();

    $brand = MailBranding::for($tenant->id);

    expect($brand['brand_name'])->toBe('Acme Indonesia')
        ->and($brand['logo_url'])->toContain('logos/acme.png')
        ->and($brand['address'])->toBe('Jl. Sudirman No. 1, Jakarta')
        ->and($brand['phone'])->toBe('021-555-1234')
        ->and($brand['email'])->toBe('hr@acme.test');
});

it('uses the platform website settings for the null (super admin) scope', function () {
    $settings = WebsiteSetting::current();
    $settings->update([
        'site_name' => 'AvanaHR Platform',
        'logo_path' => 'branding/platform-logo.png',
        'contact_email' => 'support@avanahr.id',
        'contact_phone' => '0800-1-AVANA',
        'contact_address' => 'Menara Avana, Jakarta',
    ]);

    $brand = MailBranding::for(null);

    expect($brand['brand_name'])->toBe('AvanaHR Platform')
        ->and($brand['logo_url'])->toContain('branding/platform-logo.png')
        ->and($brand['email'])->toBe('support@avanahr.id');
});

it('falls back to the bundled logo and AvanaHR name when nothing is configured', function () {
    $brand = MailBranding::for(null);

    expect($brand['brand_name'])->toBe('AvanaHR')
        ->and($brand['logo_url'])->toContain('avana/logo-full.png');
});

it('renders header logo, body and footer in the branded email', function () {
    $tenant = makeTenantWithCompany();

    $html = BrandedNotification::make(
        tenantId: $tenant->id,
        subjectLine: 'Slip Gaji Tersedia',
        heading: 'Slip gaji Anda sudah tersedia',
        paragraphs: ['Slip gaji untuk periode ini sudah dapat diunduh.'],
        details: ['Periode' => 'Juli 2026'],
        greetingName: 'Budi',
    )->render();

    // Header logo
    expect($html)->toContain('logos/acme.png')
        // Body
        ->and($html)->toContain('Slip gaji Anda sudah tersedia')
        ->and($html)->toContain('Slip gaji untuk periode ini sudah dapat diunduh.')
        ->and($html)->toContain('Budi')
        ->and($html)->toContain('Periode')
        ->and($html)->toContain('Juli 2026')
        // Footer about the tenant
        ->and($html)->toContain('Acme Indonesia')
        ->and($html)->toContain('Jl. Sudirman No. 1, Jakarta')
        ->and($html)->toContain('hr@acme.test');
});

it('queues a branded email to the employee when a reimbursement is paid', function () {
    Bus::fake();
    $tenant = makeTenantWithCompany();

    $employee = Employee::create([
        'tenant_id' => $tenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Siti Rahma',
        'email' => 'siti@acme.test',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    $claim = Claim::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'title' => 'Berobat',
        'amount' => 250000,
        'status' => 'paid',
    ]);

    Notifier::reimbursementPaid($claim);

    Bus::assertDispatched(SendBrandedNotificationJob::class);
});

it('does not queue an email when the employee has no email address', function () {
    Bus::fake();
    $tenant = makeTenantWithCompany();

    $employee = Employee::create([
        'tenant_id' => $tenant->id,
        'employee_number' => 'EMP-0002',
        'full_name' => 'Tanpa Email',
        'email' => null,
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    $claim = Claim::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => 'paid',
    ]);

    Notifier::reimbursementPaid($claim);

    Bus::assertNotDispatched(SendBrandedNotificationJob::class);
});
