<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the Pakasir payment gateway. Builds the hosted-checkout URL a
 * buyer is redirected to and verifies a transaction server-to-server before any
 * wallet is credited (never trust the webhook body alone).
 *
 * @see https://pakasir.com/p/docs
 */
final class PakasirGateway
{
    private string $baseUrl;

    private string $slug;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.pakasir.base_url', 'https://app.pakasir.com'), '/');
        $this->slug = (string) config('services.pakasir.slug', '');
        $this->apiKey = (string) config('services.pakasir.api_key', '');
    }

    /**
     * The project slug configured for this deployment.
     */
    public function slug(): string
    {
        return $this->slug;
    }

    /**
     * Hosted checkout URL the buyer is redirected to.
     */
    public function payUrl(string $orderNumber, int $amount, string $returnUrl): string
    {
        $query = http_build_query([
            'order_id' => $orderNumber,
            'redirect' => $returnUrl,
        ]);

        return "{$this->baseUrl}/pay/{$this->slug}/{$amount}?{$query}";
    }

    /**
     * Fetch the authoritative transaction record from Pakasir, or null when the
     * lookup fails or the transaction is unknown.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $orderNumber, int $amount): ?array
    {
        $response = Http::acceptJson()
            ->get("{$this->baseUrl}/api/transactiondetail", [
                'project' => $this->slug,
                'amount' => $amount,
                'order_id' => $orderNumber,
                'api_key' => $this->apiKey,
            ]);

        if ($response->failed()) {
            Log::warning('Pakasir verify failed', [
                'order' => $orderNumber,
                'status' => $response->status(),
                'has_api_key' => $this->apiKey !== '',
            ]);

            return null;
        }

        $transaction = $response->json('transaction');

        if (! is_array($transaction)) {
            Log::warning('Pakasir verify returned no transaction', [
                'order' => $orderNumber,
                'has_api_key' => $this->apiKey !== '',
            ]);

            return null;
        }

        return $transaction;
    }

    /**
     * Whether a verified transaction record represents a settled payment.
     *
     * @param  array<string, mixed>|null  $transaction
     */
    public function isCompleted(?array $transaction): bool
    {
        return is_array($transaction) && ($transaction['status'] ?? null) === 'completed';
    }
}
