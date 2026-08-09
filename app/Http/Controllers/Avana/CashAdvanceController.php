<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\Settlement;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational cash advances: an employee asks for a float up front, a manager
 * approves it, Finance disburses it, and the employee later accounts for what
 * they actually spent — returning what is left over, or being paid the
 * shortfall. Nothing here touches payroll: salary-deducted lending lives in
 * {@see LoanController}, and a standalone expense claim is a {@see Settlement}.
 */
class CashAdvanceController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     * Cash advances live under the payroll module, mirroring the prior guard.
     */
    private const MODULE = 'payroll';

    /**
     * Indonesian labels for the status enum.
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
     * How Finance can hand the money over.
     *
     * @var array<string, string>
     */
    private const DISBURSEMENT_METHODS = [
        'transfer' => 'Transfer Bank',
        'cash' => 'Tunai',
    ];

    /**
     * Deterministic avatar background palette (mirrors LeaveController).
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display a server-side paginated, filterable list of cash advances.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = $request->user()->tenant_id;

        $paginator = CashAdvance::query()
            ->forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'settledBy:id,name'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $totals = CashAdvance::forTenant($tenantId)
            ->selectRaw('status, COUNT(*) as total, SUM(amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('avana/kasbon/index', [
            'requests' => [
                'data' => $paginator->getCollection()
                    ->map(fn (CashAdvance $advance): array => $this->shapeAdvance($advance))
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
            'employees' => $this->employeeOptions($tenantId),
            'disbursementMethods' => $this->disbursementMethodOptions(),
            // Whoever released the money may not sign off on how it was spent.
            'authUserId' => (int) $request->user()->id,
            'kpis' => [
                'pending' => (int) ($totals['pending']->total ?? 0),
                'approved' => (int) ($totals['approved']->total ?? 0),
                'disbursed' => (int) ($totals['disbursed']->total ?? 0),
                'outstanding_amount' => (float) ($totals['disbursed']->amount ?? 0),
                'settled' => (int) ($totals['settled']->total ?? 0),
            ],
        ]);
    }

    /**
     * Show one advance with its approval trail and, once accounted for, what
     * the money actually went on.
     */
    public function show(Request $request, CashAdvance $cashAdvance): Response
    {
        $this->ensureCan($request, 'view');
        $this->ensureTenantOwnership($request, $cashAdvance);

        $cashAdvance->load([
            'employee:id,full_name,employee_number',
            'approvedBy:id,name',
            'disbursedBy:id,name',
            'settledBy:id,name',
        ]);

        return Inertia::render('avana/kasbon/show', [
            'advance' => $this->shapeAdvance($cashAdvance) + [
                'approved_at' => $cashAdvance->approved_at?->translatedFormat('d M Y H:i'),
                'approved_by_name' => $cashAdvance->approvedBy?->name,
                'disbursed_by_name' => $cashAdvance->disbursedBy?->name,
                'settled_at_full' => $cashAdvance->settled_at?->translatedFormat('d M Y H:i'),
            ],
            'disbursementMethods' => $this->disbursementMethodOptions(),
            // Whoever released the money may not sign off on how it was spent.
            'authUserId' => (int) $request->user()->id,
        ]);
    }

    /**
     * Show the form for submitting a new cash advance request.
     */
    public function create(Request $request): Response
    {
        $this->ensureCan($request, 'create');

        return Inertia::render('avana/kasbon/create', [
            'employees' => $this->employeeOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Show the form for editing a cash advance that has not been paid out yet.
     */
    public function edit(Request $request, CashAdvance $cashAdvance): Response
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $cashAdvance);
        $this->ensureEditable($cashAdvance);

        return Inertia::render('avana/kasbon/edit', [
            'advance' => [
                'id' => $cashAdvance->id,
                'route_key' => $cashAdvance->public_id,
                'employee_id' => $cashAdvance->employee_id,
                'amount' => (float) $cashAdvance->amount,
                'purpose' => $cashAdvance->purpose,
                'request_date' => $cashAdvance->request_date?->toDateString(),
                'needed_date' => $cashAdvance->needed_date?->toDateString(),
                'reason' => $cashAdvance->reason,
            ],
            'employees' => $this->employeeOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Persist a new cash advance on behalf of an employee under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;
        $data = $this->validateAdvance($request, $tenantId);

        CashAdvance::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'amount' => round((float) $data['amount'], 2),
            'purpose' => $data['purpose'],
            'request_date' => $data['request_date'],
            'needed_date' => $data['needed_date'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.kasbon')
            ->with('success', 'Pengajuan uang muka dibuat');
    }

    /**
     * Update a cash advance that has not been paid out yet.
     */
    public function update(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $cashAdvance);
        $this->ensureEditable($cashAdvance);

        $data = $this->validateAdvance($request, $request->user()->tenant_id);

        $cashAdvance->update([
            'employee_id' => $data['employee_id'],
            'amount' => round((float) $data['amount'], 2),
            'purpose' => $data['purpose'],
            'request_date' => $data['request_date'],
            'needed_date' => $data['needed_date'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect()->route('avana.kasbon')
            ->with('success', 'Pengajuan uang muka diperbarui');
    }

    /**
     * Delete a cash advance that has not been paid out yet.
     */
    public function destroy(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $cashAdvance);
        $this->ensureEditable($cashAdvance);

        $cashAdvance->delete();

        return back()->with('success', 'Pengajuan uang muka dihapus');
    }

    /**
     * Approve a pending cash advance, clearing it for disbursement.
     */
    public function approve(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $cashAdvance);
        $this->ensureStatusIs($cashAdvance, ['pending'], 'Hanya pengajuan berstatus menunggu yang bisa disetujui');

        $cashAdvance->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Uang muka disetujui, menunggu pencairan Finance');
    }

    /**
     * Reject a pending cash advance.
     */
    public function reject(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
        $this->ensureTenantOwnership($request, $cashAdvance);
        $this->ensureStatusIs($cashAdvance, ['pending'], 'Hanya pengajuan berstatus menunggu yang bisa ditolak');

        $cashAdvance->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Uang muka ditolak');
    }

    /**
     * Record Finance handing the money over to the employee.
     */
    public function disburse(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $cashAdvance);
        $this->ensureStatusIs($cashAdvance, ['approved'], 'Hanya uang muka yang sudah disetujui yang bisa dicairkan');

        // Whoever approved the advance may not also release the money — the same
        // four-eyes rule the reimbursement, settlement and kasbon-settlement
        // payouts carry.
        abort_if(
            (int) $cashAdvance->approved_by === (int) $request->user()->id,
            403,
            'Anda yang menyetujui uang muka ini. Pencairan harus dilakukan orang lain.',
        );

        $data = $request->validate([
            'disbursement_method' => ['required', 'in:'.implode(',', array_keys(self::DISBURSEMENT_METHODS))],
            'disbursement_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $cashAdvance->update([
            'status' => 'disbursed',
            'disbursed_at' => now(),
            'disbursed_by' => $request->user()->id,
            'disbursement_method' => $data['disbursement_method'],
            'disbursement_reference' => $data['disbursement_reference'] ?? null,
        ]);

        return back()->with('success', 'Uang muka dicairkan, menunggu pertanggungjawaban');
    }

    /**
     * Account for a disbursed advance: what was actually spent, the receipt
     * proving it, and which way the difference goes.
     *
     * Whoever released the money may not also sign off on how it was spent —
     * the same four-eyes rule the settlement and reimbursement payouts carry.
     */
    public function settle(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $cashAdvance);
        $this->ensureStatusIs($cashAdvance, ['disbursed'], 'Hanya uang muka yang sudah dicairkan yang bisa dipertanggungjawabkan');

        abort_if(
            (int) $cashAdvance->disbursed_by === (int) $request->user()->id,
            403,
            'Anda yang mencairkan uang muka ini. Pertanggungjawaban harus diperiksa orang lain.',
        );

        $data = $request->validate([
            'spent_amount' => ['required', 'numeric', 'min:0'],
            'settlement_note' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $split = CashAdvance::reconcile(
            (float) $cashAdvance->amount,
            (float) $data['spent_amount'],
        );

        $cashAdvance->update([
            'status' => 'settled',
            'settled_at' => now(),
            'settled_by' => $request->user()->id,
            'spent_amount' => round((float) $data['spent_amount'], 2),
            'returned_amount' => $split['returned'],
            'topup_amount' => $split['topup'],
            'settlement_note' => $data['settlement_note'] ?? null,
            'settlement_receipt_path' => $request->hasFile('receipt')
                ? PrivateFile::store($request->file('receipt'), 'cash-advances')
                : $cashAdvance->settlement_receipt_path,
        ]);

        return back()->with('success', $this->settlementMessage($split));
    }

    /**
     * Tell the user which way the money still has to move, if at all.
     *
     * @param  array{returned: float, topup: float}  $split
     */
    private function settlementMessage(array $split): string
    {
        if ($split['returned'] > 0) {
            return 'Dipertanggungjawabkan. Sisa Rp '
                .number_format($split['returned'], 0, ',', '.')
                .' harus dikembalikan karyawan.';
        }

        if ($split['topup'] > 0) {
            return 'Dipertanggungjawabkan. Kekurangan Rp '
                .number_format($split['topup'], 0, ',', '.')
                .' harus dibayarkan ke karyawan.';
        }

        return 'Dipertanggungjawabkan. Uang muka terpakai pas, tidak ada sisa.';
    }

    /**
     * Validate the create/update payload for a cash advance.
     *
     * @return array<string, mixed>
     */
    private function validateAdvance(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'purpose' => ['required', 'string', 'max:255'],
            'request_date' => ['required', 'date'],
            'needed_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'reason' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the selectable employee option list for the acting tenant.
     *
     * @return Collection<int, array{id: int, name: string, employee_number: string|null}>
     */
    private function employeeOptions(int $tenantId): Collection
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]);
    }

    /**
     * Build the `{ value, label }` list of disbursement methods.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function disbursementMethodOptions(): array
    {
        return collect(self::DISBURSEMENT_METHODS)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * Shape a cash advance row for the index DataTable.
     *
     * @return array<string, mixed>
     */
    private function shapeAdvance(CashAdvance $advance): array
    {
        return [
            'id' => $advance->id,
            'route_key' => $advance->public_id,
            'employee' => $this->shapeEmployee($advance),
            'amount' => (float) $advance->amount,
            'purpose' => $advance->purpose,
            'request_date' => $advance->request_date?->translatedFormat('d M Y'),
            'needed_date' => $advance->needed_date?->translatedFormat('d M Y'),
            'reason' => $advance->reason,
            'status' => $advance->status,
            'status_label' => $this->statusLabel($advance->status),
            'disbursed_at' => $advance->disbursed_at?->translatedFormat('d M Y'),
            'disbursement_method' => $advance->disbursement_method,
            'disbursement_method_label' => $advance->disbursement_method === null
                ? null
                : (self::DISBURSEMENT_METHODS[$advance->disbursement_method] ?? $advance->disbursement_method),
            'disbursement_reference' => $advance->disbursement_reference,
            'approved_by' => $advance->approved_by,
            'disbursed_by' => $advance->disbursed_by,
            'settled_at' => $advance->settled_at?->translatedFormat('d M Y'),
            'settled_by_name' => $advance->settledBy?->name,
            'spent_amount' => $advance->spent_amount === null ? null : (float) $advance->spent_amount,
            'returned_amount' => (float) $advance->returned_amount,
            'topup_amount' => (float) $advance->topup_amount,
            'settlement_note' => $advance->settlement_note,
            'settlement_receipt_url' => $advance->settlement_receipt_path === null
                ? null
                : PrivateFile::urlFor($advance->settlement_receipt_path),
        ];
    }

    /**
     * Shape the eager-loaded employee for a row, deriving initials and color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(Model $request): ?array
    {
        $employee = $request->employee;

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
     * Map a status enum value to its Indonesian label.
     */
    private function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Abort with 404 when the cash advance does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, CashAdvance $cashAdvance): void
    {
        abort_if((int) $cashAdvance->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Money already handed over must not be edited out from under the
     * settlement that accounts for it.
     */
    private function ensureEditable(CashAdvance $cashAdvance): void
    {
        $this->ensureStatusIs(
            $cashAdvance,
            ['pending', 'approved'],
            'Uang muka yang sudah dicairkan tidak bisa diubah',
        );
    }

    /**
     * Abort with 422 unless the advance sits in one of the allowed statuses.
     *
     * @param  array<int, string>  $allowed
     */
    private function ensureStatusIs(CashAdvance $cashAdvance, array $allowed, string $message): void
    {
        abort_unless(in_array($cashAdvance->status, $allowed, true), 422, $message);
    }

    /**
     * Abort with 403 unless the user is privileged or holds a payroll permission.
     */
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
