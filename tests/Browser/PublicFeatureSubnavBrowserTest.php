<?php

it('shows the matching module navigation on public feature pages', function (
    string $slug,
    string $moduleLabel,
    string $activeLabel,
    array $expectedItems,
): void {
    $page = visit("/fitur/{$slug}");
    $selector = json_encode("nav[aria-label=\"Navigasi modul {$moduleLabel}\"] a", JSON_THROW_ON_ERROR);
    $items = $page->script("JSON.stringify([...document.querySelectorAll({$selector})].map((link) => link.textContent.trim()))");
    $activeItem = $page->script("document.querySelector({$selector} + '[aria-current=page]')?.textContent.trim()");

    expect(json_decode($items, true, flags: JSON_THROW_ON_ERROR))
        ->toBe($expectedItems)
        ->and($activeItem)->toBe($activeLabel);

    $page->assertNoJavascriptErrors();
})->with([
    'HR Core parent' => [
        'core-hr',
        'HR Core',
        'Core HR',
        [
            'Core HR',
            'Struktur Organisasi',
            'Data Karyawan',
            'Administrasi Karier',
            'Helpdesk HR',
            'ESS',
            'Otomatisasi Alur Kerja',
        ],
    ],
    'HR Core child' => [
        'data-karyawan',
        'HR Core',
        'Data Karyawan',
        [
            'Core HR',
            'Struktur Organisasi',
            'Data Karyawan',
            'Administrasi Karier',
            'Helpdesk HR',
            'ESS',
            'Otomatisasi Alur Kerja',
        ],
    ],
    'Payroll' => ['payroll', 'Payroll', 'Payroll', ['Payroll']],
    'Pelatihan' => ['pelatihan', 'Pelatihan', 'Pelatihan', ['Pelatihan']],
    'Recruitment' => [
        'rekrutmen',
        'Recruitment',
        'Recruitment',
        ['Recruitment'],
    ],
    'Time Management' => [
        'time-management',
        'Time Management',
        'Time Management',
        ['Time Management'],
    ],
    'Manajemen Talenta' => [
        'manajemen-talenta',
        'Manajemen Talenta',
        'Manajemen Talenta',
        ['Manajemen Talenta'],
    ],
    'Compensation & Benefits' => [
        'compensation-benefits',
        'Compensation & Benefits',
        'Compensation & Benefits',
        ['Compensation & Benefits'],
    ],
    'Expenses & Reimbursement' => [
        'reimbursement',
        'Expenses & Reimbursement',
        'Expenses & Reimbursement',
        ['Expenses & Reimbursement'],
    ],
    'Loans Management' => [
        'loans-management',
        'Loans Management',
        'Loans Management',
        ['Loans Management'],
    ],
    'KPI' => ['kpi', 'KPI', 'KPI', ['KPI']],
    'AI & Analytics' => [
        'ai-analytics',
        'AI & Analytics',
        'AI & Analytics',
        ['AI & Analytics'],
    ],
]);

it('shows the complete Payroll capabilities on its public page', function (): void {
    $page = visit('/fitur/payroll');

    $page->assertSee('Manajemen Komponen')
        ->assertSee('pajak lintas yurisdiksi')
        ->assertSee('pembayaran multi-mata uang')
        ->assertSee('perencanaan kompensasi yang kompleks')
        ->assertSee('portal penggajian mandiri yang dapat diakses 24/7')
        ->assertSee('visualisasi dashboard')
        ->assertSee('satu tampilan terpusat')
        ->assertSee('setiap langkah wajib')
        ->assertSee('melacak penyelesaiannya')
        ->assertNoJavascriptErrors();
});

it('shows the complete Pelatihan capabilities on its public page', function (): void {
    $page = visit('/fitur/pelatihan');

    $page->assertSee('Flexible & scalable')
        ->assertSee('upskilling, reskilling')
        ->assertSee('learning path yang dapat dikustomisasi')
        ->assertSee('performance review, succession planning, dan IDP')
        ->assertSee('perencanaan karier')
        ->assertSee('di luar bidang keahlian')
        ->assertSee('pelatihan langsung dan daring')
        ->assertSee('beragam format')
        ->assertSee('dapat dilacak dan didukung feedback')
        ->assertSee('kuis, tes, dan survei')
        ->assertNoJavascriptErrors();
});

it('shows the complete Time Management capabilities on its public page', function (): void {
    $page = visit('/fitur/time-management');

    $page->assertSee('operasional global, dan regulasi lokal')
        ->assertSee('persetujuan lintas perangkat')
        ->assertSee('jam kerja mingguan serta biaya proyek')
        ->assertSee('mencegah penugasan berlebih')
        ->assertSee('quick apply')
        ->assertSee('jadwal langsung di ponsel')
        ->assertSee('cuti, lembur, atau tugas')
        ->assertSee('GPS tagging serta pengenalan wajah')
        ->assertSee('clock-in/out lewat ponsel')
        ->assertSee('mengajukan koreksi mandiri')
        ->assertSee('mencatat kehadiran saat offline')
        ->assertSee('Deteksi keterlambatan dan ketidakhadiran secara otomatis')
        ->assertSee('variabel sederhana atau kompleks dari berbagai dimensi')
        ->assertNoJavascriptErrors();
});

it('presents related modules as accessible product navigation', function (): void {
    $page = visit('/fitur/time-management');
    $relatedLinkLabels = $page->script(<<<'JS'
        JSON.stringify(
            [...document.querySelectorAll('#integrasi a')].map((link) =>
                link.getAttribute('aria-label'),
            ),
        )
    JS);

    expect(json_decode($relatedLinkLabels, true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'Buka halaman fitur Attendance',
            'Buka halaman fitur Leave & Cuti',
            'Buka halaman fitur Payroll',
        ]);

    $page->assertSee('Satu data, lanjut ke banyak proses.')
        ->assertSee('Modul sumber')
        ->assertSee('3 terhubung')
        ->assertNoJavascriptErrors();
});
