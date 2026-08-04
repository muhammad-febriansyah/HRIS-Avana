<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Employee extends Model
{
    use Auditable, HasPublicId, SoftDeletes;

    /**
     * Sentinel the Atasan Langsung pickers post for "Tidak ada — Approver
     * Puncak". A deliberate choice, unlike an empty value which only means the
     * field was left untouched.
     */
    public const NO_MANAGER = 'none';

    /**
     * Sentinel for "belum ditentukan": nobody is recorded above this person
     * *yet*, which is not the same as declaring them the top of the chain.
     *
     * Without it the very first employee of a new tenant could only be saved as
     * an approver puncak — the picker had no colleagues to offer, so the sole
     * selectable entry was NO_MANAGER, and a rank-and-file hire silently gained
     * self-approving leave, overtime and reimbursement.
     */
    public const UNASSIGNED_MANAGER = 'unassigned';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'resign_date' => 'date',
            'custom_data' => 'array',
            'is_top_approver' => 'boolean',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  array<int, int>  $branchIds
     */
    public function scopeForBranches(Builder $query, array $branchIds): Builder
    {
        return $query->whereIn('branch_id', $branchIds);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * The attendance scope actually in force for this employee: their own
     * override when set, otherwise the tenant policy's default.
     */
    public function effectiveAttendanceScope(AttendancePolicy $policy): string
    {
        $scope = $this->attendance_scope ?? $policy->attendance_scope ?? AttendancePolicy::SCOPE_ASSIGNED;

        // An unrecognised value must never silently loosen the geofence.
        return in_array($scope, AttendancePolicy::SCOPES, true)
            ? $scope
            : AttendancePolicy::SCOPE_ASSIGNED;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function salaryMaster(): BelongsTo
    {
        return $this->belongsTo(SalaryMaster::class);
    }

    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    public function payday(): BelongsTo
    {
        return $this->belongsTo(Payday::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(EmployeeDependent::class);
    }

    public function careerHistories(): HasMany
    {
        return $this->hasMany(EmployeeCareerHistory::class);
    }

    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Payslip lines produced for this employee, one per payroll run.
     */
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function taxProfile(): HasOne
    {
        return $this->hasOne(TaxProfile::class);
    }

    public function bpjsProfile(): HasOne
    {
        return $this->hasOne(EmployeeBpjsProfile::class);
    }

    /**
     * Likes this employee has given, used to mark the feed without a query per
     * post.
     */
    public function socialLikes(): HasMany
    {
        return $this->hasMany(SocialPostLike::class);
    }
}
