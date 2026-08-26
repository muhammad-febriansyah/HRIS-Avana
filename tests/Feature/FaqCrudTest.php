<?php

use App\Models\Faq;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
});

it('allows the super admin to manage faqs', function (): void {
    actingAs($this->superAdmin)->post(route('avana.faqs.store'), [
        'question' => 'Pertanyaan baru',
        'answer' => 'Jawaban baru',
    ])->assertRedirect();

    $faq = Faq::where('question', 'Pertanyaan baru')->firstOrFail();
    actingAs($this->superAdmin)->put(route('avana.faqs.update', $faq), [
        'question' => 'Pertanyaan diperbarui',
        'answer' => 'Jawaban diperbarui',
    ])->assertRedirect();

    expect($faq->fresh()->answer)->toBe('Jawaban diperbarui');
    actingAs($this->superAdmin)->delete(route('avana.faqs.destroy', $faq))->assertRedirect();
    expect(Faq::find($faq->id))->toBeNull();
});

it('blocks non super admins from faq management', function (): void {
    $tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    actingAs($tenantAdmin)->get(route('avana.faqs'))->assertForbidden();
});

it('registers faq in the platform navigation', function (): void {
    $faq = collect(AvanaNav::platformGroups())
        ->flatMap(fn (array $group): array => $group['items'])
        ->firstWhere('id', 'faqs');

    expect($faq['href'])->toBe('/avana/faqs')
        ->and($faq['label'])->toBe('FAQ');
});
