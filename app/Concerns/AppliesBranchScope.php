<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Applies a user's data-scope + branch-access rules to list queries.
 *
 * The filter is a deliberate NO-OP for users whose effective scope is
 * "company" (the default) or "team", so accounts without an explicit branch
 * scope continue to see all of their tenant's data exactly as before.
 */
trait AppliesBranchScope
{
    /**
     * Resolve the user's effective data scope.
     *
     * Super admins always operate at the company level; every other user
     * falls back to their first configured data scope, or "company" when
     * none is assigned.
     */
    protected function effectiveScope(User $user): string
    {
        if ($user->roles->contains('code', 'super_admin')) {
            return 'company';
        }

        return $user->dataScopes->first()?->scope_type ?? 'company';
    }

    /**
     * The branch ids the user has explicit access to.
     *
     * @return array<int, int>
     */
    protected function scopedBranchIds(User $user): array
    {
        return $user->branchAccesses
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Constrain a query by the user's branch scope using a direct branch column.
     *
     * A "company"/"team" scope leaves the query untouched. A "branch" scope
     * limits results to the user's assigned branches (matching nothing when no
     * branch is assigned). An "own" scope narrows to the user's own employee
     * record when the table exposes an employee_id, otherwise it falls back to
     * the branch column.
     */
    protected function applyBranchScope(Builder $query, User $user, string $branchColumn = 'branch_id'): Builder
    {
        $scope = $this->effectiveScope($user);

        if ($scope === 'company' || $scope === 'team') {
            return $query;
        }

        if ($scope === 'branch') {
            return $query->whereIn($branchColumn, $this->scopedBranchIds($user) ?: [0]);
        }

        if ($scope === 'own') {
            $table = $query->getModel()->getTable();

            if (Schema::hasColumn($table, 'employee_id')) {
                return $query->where('employee_id', $user->employee?->id ?? 0);
            }

            return $query->whereIn($branchColumn, $this->scopedBranchIds($user) ?: [0]);
        }

        return $query;
    }

    /**
     * Constrain a query by the user's branch scope via a related employee.
     *
     * Used when the queried table has no branch column of its own and the
     * branch is reachable through an employee relationship.
     */
    protected function applyBranchScopeViaEmployee(Builder $query, User $user, string $relation = 'employee'): Builder
    {
        $scope = $this->effectiveScope($user);

        if ($scope === 'company' || $scope === 'team') {
            return $query;
        }

        if ($scope === 'branch') {
            $branchIds = $this->scopedBranchIds($user) ?: [0];

            return $query->whereHas($relation, function (Builder $relationQuery) use ($branchIds): void {
                $relationQuery->whereIn('branch_id', $branchIds);
            });
        }

        if ($scope === 'own') {
            return $query->where('employee_id', $user->employee?->id ?? 0);
        }

        return $query;
    }
}
