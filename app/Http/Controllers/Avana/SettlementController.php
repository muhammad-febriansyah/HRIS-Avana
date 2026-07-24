<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Models\SettlementItem;
use App\Models\User;
use App\Services\SettlementFraudAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settlements are standalone expense claims for business trips and operational
 * costs: the employee lists expense line items, an 11% tax is applied, a
 * manager approves the amounts, then Finance verifies and pays the total out to
 * the employee's bank account.
 */
class SettlementController extends Controller
{
    /**
     * Roles that may always manage settlements within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'claim';

    /**
     * Indonesian labels for the status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        Settlement::STATUS_DRAFT => 'Draft',
        Settlement::STATUS_SUBMITTED => 'Menunggu Persetujuan Manager',
        Settlement::STATUS_MANAGER_APPROVED => 'Menunggu Verifikasi Finance',
        Settlement::STATUS_PAID => 'Dibayar',
        Settlement::STATUS_REJECTED => 'Ditolak',
    ];

    /**
     * How Finance can pay a settlement out.
     *
     * @var array<string, string>
     */
    private const PAYMENT_METHODS = [
        'transfer' => 'Transfer Bank',
        'cash' => 'Tunai',
        'payroll' => 'Ikut Payroll',
    ];

    /**
     * Deterministic avatar background palette (mirrors ReimbursementController).
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display a server-side paginated, filterable list of settlements.
     */
    public function index(Request $request): Response
    {
        $isManager = $this->canManage($request);

        abort_unless($isManager || $this->isTeamApprover($request), 403);

        $tenantId = $request->user()->tenant_id;
        // A line manager sees only what is sitting in their own queue.
        $teamOnly = $isManager ? null : $request->user()->employee?->id;

        $paginator = Settlement::query()
            ->forTenant($tenantId)
            ->when($teamOnly !== null, fn ($q) => $q->where('current_approver_id', $teamOnly))
            ->with(['employee:id,full_name,employee_number'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($sub) use ($search): void {
                            $sub->where('full_name', 'like', "%{$search}%")
                                ->orWhere('employee_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $totals = Settlement::forTenant($tenantId)
            ->when($teamOnly !== null, fn ($q) => $q->where('current_approver_id', $teamOnly))
            ->selectRaw('status, COUNT(*) as total, SUM(total) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('avana/settlement/index', [
            'settlements' => [
                'data' => $paginator->getCollection()
                    ->map(fn (Settlement $settlement): array => $this->shapeSettlement($settlement))
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'filters' => $request->only(['search', 'status', 'per_page']),
            'statusOptions' => $this->statusOptions(),
            // A line manager only reviews; filing and editing stay with HR.
            'canManage' => $isManager,
            'kpis' => [
                'submitted' => (int) ($totals[Settlement::STATUS_SUBMITTED]->total ?? 0),
                'manager_approved' => (int) ($totals[Settlement::STATUS_MANAGER_APPROVED]->total ?? 0),
                'paid' => (int) ($totals[Settlement::STATUS_PAID]->total ?? 0),
                'paid_amount' => (float) ($totals[Settlement::STATUS_PAID]->amount ?? 0),
            ],
        ]);
    }

    /**
     * Show the form for creating a new settlement request.
     */
    public function create(Request $request): Response
    {
        $this->ensureCan($request, 'create');

        return Inertia::render('avana/settlement/create', [
            'employees' => $this->employeeOptions($request->user()->tenant_id),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Show the form for editing a settlement that is still a draft (or was sent
     * back for changes).
     */
    public function edit(Request $request, Settlement $settlement): Response
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, [Settlement::STATUS_DRAFT, Settlement::STATUS_REJECTED], 'Settlement yang sudah diajukan tidak bisa diubah');

        $settlement->load(['items', 'attachments']);

        return Inertia::render('avana/settlement/edit', [
            'settlement' => [
                'id' => $settlement->id,
                'number' => $settlement->number,
                'employee_id' => $settlement->employee_id,
                'title' => $settlement->title,
                'category' => $settlement->category,
                'department' => $settlement->department,
                'submission_date' => $settlement->submission_date?->toDateString(),
                'destination' => $settlement->destination,
                'trip_start_date' => $settlement->trip_start_date?->toDateString(),
                'trip_end_date' => $settlement->trip_end_date?->toDateString(),
                'destination_latitude' => $settlement->destination_latitude,
                'destination_longitude' => $settlement->destination_longitude,
                'notes' => $settlement->notes,
                'items' => $settlement->items
                    ->map(fn (SettlementItem $item): array => [
                        'description' => $item->description,
                        'detail' => $item->detail,
                        'category' => $item->category,
                        'amount' => (float) $item->amount,
                    ])
                    ->all(),
                'attachments' => $this->shapeAttachments($settlement),
            ],
            'employees' => $this->employeeOptions($request->user()->tenant_id),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Show one settlement with its line items and the actions open to it.
     */
    public function show(Request $request, Settlement $settlement): Response
    {
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureCanReview($request, $settlement);

        $settlement->load([
            'employee:id,full_name,employee_number',
            'items',
            'attachments',
            'managerApprover:id,name',
            'financeVerifier:id,name',
            'rejecter:id,name',
        ]);

        return Inertia::render('avana/settlement/show', [
            'settlement' => $this->shapeSettlement($settlement, detailed: true),
            'paymentMethods' => $this->paymentMethodOptions(),
            // Drives the Finance panel: the manager who approved this claim is
            // not allowed to release its payout too.
            'selfApproved' => (int) $settlement->manager_approved_by === (int) $request->user()->id,
            // A line manager reviews their team's claims but never pays them.
            'canManage' => $this->canManage($request),
        ]);
    }

    /**
     * Persist a new settlement — either a draft or straight to submission.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;
        $data = $this->validateSettlement($request, $tenantId);

        $settlement = DB::transaction(function () use ($request, $tenantId, $data): Settlement {
            $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);
            $bank = $this->primaryBankAccount($employee);

            $settlement = Settlement::create($this->travelFields($data) + [
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'number' => $this->nextNumber($tenantId),
                'title' => $data['title'],
                'category' => $data['category'] ?? null,
                'department' => $data['department'] ?? null,
                'submission_date' => $data['submission_date'],
                'status' => $data['action'] === 'submit'
                    ? Settlement::STATUS_SUBMITTED
                    : Settlement::STATUS_DRAFT,
                'current_approver_id' => $data['action'] === 'submit' ? $employee->manager_id : null,
                'notes' => $data['notes'] ?? null,
                'bank_name' => $bank['bank_name'] ?? null,
                'bank_account_number' => $bank['account_number'] ?? null,
                'bank_account_holder' => $bank['account_holder'] ?? $employee->full_name,
                'bank_swift' => null,
            ]);

            $this->syncItems($settlement, $data['items']);
            $this->storeAttachments($request, $settlement);
            $settlement->recalculateTotals();

            return $settlement;
        });

        if ($settlement->status === Settlement::STATUS_SUBMITTED) {
            $this->screenForFraud($settlement);
        }

        return redirect()->route('avana.settlement.show', $settlement)
            ->with('success', $settlement->status === Settlement::STATUS_SUBMITTED
                ? 'Settlement diajukan untuk persetujuan'
                : 'Settlement disimpan sebagai draft');
    }

    /**
     * Update a draft/returned settlement, replacing its line items.
     */
    public function update(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, [Settlement::STATUS_DRAFT, Settlement::STATUS_REJECTED], 'Settlement yang sudah diajukan tidak bisa diubah');

        $tenantId = $request->user()->tenant_id;
        $data = $this->validateSettlement($request, $tenantId);

        DB::transaction(function () use ($request, $tenantId, $data, $settlement): void {
            $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);
            $bank = $this->primaryBankAccount($employee);

            $settlement->update($this->travelFields($data) + [
                'employee_id' => $employee->id,
                'title' => $data['title'],
                'category' => $data['category'] ?? null,
                'department' => $data['department'] ?? null,
                'submission_date' => $data['submission_date'],
                'status' => $data['action'] === 'submit'
                    ? Settlement::STATUS_SUBMITTED
                    : Settlement::STATUS_DRAFT,
                'current_approver_id' => $data['action'] === 'submit' ? $employee->manager_id : null,
                'rejection_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'notes' => $data['notes'] ?? null,
                'bank_name' => $bank['bank_name'] ?? null,
                'bank_account_number' => $bank['account_number'] ?? null,
                'bank_account_holder' => $bank['account_holder'] ?? $employee->full_name,
            ]);

            $settlement->items()->delete();
            $this->syncItems($settlement, $data['items']);
            $this->storeAttachments($request, $settlement);
            $settlement->recalculateTotals();
        });

        if ($settlement->status === Settlement::STATUS_SUBMITTED) {
            $this->screenForFraud($settlement);
        }

        return redirect()->route('avana.settlement.show', $settlement)
            ->with('success', $settlement->status === Settlement::STATUS_SUBMITTED
                ? 'Settlement diajukan untuk persetujuan'
                : 'Perubahan settlement disimpan');
    }

    /**
     * Delete a settlement that has not been paid, with its receipts.
     */
    public function destroy(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, [Settlement::STATUS_DRAFT, Settlement::STATUS_REJECTED], 'Settlement yang sudah diajukan tidak bisa dihapus');

        $this->deleteAttachmentFiles($settlement);
        $settlement->delete();

        return redirect()->route('avana.settlement')
            ->with('success', 'Settlement dihapus');
    }

    /**
     * Hand a draft/returned settlement to the manager for approval.
     */
    public function submit(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, [Settlement::STATUS_DRAFT, Settlement::STATUS_REJECTED], 'Settlement ini sudah diajukan');

        abort_if($settlement->items()->count() === 0, 422, 'Tambahkan minimal satu baris pengeluaran sebelum mengajukan');

        $settlement->recalculateTotals();
        $settlement->update([
            'status' => Settlement::STATUS_SUBMITTED,
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        $this->screenForFraud($settlement);

        return back()->with('success', 'Settlement diajukan untuk persetujuan');
    }

    /**
     * Re-run the tamper screening on a settlement that is under review.
     */
    public function rescan(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs(
            $settlement,
            [Settlement::STATUS_SUBMITTED, Settlement::STATUS_MANAGER_APPROVED],
            'Pemeriksaan keaslian hanya untuk settlement yang sedang diproses',
        );

        $this->screenForFraud($settlement);

        return back()->with('success', 'Pemeriksaan keaslian bukti diperbarui');
    }

    /**
     * Screen a settlement's documents for tampering. Wrapped so a screening
     * failure never blocks submission or review.
     */
    private function screenForFraud(Settlement $settlement): void
    {
        try {
            app(SettlementFraudAnalyzer::class)->analyzeSettlement($settlement);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Manager approves the claimed amounts, sending it on to Finance.
     */
    public function managerApprove(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureCanReview($request, $settlement);
        $this->ensureStatusIs($settlement, [Settlement::STATUS_SUBMITTED], 'Hanya settlement yang diajukan yang bisa disetujui manager');

        $settlement->update([
            'status' => Settlement::STATUS_MANAGER_APPROVED,
            'manager_approved_by' => $request->user()->id,
            'manager_approved_at' => now(),
            'current_approver_id' => null,
        ]);

        return back()->with('success', 'Settlement disetujui manager, menunggu verifikasi Finance');
    }

    /**
     * Finance verifies the settlement and disburses the payout. Confirming both
     * the bank details and the tax-compliant receipts is required, and the
     * approver may not also be the payer.
     */
    public function financeVerify(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, [Settlement::STATUS_MANAGER_APPROVED], 'Hanya settlement yang disetujui manager yang bisa diverifikasi Finance');
        $this->ensureNotSelfApproved($request, $settlement);

        $data = $request->validate([
            'payment_method' => ['required', 'in:'.implode(',', array_keys(self::PAYMENT_METHODS))],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'confirm_bank' => ['accepted'],
            'confirm_receipts' => ['accepted'],
        ]);

        $settlement->update([
            'status' => Settlement::STATUS_PAID,
            'finance_verified_by' => $request->user()->id,
            'paid_at' => now(),
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
        ]);

        return back()->with('success', 'Settlement diverifikasi & pembayaran dicatat');
    }

    /**
     * Send a submitted or manager-approved settlement back to the employee.
     */
    public function reject(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureCanReview($request, $settlement);
        $this->ensureStatusIs($settlement, [Settlement::STATUS_SUBMITTED, Settlement::STATUS_MANAGER_APPROVED], 'Hanya settlement yang sedang diproses yang bisa dikembalikan');

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $settlement->update([
            'status' => Settlement::STATUS_REJECTED,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
            'current_approver_id' => null,
        ]);

        return back()->with('success', 'Settlement dikembalikan untuk diperbaiki');
    }

    /**
     * Validate the create/update payload for a settlement.
     *
     * @return array<string, mixed>
     */
    private function validateSettlement(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', "exists:employees,id,tenant_id,{$tenantId}"],
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
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'action' => ['required', 'in:draft,submit'],
        ]);
    }

    /**
     * The trip-context columns present in the payload. The web form has no
     * inputs for these — only the mobile app files them — so an absent key must
     * leave the stored value alone rather than null it out.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function travelFields(array $data): array
    {
        $keys = [
            'destination',
            'trip_start_date',
            'trip_end_date',
            'destination_latitude',
            'destination_longitude',
        ];

        return collect($keys)
            ->filter(fn (string $key): bool => array_key_exists($key, $data))
            ->mapWithKeys(fn (string $key): array => [$key => $data[$key]])
            ->all();
    }

    /**
     * Persist the given expense line items onto a settlement.
     *
     * @param  array<int, array{description: string, detail?: string|null, category: string, amount: mixed}>  $items
     */
    private function syncItems(Settlement $settlement, array $items): void
    {
        foreach ($items as $item) {
            $settlement->items()->create([
                'tenant_id' => $settlement->tenant_id,
                'category' => $item['category'],
                'description' => $item['description'],
                'detail' => $item['detail'] ?? null,
                'amount' => round((float) $item['amount'], 2),
            ]);
        }
    }

    /**
     * Store any uploaded supporting documents against a settlement.
     */
    private function storeAttachments(Request $request, Settlement $settlement): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $settlement->attachments()->create([
                'tenant_id' => $settlement->tenant_id,
                'path' => $file->store('settlements', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * Delete a settlement's attachment files from disk.
     */
    private function deleteAttachmentFiles(Settlement $settlement): void
    {
        foreach ($settlement->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
        }
    }

    /**
     * The employee's primary bank account, snapshotted onto the settlement.
     *
     * @return array{bank_name?: string, account_number?: string, account_holder?: string|null}
     */
    private function primaryBankAccount(Employee $employee): array
    {
        $account = DB::table('employee_bank_accounts')
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
     * Shape a settlement for the index table or the detail screen.
     *
     * @return array<string, mixed>
     */
    private function shapeSettlement(Settlement $settlement, bool $detailed = false): array
    {
        $shaped = [
            'id' => $settlement->id,
            'number' => $settlement->number,
            'employee' => $this->shapeEmployee($settlement),
            'title' => $settlement->title,
            'category' => $settlement->category,
            'category_label' => $settlement->category === null
                ? null
                : (Settlement::ITEM_CATEGORIES[$settlement->category] ?? $settlement->category),
            'department' => $settlement->department,
            'submission_date' => $settlement->submission_date?->format('d M Y'),
            'subtotal' => (float) $settlement->subtotal,
            'tax_amount' => (float) $settlement->tax_amount,
            'total' => (float) $settlement->total,
            'status' => $settlement->status,
            'status_label' => self::STATUS_LABELS[$settlement->status] ?? $settlement->status,
        ];

        if ($detailed) {
            $shaped['bank_name'] = $settlement->bank_name;
            $shaped['bank_account_number'] = $settlement->bank_account_number;
            $shaped['bank_account_holder'] = $settlement->bank_account_holder;
            $shaped['bank_swift'] = $settlement->bank_swift;
            $shaped['notes'] = $settlement->notes;
            $shaped['rejection_reason'] = $settlement->rejection_reason;
            $shaped['manager_approved_by'] = $settlement->managerApprover?->name;
            $shaped['manager_approved_at'] = $settlement->manager_approved_at?->format('d M Y H:i');
            $shaped['finance_verified_by'] = $settlement->financeVerifier?->name;
            $shaped['paid_at'] = $settlement->paid_at?->format('d M Y H:i');
            $shaped['payment_method_label'] = $settlement->payment_method === null
                ? null
                : (self::PAYMENT_METHODS[$settlement->payment_method] ?? $settlement->payment_method);
            $shaped['payment_reference'] = $settlement->payment_reference;
            $shaped['rejected_by'] = $settlement->rejecter?->name;
            $shaped['rejected_at'] = $settlement->rejected_at?->format('d M Y H:i');
            $shaped['fraud_level'] = $settlement->fraud_level;
            $shaped['fraud_checked_at'] = $settlement->fraud_checked_at?->format('d M Y H:i');
            $shaped['destination'] = $settlement->destination;
            $shaped['trip_start_date'] = $settlement->trip_start_date?->format('d M Y');
            $shaped['trip_end_date'] = $settlement->trip_end_date?->format('d M Y');
            $shaped['trip_days'] = $settlement->tripDays();
            $shaped['destination_latitude'] = $settlement->destination_latitude !== null
                ? (float) $settlement->destination_latitude
                : null;
            $shaped['destination_longitude'] = $settlement->destination_longitude !== null
                ? (float) $settlement->destination_longitude
                : null;
            $shaped['items'] = $settlement->items
                ->map(fn (SettlementItem $item): array => [
                    'id' => $item->id,
                    'category' => $item->category,
                    'category_label' => $item->categoryLabel(),
                    'description' => $item->description,
                    'detail' => $item->detail,
                    'amount' => (float) $item->amount,
                ])
                ->all();
            $shaped['attachments'] = $this->shapeAttachments($settlement);
        }

        return $shaped;
    }

    /**
     * Shape a settlement's uploaded documents.
     *
     * @return array<int, array{id: int, name: string, url: string}>
     */
    private function shapeAttachments(Settlement $settlement): array
    {
        return $settlement->attachments
            ->map(function (SettlementAttachment $attachment): array {
                $analysis = $attachment->fraud_analysis ?? [];
                $vision = $analysis['vision'] ?? [];

                return [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name ?? basename($attachment->path),
                    'url' => Storage::disk('public')->url($attachment->path),
                    'fraud_score' => $attachment->fraud_score,
                    'fraud_level' => $attachment->fraud_level,
                    'fraud_flags' => $attachment->fraud_flags ?? [],
                    'extracted_amount' => isset($vision['amount']) ? (float) $vision['amount'] : null,
                    'extracted_vendor' => $vision['vendor'] ?? null,
                    'vision_summary' => $vision['summary'] ?? null,
                    'analyzed_at' => $attachment->analyzed_at?->format('d M Y H:i'),
                ];
            })
            ->all();
    }

    /**
     * Shape the eager-loaded employee for a row, deriving initials and color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(Settlement $settlement): ?array
    {
        $employee = $settlement->employee;

        if ($employee === null) {
            return null;
        }

        return [
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'initials' => $this->initials($employee->full_name),
            'avatar_color' => $this->avatarColor($employee->full_name),
        ];
    }

    /**
     * Build up to two uppercase initials from a full name.
     */
    private function initials(?string $fullName): string
    {
        $words = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        $initials = collect($words)
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Pick a deterministic avatar color derived from the employee name.
     */
    private function avatarColor(?string $fullName): string
    {
        $index = crc32((string) $fullName) % count(self::AVATAR_PALETTE);

        return self::AVATAR_PALETTE[$index];
    }

    /**
     * Build the tenant's selectable employee options.
     *
     * @return array<int, array{id: int, name: string, employee_number: string|null}>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of expense categories.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return collect(Settlement::ITEM_CATEGORIES)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * Build the `{ value, label }` list of payment methods.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function paymentMethodOptions(): array
    {
        return collect(self::PAYMENT_METHODS)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * Build the `{ value, label }` list of status filter options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(self::STATUS_LABELS)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * Abort with 404 when the settlement belongs to another tenant.
     */
    private function ensureTenantOwnership(Request $request, Settlement $settlement): void
    {
        abort_if((int) $settlement->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 422 unless the settlement sits in one of the allowed statuses.
     *
     * @param  array<int, string>  $allowed
     */
    private function ensureStatusIs(Settlement $settlement, array $allowed, string $message): void
    {
        abort_unless(in_array($settlement->status, $allowed, true), 422, $message);
    }

    /**
     * Abort with 403 unless the user is privileged or holds a claim permission.
     */
    /**
     * Four eyes on money leaving the company: whoever approved the settlement as
     * manager cannot also be the one who verifies it and releases the payout.
     * Without this the two review desks are a UI formality — one person can walk
     * a claim they raised all the way to their own bank account.
     */
    private function ensureNotSelfApproved(Request $request, Settlement $settlement): void
    {
        if ((int) $settlement->manager_approved_by !== (int) $request->user()->id) {
            return;
        }

        abort(403, 'Anda yang menyetujui settlement ini sebagai manager. Verifikasi & pembayaran harus dilakukan orang lain.');
    }

    /**
     * Authorize an action-level permission on this module (super admin bypasses).
     * The team-approval desks use {@see ensureCanReview()} instead.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }

    /**
     * Finance/HR reach: every settlement in the tenant, all desks.
     */
    private function canManage(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasClaimPermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'claim.'));

        return $isPrivileged || $hasClaimPermission;
    }

    /**
     * A line manager holds `team.claim.approve` and reaches only the settlements
     * routed to them — their own direct reports'. They get the manager desk and
     * nothing else: no creating, no editing, no releasing money.
     */
    private function isTeamApprover(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        return $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains('team.claim.approve');
    }

    /**
     * Let Finance/HR through unconditionally; let a line manager through only
     * for a settlement sitting in their own queue.
     */
    private function ensureCanReview(Request $request, Settlement $settlement): void
    {
        if ($this->canManage($request)) {
            return;
        }

        $employeeId = $request->user()->employee?->id;

        abort_unless(
            $this->isTeamApprover($request)
                && $employeeId !== null
                && (int) $settlement->current_approver_id === (int) $employeeId,
            403,
        );
    }
}
