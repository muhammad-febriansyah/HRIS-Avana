<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight global search behind the topbar search box. Returns a small,
 * capped set of matches as JSON so the client can render a quick-jump palette.
 * Terms under two characters are short-circuited to keep the query cheap.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['employees' => [], 'tenants' => []]);
        }

        $user = $request->user();
        $like = '%'.$term.'%';

        $employees = Employee::forTenant((int) $user->tenant_id)
            ->where('status', 'active')
            ->where(function ($query) use ($like): void {
                $query->where('full_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like)
                    ->orWhere('nik', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->with('position:id,name')
            ->limit(6)
            // public_id is the route key, so the link cannot be built without it.
            ->get(['id', 'public_id', 'full_name', 'employee_number', 'position_id'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'label' => $employee->full_name,
                'sub' => $employee->position?->name ?? $employee->employee_number,
                'href' => route('avana.employees.show', $employee),
            ])
            ->all();

        $tenants = [];

        if ($user->roles()->where('code', 'super_admin')->exists()) {
            $tenants = Tenant::where('name', 'like', $like)
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'company_name'])
                ->map(fn (Tenant $tenant): array => [
                    'id' => $tenant->id,
                    'label' => $tenant->name,
                    'sub' => $tenant->company_name,
                    'href' => route('avana.klien.show', $tenant->id),
                ])
                ->all();
        }

        return response()->json([
            'employees' => $employees,
            'tenants' => $tenants,
        ]);
    }
}
