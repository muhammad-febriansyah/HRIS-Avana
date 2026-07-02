<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Claim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Employee self-service reimbursement (backed by the Claim model). */
class ReimbursementController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = Claim::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('claim_date')
            ->get(['id', 'claim_type', 'title', 'amount', 'claim_date', 'status'])
            ->map(fn (Claim $c): array => [
                'id' => $c->id,
                'category' => $c->claim_type,
                'title' => $c->title,
                'amount' => (int) round((float) $c->amount),
                'date' => $c->claim_date instanceof Carbon ? $c->claim_date->toDateString() : $c->claim_date,
                'status' => $c->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $claim = Claim::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'claim_type' => $data['category'],
            'title' => $data['category'],
            'amount' => $data['amount'],
            'claim_date' => now()->toDateString(),
            'description' => $data['description'] ?? null,
            'receipt_path' => $request->hasFile('receipt') ? $request->file('receipt')->store('claims', 'public') : null,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Reimbursement terkirim', 'data' => ['id' => $claim->id]], 201);
    }
}
