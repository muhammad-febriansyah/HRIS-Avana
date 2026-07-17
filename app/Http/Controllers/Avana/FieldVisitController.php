<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\FieldVisit;
use App\Models\FieldVisitTask;
use App\Models\User;
use App\Services\FieldVisitPhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FieldVisitController extends Controller
{
    /**
     * Roles that may always manage field visits within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

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
     * Display a server-side paginated, filterable list of field visits.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $paginator = FieldVisit::query()
            ->forTenant($tenantId)
            ->with(['employees:id,full_name,employee_number', 'branch:id,name', 'photos', 'tasks'])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('location', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhereHas('employees', fn ($sub) => $sub->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('date'), fn ($q, $date) => $q->whereDate('visit_date', $date))
            ->when($request->query('branch_id'), fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/visiting/index', [
            'visits' => [
                'data' => $paginator->getCollection()
                    ->map(fn (FieldVisit $visit): array => $this->shapeVisit($visit))
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
            'filters' => $request->only(['search', 'date', 'branch_id', 'per_page']),
            'employees' => $this->employeeOptions($tenantId),
            'branches' => $this->branchOptions($tenantId),
        ]);
    }

    /**
     * Show the form for recording a new field visit.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/visiting/create', [
            'employees' => $this->employeeOptions($tenantId),
            'branches' => $this->branchOptions($tenantId),
        ]);
    }

    /**
     * Persist a new field visit, its attendees and its tasklist.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'branch_id' => ['nullable', 'integer', "exists:branches,id,tenant_id,{$tenantId}"],
            'visit_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['required', 'string', 'max:255'],
            ...FieldVisitPhotoStore::rules(),
        ]);

        $employeeIds = array_values(array_unique(array_map('intval', $data['employee_ids'])));

        $visit = DB::transaction(function () use ($tenantId, $data, $employeeIds): FieldVisit {
            $visit = FieldVisit::create([
                'tenant_id' => $tenantId,
                // The first pick owns the report; the rest ride along on the pivot.
                'employee_id' => $employeeIds[0],
                'branch_id' => $data['branch_id'] ?? null,
                'visit_date' => $data['visit_date'],
                'location' => $data['location'],
                'client_name' => $data['client_name'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'notes' => $data['notes'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status' => 'submitted',
            ]);

            $visit->syncAttendees($employeeIds);

            foreach (array_values($data['tasks'] ?? []) as $order => $title) {
                $visit->tasks()->create([
                    'tenant_id' => $tenantId,
                    'title' => $title,
                    'sort_order' => $order,
                ]);
            }

            return $visit;
        });

        FieldVisitPhotoStore::attach($visit, $request->file('photos') ?? []);

        return redirect()->route('avana.visiting')
            ->with('success', 'Kunjungan kerja dicatat');
    }

    /**
     * Tick a task off the visit's list, or put it back.
     */
    public function toggleTask(Request $request, FieldVisit $visit, FieldVisitTask $task): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $visit);
        abort_if((int) $task->field_visit_id !== (int) $visit->id, 404);

        $isDone = ! $task->is_done;

        $task->update([
            'is_done' => $isDone,
            'done_at' => $isDone ? now() : null,
        ]);

        return back()->with('success', $isDone ? 'Tugas ditandai selesai' : 'Tugas dibuka kembali');
    }

    /**
     * Delete a field visit and its uploaded photos.
     */
    public function destroy(Request $request, FieldVisit $visit): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $visit);

        FieldVisitPhotoStore::purge($visit);

        $visit->delete();

        return back()->with('success', 'Kunjungan kerja dihapus');
    }

    /**
     * Shape a field visit row for the index DataTable.
     *
     * @return array<string, mixed>
     */
    private function shapeVisit(FieldVisit $visit): array
    {
        $progress = $visit->taskProgress();

        return [
            'id' => $visit->id,
            'employees' => $visit->employees
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                    'initials' => $this->initials($employee->full_name),
                    'avatar_color' => $this->avatarColor($employee->full_name),
                ])
                ->values()
                ->all(),
            'branch' => $visit->branch?->name,
            'visit_date' => $visit->visit_date?->format('d M Y'),
            'location' => $visit->location,
            'client_name' => $visit->client_name,
            'purpose' => $visit->purpose,
            'notes' => $visit->notes,
            'photo_urls' => FieldVisitPhotoStore::urls($visit),
            'latitude' => $visit->latitude !== null ? (float) $visit->latitude : null,
            'longitude' => $visit->longitude !== null ? (float) $visit->longitude : null,
            'status' => $visit->status,
            'tasks' => $visit->tasks
                ->map(fn (FieldVisitTask $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'is_done' => $task->is_done,
                ])
                ->values()
                ->all(),
            'task_progress' => $progress,
        ];
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
     * Build the tenant's selectable branch options.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function branchOptions(int $tenantId): array
    {
        return Branch::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->all();
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
     * Abort with 404 when the field visit does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, FieldVisit $visit): void
    {
        abort_if((int) $visit->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an attendance permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasAttendancePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'attendance.'));

        abort_unless($isPrivileged || $hasAttendancePermission, 403);
    }
}
