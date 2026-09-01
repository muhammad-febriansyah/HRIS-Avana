<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Models\SettlementItem;
use App\Support\PrivateFile;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Employee self-service settlements ("Settlement Perdin"): the expense claims an
 * employee files for a business trip, listing what they spent, where they went,
 * and the receipts proving it. Every endpoint is scoped to the caller's own
 * employee record — a settlement belonging to anyone else is a 404 here.
 */
class SettlementController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Icon hints for the mobile transaction rows, keyed by expense category.
     *
     * @var array<string, string>
     */
    private const CATEGORY_ICONS = [
        'penerbangan' => 'flight',
        'akomodasi' => 'hotel',
        'transportasi' => 'car',
        'medical' => 'medical',
        'komunikasi' => 'phone',
        'operasional' => 'cart',
        'representasi' => 'users',
    ];

    /**
     * The caller's own settlements, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = Settlement::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('submission_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Settlement $settlement): array => $this->shapeSummary($settlement));

        return response()->json(['data' => $data]);
    }

    /**
     * One settlement in full: trip context, expense lines, receipts, timeline.
     */
    public function show(Request $request, Settlement $settlement): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $this->ensureOwnedBy($settlement, $employee);

        $settlement->load(['items', 'attachments']);

        return response()->json([
            'data' => $this->shapeSummary($settlement) + [
                'department' => $settlement->department,
                'notes' => $settlement->notes,
                'subtotal' => self::money($settlement->subtotal),
                'tax_amount' => self::money($settlement->tax_amount),
                'tax_rate' => Settlement::TAX_RATE,
                'travel' => [
                    'destination' => $settlement->destination,
                    'start_date' => self::dateString($settlement->trip_start_date),
                    'end_date' => self::dateString($settlement->trip_end_date),
                    'days' => $settlement->tripDays(),
                    'latitude' => $settlement->destination_latitude !== null
                        ? (float) $settlement->destination_latitude
                        : null,
                    'longitude' => $settlement->destination_longitude !== null
                        ? (float) $settlement->destination_longitude
                        : null,
                ],
                'payout_account' => [
                    'bank_name' => $settlement->bank_name,
                    'account_number' => $settlement->bank_account_number,
                    'account_holder' => $settlement->bank_account_holder,
                    'swift' => $settlement->bank_swift,
                ],
                'items' => $settlement->items
                    ->map(fn (SettlementItem $item): array => [
                        'id' => $item->id,
                        'description' => $item->description,
                        'detail' => $item->detail,
                        'category' => $item->category,
                        'category_label' => $item->categoryLabel(),
                        'icon' => self::CATEGORY_ICONS[$item->category] ?? 'receipt',
                        'amount' => self::money($item->amount),
                    ])
                    ->values()
                    ->all(),
                'documents' => $settlement->attachments
                    ->map(fn (SettlementAttachment $attachment): array => [
                        'id' => $attachment->id,
                        'name' => $attachment->original_name,
                        'url' => PrivateFile::urlFor($attachment->path),
                        'size' => $attachment->size,
                    ])
                    ->values()
                    ->all(),
                'timeline' => $this->timeline($settlement),
                'rejection_reason' => $settlement->rejection_reason,
            ],
        ]);
    }

    /**
     * File a new settlement, as a draft or straight to the manager's queue.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(Settlement::ITEM_CATEGORIES))],
            'department' => ['nullable', 'string', 'max:255'],
            'submission_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'destination' => ['nullable', 'string', 'max:255'],
            'trip_start_date' => ['nullable', 'date'],
            'trip_end_date' => ['nullable', 'date', 'after_or_equal:trip_start_date'],
            'destination_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.detail' => ['nullable', 'string', 'max:255'],
            'items.*.category' => ['required', 'in:'.implode(',', array_keys(Settlement::ITEM_CATEGORIES))],
            'items.*.amount' => ['required', 'numeric', 'min:1'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'action' => ['required', 'in:draft,submit'],
        ]);

        $settlement = DB::transaction(function () use ($request, $employee, $data): Settlement {
            $bank = $this->primaryBankAccount($employee);

            $settlement = Settlement::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'number' => $this->nextNumber($employee->tenant_id),
                'title' => $data['title'],
                'category' => $data['category'] ?? null,
                'department' => $data['department'] ?? null,
                'submission_date' => $data['submission_date'],
                'destination' => $data['destination'] ?? null,
                'trip_start_date' => $data['trip_start_date'] ?? null,
                'trip_end_date' => $data['trip_end_date'] ?? null,
                'destination_latitude' => $data['destination_latitude'] ?? null,
                'destination_longitude' => $data['destination_longitude'] ?? null,
                'status' => $data['action'] === 'submit'
                    ? Settlement::STATUS_SUBMITTED
                    : Settlement::STATUS_DRAFT,
                'current_approver_id' => $data['action'] === 'submit' ? $employee->manager_id : null,
                'notes' => $data['notes'] ?? null,
                'bank_name' => $bank['bank_name'] ?? null,
                'bank_account_number' => $bank['account_number'] ?? null,
                'bank_account_holder' => $bank['account_holder'] ?? $employee->full_name,
            ]);

            foreach ($data['items'] as $item) {
                $settlement->items()->create([
                    'tenant_id' => $settlement->tenant_id,
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'detail' => $item['detail'] ?? null,
                    'amount' => round((float) $item['amount'], 2),
                ]);
            }

            foreach ($request->file('documents') ?? [] as $file) {
                $settlement->attachments()->create([
                    'tenant_id' => $settlement->tenant_id,
                    'path' => PrivateFile::store($file, 'settlements'),
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]);
            }

            $settlement->recalculateTotals();

            return $settlement;
        });

        return response()->json([
            'message' => $settlement->status === Settlement::STATUS_SUBMITTED
                ? 'Settlement diajukan untuk persetujuan'
                : 'Settlement disimpan sebagai draft',
            'data' => $this->shapeSummary($settlement),
        ], 201);
    }

    /**
     * The list-row shape, also the header of the detail payload.
     *
     * @return array<string, mixed>
     */
    private function shapeSummary(Settlement $settlement): array
    {
        return [
            'id' => $settlement->id,
            'number' => $settlement->number,
            'title' => $settlement->title,
            'category' => $settlement->category,
            'destination' => $settlement->destination,
            'total' => self::money($settlement->total),
            'status' => $settlement->status,
            'submission_date' => self::dateString($settlement->submission_date),
            'paid_at' => $settlement->paid_at?->toIso8601String(),
        ];
    }

    /**
     * The four process steps the mobile detail screen renders as a timeline.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Settlement $settlement): array
    {
        $rejected = $settlement->status === Settlement::STATUS_REJECTED;

        return [
            [
                'key' => 'submitted',
                'label' => 'Diajukan Karyawan',
                'done' => $settlement->status !== Settlement::STATUS_DRAFT,
                'at' => self::dateString($settlement->submission_date),
            ],
            [
                'key' => 'manager_approved',
                'label' => 'Disetujui Manager',
                'done' => $settlement->manager_approved_at !== null,
                'at' => $settlement->manager_approved_at?->toIso8601String(),
            ],
            [
                'key' => 'finance_verified',
                'label' => 'Verifikasi Finance',
                'done' => $settlement->finance_verified_by !== null,
                'at' => $settlement->paid_at?->toIso8601String(),
            ],
            [
                'key' => 'paid',
                'label' => $rejected ? 'Dikembalikan' : 'Pembayaran',
                'done' => $settlement->paid_at !== null,
                'at' => $rejected
                    ? $settlement->rejected_at?->toIso8601String()
                    : $settlement->paid_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * The employee's primary bank account, snapshotted onto the settlement so a
     * later change to their account details cannot rewrite a past payout.
     *
     * @return array{bank_name?: string, account_number?: string, account_holder?: string|null}
     */
    private function primaryBankAccount(Employee $employee): array
    {
        // Eloquent, not the query builder: the account number is encrypted at
        // rest, and only the model's cast decrypts it.
        $account = EmployeeBankAccount::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if ($account === null) {
            return [];
        }

        return [
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_holder' => $account->account_holder,
        ];
    }

    /**
     * Allocate the next per-tenant settlement number (STL-YYYYMM-0001).
     */
    private function nextNumber(int $tenantId): string
    {
        $prefix = 'STL-'.now()->format('Ym').'-';

        return DB::transaction(function () use ($tenantId, $prefix): string {
            $last = Settlement::forTenant($tenantId)
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Settlements belonging to another employee are invisible, not forbidden —
     * a 403 would confirm the row exists.
     */
    private function ensureOwnedBy(Settlement $settlement, Employee $employee): void
    {
        abort_if(
            (int) $settlement->tenant_id !== (int) $employee->tenant_id
                || (int) $settlement->employee_id !== (int) $employee->id,
            404,
        );
    }

    private static function money(mixed $value): int
    {
        return (int) round((float) $value);
    }

    private static function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
