<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\Reimbursement;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settlements account for a disbursed cash advance: the employee lists what
 * they spent and attaches the receipts, an approver checks it, then whichever
 * side is short settles up.
 *
 * The advance amount is snapshotted onto the settlement when it is opened, so
 * the balance cannot drift if the advance is touched afterwards.
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
     * Indonesian labels for the status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'draft' => 'Draft',
        'submitted' => 'Menunggu Verifikasi',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'closed' => 'Selesai',
    ];

    /**
     * Indonesian labels for the derived outcome.
     *
     * @var array<string, string>
     */
    private const OUTCOME_LABELS = [
        Settlement::OUTCOME_RETURN => 'Pengembalian Sisa Dana',
        Settlement::OUTCOME_TOPUP => 'Pembayaran Kekurangan',
        Settlement::OUTCOME_BALANCED => 'Pas (Tanpa Selisih)',
    ];

    /**
     * How money can change hands when closing a settlement.
     *
     * @var array<string, string>
     */
    private const PAYMENT_METHODS = [
        'transfer' => 'Transfer Bank',
        'cash' => 'Tunai',
    ];

    /**
     * Deterministic avatar background palette (mirrors CashAdvanceController).
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
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $paginator = Settlement::query()
            ->forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'cashAdvance:id,purpose,amount'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('number', 'like', "%{$search}%")
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

        $all = Settlement::forTenant($tenantId)->get(['status', 'advance_amount', 'total_spent']);

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
            'kpis' => [
                'draft' => $all->where('status', 'draft')->count(),
                'submitted' => $all->where('status', 'submitted')->count(),
                'closed' => $all->where('status', 'closed')->count(),
                'unsettled_advances' => $this->settleableAdvances($tenantId)->count(),
            ],
        ]);
    }

    /**
     * Show the form for opening a settlement against a disbursed advance.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('avana/settlement/create', [
            'advances' => $this->settleableAdvanceOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Show one settlement with its receipt lines and the actions open to it.
     */
    public function show(Request $request, Settlement $settlement): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);

        $settlement->load(['employee:id,full_name,employee_number', 'cashAdvance', 'items', 'approver:id,name', 'returnedReceivedBy:id,name', 'topupPaidBy:id,name']);

        return Inertia::render('avana/settlement/show', [
            'settlement' => $this->shapeSettlement($settlement, withItems: true),
            'categories' => $this->categoryOptions(),
            'paymentMethods' => $this->paymentMethodOptions(),
        ]);
    }

    /**
     * Open a draft settlement against a disbursed cash advance.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'cash_advance_id' => [
                'required',
                'integer',
                "exists:cash_advances,id,tenant_id,{$tenantId}",
                // One settlement per advance; the DB unique index is the backstop.
                'unique:settlements,cash_advance_id',
            ],
            'settlement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $advance = CashAdvance::forTenant($tenantId)->findOrFail($data['cash_advance_id']);

        abort_unless(
            $advance->isSettleable(),
            422,
            'Hanya uang muka yang sudah dicairkan yang bisa dipertanggungjawabkan',
        );

        $settlement = Settlement::create([
            'tenant_id' => $tenantId,
            'cash_advance_id' => $advance->id,
            'employee_id' => $advance->employee_id,
            'number' => $this->nextNumber($tenantId),
            'settlement_date' => $data['settlement_date'],
            // Snapshot: the balance must not move if the advance is edited later.
            'advance_amount' => (float) $advance->amount,
            'total_spent' => 0,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('avana.settlement.show', $settlement)
            ->with('success', 'Settlement dibuka, unggah bukti pengeluaran');
    }

    /**
     * Delete a settlement that has not been verified yet, along with its receipts.
     */
    public function destroy(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['draft', 'rejected'], 'Settlement yang sudah diverifikasi tidak bisa dihapus');

        foreach ($settlement->items as $item) {
            if ($item->receipt_path !== null) {
                Storage::disk('public')->delete($item->receipt_path);
            }
        }

        $settlement->delete();

        return redirect()->route('avana.settlement')
            ->with('success', 'Settlement dihapus');
    }

    /**
     * Attach one receipt line to an open settlement.
     */
    public function storeItem(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['draft', 'rejected'], 'Bukti hanya bisa diubah selama settlement masih terbuka');

        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(Reimbursement::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'spent_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $settlement->items()->create([
            'tenant_id' => $settlement->tenant_id,
            'category' => $data['category'],
            'description' => $data['description'],
            'spent_date' => $data['spent_date'],
            'amount' => round((float) $data['amount'], 2),
            'receipt_path' => $request->hasFile('receipt')
                ? $request->file('receipt')->store('settlements', 'public')
                : null,
        ]);

        $settlement->recalculateTotalSpent();

        return back()->with('success', 'Bukti pengeluaran ditambahkan');
    }

    /**
     * Remove one receipt line from an open settlement.
     */
    public function destroyItem(Request $request, Settlement $settlement, SettlementItem $item): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['draft', 'rejected'], 'Bukti hanya bisa diubah selama settlement masih terbuka');
        abort_if((int) $item->settlement_id !== (int) $settlement->id, 404);

        if ($item->receipt_path !== null) {
            Storage::disk('public')->delete($item->receipt_path);
        }

        $item->delete();
        $settlement->recalculateTotalSpent();

        return back()->with('success', 'Bukti pengeluaran dihapus');
    }

    /**
     * Hand the settlement to an approver for verification.
     */
    public function submit(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['draft', 'rejected'], 'Settlement ini sudah diajukan');

        // Recalculated rather than trusted: an empty settlement means nothing
        // was accounted for, which is never a valid submission.
        $settlement->recalculateTotalSpent();

        abort_if(
            $settlement->items()->count() === 0,
            422,
            'Tambahkan minimal satu bukti pengeluaran sebelum mengajukan',
        );

        $settlement->update([
            'status' => 'submitted',
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Settlement diajukan untuk verifikasi');
    }

    /**
     * Verify a submitted settlement. A settlement that comes out exactly even
     * has nothing left to move, so it closes on the spot.
     */
    public function approve(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['submitted'], 'Hanya settlement yang diajukan yang bisa diverifikasi');

        $settlement->recalculateTotalSpent();

        $settlement->update([
            'status' => 'approved',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        if ($settlement->outcome() === Settlement::OUTCOME_BALANCED) {
            $this->close($settlement);

            return back()->with('success', 'Settlement disetujui dan selesai — pengeluaran pas dengan uang muka');
        }

        return back()->with('success', 'Settlement disetujui, menunggu penyelesaian selisih');
    }

    /**
     * Send a submitted settlement back to the employee to fix.
     */
    public function reject(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['submitted'], 'Hanya settlement yang diajukan yang bisa ditolak');

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $settlement->update([
            'status' => 'rejected',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('success', 'Settlement dikembalikan untuk diperbaiki');
    }

    /**
     * Record the employee handing the leftover float back.
     */
    public function recordReturn(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['approved'], 'Selisih hanya bisa diselesaikan setelah settlement disetujui');

        abort_unless(
            $settlement->outcome() === Settlement::OUTCOME_RETURN,
            422,
            'Settlement ini tidak menyisakan dana untuk dikembalikan',
        );

        $data = $request->validate([
            'returned_amount' => ['required', 'numeric', 'min:1', 'max:'.$settlement->outstanding()],
        ]);

        $settlement->update([
            'returned_amount' => round((float) $settlement->returned_amount + (float) $data['returned_amount'], 2),
            'returned_at' => now(),
            'returned_received_by' => $request->user()->id,
        ]);

        return $this->closeWhenSquared($settlement, 'Pengembalian sisa dana dicatat');
    }

    /**
     * Record the company paying the employee back what they overspent.
     */
    public function recordTopup(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $settlement);
        $this->ensureStatusIs($settlement, ['approved'], 'Selisih hanya bisa diselesaikan setelah settlement disetujui');

        abort_unless(
            $settlement->outcome() === Settlement::OUTCOME_TOPUP,
            422,
            'Settlement ini tidak memiliki kekurangan untuk dibayarkan',
        );

        $data = $request->validate([
            'topup_amount' => ['required', 'numeric', 'min:1', 'max:'.$settlement->outstanding()],
            'topup_method' => ['required', 'in:'.implode(',', array_keys(self::PAYMENT_METHODS))],
            'topup_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $settlement->update([
            'topup_amount' => round((float) $settlement->topup_amount + (float) $data['topup_amount'], 2),
            'topup_paid_at' => now(),
            'topup_paid_by' => $request->user()->id,
            'topup_method' => $data['topup_method'],
            'topup_reference' => $data['topup_reference'] ?? null,
        ]);

        return $this->closeWhenSquared($settlement, 'Pembayaran kekurangan dicatat');
    }

    /**
     * Close the settlement once nothing is outstanding either way.
     */
    private function closeWhenSquared(Settlement $settlement, string $message): RedirectResponse
    {
        if ($settlement->outstanding() > 0) {
            return back()->with('success', $message.' — masih ada sisa yang belum diselesaikan');
        }

        $this->close($settlement);

        return back()->with('success', $message.', settlement selesai');
    }

    /**
     * Mark the settlement and the advance it accounts for as done. Both flips
     * are one transaction so an advance can never read settled while its
     * settlement is still open.
     */
    private function close(Settlement $settlement): void
    {
        DB::transaction(function () use ($settlement): void {
            $settlement->update(['status' => 'closed']);

            $settlement->cashAdvance?->update([
                'status' => 'settled',
                'settled_at' => now(),
            ]);
        });
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
     * Disbursed advances that still have no settlement against them.
     *
     * @return Collection<int, CashAdvance>
     */
    private function settleableAdvances(int $tenantId)
    {
        return CashAdvance::forTenant($tenantId)
            ->where('status', 'disbursed')
            ->whereDoesntHave('settlement')
            ->with('employee:id,full_name,employee_number')
            ->orderBy('disbursed_at')
            ->get();
    }

    /**
     * Build the selectable advance options for the create screen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function settleableAdvanceOptions(int $tenantId): array
    {
        return $this->settleableAdvances($tenantId)
            ->map(fn (CashAdvance $advance): array => [
                'id' => $advance->id,
                'employee_name' => $advance->employee?->full_name,
                'employee_number' => $advance->employee?->employee_number,
                'amount' => (float) $advance->amount,
                'purpose' => $advance->purpose,
                'disbursed_at' => $advance->disbursed_at?->format('d M Y'),
            ])
            ->all();
    }

    /**
     * Shape a settlement for the index table or the detail screen.
     *
     * @return array<string, mixed>
     */
    private function shapeSettlement(Settlement $settlement, bool $withItems = false): array
    {
        $outcome = $settlement->outcome();

        $shaped = [
            'id' => $settlement->id,
            'number' => $settlement->number,
            'employee' => $this->shapeEmployee($settlement),
            'cash_advance_id' => $settlement->cash_advance_id,
            'purpose' => $settlement->cashAdvance?->purpose,
            'settlement_date' => $settlement->settlement_date?->format('d M Y'),
            'advance_amount' => (float) $settlement->advance_amount,
            'total_spent' => (float) $settlement->total_spent,
            'balance' => $settlement->balance(),
            'outcome' => $outcome,
            'outcome_label' => self::OUTCOME_LABELS[$outcome],
            'outstanding' => $settlement->outstanding(),
            'status' => $settlement->status,
            'status_label' => self::STATUS_LABELS[$settlement->status] ?? $settlement->status,
            'returned_amount' => (float) $settlement->returned_amount,
            'returned_at' => $settlement->returned_at?->format('d M Y H:i'),
            'topup_amount' => (float) $settlement->topup_amount,
            'topup_paid_at' => $settlement->topup_paid_at?->format('d M Y H:i'),
            'rejection_reason' => $settlement->rejection_reason,
            'notes' => $settlement->notes,
        ];

        if ($withItems) {
            $shaped['approver'] = $settlement->approver?->name;
            $shaped['approved_at'] = $settlement->approved_at?->format('d M Y H:i');
            $shaped['returned_received_by'] = $settlement->returnedReceivedBy?->name;
            $shaped['topup_paid_by'] = $settlement->topupPaidBy?->name;
            $shaped['topup_reference'] = $settlement->topup_reference;
            $shaped['items'] = $settlement->items
                ->map(fn (SettlementItem $item): array => [
                    'id' => $item->id,
                    'category' => $item->category,
                    'category_label' => $item->categoryLabel(),
                    'description' => $item->description,
                    'spent_date' => $item->spent_date?->format('d M Y'),
                    'amount' => (float) $item->amount,
                    'receipt_url' => $item->receipt_path === null
                        ? null
                        : Storage::disk('public')->url($item->receipt_path),
                ])
                ->all();
        }

        return $shaped;
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
     * Build the `{ value, label }` list of expense categories.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return collect(Reimbursement::CATEGORIES)
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
    private function ensureCanManage(Request $request): void
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

        abort_unless($isPrivileged || $hasClaimPermission, 403);
    }
}
