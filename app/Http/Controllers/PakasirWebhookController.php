<?php

namespace App\Http\Controllers;

use App\Models\AiTokenOrder;
use App\Services\AiTokenService;
use App\Services\PakasirGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public, unauthenticated Pakasir payment callback. The tenant is resolved from
 * the referenced order, so no auth or tenant middleware is needed. Never trust
 * the body alone: the payload is matched against our order and then re-verified
 * server-to-server before any wallet is credited. Crediting is idempotent.
 */
class PakasirWebhookController extends Controller
{
    public function handle(Request $request, PakasirGateway $gateway, AiTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer'],
            'order_id' => ['required', 'string'],
            'project' => ['required', 'string'],
            'status' => ['required', 'string'],
            'payment_method' => ['nullable', 'string'],
            'completed_at' => ['nullable', 'string'],
        ]);

        $order = AiTokenOrder::query()->where('order_number', $data['order_id'])->first();

        if ($order === null) {
            return response()->json(['ok' => false, 'error' => 'unknown_order'], 404);
        }

        // Belongs to this project and the amount matches our snapshot.
        if ($data['project'] !== $gateway->slug()) {
            return response()->json(['ok' => false, 'error' => 'project_mismatch'], 422);
        }

        if ((int) $data['amount'] !== (int) $order->amount) {
            Log::warning('Pakasir webhook amount mismatch', [
                'order' => $order->order_number,
                'expected' => $order->amount,
                'received' => $data['amount'],
            ]);

            return response()->json(['ok' => false, 'error' => 'amount_mismatch'], 422);
        }

        // Already credited — idempotent no-op.
        if ($order->credited_at !== null) {
            return response()->json(['ok' => true]);
        }

        // Mandatory server-to-server verification before crediting.
        $transaction = $gateway->verify($order->order_number, (int) $order->amount);

        if (! $gateway->isCompleted($transaction)) {
            return response()->json(['ok' => false, 'error' => 'not_completed'], 422);
        }

        $order->update([
            'status' => AiTokenOrder::STATUS_COMPLETED,
            'payment_method' => $data['payment_method'] ?? ($transaction['payment_method'] ?? null),
            'completed_at' => now(),
            'raw_payload' => $data,
        ]);

        $tokens->creditWallet($order);

        return response()->json(['ok' => true]);
    }
}
