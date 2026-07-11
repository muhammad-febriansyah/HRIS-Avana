<?php

namespace App\Observers;

use App\Models\Claim;
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

        if (in_array($request->status, self::DECIDED, true)) {
            Notifier::requestDecided($request, $request->status);

            return;
        }

        // A reimbursement moving on to paid closes the loop for the employee.
        if ($request instanceof Claim && $request->status === 'paid') {
            Notifier::reimbursementPaid($request);
        }
    }
}
