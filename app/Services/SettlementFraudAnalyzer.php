<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Support\PrivateFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Screens settlement supporting documents for tampering. It combines three
 * independent signals into a 0-100 risk score:
 *
 *   1. Metadata — editing-software fingerprints (Photoshop/GIMP/…) and
 *      modification dates that betray a doctored file.
 *   2. Reuse — a perceptual hash matched against other settlements, catching
 *      the same receipt claimed twice.
 *   3. Vision — a multimodal model reads the receipt, extracts the amount, and
 *      reports forgery cues (inconsistent fonts, retouched numbers, …).
 *
 * The score is a signal for a human reviewer, never a verdict: a forger who
 * strips metadata and re-photographs a screen can still pass. Every step is
 * defensive — a failure in one never blocks the settlement flow.
 */
class SettlementFraudAnalyzer
{
    /** Image/PDF editors whose fingerprint in a receipt suggests manipulation. */
    private const EDITOR_SIGNATURES = [
        'Adobe Photoshop', 'Photoshop', 'GIMP', 'Pixlr', 'Canva', 'Paint.NET',
        'Affinity Photo', 'Affinity', 'Snapseed', 'PicsArt', 'Facetune',
        'Adobe Lightroom', 'Lightroom', 'Inkscape', 'CorelDRAW',
        'iLovePDF', 'PDFescape', 'Smallpdf', 'Sejda',
    ];

    /** How far an extracted amount may drift from the claim before it is flagged. */
    private const AMOUNT_TOLERANCE = 0.02;

    /** Perceptual-hash Hamming distance under which two images are "the same". */
    private const DUPLICATE_DISTANCE = 6;

