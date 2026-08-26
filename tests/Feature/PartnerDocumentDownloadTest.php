<?php

use App\Models\PartnerDocumentDownload;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('the partner page exposes the company profile download link', function () {
    $this->withoutVite();

    $this->get(route('partnership'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/partnership')
            ->where('companyProfileDownloadUrl', route('partnership.document.download'))
            ->has('faqs'));
});

test('the public company profile download is tracked', function () {
    $response = $this->get(route('partnership.document.download'));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
    expect(PartnerDocumentDownload::query()->count())->toBe(1);
});

test('the super admin dashboard shows unique company profile downloaders', function () {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    PartnerDocumentDownload::create([
        'visitor_hash' => 'visitor-a',
        'document' => 'company-profile',
    ]);
    PartnerDocumentDownload::create([
        'visitor_hash' => 'visitor-a',
        'document' => 'company-profile',
    ]);
    PartnerDocumentDownload::create([
        'visitor_hash' => 'visitor-b',
        'document' => 'company-profile',
    ]);

    $superAdmin = User::whereHas('roles', fn ($query) => $query->where('code', 'super_admin'))
        ->firstOrFail();

    actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('kpis.4.label', 'Company Profile Download')
            ->where('kpis.4.value', '2')
            ->where('kpis.4.delta', '3 total unduhan'));
});
