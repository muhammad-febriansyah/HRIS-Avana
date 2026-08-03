<?php

namespace App\Concerns;

use App\Models\Employee;
use App\Support\PrivateFile;
use Illuminate\Http\Request;

/**
 * Resolves the employee record behind the authenticated mobile (API) user, so
 * every self-service endpoint is scoped to the caller's own employee + tenant.
 */
trait ResolvesApiEmployee
{
    protected function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()?->employee;

        abort_if($employee === null, 403, 'Akun ini tidak terhubung ke data karyawan.');

        return $employee;
    }

    /**
     * The mobile "Profile" shape shared by /auth/me and /me/profile.
     *
     * @return array<string, mixed>
     */
    protected function employeeProfile(Employee $employee): array
    {
        $employee->loadMissing(['position:id,name', 'department:id,name', 'branch:id,name', 'jobLevel:id,name', 'tenant:id,name,company_name']);

        return [
            'id' => $employee->id,
            'employee_no' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'nik' => $employee->nik,
            'gender' => $employee->gender,
            'birth_place' => $employee->birth_place,
            'birth_date' => $employee->birth_date?->toDateString(),
            'religion' => $employee->religion,
            'marital_status' => $employee->marital_status,
            'status' => $employee->status,
            'join_date' => $employee->join_date?->toDateString(),
            'photo_url' => PrivateFile::urlFor($employee->photo_path),
            'employment' => [
                'company' => $employee->tenant?->company_name ?? $employee->tenant?->name,
                'branch' => $employee->branch?->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'job_grade' => $employee->jobLevel?->name,
                'employment_type' => $employee->employment_status,
            ],
            // Drives the Manager Self-Service entry in the mobile app. A top
            // approver (director) always qualifies — they sit at the head of the
            // approval chain even when no one reports to them directly.
            'is_manager' => $employee->is_top_approver
                || Employee::where('tenant_id', $employee->tenant_id)
                    ->where('manager_id', $employee->id)
                    ->exists(),
        ];
    }
}
