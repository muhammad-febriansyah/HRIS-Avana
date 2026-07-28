<?php

namespace App\Http\Controllers;

use App\Models\AiTokenOrder;
use App\Models\SubscriptionOrder;
use App\Services\AiTokenService;
use App\Services\PakasirGateway;
use App\Services\SubscriptionRenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public, unauthenticated Pakasir payment callback. The tenant is resolved from
 * the referenced order, so no auth or tenant middleware is needed. Never trust
 * the body alone: the payload is matched against our order and then re-verified
 * server-to-server before anything is granted. Granting is idempotent.
 *
 * Two kinds of order share the endpoint — AI token top-ups (`AIT-…`) and
 * subscription renewals (`SUB-…`) — because Pakasir posts every payment for the
 * project to a single URL.
 */
class PakasirWebhookController extends Controller
{
    public function handle(
        Request $request,
        PakasirGateway $gateway,
        AiTokenService $tokens,
        SubscriptionRenewalService $renewals,
    ): JsonResponse {
        $data = $request->validate([
            'amount' => ['required', 'integer'],
            'order_id' => ['required', 'string'],
            'project' => ['required', 'string'],
            'status' => ['required', 'string'],
            'payment_method' => ['nullable', 'string'],
            'completed_at' => ['nullable', 'string'],
        ]);

        $order = $this->findOrder($data['order_id']);

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

        // Already granted — idempotent no-op.
        $grantedAt = $order instanceof SubscriptionOrder ? $order->applied_at : $order->credited_at;

        if ($grantedAt !== null) {
            return response()->json(['ok' => true]);
        }

        // Mandatory server-to-server verification before granting.
        $transaction = $gateway->verify($order->order_number, (int) $order->amount);

        if (! $gateway->isCompleted($transaction)) {
            return response()->json(['ok' => false, 'error' => 'not_completed'], 422);
        }

        $order->update([
            'status' => $order::STATUS_COMPLETED,
            'payment_method' => $data['payment_method'] ?? ($transaction['payment_method'] ?? null),
            'completed_at' => now(),
            'raw_payload' => $data,
        ]);

        if ($order instanceof SubscriptionOrder) {
            $renewals->apply($order);
        } else {
            $tokens->creditWallet($order);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * The order this payment refers to, from whichever kind owns that number.
     */
    private function findOrder(string $orderNumber): AiTokenOrder|SubscriptionOrder|null
    {
        return AiTokenOrder::query()->where('order_number', $orderNumber)->first()
            ?? SubscriptionOrder::query()->where('order_number', $orderNumber)->first();
    }
}
