<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee self-service cash advances: the operational float an employee asks
 * for up front, before spending. Every endpoint is scoped to the caller's own
 * employee record — an advance belonging to anyone else is a 404 here.
 */
class CashAdvanceController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the status enum, mirroring the web module.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'disbursed' => 'Dicairkan',
        'settled' => 'Selesai',
    ];

    /**
     * The caller's own advances, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = CashAdvance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CashAdvance $advance): array => $this->shape($advance));

        return response()->json(['data' => $data]);
    }

    /**
     * One advance in full, including how the money was handed over.
     */
    public function show(Request $request, CashAdvance $cashAdvance): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if(
            (int) $cashAdvance->tenant_id !== (int) $employee->tenant_id
                || (int) $cashAdvance->employee_id !== (int) $employee->id,
            404,
        );

        return response()->json([
            'data' => $this->shape($cashAdvance) + [
                'reason' => $cashAdvance->reason,
                'disbursement_method' => $cashAdvance->disbursement_method,
                'disbursement_reference' => $cashAdvance->disbursement_reference,
                'timeline' => $this->timeline($cashAdvance),
            ],
        ]);
    }

    /**
     * Ask for an advance. It lands in the approver's queue as `pending`.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'purpose' => ['required', 'string', 'max:255'],
            'needed_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $advance = CashAdvance::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'amount' => round((float) $data['amount'], 2),
            'purpose' => $data['purpose'],
            'request_date' => now()->toDateString(),
            'needed_date' => $data['needed_date'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan uang muka terkirim',
            'data' => $this->shape($advance),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CashAdvance $advance): array
    {
        return [
            'id' => $advance->id,
            'amount' => (int) round((float) $advance->amount),
            'purpose' => $advance->purpose,
            'status' => $advance->status,
            'status_label' => self::STATUS_LABELS[$advance->status] ?? $advance->status,
            'request_date' => self::dateString($advance->request_date),
            'needed_date' => self::dateString($advance->needed_date),
            'disbursed_at' => $advance->disbursed_at?->toIso8601String(),
        ];
    }

    /**
     * The request -> approval -> disbursement trail the app renders.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timeline(CashAdvance $advance): array
    {
        $rejected = $advance->status === 'rejected';

        return [
            [
                'key' => 'requested',
                'label' => 'Diajukan',
                'done' => true,
                'at' => self::dateString($advance->request_date),
            ],
            [
                'key' => 'approved',
                'label' => $rejected ? 'Ditolak' : 'Disetujui',
                'done' => $advance->approved_at !== null || $rejected,
                'at' => $advance->approved_at?->toIso8601String(),
            ],
            [
                'key' => 'disbursed',
                'label' => 'Dicairkan Finance',
                'done' => $advance->disbursed_at !== null,
                'at' => $advance->disbursed_at?->toIso8601String(),
            ],
        ];
    }

    private static function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
