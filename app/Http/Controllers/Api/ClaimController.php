<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Claim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Employee self-service reimbursement claims. */
class ClaimController extends Controller
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
                'claim_type' => $c->claim_type,
                'title' => $c->title,
                'amount' => (float) $c->amount,
                'claim_date' => $c->claim_date instanceof Carbon ? $c->claim_date->toDateString() : $c->claim_date,
                'status' => $c->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'claim_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'claim_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $path = $request->hasFile('receipt')
            ? $request->file('receipt')->store('claims', 'public')
            : null;

        $claim = Claim::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'claim_type' => $data['claim_type'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'claim_date' => $data['claim_date'],
            'description' => $data['description'] ?? null,
            'receipt_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Klaim terkirim', 'id' => $claim->id], 201);
    }
}
