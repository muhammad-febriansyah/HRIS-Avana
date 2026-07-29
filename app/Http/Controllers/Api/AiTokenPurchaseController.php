<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Avana\EssAiTokenController;
use App\Http\Controllers\Controller;
use App\Models\AiTokenOrder;
use App\Models\AiTokenPack;
use App\Services\AiTokenService;
use App\Services\PakasirGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buying personal AI tokens from the phone.
 *
 * The order and the crediting are the web controller's — one purchase should
 * look the same whichever screen started it, and the Pakasir webhook settles
 * both. Only the shape of the answers differs: JSON and a payment URL the app
 * opens, rather than a redirect.
 */
class AiTokenPurchaseController extends Controller
{
    public function __construct(
        private readonly AiTokenService $tokens,
        private readonly EssAiTokenController $web,
    ) {}

    /**
     * Balance, packs on sale, and recent personal orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'summary' => $this->tokens->remainingForUser($user),
                'usage' => $this->tokens->usageSeriesForUser($user),
                'packs' => AiTokenPack::query()
                    ->active()
                    ->orderBy('price')
                    ->get(['id', 'name', 'token_amount', 'price', 'description']),
                'orders' => AiTokenOrder::query()
                    ->where('user_id', $user->id)
                    ->where('scope', AiTokenOrder::SCOPE_USER)
                    ->latest()
                    ->limit(10)
                    ->get(['order_number', 'pack_name', 'token_amount', 'amount', 'status', 'created_at']),
            ],
        ]);
    }

    /**
     * Open a personal order and hand back the URL to pay it.
     *
     * The app opens that URL in a browser rather than embedding the gateway, so
     * the payment page stays whatever Pakasir serves.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'pack_id' => ['required', 'integer', 'exists:ai_token_packs,id'],
        ]);

        $pack = AiTokenPack::query()->active()->findOrFail($data['pack_id']);
        $order = $this->web->openOrder($user->id, $user->tenant_id, $pack);

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'amount' => (int) $order->amount,
                'token_amount' => (int) $order->token_amount,
                // The public return page, not the signed-in one: payment opens
                // in an external browser that carries no Laravel session, so
                // the /avana callback would only redirect the buyer to login
                // after they had already paid.
                'pay_url' => app(PakasirGateway::class)->payUrl(
                    $order->order_number,
                    (int) $order->amount,
                    route('bayar.token-ai.selesai', ['order' => $order->order_number]),
                ),
            ],
        ], 201);
    }

    /**
     * Ask whether an order has been paid yet.
     *
     * The app polls this after sending the buyer to the payment page: it cannot
     * see the browser's return, and waiting for the webhook alone would leave
     * the balance stale on screen after a payment that already went through.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = AiTokenOrder::query()
            ->where('user_id', $request->user()->id)
            ->where('scope', AiTokenOrder::SCOPE_USER)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if ($order->credited_at === null) {
            $this->web->settle($order);
            $order->refresh();
        }

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'credited' => $order->credited_at !== null,
                'summary' => $this->tokens->remainingForUser($request->user()->fresh()),
            ],
        ]);
    }
}
