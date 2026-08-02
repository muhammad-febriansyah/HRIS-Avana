<?php

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiToolkit;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->hr = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->hr->tenant_id);

    $this->staff = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('user_id', User::where('email', 'bagus.p@nusantara.co.id')->value('id'))
        ->firstOrFail();

    $this->other = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('id', '!=', $this->staff->id)
        ->firstOrFail();

    $this->mine = EmployeeDocument::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->staff->id,
        'name' => 'Kontrak Kerja 2026',
        'type' => 'Kontrak',
        'file_path' => 'documents/1/kontrak.pdf',
        'file_size' => 1024,
        'content' => 'Pihak pertama menyepakati masa kerja dua belas bulan terhitung 1 Januari 2026.',
        'uploaded_at' => now(),
    ]);

    $this->theirs = EmployeeDocument::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->other->id,
        'name' => 'Ijazah Rahasia',
        'type' => 'Ijazah',
        'file_path' => 'documents/1/ijazah.pdf',
        'file_size' => 2048,
        'content' => 'Rahasia milik orang lain.',
        'uploaded_at' => now(),
    ]);

    $this->run = function (User $user, string $tool, array $args): string {
        $found = collect(AiToolkit::forUser($user))->first(fn ($t) => $t->name() === $tool);

        expect($found)->not->toBeNull();

        return (string) $found->handle(...array_values($args));
    };
});

it('reads the employee their own document', function (): void {
    $answer = ($this->run)($this->staff->user, 'baca_dokumen', ['kata_kunci' => 'kontrak']);

    expect($answer)->toContain('dua belas bulan');
});

it('does not hand one employee another employee document', function (): void {
    $answer = ($this->run)($this->staff->user, 'baca_dokumen', ['kata_kunci' => 'ijazah']);

    expect($answer)->not->toContain('Rahasia milik orang lain');
    expect($answer)->toContain('Tidak ada dokumen');
});

it('lets HR read across the company', function (): void {
    $answer = ($this->run)($this->hr, 'baca_dokumen', ['kata_kunci' => 'ijazah']);

    expect($answer)->toContain('Rahasia milik orang lain');
});

it('says a scanned document cannot be read rather than inventing it', function (): void {
    EmployeeDocument::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->staff->id,
        'name' => 'Surat Hasil Scan',
        'type' => 'Surat',
        'file_path' => 'documents/1/scan.pdf',
        'file_size' => 4096,
        'content' => '',
        'uploaded_at' => now(),
    ]);

    $answer = ($this->run)($this->staff->user, 'baca_dokumen', ['kata_kunci' => 'scan']);

    expect($answer)->toContain('tidak terbaca sistem');
    expect($answer)->toContain('JANGAN mengarang');
});

it('lists only the documents the caller may see', function (): void {
    $answer = ($this->run)($this->staff->user, 'daftar_dokumen', ['kata_kunci' => null]);

    expect($answer)->toContain('Kontrak Kerja 2026');
    expect($answer)->not->toContain('Ijazah Rahasia');
});

it('flags an unreadable document in the list too', function (): void {
    $this->mine->update(['content' => '']);

    $answer = ($this->run)($this->staff->user, 'daftar_dokumen', ['kata_kunci' => null]);

    expect($answer)->toContain('isi belum terbaca sistem');
});
