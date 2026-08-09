<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\AttendancePenaltyRule;
use App\Models\Employee;
use App\Support\AttendanceFines;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendancePenaltyController extends Controller
{
    use AuthorizesRequests;

    /**
     * Violation types a penalty may reference.
     *
     * @var array<int, string>
     */
    private const VIOLATION_TYPES = ['late', 'absent', 'incomplete', 'early_leave'];

    /**
     * Deterministic avatar background palette (mirrors AttendanceResource).
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display a server-side paginated, filterable list of attendance penalties.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $penalties = AttendancePenalty::query()
            ->forTenant($tenantId)
            ->with('employee:id,full_name,employee_number')
            ->when($request->query('search'), function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('violation_type'), fn ($q, $type) => $q->where('violation_type', $type))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/sanksi/index', [
            'penalties' => [
                'data' => collect($penalties->items())
                    ->map(fn (AttendancePenalty $penalty): array => $this->transformPenalty($penalty))
                    ->all(),
                'meta' => [
                    'current_page' => $penalties->currentPage(),
                    'last_page' => $penalties->lastPage(),
                    'per_page' => $penalties->perPage(),
                    'total' => $penalties->total(),
                    'from' => $penalties->firstItem(),
                    'to' => $penalties->lastItem(),
                ],
            ],
            'employees' => $this->employeeOptions($tenantId),
            'rules' => AttendancePenaltyRule::tiersFor($tenantId)
                ->map(fn (AttendancePenaltyRule $rule): array => [
                    'id' => $rule->id,
                    'min_minutes' => $rule->min_minutes,
                    'max_minutes' => $rule->max_minutes,
                    'penalty_type' => $rule->penalty_type,
                    'amount' => (float) $rule->amount,
                    'is_active' => $rule->is_active,
                ])
                ->all(),
            'filters' => $request->only(['search', 'violation_type', 'per_page']),
        ]);
    }

    /**
     * Add or update one tier of the tenant's late-penalty table.
     */
    public function storeRule(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $tenantId = (int) $request->user()->tenant_id;

        $validated = $request->validate([
            'id' => ['nullable', 'integer', Rule::exists('attendance_penalty_rules', 'id')->where('tenant_id', $tenantId)],
            'min_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            // Null is the last, open-ended tier ("lebih dari 60 menit").
            'max_minutes' => ['nullable', 'integer', 'min:1', 'max:1440', 'gt:min_minutes'],
            'penalty_type' => ['required', Rule::in(['warning', 'deduction'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_if:penalty_type,deduction'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'max_minutes.gt' => 'Menit akhir harus lebih besar dari menit awal.',
        ]);

        $attributes = [
            'tenant_id' => $tenantId,
            'violation_type' => 'late',
            'min_minutes' => $validated['min_minutes'],
            'max_minutes' => $validated['max_minutes'] ?? null,
            'penalty_type' => $validated['penalty_type'],
            'amount' => $validated['penalty_type'] === 'deduction' ? ($validated['amount'] ?? 0) : 0,
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (($validated['id'] ?? null) !== null) {
            AttendancePenaltyRule::forTenant($tenantId)->findOrFail($validated['id'])->update($attributes);
            AttendanceFines::refreshAutomaticForTenant($tenantId);

            return back()->with('success', 'Aturan denda diperbarui');
        }

        AttendancePenaltyRule::create($attributes);
        AttendanceFines::refreshAutomaticForTenant($tenantId);

        return back()->with('success', 'Aturan denda ditambahkan');
    }

    /**
     * Remove one tier from the tenant's late-penalty table.
     */
    public function destroyRule(Request $request, AttendancePenaltyRule $rule): RedirectResponse
    {
        abort_if((int) $rule->tenant_id !== (int) $request->user()->tenant_id, 404);

        $this->authorize('delete', Attendance::class);

        $rule->delete();
        AttendanceFines::refreshAutomaticForTenant((int) $rule->tenant_id);

        return back()->with('success', 'Aturan denda dihapus');
    }

    /**
     * Show the form for issuing a new manual attendance penalty.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Attendance::class);

        return Inertia::render('avana/sanksi/create', [
            'employees' => $this->employeeOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Persist a manually issued attendance penalty under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'violation_type' => ['required', Rule::in(self::VIOLATION_TYPES)],
            'penalty_type' => ['required', Rule::in(['warning', 'deduction'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_if:penalty_type,deduction'],
            'notes' => ['nullable', 'string'],
        ]);

        AttendancePenalty::create([
            'tenant_id' => $tenantId,
            'employee_id' => $validated['employee_id'],
            'date' => Carbon::parse($validated['date'])->format('Y-m-d'),
            'violation_type' => $validated['violation_type'],
            'source' => 'manual',
            'penalty_type' => $validated['penalty_type'],
            'amount' => $validated['amount'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'status' => 'active',
        ]);

        return redirect()->route('avana.sanksi')
            ->with('success', 'Sanksi absensi dibuat');
    }

    /**
     * Auto-generate warning penalties from late/absent/incomplete attendance
     * rows within the requested date range, skipping any duplicates.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->format('Y-m-d');
        $endDate = Carbon::parse($validated['end_date'])->format('Y-m-d');

        // Shared with the payroll run, which applies the same tiers to every
        // employee's attendance window automatically.
        $result = AttendanceFines::generate($tenantId, $startDate, $endDate);

        $message = "{$result['created']} sanksi dibuat dari absensi";

        if ($result['fined'] > 0) {
            $message .= ", {$result['fined']} di antaranya kena denda sesuai aturan tenant";
        }

        return redirect()->route('avana.sanksi')->with('success', $message);
    }

    /**
     * Delete a penalty after verifying it belongs to the acting user's tenant.
     */
    public function destroy(Request $request, AttendancePenalty $penalty): RedirectResponse
    {
        abort_if((int) $penalty->tenant_id !== (int) $request->user()->tenant_id, 404);

        $this->authorize('delete', Attendance::class);

        $penalty->delete();

        return back()->with('success', 'Sanksi absensi dihapus');
    }

    /**
     * Build the selectable employee options backing the penalty forms.
     *
     * @return Collection<int, array{id: int, name: string, employee_number: string}>
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
     * Build the row shape consumed by the Sanksi Absensi DataTable.
     *
     * @return array<string, mixed>
     */
    private function transformPenalty(AttendancePenalty $penalty): array
    {
        $employee = $penalty->employee;
        $date = Carbon::parse($penalty->date);

        return [
            'id' => $penalty->id,
            'employee' => $employee === null ? null : [
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'initials' => $this->initials($employee->full_name),
                'avatar_color' => $this->avatarColor($employee->full_name),
            ],
            'date' => $date->translatedFormat('d M Y'),
            'date_raw' => $date->format('Y-m-d'),
            'violation_type' => $penalty->violation_type,
            'penalty_type' => $penalty->penalty_type,
            'amount' => (float) $penalty->amount,
            'notes' => $penalty->notes,
            'status' => $penalty->status,
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
}
