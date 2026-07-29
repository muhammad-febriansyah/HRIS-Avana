<?php

namespace App\Http\Controllers;

use App\Models\AiTokenOrder;
use App\Services\AiTokenService;
use App\Services\PakasirGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Where Pakasir sends a buyer back to after paying from the phone.
 *
 * The app hands payment to an external browser, which carries no Laravel
 * session — so the signed-in return page inside /avana would only bounce them
 * to a login screen after they had already paid. This page is public and says
 * what happened.
 *
 * Public is safe here because nothing is taken on trust: the order number alone
 * grants nothing, Pakasir is asked server-to-server whether the payment
 * settled, and crediting is idempotent. Nothing about the buyer is shown, so an
 * order number guessed by a stranger reveals only whether some payment cleared.
 */
class AiTokenReturnController extends Controller
{
    public function __invoke(Request $request, AiTokenService $tokens, PakasirGateway $gateway): View
    {
        $order = AiTokenOrder::query()
            ->where('scope', AiTokenOrder::SCOPE_USER)
            ->where('order_number', (string) $request->query('order'))
            ->first();

        if ($order === null) {
            return view('payment.ai-token-return', ['state' => 'unknown']);
        }

        if ($order->credited_at !== null) {
            return view('payment.ai-token-return', ['state' => 'credited']);
        }

        // Settle on the way past rather than leaving it to the webhook: the
        // buyer is looking at the screen now, and the app's next poll should
        // find the tokens already there.
        $transaction = $gateway->verify($order->order_number, (int) $order->amount);

        if (! $gateway->isCompleted($transaction)) {
            return view('payment.ai-token-return', ['state' => 'pending']);
        }

        $order->update([
            'status' => AiTokenOrder::STATUS_COMPLETED,
            'payment_method' => $transaction['payment_method'] ?? null,
            'completed_at' => now(),
            'raw_payload' => $transaction,
        ]);

        $tokens->creditWallet($order);

        return view('payment.ai-token-return', ['state' => 'credited']);
    }
}