    /**
     * Analyse every attachment of a settlement and roll the worst level up onto
     * the settlement itself.
     */
    public function analyzeSettlement(Settlement $settlement): void
    {
        $settlement->loadMissing(['attachments', 'items']);

        foreach ($settlement->attachments as $attachment) {
            try {
                $this->analyze($attachment, $settlement);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $settlement->update([
            'fraud_level' => $this->rollup($settlement->attachments()->get()),
            'fraud_checked_at' => now(),
        ]);
    }

    /**
     * Analyse one attachment and persist its score, level, flags and findings.
     */
    public function analyze(SettlementAttachment $attachment, ?Settlement $settlement = null): void
    {
        $settlement ??= $attachment->settlement;
        $absolutePath = Storage::disk(PrivateFile::DISK)->path($attachment->path);
        $extension = strtolower(pathinfo($attachment->path, PATHINFO_EXTENSION));
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png'], true);

        $flags = [];
        $analysis = [];
        $score = 0;

        // 1. Metadata / editor fingerprint.
        [$metaScore, $metaFlags, $metadata] = $this->inspectMetadata($absolutePath, $extension);
        $score += $metaScore;
        $flags = array_merge($flags, $metaFlags);
        $analysis['metadata'] = $metadata;

        // 2. Perceptual hash + reuse across settlements.
        $phash = $isImage ? $this->perceptualHash($absolutePath) : null;
        if ($phash !== null) {
            $duplicateOf = $this->findDuplicate($attachment, $phash);
            if ($duplicateOf !== null) {
                $score += 60;
                $flags[] = $this->flag('duplicate', "Bukti identik sudah dipakai di settlement {$duplicateOf}", 'high');
                $analysis['duplicate_of'] = $duplicateOf;
            }
        }

        // 3. Vision authenticity + amount cross-check.
        if ($isImage) {
            $vision = $this->inspectWithVision($absolutePath);
            $analysis['vision'] = $vision ?? ['status' => 'skipped'];

            if ($vision !== null) {
                $visionRisk = max(0, min(100, (int) ($vision['risk'] ?? 0)));
                $score += (int) round($visionRisk * 0.5);

                foreach (($vision['red_flags'] ?? []) as $redFlag) {
                    if (is_string($redFlag) && $redFlag !== '') {
                        $flags[] = $this->flag('vision', $redFlag, $visionRisk >= 60 ? 'high' : 'medium');
                    }
                }

                $visionAmount = isset($vision['amount']) ? (float) $vision['amount'] : 0.0;
                if ($this->amountMismatch($settlement, $visionAmount)) {
                    $score += 30;
                    $flags[] = $this->flag(
                        'amount_mismatch',
                        'Nominal pada bukti (Rp '.number_format($visionAmount, 0, ',', '.').') tidak cocok dengan jumlah yang diklaim',
                        'high',
                    );
                }
            }
        }

        $score = min(100, $score);

        $attachment->update([
            'phash' => $phash,
            'fraud_score' => $score,
            'fraud_level' => $this->levelFor($score),
            'fraud_flags' => array_values($flags),
            'fraud_analysis' => $analysis,
            'analyzed_at' => now(),
        ]);
    }

    /**
     * Scan the file for editing-software fingerprints and telltale dates.
     *
     * @return array{0: int, 1: array<int, array<string, string>>, 2: array<string, mixed>}
     */
    private function inspectMetadata(string $path, string $extension): array
    {
        $flags = [];
        $meta = [];
        $score = 0;

        if (! is_readable($path)) {
            return [0, [], ['status' => 'unreadable']];
        }

        // The whole file (uploads are capped at 5MB) — XMP/metadata can sit
        // anywhere, so a byte scan is the most format-agnostic signal.
        $contents = (string) file_get_contents($path);

        foreach (self::EDITOR_SIGNATURES as $signature) {
            if (stripos($contents, $signature) !== false) {
                $score += 45;
                $meta['editor'] = $signature;
                $flags[] = $this->flag('editor', "Berkas mengandung jejak editor \"{$signature}\"", 'high');
                break;
            }
        }

        if (in_array($extension, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (is_array($exif)) {
                $meta['has_exif'] = true;
                $original = $exif['DateTimeOriginal'] ?? null;
                $modified = $exif['DateTime'] ?? null;
                if ($original && $modified && strtotime((string) $modified) - strtotime((string) $original) > 86400) {
                    $score += 15;
                    $flags[] = $this->flag('modified_date', 'Tanggal modifikasi jauh lebih baru dari tanggal foto', 'medium');
                }
            } else {
                $meta['has_exif'] = false;
            }
        }

        if ($extension === 'pdf' && substr_count($contents, '%%EOF') > 1) {
            $score += 15;
            $flags[] = $this->flag('pdf_incremental', 'PDF memiliki revisi bertingkat (kemungkinan diedit ulang)', 'medium');
        }

        return [$score, $flags, $meta];
    }

    /**
     * A 64-bit difference hash (dHash) of an image, as 16 hex characters, or
     * null when the image cannot be read.
     */
    private function perceptualHash(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if ($source === false) {
            return null;
        }

        $width = 9;
        $height = 8;
        $small = imagescale($source, $width, $height, IMG_BILINEAR_FIXED);
        imagedestroy($source);
        if ($small === false) {
            return null;
        }

        $bits = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width - 1; $x++) {
                $bits .= $this->grayscaleAt($small, $x, $y) > $this->grayscaleAt($small, $x + 1, $y) ? '1' : '0';
            }
        }
        imagedestroy($small);

        // 64 bits -> 16 hex chars.
        $hex = '';
        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex(bindec($nibble));
        }

        return $hex;
    }

    /**
     * The 0-255 luminance of one pixel.
     */
    private function grayscaleAt(\GdImage $image, int $x, int $y): int
    {
        $rgb = imagecolorat($image, $x, $y);

        return (int) (
            0.299 * (($rgb >> 16) & 0xFF)
            + 0.587 * (($rgb >> 8) & 0xFF)
            + 0.114 * ($rgb & 0xFF)
        );
    }

    /**
     * The number of a settlement whose attachment is near-identical to this
     * one, or null. Scoped to the tenant and excluding the same settlement.
     */
    private function findDuplicate(SettlementAttachment $attachment, string $phash): ?string
    {
        $candidates = SettlementAttachment::query()
            ->where('tenant_id', $attachment->tenant_id)
            ->where('settlement_id', '!=', $attachment->settlement_id)
            ->whereNotNull('phash')
            ->with('settlement:id,number')
            ->get(['id', 'settlement_id', 'phash']);

        foreach ($candidates as $candidate) {
            if ($this->hammingDistance($phash, (string) $candidate->phash) <= self::DUPLICATE_DISTANCE) {
                return $candidate->settlement?->number ?? "#{$candidate->settlement_id}";
            }
        }

        return null;
    }

