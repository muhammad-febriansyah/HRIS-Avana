<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DutyTravelController extends Controller
{
    /**
     * Roles that always pass duty travel authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Indonesian labels for the status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'completed' => 'Selesai',
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
     * Display a server-side paginated, filterable list of duty travel requests.
     */
    public function index(Request $request): Response
    {
        $this->authorizeEmployeeAccess($request);

        $tenantId = $request->user()->tenant_id;

        $travels = DutyTravel::query()
            ->forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('destination', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employee) use ($search): void {
                            $employee->where('full_name', 'like', "%{$search}%")
                                ->orWhere('employee_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $travels->getCollection()->transform(fn (DutyTravel $travel): array => $this->shapeTravel($travel));

        return Inertia::render('avana/dinas/index', [
            'travels' => [
                'data' => $travels->items(),
                'meta' => [
                    'current_page' => $travels->currentPage(),
                    'last_page' => $travels->lastPage(),
                    'per_page' => $travels->perPage(),
                    'total' => $travels->total(),
                    'from' => $travels->firstItem(),
                    'to' => $travels->lastItem(),
                ],
                'links' => [
                    'first' => $travels->url(1),
                    'last' => $travels->url($travels->lastPage()),
                    'prev' => $travels->previousPageUrl(),
                    'next' => $travels->nextPageUrl(),
                ],
            ],
            'filters' => $request->only(['search', 'status', 'per_page']),
            'employees' => Employee::forTenant($tenantId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ]),
        ]);
    }

    /**
     * Show the form for creating a new duty travel request.
     */
    public function create(Request $request): Response
    {
        $this->authorizeEmployeeAccess($request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/dinas/create', [
            'employees' => Employee::forTenant($tenantId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ]),
        ]);
    }

    /**
     * Persist a new duty travel request on behalf of an employee under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEmployeeAccess($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'transport' => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'per_diem' => ['nullable', 'numeric', 'min:0'],
        ]);

        DutyTravel::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'destination' => $data['destination'],
            'purpose' => $data['purpose'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'transport' => $data['transport'] ?? null,
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'per_diem' => $data['per_diem'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.dinas')
            ->with('success', 'Pengajuan perjalanan dinas dibuat');
    }

    /**
     * Approve a pending duty travel request.
     */
    public function approve(Request $request, DutyTravel $dutyTravel): RedirectResponse
    {
        $this->authorizeEmployeeAccess($request);
        $this->ensureTenantOwnership($request, $dutyTravel);

        $dutyTravel->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Perjalanan dinas disetujui');
    }

    /**
     * Reject a pending duty travel request.
     */
    public function reject(Request $request, DutyTravel $dutyTravel): RedirectResponse
    {
        $this->authorizeEmployeeAccess($request);
        $this->ensureTenantOwnership($request, $dutyTravel);

        $dutyTravel->update(['status' => 'rejected']);

        return back()->with('success', 'Perjalanan dinas ditolak');
    }

    /**
     * Shape a duty travel record for the index table.
     *
     * @return array<string, mixed>
     */
    private function shapeTravel(DutyTravel $travel): array
    {
        $start = $travel->start_date ? Carbon::parse($travel->start_date) : null;
        $end = $travel->end_date ? Carbon::parse($travel->end_date) : null;

        $days = $start !== null && $end !== null
            ? (int) $start->diffInDays($end) + 1
            : null;

        return [
            'id' => $travel->id,
            'employee' => $this->shapeEmployee($travel),
            'destination' => $travel->destination,
            'purpose' => $travel->purpose,
            'start_date' => $start?->format('d M Y'),
            'end_date' => $end?->format('d M Y'),
            'days' => $days,
            'transport' => $travel->transport,
            'estimated_cost' => $travel->estimated_cost !== null ? (float) $travel->estimated_cost : null,
            'per_diem' => $travel->per_diem !== null ? (float) $travel->per_diem : null,
            'status' => $travel->status,
            'status_label' => $this->statusLabel($travel->status),
        ];
    }

    /**
     * Shape the eager-loaded employee, deriving initials and avatar color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(DutyTravel $travel): ?array
    {
        $employee = $travel->employee;

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
     * Abort with 403 when the user lacks employee-module access.
     */
    private function authorizeEmployeeAccess(Request $request): void
    {
        abort_unless($this->hasEmployeeAccess($request->user()), 403);
    }

    /**
     * Privileged roles bypass the check; otherwise require the `employee.view` code.
     */
    private function hasEmployeeAccess(User $user): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        $user->loadMissing('roles.permissions');

        if ($user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty()) {
            return true;
        }

        return $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains('employee.view');
    }

    /**
     * Abort with 404 when the duty travel does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, DutyTravel $dutyTravel): void
    {
        abort_if((int) $dutyTravel->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
