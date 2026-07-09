<?php

use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->hrAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
});

it('shows the platform scope to a super admin', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.email-settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/email-settings/index', false)
            ->where('scope', 'platform')
            ->has('settings.host')
            ->has('encryptions.tls')
            ->has('logs'));
});

it('shows the tenant scope to a tenant HR admin', function (): void {
    actingAs($this->hrAdmin)
        ->get(route('avana.email-settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'tenant'));
});

it('forbids a non-privileged employee', function (): void {
    actingAs($this->employee)
        ->get(route('avana.email-settings'))
        ->assertForbidden();

    actingAs($this->employee)
        ->post(route('avana.email-settings.update'), ['host' => 'evil'])
        ->assertForbidden();
});

it('saves the platform settings for a super admin with an encrypted password', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.email-settings.update'), [
            'from_name' => 'AvanaHR',
            'from_email' => 'noreply@avanahr.id',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'postmaster',
            'password' => 'super-secret',
            'is_enabled' => true,
        ])
        ->assertSessionHas('success');

    $row = EmailSetting::whereNull('tenant_id')->firstOrFail();

    expect($row->host)->toBe('smtp.mailgun.org');
    expect($row->port)->toBe(587);
    expect($row->password)->toBe('super-secret'); // decrypted via cast

    $raw = DB::table('email_settings')->where('id', $row->id)->value('password');
    expect($raw)->not->toBe('super-secret');
});

it('isolates tenant settings from the platform default', function (): void {
    actingAs($this->hrAdmin)
        ->post(route('avana.email-settings.update'), [
            'host' => 'smtp.tenant.co.id',
            'port' => 465,
            'encryption' => 'ssl',
            'from_email' => 'hr@nusantara.co.id',
            'is_enabled' => true,
        ])
        ->assertSessionHas('success');

    $tenantRow = EmailSetting::where('tenant_id', $this->hrAdmin->tenant_id)->firstOrFail();
    expect($tenantRow->host)->toBe('smtp.tenant.co.id');

    // The platform default row is untouched (separate scope).
    expect(EmailSetting::whereNull('tenant_id')->value('host'))->toBeNull();
});

it('keeps the existing password when submitted blank', function (): void {
    EmailSetting::forScope(null)->update(['password' => 'original-pass']);

    actingAs($this->superAdmin)
        ->post(route('avana.email-settings.update'), [
            'host' => 'smtp.host',
            'port' => 587,
            'password' => '',
        ])
        ->assertSessionHas('success');

    expect(EmailSetting::forScope(null)->password)->toBe('original-pass');
});

it('rejects a test send when the config is incomplete', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.email-settings.test'))
        ->assertSessionHasErrors('email');

    expect(EmailLog::count())->toBe(0);
});

it('sends a test email and logs it when configured', function (): void {
    Mail::fake();

    EmailSetting::forScope(null)->update([
        'host' => 'smtp.host',
        'port' => 587,
        'encryption' => 'tls',
        'from_email' => 'noreply@avanahr.id',
        'is_enabled' => true,
    ]);

    actingAs($this->superAdmin)
        ->post(route('avana.email-settings.test'))
        ->assertSessionHas('success');

    $log = EmailLog::whereNull('tenant_id')->latest('id')->firstOrFail();
    expect($log->status)->toBe('sent');
    expect($log->to_email)->toBe($this->superAdmin->email);
});
