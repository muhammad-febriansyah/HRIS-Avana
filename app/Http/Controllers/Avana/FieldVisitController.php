<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FieldVisit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            ->with('employee:id,full_name,employee_number')
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('location', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%");
                });
            })
            ->when($request->query('date'), fn ($q, $date) => $q->whereDate('visit_date', $date))
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
            'filters' => $request->only(['search', 'date', 'per_page']),
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
     * Show the form for recording a new field visit.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/visiting/create', [
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
     * Persist a new field visit on behalf of an employee under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,tenant_id,{$tenantId}",
            ],
            'visit_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('field-visits', 'public');
        }

        FieldVisit::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'visit_date' => $data['visit_date'],
            'location' => $data['location'],
            'client_name' => $data['client_name'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'notes' => $data['notes'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'photo_path' => $photoPath,
            'status' => 'submitted',
        ]);

        return redirect()->route('avana.visiting')
            ->with('success', 'Kunjungan kerja dicatat');
    }

    /**
     * Delete a field visit and its uploaded photo.
     */
    public function destroy(Request $request, FieldVisit $visit): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $visit);

        if ($visit->photo_path !== null) {
            Storage::disk('public')->delete($visit->photo_path);
        }

        $visit->delete();

        return back()->with('success', 'Kunjungan kerja dihapus');
    }

    /**
     * Shape a field visit row for the index DataTable.
     *
     * @return array{id: int, employee: array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null, visit_date: string|null, location: string, client_name: string|null, purpose: string|null, notes: string|null, photo_url: string|null, latitude: float|null, longitude: float|null, status: string}
     */
    private function shapeVisit(FieldVisit $visit): array
    {
        $employee = $visit->employee;

        return [
            'id' => $visit->id,
            'employee' => $employee === null ? null : [
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'initials' => $this->initials($employee->full_name),
                'avatar_color' => $this->avatarColor($employee->full_name),
            ],
            'visit_date' => $visit->visit_date?->format('d M Y'),
            'location' => $visit->location,
            'client_name' => $visit->client_name,
            'purpose' => $visit->purpose,
            'notes' => $visit->notes,
            'photo_url' => $visit->photo_path !== null
                ? Storage::disk('public')->url($visit->photo_path)
                : null,
            'latitude' => $visit->latitude !== null ? (float) $visit->latitude : null,
            'longitude' => $visit->longitude !== null ? (float) $visit->longitude : null,
            'status' => $visit->status,
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
