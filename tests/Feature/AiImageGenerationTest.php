<?php

use App\Models\AiSetting;
use App\Models\AiTokenLedger;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiImageGenerator;
use App\Services\AiToolkit;
use App\Support\GeneratedImageBag;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\ImageResponseFake;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\GeneratedImage;

beforeEach(function (): void {
    Storage::fake('public');

    $this->tenant = Tenant::create([
        'name' => 'PT Gambar',
        'company_name' => 'PT Gambar',
        'slug' => 'gambar',
        'status' => 'active',
        'ai_token_quota' => 100_000,
        'ai_token_balance' => 0,
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    AiSetting::current()->update([
        'provider' => 'openai',
        'api_key' => 'sk-test',
        'model' => 'gpt-4o-mini',
        'is_enabled' => true,
        'image_enabled' => true,
        'image_model' => 'gpt-image-1',
        'image_token_cost' => 2500,
    ]);
});

/** A 1x1 PNG, so the fake provider hands back the kind of bytes a real one does. */
function fakeImageResponse(?string $revised = null): ImageResponseFake
{
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    return ImageResponseFake::make()->withImages([
        new GeneratedImage(base64: $png, revisedPrompt: $revised),
    ]);
}

/**
 * @return array<int, string>
 */
function toolNamesFor(User $user): array
{
    return array_map(
        fn (Tool $tool): string => $tool->name(),
        AiToolkit::forUser($user),
    );
}

it('stores the generated image on the tenant disk', function (): void {
    Prism::fake([fakeImageResponse()]);

    $result = app(AiImageGenerator::class)->generate($this->user, 'kucing oranye');

    Storage::disk('public')->assertExists($result['path']);

    expect($result['path'])->toStartWith("ai-images/{$this->tenant->id}/")
        ->and($result['url'])->toContain($result['path'])
        ->and($result['prompt'])->toBe('kucing oranye');
});

it('bills the image to the same wallet as chat', function (): void {
    Prism::fake([fakeImageResponse()]);

    app(AiImageGenerator::class)->generate($this->user, 'poster rapat');

    $ledger = AiTokenLedger::where('user_id', $this->user->id)->sole();

    expect((int) $ledger->tokens)->toBe(2500)
        ->and($ledger->source)->toBe('image')
        ->and($ledger->type)->toBe(AiTokenLedger::TYPE_DEBIT);
});

it('charges the cost the super admin configured', function (): void {
    AiSetting::current()->update(['image_token_cost' => 900]);
    Prism::fake([fakeImageResponse()]);

    app(AiImageGenerator::class)->generate($this->user, 'logo koperasi');

    expect((int) AiTokenLedger::where('user_id', $this->user->id)->sum('tokens'))->toBe(900);
});

it('prefers the revised prompt as the caption', function (): void {
    Prism::fake([fakeImageResponse('a fluffy orange cat on a red sofa')]);

    $result = app(AiImageGenerator::class)->generate($this->user, 'kucing');

    expect($result['prompt'])->toBe('a fluffy orange cat on a red sofa');
});

it('charges nothing when the provider returns no picture', function (): void {
    Prism::fake([ImageResponseFake::make()->withImages([])]);

    expect(fn () => app(AiImageGenerator::class)->generate($this->user, 'apa saja'))
        ->toThrow(RuntimeException::class);

    expect(AiTokenLedger::where('user_id', $this->user->id)->count())->toBe(0);
});

it('refuses to draw once the tenant pool is empty', function (): void {
    $this->tenant->update(['ai_token_quota' => 0, 'ai_token_balance' => 0]);
    Prism::fake([fakeImageResponse()]);

    expect(fn () => app(AiImageGenerator::class)->generate($this->user, 'apa saja'))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('offers the drawing tool only when a super admin turned it on', function (): void {
    expect(toolNamesFor($this->user))->toContain('buat_gambar');

    AiSetting::current()->update(['image_enabled' => false]);

    expect(toolNamesFor($this->user->fresh()))->not->toContain('buat_gambar');
});

it('withholds the tool from a provider that cannot draw', function (): void {
    // Anthropic has no Prism image handler; registering the tool would promise
    // a picture the provider can never produce.
    AiSetting::current()->update(['provider' => 'anthropic']);

    expect(toolNamesFor($this->user))->not->toContain('buat_gambar');
});

it('appends what it drew to the reply as markdown', function (): void {
    $bag = app(GeneratedImageBag::class);

    expect($bag->toMarkdown())->toBe('');

    $bag->push('http://localhost/storage/ai-images/1/abc.png', 'kucing oranye');

    expect($bag->toMarkdown())
        ->toContain('![kucing oranye](http://localhost/storage/ai-images/1/abc.png)');
});
