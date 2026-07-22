<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Reimbursement;
use App\Services\ApprovalEngine;
use App\Services\AutoApproval;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Employee self-service reimbursement: money already spent, claimed back. */
class ReimbursementController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = Reimbursement::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Reimbursement $r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'category' => $r->category,
                'category_label' => $r->categoryLabel(),
                'title' => $r->title,
                'amount' => (int) round((float) $r->amount),
                'date' => self::dateString($r->expense_date),
                'status' => $r->status,
                'approved_at' => $r->approved_at?->toIso8601String(),
                'paid_at' => $r->paid_at?->toIso8601String(),
                'rejection_reason' => $r->rejection_reason,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(Reimbursement::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'expense_date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $reimbursement = Reimbursement::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'current_approver_id' => $employee->manager_id,
            'number' => $this->nextNumber($employee->tenant_id),
            'category' => $data['category'],
            'title' => $data['title'] ?? (Reimbursement::CATEGORIES[$data['category']] ?? $data['category']),
            'amount' => round((float) $data['amount'], 2),
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
            'description' => $data['description'] ?? null,
            'receipt_path' => $request->hasFile('receipt')
                ? $request->file('receipt')->store('reimbursements', 'public')
                : null,
            'status' => 'pending',
        ]);

        // A top approver (director) has no manager above them, so their own
        // claim is approved on the spot (self-approved) and moves to Finance.
        if ($employee->is_top_approver) {
            AutoApproval::reimbursement($reimbursement, $employee->user_id);

            return response()->json([
                'message' => 'Reimbursement langsung disetujui (approver puncak)',
                'data' => ['id' => $reimbursement->id, 'number' => $reimbursement->number, 'status' => 'approved'],
            ], 201);
        }

        // Route through the configured approval workflow when one is active.
        ApprovalEngine::start($reimbursement, $employee);

        return response()->json([
            'message' => 'Reimbursement terkirim',
            'data' => ['id' => $reimbursement->id, 'number' => $reimbursement->number],
        ], 201);
    }

    /**
     * Allocate the next per-tenant reimbursement number (RMB-YYYYMM-0001),
     * mirroring the web module so both sides stay in one sequence.
     */
    private function nextNumber(int $tenantId): string
    {
        $prefix = 'RMB-'.now()->format('Ym').'-';

        return DB::transaction(function () use ($tenantId, $prefix): string {
            $last = Reimbursement::forTenant($tenantId)
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    private static function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
