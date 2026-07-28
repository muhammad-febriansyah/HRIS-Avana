<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Prism\Prism\Facades\Prism;
use RuntimeException;

/**
 * Draws a picture for the AI assistant and bills it to the same token wallet
 * chat uses.
 *
 * The provider returns either a URL that expires within the hour or raw
 * base64; both are copied to the tenant's own storage so a saved conversation
 * still shows its images next month.
 */
final class AiImageGenerator
{
    /**
     * Where a generated image is stored, per tenant, on the public disk.
     */
    private const DIRECTORY = 'ai-images';

    /**
     * Seconds to wait for the provider to draw.
     *
     * Prism applies one `prism.request_timeout` (30s by default) to every call
     * it makes, which suits a chat completion and is far too short for image
     * models: gpt-image-1 and Imagen routinely need a minute. Left at the
     * shared value the call always aborts, and because it runs inside the
     * streamed chat turn it takes the whole reply down with it.
     */
    private const DRAW_TIMEOUT = 180;

    /**
     * Seconds to wait for the connection itself, which either opens promptly
     * or is not going to.
     */
    private const CONNECT_TIMEOUT = 15;

    public function __construct(private readonly AiTokenService $tokens) {}

    /**
     * Whether the assistant is allowed to draw at all.
     */
    public function isAvailable(): bool
    {
        return AiSetting::current()->resolvedImage() !== null;
    }

    /**
     * Generate one image and return where it now lives.
     *
     * Charging happens only after the file is safely stored: a provider error,
     * or an image we could not save, must not cost the tenant anything.
     *
     * @return array{url: string, path: string, prompt: string}
     *
     * @throws RuntimeException when generation is off, blocked, or failed
     */
    public function generate(User $user, string $prompt): array
    {
        $settings = AiSetting::current();
        $image = $settings->resolvedImage();

        if ($image === null) {
            throw new RuntimeException('Pembuatan gambar sedang tidak aktif.');
        }

        $gate = $this->tokens->canChat($user);

        if (! $gate->allowed) {
            throw new RuntimeException((string) $gate->message);
        }

        $apiKey = $settings->resolved()['api_key'];

        if ($apiKey !== '') {
            config(["prism.providers.{$image['provider']}.api_key" => $apiKey]);
        }

        $response = Prism::image()
            ->using($image['provider'], $image['model'])
            ->withPrompt($prompt)
            ->withClientOptions([
                'timeout' => self::DRAW_TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
            ])
            ->generate();

        $generated = $response->firstImage();

        if ($generated === null) {
            throw new RuntimeException('Penyedia AI tidak mengembalikan gambar.');
        }

        $binary = $this->binaryOf($generated->base64, $generated->url);

        $path = sprintf(
            '%s/%s/%s.%s',
            self::DIRECTORY,
            $user->tenant_id ?? 'platform',
            (string) Str::ulid(),
            $this->extensionOf($binary),
        );

        Storage::disk('public')->put($path, $binary);

        $this->tokens->debit($user, $image['token_cost'], 'image');

        return [
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            // The provider may rewrite a short prompt into a fuller one; that
            // rewrite is what the picture actually shows, so it is the honest
            // caption and the better alt text.
            'prompt' => $generated->revisedPrompt ?: $prompt,
        ];
    }

    /**
     * The extension the bytes actually deserve.
     *
     * The file is offered as a download, so naming a WebP or JPEG ".png"
     * hands the user a file their image editor will refuse to open. Providers
     * differ — and gpt-image-1 returns WebP when asked to — so the magic
     * number decides, with PNG as the fallback.
     */
    private function extensionOf(string $binary): string
    {
        return match (true) {
            str_starts_with($binary, "\x89PNG\x0d\x0a\x1a\x0a") => 'png',
            str_starts_with($binary, "\xff\xd8\xff") => 'jpg',
            str_starts_with($binary, 'GIF8') => 'gif',
            str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP' => 'webp',
            default => 'png',
        };
    }

    /**
     * The image bytes, whichever way the provider chose to hand them over.
     */
    private function binaryOf(?string $base64, ?string $url): string
    {
        if ($base64 !== null && $base64 !== '') {
            $decoded = base64_decode($base64, true);

            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        if ($url !== null && $url !== '') {
            $response = Http::timeout(30)->get($url);

            if ($response->successful() && $response->body() !== '') {
                return $response->body();
            }
        }

        throw new RuntimeException('Gambar yang dihasilkan tidak dapat dibaca.');
    }
}
