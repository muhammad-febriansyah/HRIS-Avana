<?php

namespace App\Observers;

use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Fires an in-app notification to the requester the moment any approvable
 * request (leave, overtime, permission, WFH, correction, claim) is decided —
 * regardless of whether the decision came from the web approval centre or the
 * mobile MSS endpoint. Registered for each request model in AppServiceProvider.
 */
class RequestDecisionObserver
{
    /**
     * @var array<int, string>
     */
    private const DECIDED = ['approved', 'rejected'];

    public function updated(Model $request): void
    {
        if (! $request->wasChanged('status')) {
            return;
        }

        if (! in_array($request->status, self::DECIDED, true)) {
            return;
        }

        Notifier::requestDecided($request, $request->status);
    }
}
