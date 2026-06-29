<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\Employee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * Attendance statuses that auto-generate a warning penalty.
     *
     * @var array<int, string>
     */
    private const GENERATABLE_STATUSES = ['late', 'absent', 'incomplete'];

    /**
     * Indonesian labels used to build auto-generated penalty notes.
     *
     * @var array<string, string>
     */
    private const VIOLATION_NOTES = [
        'late' => 'Terlambat',
        'absent' => 'Tidak hadir (alpa)',
        'incomplete' => 'Absensi belum lengkap',
    ];

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

        return Inertia::render('avana/sanksi', [
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
            'employees' => Employee::forTenant($tenantId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ]),
            'filters' => $request->only(['search', 'violation_type', 'per_page']),
        ]);
    }

    /**
     * Persist a manually issued attendance penalty under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Attendance::class);

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
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->format('Y-m-d');
        $endDate = Carbon::parse($validated['end_date'])->format('Y-m-d');

        $attendances = Attendance::query()
            ->forTenant($tenantId)
            ->whereIn('status', self::GENERATABLE_STATUSES)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get(['id', 'employee_id', 'date', 'status', 'late_minutes']);

        $created = 0;

        foreach ($attendances as $attendance) {
            $penalty = AttendancePenalty::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $attendance->employee_id,
                    'date' => $attendance->date->format('Y-m-d'),
                    'violation_type' => $attendance->status,
                ],
                [
                    'penalty_type' => 'warning',
                    'amount' => 0,
                    'notes' => $this->generatedNote($attendance),
                    'status' => 'active',
                ],
            );

            if ($penalty->wasRecentlyCreated) {
                $created++;
            }
        }

        return redirect()->route('avana.sanksi')
            ->with('success', "{$created} sanksi dibuat dari absensi");
    }

    /**
     * Delete a penalty after verifying it belongs to the acting user's tenant.
     */
    public function destroy(Request $request, AttendancePenalty $penalty): RedirectResponse
    {
        abort_if((int) $penalty->tenant_id !== (int) $request->user()->tenant_id, 404);

        $this->authorize('viewAny', Attendance::class);

        $penalty->delete();

        return back()->with('success', 'Sanksi absensi dihapus');
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
            'date' => $date->format('d M Y'),
            'date_raw' => $date->format('Y-m-d'),
            'violation_type' => $penalty->violation_type,
            'penalty_type' => $penalty->penalty_type,
            'amount' => (float) $penalty->amount,
            'notes' => $penalty->notes,
            'status' => $penalty->status,
        ];
    }

    /**
     * Compose the human-readable note for an auto-generated penalty.
     */
    private function generatedNote(Attendance $attendance): string
    {
        $label = self::VIOLATION_NOTES[$attendance->status] ?? $attendance->status;

        if ($attendance->status === 'late' && (int) $attendance->late_minutes > 0) {
            $label .= ' '.(int) $attendance->late_minutes.' menit';
        }

        return 'Otomatis dari absensi: '.$label;
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
