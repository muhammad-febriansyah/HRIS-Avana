<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AiTokenOrder;
use App\Models\AiTokenPack;
use App\Services\AiTokenService;
use App\Services\PakasirGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Buying AI tokens for yourself.
 *
 * The company's pool is rationed by a per-user cap, so a heavy user could be
 * stopped for the month with nobody to ask but the admin. Tokens bought here
 * belong to the buyer, sit outside that cap, and are only drawn on once the
 * company's own pools cannot cover a message — so paying never subsidises the
 * employer.
 */
class EssAiTokenController extends Controller
{
    public function __construct(private readonly AiTokenService $tokens) {}

    /**
     * Balance, packs on sale, and this user's own order history.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('avana/saya/token-ai', [
            'summary' => $this->tokens->remainingForUser($user),
            'packs' => AiTokenPack::query()
                ->active()
                ->orderBy('price')
                ->get(['id', 'name', 'token_amount', 'price', 'description']),
            'orders' => AiTokenOrder::query()
                ->where('user_id', $user->id)
                ->where('scope', AiTokenOrder::SCOPE_USER)
                ->latest()
                ->limit(20)
                ->get(['id', 'order_number', 'pack_name', 'token_amount', 'amount', 'status', 'created_at'])
                ->map(fn (AiTokenOrder $order): array => [
                    'order_number' => $order->order_number,
                    'pack_name' => $order->pack_name,
                    'token_amount' => $order->token_amount,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at?->locale('id')->translatedFormat('d M Y H:i'),
                ]),
        ]);
    }

    /**
     * Start a personal purchase and hand the browser to Pakasir.
     *
     * Returns a Symfony response because {@see Inertia::location()} answers an
     * Inertia request with a 409 and a plain away-redirect otherwise.
     */
    public function purchase(Request $request): SymfonyResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'pack_id' => ['required', 'integer', 'exists:ai_token_packs,id'],
        ]);

        $pack = AiTokenPack::query()->active()->findOrFail($data['pack_id']);
        $order = $this->openOrder($user->id, $user->tenant_id, $pack);

        $payUrl = app(PakasirGateway::class)->payUrl(
            $order->order_number,
            (int) $order->amount,
            route('avana.saya.token-ai.callback', ['order' => $order->order_number]),
        );

        return Inertia::location($payUrl);
    }

    /**
     * Browser return from Pakasir. Verifies server-side and credits straight
     * away; the webhook still settles anyone who never comes back.
     */
    public function callback(Request $request): RedirectResponse
    {
        $order = AiTokenOrder::query()
            ->where('user_id', $request->user()->id)
            ->where('scope', AiTokenOrder::SCOPE_USER)
            ->where('order_number', (string) $request->query('order'))
            ->first();

        if ($order === null) {
            return redirect()->route('avana.saya.token-ai')->with('error', 'Pesanan token tidak ditemukan.');
        }

        if ($order->credited_at !== null) {
            return redirect()->route('avana.saya.token-ai')->with('success', 'Token sudah masuk ke saldo pribadi Anda.');
        }

        if ($this->settle($order)) {
            return redirect()->route('avana.saya.token-ai')->with('success', 'Pembayaran berhasil. Token masuk ke saldo pribadi Anda.');
        }

        return redirect()->route('avana.saya.token-ai')
            ->with('info', 'Pembayaran belum selesai. Saldo diperbarui otomatis setelah pembayaran dikonfirmasi.');
    }

    /**
     * Create the pending personal order for a pack.
     *
     * Shared with the mobile API so both surfaces produce the same row — an
     * order started on the phone and finished in a browser is one purchase.
     */
    public function openOrder(int $userId, ?int $tenantId, AiTokenPack $pack): AiTokenOrder
    {
        return AiTokenOrder::create([
            'order_number' => 'AIU-'.$userId.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'scope' => AiTokenOrder::SCOPE_USER,
            'ai_token_pack_id' => $pack->id,
            'pack_name' => $pack->name,
            'token_amount' => $pack->token_amount,
            'amount' => $pack->price,
            'status' => AiTokenOrder::STATUS_PENDING,
        ]);
    }

    /**
     * Verify with Pakasir and credit when paid. True once the tokens have landed.
     */
    public function settle(AiTokenOrder $order): bool
    {
        $gateway = app(PakasirGateway::class);
        $transaction = $gateway->verify($order->order_number, (int) $order->amount);

        if (! $gateway->isCompleted($transaction)) {
            return false;
        }

        $order->update([
            'status' => AiTokenOrder::STATUS_COMPLETED,
            'payment_method' => $transaction['payment_method'] ?? null,
            'completed_at' => now(),
            'raw_payload' => $transaction,
        ]);

        $this->tokens->creditWallet($order);

        return true;
    }
}
