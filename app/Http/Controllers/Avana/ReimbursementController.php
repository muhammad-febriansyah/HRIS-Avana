<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reimbursements: money an employee already spent from their own pocket and is
 * claiming back. An approver clears it, then Finance pays it out.
 */
class ReimbursementController extends Controller
{
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
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'paid' => 'Dibayar',
    ];

    /**
     * How Finance can pay a reimbursement out.
     *
     * @var array<string, string>
     */
    private const PAYMENT_METHODS = [
        'transfer' => 'Transfer Bank',
        'cash' => 'Tunai',
        'payroll' => 'Ikut Payroll',
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
     * Display a server-side paginated, filterable list of reimbursements.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = $request->user()->tenant_id;

        $paginator = Reimbursement::query()
            ->forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'approver:id,name', 'paidBy:id,name'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($sub) use ($search): void {
                            $sub->where('full_name', 'like', "%{$search}%")
                                ->orWhere('employee_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $totals = Reimbursement::forTenant($tenantId)
            ->selectRaw('status, COUNT(*) as total, SUM(amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('avana/reimbursement/index', [
            'requests' => [
                'data' => $paginator->getCollection()
                    ->map(fn (Reimbursement $reimbursement): array => $this->shapeReimbursement($reimbursement, (int) $request->user()->id))
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
            'filters' => $request->only(['search', 'status', 'category', 'per_page']),
            'employees' => $this->employeeOptions($tenantId),
            'categories' => $this->categoryOptions(),
            'paymentMethods' => $this->paymentMethodOptions(),
            'kpis' => [
                'pending' => (int) ($totals['pending']->total ?? 0),
                'approved' => (int) ($totals['approved']->total ?? 0),
                'paid' => (int) ($totals['paid']->total ?? 0),
                'payable_amount' => (float) ($totals['approved']->amount ?? 0),
            ],
        ]);
    }

    /**
     * Show the form for creating a new reimbursement.
     */
    public function create(Request $request): Response
    {
        $this->ensureCan($request, 'create');

        return Inertia::render('avana/reimbursement/create', [
            'employees' => $this->employeeOptions($request->user()->tenant_id),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Show the form for editing a reimbursement that has not been paid yet.
     */
    public function edit(Request $request, Reimbursement $reimbursement): Response
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $reimbursement);
        $this->ensureEditable($reimbursement);

        return Inertia::render('avana/reimbursement/edit', [
            'reimbursement' => [
                'id' => $reimbursement->id,
                'number' => $reimbursement->number,
                'employee_id' => $reimbursement->employee_id,
                'category' => $reimbursement->category,
                'title' => $reimbursement->title,
                'amount' => (float) $reimbursement->amount,
                'expense_date' => $reimbursement->expense_date?->toDateString(),
                'description' => $reimbursement->description,
                'receipt_url' => $this->receiptUrl($reimbursement),
                'notes' => $reimbursement->notes,
            ],
            'employees' => $this->employeeOptions($request->user()->tenant_id),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Persist a new reimbursement under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;
        $data = $this->validateReimbursement($request, $tenantId);

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('reimbursements', 'public')
            : null;

        Reimbursement::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'number' => $this->nextNumber($tenantId),
            'category' => $data['category'],
            'title' => $data['title'],
            'amount' => round((float) $data['amount'], 2),
            'expense_date' => $data['expense_date'],
            'description' => $data['description'] ?? null,
            'receipt_path' => $receiptPath,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.reimbursement')
            ->with('success', 'Reimbursement berhasil diajukan');
    }

    /**
     * Update a reimbursement, replacing the receipt when a new one is sent.
     */
    public function update(Request $request, Reimbursement $reimbursement): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $reimbursement);
        $this->ensureEditable($reimbursement);

        $data = $this->validateReimbursement($request, $request->user()->tenant_id);

        $payload = [
            'employee_id' => $data['employee_id'],
            'category' => $data['category'],
            'title' => $data['title'],
            'amount' => round((float) $data['amount'], 2),
            'expense_date' => $data['expense_date'],
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($request->hasFile('receipt')) {
            if ($reimbursement->receipt_path !== null) {
                Storage::disk('public')->delete($reimbursement->receipt_path);
            }

            $payload['receipt_path'] = $request->file('receipt')->store('reimbursements', 'public');
        }

        $reimbursement->update($payload);

        return redirect()->route('avana.reimbursement')
            ->with('success', 'Reimbursement berhasil diperbarui');
    }

    /**
     * Delete a reimbursement and its uploaded receipt.
     */
    public function destroy(Request $request, Reimbursement $reimbursement): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $reimbursement);
        $this->ensureEditable($reimbursement);

        if ($reimbursement->receipt_path !== null) {
            Storage::disk('public')->delete($reimbursement->receipt_path);
        }

        $reimbursement->delete();

        return back()->with('success', 'Reimbursement dihapus');
    }

    /**
     * Approve a pending reimbursement, clearing it for payment.
     */
    public function approve(Request $request, Reimbursement $reimbursement): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $reimbursement);
        $this->ensureStatusIs($reimbursement, ['pending'], 'Hanya reimbursement berstatus menunggu yang bisa disetujui');

        $reimbursement->update([
            'status' => 'approved',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Reimbursement disetujui, menunggu pembayaran');
    }

    /**
     * Reject a pending reimbursement with a reason.
     */
    public function reject(Request $request, Reimbursement $reimbursement): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $reimbursement);
        $this->ensureStatusIs($reimbursement, ['pending'], 'Hanya reimbursement berstatus menunggu yang bisa ditolak');

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reimbursement->update([
            'status' => 'rejected',
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('success', 'Reimbursement ditolak');
    }

    /**
     * Record Finance paying an approved reimbursement out.
     */
    public function markPaid(Request $request, Reimbursement $reimbursement): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $reimbursement);
        $this->ensureStatusIs($reimbursement, ['approved'], 'Hanya reimbursement yang sudah disetujui yang bisa dibayar');
        $this->ensureNotSelfApproved($request, $reimbursement);

        $data = $request->validate([
            'payment_method' => ['required', 'in:'.implode(',', array_keys(self::PAYMENT_METHODS))],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $reimbursement->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_by' => $request->user()->id,
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
        ]);

        return back()->with('success', 'Reimbursement ditandai dibayar');
    }

    /**
     * Validate the create/update payload for a reimbursement.
     *
     * @return array<string, mixed>
     */
    private function validateReimbursement(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'category' => ['required', 'in:'.implode(',', array_keys(Reimbursement::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);
    }

    /**
     * Allocate the next per-tenant reimbursement number (RMB-YYYYMM-0001).
     *
     * Serialised on the tenant's existing rows so two concurrent submissions
     * cannot be handed the same sequence.
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

    /**
     * Shape a reimbursement row for the index table and edit screen.
     *
     * @return array<string, mixed>
     */
    private function shapeReimbursement(Reimbursement $reimbursement, int $viewerId): array
    {
        return [
            'id' => $reimbursement->id,
            'number' => $reimbursement->number,
            'employee' => $this->shapeEmployee($reimbursement),
            'employee_id' => $reimbursement->employee_id,
            'category' => $reimbursement->category,
            'category_label' => $reimbursement->categoryLabel(),
            'title' => $reimbursement->title,
            'amount' => (float) $reimbursement->amount,
            'expense_date' => $reimbursement->expense_date?->format('d M Y'),
            'description' => $reimbursement->description,
            'receipt_url' => $this->receiptUrl($reimbursement),
            'status' => $reimbursement->status,
            'status_label' => self::STATUS_LABELS[$reimbursement->status] ?? $reimbursement->status,
            'notes' => $reimbursement->notes,
            'rejection_reason' => $reimbursement->rejection_reason,
            'approver' => $reimbursement->approver?->name,
            // The approver may not also release the payout.
            'self_approved' => (int) $reimbursement->approver_id === $viewerId,
            'approved_at' => $reimbursement->approved_at?->format('d M Y H:i'),
            'paid_by' => $reimbursement->paidBy?->name,
            'paid_at' => $reimbursement->paid_at?->format('d M Y H:i'),
            'payment_method_label' => $reimbursement->payment_method === null
                ? null
                : (self::PAYMENT_METHODS[$reimbursement->payment_method] ?? $reimbursement->payment_method),
            'payment_reference' => $reimbursement->payment_reference,
        ];
    }

    /**
     * The public URL of the uploaded receipt, when there is one.
     */
    private function receiptUrl(Reimbursement $reimbursement): ?string
    {
        return $reimbursement->receipt_path === null
            ? null
            : Storage::disk('public')->url($reimbursement->receipt_path);
    }

    /**
     * Shape the eager-loaded employee for a row, deriving initials and color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(Reimbursement $reimbursement): ?array
    {
        $employee = $reimbursement->employee;

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
     * Abort with 404 when the reimbursement belongs to another tenant.
     */
    private function ensureTenantOwnership(Request $request, Reimbursement $reimbursement): void
    {
        abort_if((int) $reimbursement->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * A reimbursement already paid out is a financial record, not a draft.
     */
    private function ensureEditable(Reimbursement $reimbursement): void
    {
        $this->ensureStatusIs(
            $reimbursement,
            ['pending', 'approved', 'rejected'],
            'Reimbursement yang sudah dibayar tidak bisa diubah',
        );
    }

    /**
     * Abort with 422 unless the reimbursement sits in one of the allowed statuses.
     *
     * @param  array<int, string>  $allowed
     */
    private function ensureStatusIs(Reimbursement $reimbursement, array $allowed, string $message): void
    {
        abort_unless(in_array($reimbursement->status, $allowed, true), 422, $message);
    }

    /**
     * Abort with 403 unless the user is privileged or holds a claim permission.
     */
    /**
     * Four eyes on money leaving the company: whoever approved a reimbursement
     * cannot also be the one who pays it out. Mirrors the settlement rule.
     */
    private function ensureNotSelfApproved(Request $request, Reimbursement $reimbursement): void
    {
        if ((int) $reimbursement->approver_id !== (int) $request->user()->id) {
            return;
        }

        abort(403, 'Anda yang menyetujui reimbursement ini. Pembayaran harus dilakukan orang lain.');
    }

    /**
     * Authorize an action-level permission on this module (super admin bypasses).
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
}
