<?php

namespace App\Concerns;

use App\Models\Employee;
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
}
