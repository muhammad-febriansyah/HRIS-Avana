<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LearningController extends Controller
{
    /**
     * Roles that may always manage learning within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Allowed training type enum values.
     *
     * @var array<int, string>
     */
    private const TYPES = ['internal', 'external', 'online'];

    /**
     * Allowed training status enum values.
     *
     * @var array<int, string>
     */
    private const STATUSES = ['planned', 'ongoing', 'completed'];

    /**
     * Allowed enrollment status enum values.
     *
     * @var array<int, string>
     */
    private const ENROLLMENT_STATUSES = ['enrolled', 'attended', 'completed'];

    /**
     * Display the training catalogue together with employee enrollments.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $trainings = Training::forTenant($tenantId)
            ->withCount('enrollments')
            ->latest('id')
            ->get()
            ->map(fn (Training $training): array => $this->transformTraining($training));

        $enrollments = TrainingEnrollment::forTenant($tenantId)
            ->with(['training:id,title', 'employee:id,full_name,employee_number'])
            ->latest('id')
            ->get()
            ->map(fn (TrainingEnrollment $enrollment): array => $this->transformEnrollment($enrollment));

        return Inertia::render('avana/pembelajaran/index', [
            'trainings' => $trainings,
            'enrollments' => $enrollments,
            'employees' => $this->employeeOptions($tenantId),
            'statuses' => $this->statusOptions(),
            'kpis' => [
                'total_training' => $trainings->count(),
                'ongoing' => $trainings->where('status', 'ongoing')->count(),
                'peserta' => $enrollments->count(),
                'completed' => $enrollments->where('status', 'completed')->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new training.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('avana/pembelajaran/create');
    }

    /**
     * Show the form for editing an existing training.
     */
    public function edit(Request $request, Training $training): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $training);

        return Inertia::render('avana/pembelajaran/edit', [
            'training' => [
                'id' => $training->id,
                'title' => $training->title,
                'category' => $training->category,
                'type' => $training->type,
                'start_date' => $training->start_date?->toDateString(),
                'end_date' => $training->end_date?->toDateString(),
                'cost' => (float) $training->cost,
                'instructor' => $training->instructor,
                'quota' => $training->quota,
                'status' => $training->status,
                'description' => $training->description,
            ],
        ]);
    }

    /**
     * Persist a new training under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateTraining($request);

        Training::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.pembelajaran')
            ->with('success', 'Pelatihan berhasil ditambahkan');
    }

    /**
     * Update an existing training.
     */
    public function update(Request $request, Training $training): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $training);

        $data = $this->validateTraining($request);

        $training->update($data);

        return redirect()->route('avana.pembelajaran')
            ->with('success', 'Pelatihan berhasil diperbarui');
    }

    /**
     * Delete a training.
     */
    public function destroy(Request $request, Training $training): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $training);

        $training->delete();

        return back()->with('success', 'Pelatihan dihapus');
    }

    /**
     * Enroll an employee into a training within the acting user's tenant.
     */
    public function enroll(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'training_id' => [
                'required',
                'integer',
                Rule::exists('trainings', 'id')->where('tenant_id', $tenantId),
            ],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['required', Rule::in(self::ENROLLMENT_STATUSES)],
        ]);

        TrainingEnrollment::create([
            'tenant_id' => $tenantId,
            'training_id' => $data['training_id'],
            'employee_id' => $data['employee_id'],
            'status' => $data['status'],
        ]);

        return redirect()->route('avana.pembelajaran')
            ->with('success', 'Peserta berhasil ditambahkan');
    }

    /**
     * Update an enrollment's progress (attended/completed + score + certificate).
     */
    public function updateEnrollment(Request $request, TrainingEnrollment $enrollment): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $enrollment);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::ENROLLMENT_STATUSES)],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'certificate_no' => ['nullable', 'string', 'max:255'],
            'completed_date' => ['nullable', 'date'],
        ]);

        $enrollment->update([
            'status' => $data['status'],
            'score' => $data['score'] ?? null,
            'certificate_no' => $data['certificate_no'] ?? null,
            'completed_date' => $data['completed_date'] ?? null,
        ]);

        return back()->with('success', 'Data peserta diperbarui');
    }

    /**
     * Validate the create/update payload for a training.
     *
     * @return array<string, mixed>
     */
    private function validateTraining(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'cost' => ['required', 'numeric', 'min:0'],
            'instructor' => ['nullable', 'string', 'max:255'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'description' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the row shape consumed by the trainings table.
     *
     * @return array<string, mixed>
     */
    private function transformTraining(Training $training): array
    {
        return [
            'id' => $training->id,
            'title' => $training->title,
            'category' => $training->category,
            'type' => $training->type,
            'start_date' => $training->start_date?->toDateString(),
            'end_date' => $training->end_date?->toDateString(),
            'cost' => (float) $training->cost,
            'instructor' => $training->instructor,
            'quota' => $training->quota,
            'status' => $training->status,
            'description' => $training->description,
            'enrollments_count' => $training->enrollments_count,
        ];
    }

    /**
     * Build the row shape consumed by the enrollment list.
     *
     * @return array<string, mixed>
     */
    private function transformEnrollment(TrainingEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'training_id' => $enrollment->training_id,
            'training_title' => $enrollment->training?->title,
            'employee' => $enrollment->employee === null ? null : [
                'name' => $enrollment->employee->full_name,
                'employee_number' => $enrollment->employee->employee_number,
            ],
            'status' => $enrollment->status,
            'score' => $enrollment->score === null ? null : (float) $enrollment->score,
            'certificate_no' => $enrollment->certificate_no,
            'completed_date' => $enrollment->completed_date?->toDateString(),
        ];
    }

    /**
     * Build the tenant's selectable employee options.
     *
     * @return array<int, array<string, mixed>>
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
     * Build the `{ value, label }` list of training statuses.
     *
     * @return array<int, array<string, string>>
     */
    private function statusOptions(): array
    {
        $labels = [
            'planned' => 'Direncanakan',
            'ongoing' => 'Berjalan',
            'completed' => 'Selesai',
        ];

        return collect(self::STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $labels[$status],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Training|TrainingEnrollment $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}
