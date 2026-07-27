<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Struktur Organisasi" for employees.
 *
 * The admin org chart at /avana/organisasi authorises through EmployeePolicy,
 * which also guards the full employee console — loosening it would hand plain
 * karyawan the whole admin directory. This renders the same chart from the
 * caller's own tenant instead, carrying only the directory-safe fields.
 */
class EssDirectoryController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * The tenant's org chart, as seen by one of its employees.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $colleagues = Employee::forTenant($employee->tenant_id)
            ->where('status', 'active')
            ->with(['position:id,name', 'department:id,name', 'branch:id,name'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number', 'email', 'position_id', 'department_id', 'branch_id', 'manager_id', 'join_date', 'is_top_approver']);

        $names = $colleagues->pluck('full_name', 'id');

        return Inertia::render('avana/employees/org-chart', [
            // The full profile screen sits behind EmployeePolicy, so the detail
            // sheet must not offer a link an employee cannot follow.
            'canOpenProfile' => false,
            'nodes' => $colleagues->map(fn (Employee $colleague): array => [
                'id' => $colleague->id,
                'name' => $colleague->full_name,
                'employee_number' => $colleague->employee_number,
                'email' => $colleague->email,
                'position' => $colleague->position?->name,
                'department' => $colleague->department?->name,
                'branch' => $colleague->branch?->name,
                'join_date' => $colleague->join_date?->locale('id')->translatedFormat('d M Y'),
                'manager_id' => $colleague->manager_id,
                // The company head legitimately reports to nobody; anyone else
                // without a manager is a gap the chart should point out.
                'is_top_approver' => (bool) $colleague->is_top_approver,
                'manager_name' => $colleague->manager_id !== null ? $names->get($colleague->manager_id) : null,
            ])->values(),
        ]);
    }
}