    /**
     * Bit difference between two equal-length hex hashes (max distance when the
     * lengths differ, i.e. treat as not matching).
     */
    private function hammingDistance(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b)) {
            return PHP_INT_MAX;
        }

        $distance = 0;
        for ($i = 0, $len = strlen($a); $i < $len; $i++) {
            $xor = hexdec($a[$i]) ^ hexdec($b[$i]);
            $distance += substr_count(decbin($xor), '1');
        }

        return $distance;
    }

    /**
     * Ask the configured vision model to read the receipt and rate its
     * authenticity. Returns null when no AI key is configured or the call
     * fails — screening then falls back to metadata + reuse only.
     *
     * @return array<string, mixed>|null
     */
    private function inspectWithVision(string $path): ?array
    {
        $ai = AiSetting::current()->resolved();
        if ($ai['provider'] !== 'ollama' && $ai['api_key'] === '') {
            return null;
        }

        if ($ai['api_key'] !== '') {
            config(["prism.providers.{$ai['provider']}.api_key" => $ai['api_key']]);
        }

        $schema = new ObjectSchema(
            name: 'receipt_authenticity',
            description: 'Hasil pemeriksaan keaslian bukti pembayaran',
            properties: [
                new NumberSchema('amount', 'Total nominal pada bukti sebagai angka polos tanpa pemisah, atau null bila tak terbaca', nullable: true),
                new StringSchema('date', 'Tanggal pada bukti (format bebas) atau null', nullable: true),
                new StringSchema('vendor', 'Nama merchant/penjual atau null', nullable: true),
                new NumberSchema('risk', 'Skor risiko pemalsuan 0-100 (0=meyakinkan asli, 100=jelas dimanipulasi)'),
                new ArraySchema('red_flags', 'Indikasi manipulasi, bahasa Indonesia singkat', new StringSchema('flag', 'satu indikasi')),
                new StringSchema('summary', 'Ringkasan penilaian satu kalimat'),
            ],
            requiredFields: ['amount', 'date', 'vendor', 'risk', 'red_flags', 'summary'],
        );

        $prompt = <<<'PROMPT'
            Anda auditor forensik keuangan. Periksa gambar bukti pembayaran / kwitansi ini untuk tanda-tanda pemalsuan atau editan.
            Ekstrak total nominal, tanggal, dan nama vendor. Nilai keasliannya: perhatikan font yang tidak konsisten, angka yang tampak di-retouch, artefak kompresi di sekitar nominal, bayangan/warna janggal, garis yang tidak lurus, atau jejak editor digital.
            Beri skor risiko 0-100 dan daftar indikasi. Jangan menuduh secara pasti — hasil ini untuk peninjauan manusia.
            PROMPT;

        try {
            $response = Prism::structured()
                ->using($ai['provider'], $ai['model'])
                ->withClientOptions(['timeout' => 30])
                ->withSchema($schema)
                ->withMessages([
                    new UserMessage($prompt, [Image::fromLocalPath($path)]),
                ])
                ->asStructured();

            return is_array($response->structured) ? $response->structured : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Whether the vision-read amount matches no claimed line, subtotal or total.
     */
    private function amountMismatch(Settlement $settlement, float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $candidates = $settlement->items->pluck('amount')
            ->map(fn ($value): float => (float) $value)
            ->push((float) $settlement->subtotal)
            ->push((float) $settlement->total)
            ->filter(fn (float $value): bool => $value > 0);

        foreach ($candidates as $candidate) {
            if (abs($candidate - $amount) / $candidate <= self::AMOUNT_TOLERANCE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Map a 0-100 score to a level.
     */
    private function levelFor(int $score): string
    {
        return match (true) {
            $score >= 60 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }

    /**
     * The worst level among a collection of analysed attachments, or null.
     *
     * @param  Collection<int, SettlementAttachment>  $attachments
     */
    private function rollup($attachments): ?string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        $worst = null;
        $worstRank = 0;

        foreach ($attachments as $attachment) {
            $level = $attachment->fraud_level;
            if ($level !== null && ($rank[$level] ?? 0) > $worstRank) {
                $worst = $level;
                $worstRank = $rank[$level];
            }
        }

        return $worst;
    }

    /**
     * Build one flag record.
     *
     * @return array{code: string, label: string, severity: string}
     */
    private function flag(string $code, string $label, string $severity): array
    {
        return ['code' => $code, 'label' => $label, 'severity' => $severity];
    }
}
