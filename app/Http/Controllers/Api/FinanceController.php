<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Finance/payroll disbursement of reimbursements from the mobile app: once a
 * manager has approved a claim it lands here to be paid out (status → paid),
 * which notifies the employee. Restricted to finance-capable roles.
 */
class FinanceController extends Controller
{
    /**
     * Roles allowed to disburse reimbursements.
     *
     * @var array<int, string>
     */
    private const FINANCE_ROLES = ['payroll', 'admin_tenant_hr', 'super_admin'];

    /** Reimbursement claims for finance, defaulting to the approved-unpaid queue. */
    public function reimbursements(Request $request): JsonResponse
    {
        $user = $this->ensureFinance($request);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['approved', 'paid', 'all'])],
        ]);

        $status = $data['status'] ?? 'approved';

        $claims = Claim::forTenant($user->tenant_id)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->with('employee:id,full_name,employee_number')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Claim $claim): array => [
                'id' => $claim->id,
                'title' => $claim->title,
                'category' => $claim->claim_type,
                'amount' => (int) round((float) $claim->amount),
                'status' => $claim->status,
                'claim_date' => $claim->claim_date?->toDateString(),
                'approved_at' => $claim->approved_at?->toIso8601String(),
                'paid_at' => $claim->paid_at?->toIso8601String(),
                'employee' => $claim->employee !== null ? [
                    'name' => $claim->employee->full_name,
                    'employee_number' => $claim->employee->employee_number,
                ] : null,
            ]);

        return response()->json(['data' => $claims]);
    }

    /** Pay out an approved reimbursement claim. */
    public function payReimbursement(Request $request, Claim $claim): JsonResponse
    {
        $user = $this->ensureFinance($request);

        abort_if((int) $claim->tenant_id !== (int) $user->tenant_id, 404);
        abort_unless($claim->status === 'approved', 422, 'Hanya klaim yang sudah disetujui yang dapat dibayar.');

        $claim->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_by' => $user->id,
        ]);

        return response()->json(['message' => 'Reimbursement ditandai dibayar.']);
    }

    /**
     * Abort with 403 unless the caller holds a finance-capable role.
     */
    private function ensureFinance(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles:id,code');

        abort_unless(
            $user->roles->pluck('code')->intersect(self::FINANCE_ROLES)->isNotEmpty(),
            403,
            'Anda tidak memiliki akses finance.',
        );

        return $user;
    }
}
