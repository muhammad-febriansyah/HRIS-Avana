<?php

use App\Models\AiSetting;
use App\Models\Employee;
use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SettlementFraudAnalyzer;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('public');
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->analyzer = app(SettlementFraudAnalyzer::class);
});

/**
 * A submitted claim with one expense line worth `$subtotal`.
 */
function fraudClaim(int $tenantId, float $subtotal = 500_000): Settlement
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    $settlement = Settlement::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'number' => 'STL-FR-'.fake()->unique()->numberBetween(1000, 9999),
        'title' => 'Perjalanan Dinas',
        'submission_date' => '2026-07-18',
        'status' => Settlement::STATUS_SUBMITTED,
    ]);

    $settlement->items()->create([
        'tenant_id' => $tenantId,
        'category' => 'transportasi',
        'description' => 'Tiket',
        'amount' => $subtotal,
    ]);

    $settlement->recalculateTotals();

    return $settlement;
}

/**
 * Generate deterministic JPEG bytes with a label drawn on them, so distinct
 * labels yield distinct perceptual hashes (and the same label repeats exactly).
 */
function receiptBytes(string $label = 'RECEIPT'): string
{
    $image = imagecreatetruecolor(400, 300);
    imagefilledrectangle($image, 0, 0, 400, 300, imagecolorallocate($image, 210, 200, 190));
    imagestring($image, 5, 20, 140, $label, imagecolorallocate($image, 20, 20, 20));

    ob_start();
    imagejpeg($image, null, 90);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * Attach a stored JPEG receipt to a settlement, optionally with extra bytes
 * appended (e.g. an editor fingerprint) or reusing given image bytes.
 */
function attachReceipt(Settlement $settlement, ?string $append = null, ?string $reuseBytes = null): SettlementAttachment
{
    $bytes = $reuseBytes ?? receiptBytes('R'.fake()->unique()->numberBetween(1000, 999999));
    if ($append !== null) {
        $bytes .= $append;
    }

    $path = 'settlements/'.fake()->unique()->uuid().'.jpg';
    Storage::disk('public')->put($path, $bytes);

    return $settlement->attachments()->create([
        'tenant_id' => $settlement->tenant_id,
        'path' => $path,
        'original_name' => 'receipt.jpg',
    ]);
}

it('flags a receipt carrying an image-editor fingerprint', function (): void {
    $settlement = fraudClaim($this->tenant->id);
    $attachment = attachReceipt($settlement, append: 'XMP Adobe Photoshop 2024 metadata');

    $this->analyzer->analyze($attachment->fresh(), $settlement);
    $attachment->refresh();

    expect($attachment->fraud_flags)->toBeArray();
    expect(collect($attachment->fraud_flags)->pluck('code'))->toContain('editor');
    expect($attachment->fraud_score)->toBeGreaterThanOrEqual(45);
    expect($attachment->phash)->not->toBeNull();
});

it('flags a receipt reused across settlements', function (): void {
    $sharedBytes = receiptBytes('SHARED');

    $first = fraudClaim($this->tenant->id);
    $firstAttachment = attachReceipt($first, reuseBytes: $sharedBytes);
    $this->analyzer->analyze($firstAttachment, $first);

    $second = fraudClaim($this->tenant->id);
    $secondAttachment = attachReceipt($second, reuseBytes: $sharedBytes);
    $this->analyzer->analyze($secondAttachment, $second);
    $secondAttachment->refresh();

    expect(collect($secondAttachment->fraud_flags)->pluck('code'))->toContain('duplicate');
    expect($secondAttachment->fraud_analysis['duplicate_of'] ?? null)->toBe($first->number);
    expect($secondAttachment->fraud_level)->toBe('high');
});

it('uses the vision model to flag a tampered receipt and amount mismatch', function (): void {
    AiSetting::current()->update([
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'api_key' => 'test-key',
        'is_enabled' => true,
    ]);

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'amount' => 9_999_999,
            'date' => '2026-07-18',
            'vendor' => 'Toko Fiktif',
            'risk' => 85,
            'red_flags' => ['Font nominal tidak konsisten', 'Angka tampak di-retouch'],
            'summary' => 'Bukti diduga telah dimanipulasi.',
        ]),
    ]);

    $settlement = fraudClaim($this->tenant->id, 500_000);
    $attachment = attachReceipt($settlement);

    $this->analyzer->analyze($attachment, $settlement);
    $attachment->refresh();

    $codes = collect($attachment->fraud_flags)->pluck('code');
    expect($codes)->toContain('vision');
    expect($codes)->toContain('amount_mismatch');
    expect($attachment->fraud_level)->toBe('high');
    expect($attachment->fraud_analysis['vision']['vendor'] ?? null)->toBe('Toko Fiktif');
});

it('does not flag a clean receipt when the claimed amount matches', function (): void {
    AiSetting::current()->update([
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'api_key' => 'test-key',
        'is_enabled' => true,
    ]);

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'amount' => 500_000,
            'date' => '2026-07-18',
            'vendor' => 'PT Kereta',
            'risk' => 5,
            'red_flags' => [],
            'summary' => 'Bukti terlihat wajar.',
        ]),
    ]);

    $settlement = fraudClaim($this->tenant->id, 500_000);
    $attachment = attachReceipt($settlement);

    $this->analyzer->analyze($attachment, $settlement);
    $attachment->refresh();

    expect($attachment->fraud_level)->toBe('low');
    expect(collect($attachment->fraud_flags)->pluck('code'))->not->toContain('amount_mismatch');
});

it('screens documents automatically when a settlement is submitted', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $receipt = UploadedFile::fake()->image('bukti.jpg', 400, 300);

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'employee_id' => $employee->id,
            'title' => 'Perjalanan Dinas',
            'submission_date' => '2026-07-18',
            'items' => [['description' => 'Taksi', 'category' => 'transportasi', 'amount' => 300_000]],
            'attachments' => [$receipt],
            'action' => 'submit',
        ])
        ->assertSessionHas('success');

    $settlement = Settlement::latest('id')->firstOrFail();

    expect($settlement->fraud_checked_at)->not->toBeNull();
    expect($settlement->attachments)->toHaveCount(1);
    expect($settlement->attachments->first()->analyzed_at)->not->toBeNull();
});

it('rescans a settlement under review on demand', function (): void {
    $settlement = fraudClaim($this->tenant->id);
    attachReceipt($settlement);

    actingAs($this->admin)
        ->post(route('avana.settlement.rescan', $settlement))
        ->assertSessionHas('success');

    expect($settlement->attachments()->first()->analyzed_at)->not->toBeNull();
});

it('will not rescan a paid settlement', function (): void {
    $settlement = fraudClaim($this->tenant->id);
    $settlement->update(['status' => Settlement::STATUS_PAID]);

    actingAs($this->admin)
        ->post(route('avana.settlement.rescan', $settlement))
        ->assertStatus(422);
});
