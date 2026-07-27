<?php

use App\Models\User;
use App\Services\AiToolkit;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
});

/**
 * @return array<int, string>
 */
function toolNames(User $user): array
{
    return collect(AiToolkit::forUser($user))->map(fn ($tool): string => $tool->name())->all();
}

it('gives an employee personal data tools', function (): void {
    $user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    expect(toolNames($user))->toContain('saldo_cuti', 'slip_gaji', 'rekap_kehadiran', 'pengajuan_saya', 'profil_saya', 'inbox_persetujuan');
});

it('gives HR users the tenant-wide tools', function (): void {
    $user = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    expect(toolNames($user))->toContain('cari_karyawan', 'statistik_karyawan', 'ringkasan_payroll', 'ringkasan_rekrutmen');
});

it('does not expose tenant-wide tools to a plain employee', function (): void {
    $user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    expect(toolNames($user))
        ->not->toContain('cari_karyawan')
        ->not->toContain('ringkasan_payroll')
        ->not->toContain('statistik_karyawan');
});

it('returns the employee own leave balance as scoped text', function (): void {
    $user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    $tool = collect(AiToolkit::forUser($user))->firstWhere(fn ($t): bool => $t->name() === 'saldo_cuti');

    expect($tool)->not->toBeNull();
    expect($tool->handle())->toBeString()->not->toBeEmpty();
});

it('searches employees by keyword for HR users', function (): void {
    $user = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $tool = collect(AiToolkit::forUser($user))->firstWhere(fn ($t): bool => $t->name() === 'cari_karyawan');

    expect($tool->handle(kata_kunci: 'Bagus'))->toContain('Bagus');
});

it('registers the personal tools even when the account has no employee record', function (): void {
    $user = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    expect($user->employee)->toBeNull()
        ->and(toolNames($user))->toContain('saldo_cuti', 'slip_gaji', 'pengajuan_saya');
});

it('states the real reason instead of pretending the leave module is missing', function (): void {
    $user = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $tool = collect(AiToolkit::forUser($user))->firstWhere(fn ($t): bool => $t->name() === 'saldo_cuti');

    expect($tool->handle())
        ->toContain('tidak tertaut ke data karyawan')
        ->toContain('Admin HR');
});

it('lists only the menus the signed-in user can actually open', function (): void {
    $employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $hr = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $tool = fn (User $u) => collect(AiToolkit::forUser($u))
        ->firstWhere(fn ($t): bool => $t->name() === 'fitur_tersedia');

    // A plain employee reaches self-service, never the payroll admin screens.
    expect($tool($employee)->handle())
        ->toContain('/avana/saya/cuti')
        ->not->toContain('/avana/payroll');

    expect($tool($hr)->handle())->toContain('/avana/payroll');
});

it('filters the feature list by keyword and refuses to invent a menu', function (): void {
    $user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    $tool = collect(AiToolkit::forUser($user))->firstWhere(fn ($t): bool => $t->name() === 'fitur_tersedia');

    expect($tool->handle(kata_kunci: 'cuti'))->toContain('Cuti Saya')
        ->and($tool->handle(kata_kunci: 'zzz tidak ada'))->toContain('Jangan mengarang nama menu');
});
