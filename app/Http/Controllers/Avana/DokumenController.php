<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DokumenController extends Controller
{
    /**
     * Roles that may always manage employee documents within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Display the tenant's uploaded employee documents.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $documents = EmployeeDocument::forTenant($tenantId)
            ->with('employee:id,full_name')
            ->latest('id')
            ->get()
            ->map(fn (EmployeeDocument $document): array => $this->transformDocument($document));

        $employees = $this->employeeOptions($tenantId);

        return Inertia::render('avana/dokumen/index', [
            'documents' => $documents,
            'employees' => $employees,
            'kpis' => [
                'total_documents' => $documents->count(),
                'employees_with_documents' => $documents->pluck('employee_id')->unique()->count(),
                'total_employees' => count($employees),
            ],
        ]);
    }

    /**
     * Persist an uploaded document under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $file->store("documents/{$tenantId}", 'public');

        EmployeeDocument::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        return back()->with('success', 'Dokumen berhasil diunggah');
    }

    /**
     * Delete a stored document together with its file.
     */
    public function destroy(Request $request, EmployeeDocument $document): RedirectResponse
    {
        $this->ensureCanManage($request);

        abort_if((int) $document->tenant_id !== (int) $request->user()->tenant_id, 404);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('success', 'Dokumen dihapus');
    }

    /**
     * Build the row shape consumed by the documents table.
     *
     * @return array<string, mixed>
     */
    private function transformDocument(EmployeeDocument $document): array
    {
        return [
            'id' => $document->id,
            'employee_id' => $document->employee_id,
            'employee' => $document->employee?->full_name,
            'name' => $document->name,
            'type' => $document->type,
            'file_size' => $document->file_size,
            'file_size_label' => $this->humanFileSize($document->file_size),
            'uploaded_at' => $document->uploaded_at?->toDateString(),
            'download_url' => $document->file_path ? Storage::disk('public')->url($document->file_path) : null,
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
            ->get(['id', 'full_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
            ])
            ->all();
    }

    /**
     * Render a byte count as a human-readable size label.
     */
    private function humanFileSize(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, 1).' '.$units[$unitIndex];
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
