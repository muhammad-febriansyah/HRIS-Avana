<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceCorrection;
use App\Models\DataChangeRequest;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\Reimbursement;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runtime engine for the "Setup Alur Persetujuan" configuration. When a tenant
 * has an active workflow for a request type, submissions are routed through its
 * ordered steps instead of straight to the employee's manager: each approval
 * advances to the next step's approver, and the last step finalizes the request.
 *
 * Requests without a matching workflow keep the legacy single-step behaviour
 * (routed to `manager_id`), and top approvers (directors) still short-circuit to
 * auto-approval before the engine is ever consulted.
 *
 * `current_approver_id` on the request models stores an EMPLOYEE id (that is what
 * the mobile MSS queue compares against), so every resolved approver is written
 * back as an employee id. The parallel `approval_requests` row tracks the step
 * cursor and keeps a user-id copy for its own foreign key.
 *
 * Not yet handled (documented limitations): per-workflow/per-step `conditions`
 * are ignored, and `approval_mode = parallel` is processed sequentially.
 */
class ApprovalEngine
{
    /**
     * Request model class → the `approval_workflows.request_type` key the wizard
     * stores. Only these types can be workflow-driven today.
     *
     * @var array<class-string<Model>, string>
     */
    private const TYPE_FOR_MODEL = [
        LeaveRequest::class => 'leave',
        OvertimeRequest::class => 'overtime',
        Reimbursement::class => 'reimbursement',
        PermissionRequest::class => 'permission',
        AttendanceCorrection::class => 'attendance_correction',
        DutyTravel::class => 'duty_travel',
        DataChangeRequest::class => 'data_change',
    ];

    /**
     * Route a freshly submitted request through its workflow, if one is active.
     *
     * Returns true when the request was placed on a workflow (its
     * `current_approver_id` now points at step 1's approver); false when there is
     * no workflow and the caller should keep its legacy routing.
     */
    public static function start(Model $approvable, Employee $subject): bool
    {
        $type = self::TYPE_FOR_MODEL[$approvable::class] ?? null;

        if ($type === null) {
            return false;
        }

        // A division-scoped flow beats the tenant-wide default: the requester's
        // department picks its own chain when one exists, everyone else falls
        // back to the flow with no department.
        $workflow = ApprovalWorkflow::forTenant($subject->tenant_id)
            ->where('request_type', $type)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('department_id')
                ->when($subject->department_id !== null, fn ($sub) => $sub->orWhere('department_id', $subject->department_id)))
            ->with('steps')
            ->orderByRaw('department_id IS NULL')
            ->orderByDesc('id')
            ->first();

        if ($workflow === null) {
            return false;
        }

        $firstStep = self::effectiveSteps($workflow, $approvable)->first();

        if ($firstStep === null) {
            return false;
        }

        // A parallel workflow opens every step at once (no single current
        // approver); a group step (role/department/position) has no single owner
        // either. Both are surfaced to every eligible holder via the MSS queue,
        // so the request carries no `current_approver_id`.
        $spread = self::isParallel($workflow) || self::isGroupStep($firstStep);
        $concrete = $spread ? null : self::resolveConcreteApprover($firstStep, $subject);

        DB::transaction(function () use ($approvable, $subject, $workflow, $spread, $concrete): void {
            $approvable->update([
                'current_approver_id' => $spread
                    ? null
                    : ($concrete !== null ? $concrete->getKey() : $subject->manager_id),
            ]);

            ApprovalRequest::create([
                'tenant_id' => $subject->tenant_id,
                'approvable_type' => $approvable::class,
                'approvable_id' => $approvable->getKey(),
                'requester_id' => $subject->user_id,
                'current_approver_id' => $concrete?->user_id,
                'approval_workflow_id' => $workflow->id,
                'current_step' => 1,
                'status' => 'pending',
            ]);
        });

        // A parallel workflow opens every step at once, so everyone it names is
        // told; a sequential one only troubles step 1.
        $opening = self::isParallel($workflow)
            ? self::effectiveSteps($workflow, $approvable)
            : collect([$firstStep]);

        self::notifyApprovers($approvable, $opening, $subject);

        return true;
    }

    /**
     * Apply an approve/reject decision to a workflow-driven request. Returns true
     * when the engine handled it (advanced to the next step or finalized); false
     * when there is no workflow instance and the caller should fall back to its
     * own approve logic.
     */
    public static function decide(Model $approvable, ?int $actorUserId, string $action, ?string $note = null): bool
    {
        $instance = ApprovalRequest::query()
            ->where('approvable_type', $approvable::class)
            ->where('approvable_id', $approvable->getKey())
            ->where('status', 'pending')
            ->with('workflow.steps')
            ->first();

        if ($instance === null) {
            return false;
        }

        $subject = Employee::forTenant((int) $approvable->getAttribute('tenant_id'))
            ->find((int) $approvable->getAttribute('employee_id'));
        $workflow = $instance->workflow;
        $effective = $workflow !== null ? self::effectiveSteps($workflow, $approvable) : collect();

        // Only the approver the step routes to may decide it. HR/super admin may
        // still step in, but the log records that it was an override.
        $override = self::guardActor($instance, $effective, $subject, $actorUserId, $workflow);
        $note = $override
            ? trim(($note ?? '').' [override admin]')
            : $note;

        // The step the decision hands the request on to, if any — announced
        // after the transaction commits so the approver is only told about a
        // routing that actually stuck.
        $advancedTo = null;

        $handled = DB::transaction(function () use ($instance, $approvable, $subject, $workflow, $effective, $actorUserId, $action, $note, &$advancedTo): bool {
            if ($action === 'reject') {
                self::log($instance, $actorUserId, 'reject', $instance->current_step, $note);
                $instance->update(['status' => 'rejected']);
                $approvable->update(['status' => 'rejected']);

                return true;
            }

            // Parallel: the request is approved only once every step has been
            // approved by a distinct approver.
            if ($workflow !== null && self::isParallel($workflow)) {
                $approvedBy = ApprovalLog::query()
                    ->where('approval_request_id', $instance->id)
                    ->where('action', 'approve')
                    ->pluck('approver_id');

                if ($actorUserId !== null && $approvedBy->contains($actorUserId)) {
                    return true; // already counted — idempotent
                }

                self::log($instance, $actorUserId, 'approve', null, $note);

                $distinct = $approvedBy->push($actorUserId)->filter()->unique()->count();

                if ($distinct >= max(1, $effective->count())) {
                    $instance->update(['status' => 'approved']);
                    self::finalize($approvable, $actorUserId);
                }

                return true;
            }

            // Sequential: record this step, then advance or finalize.
            self::log($instance, $actorUserId, 'approve', $instance->current_step, $note);

            if ($instance->current_step >= $effective->count()) {
                $instance->update(['status' => 'approved']);
                self::finalize($approvable, $actorUserId);

                return true;
            }

            $nextStep = $effective->get($instance->current_step); // 0-indexed: current_step is 1-based
            $group = $nextStep !== null && self::isGroupStep($nextStep);
            $concrete = ($nextStep !== null && ! $group) ? self::resolveConcreteApprover($nextStep, $subject) : null;

            $instance->update([
                'current_step' => $instance->current_step + 1,
                'current_approver_id' => $concrete?->user_id,
            ]);
            $approvable->update([
                'current_approver_id' => $group
                    ? null
                    : ($concrete !== null ? $concrete->getKey() : $subject?->manager_id),
            ]);

            $advancedTo = $nextStep;

            return true;
        });

        if ($advancedTo !== null) {
            self::notifyApprovers($approvable, collect([$advancedTo]), $subject);
        }

        return $handled;
    }

    /**
     * Tell everyone the given steps route to that a request is waiting on them.
     *
     * A group step has no single owner, so every holder of the role /
     * department / position is notified — the same set
     * {@see pendingApprovableIdsFor} surfaces the request to.
     *
     * @param  Collection<int, ApprovalStep>  $steps
     */
    private static function notifyApprovers(Model $approvable, Collection $steps, ?Employee $subject): void
    {
        $employeeIds = $steps
            ->flatMap(fn (ApprovalStep $step): array => self::approverEmployeeIds($step, $subject))
            // A step that resolves to the requester is routed to their manager
            // instead, so that is who is told about it.
            ->map(fn (int $id): int => ($subject !== null && $id === (int) $subject->getKey())
                ? (int) ($subject->manager_id ?? 0)
                : $id)
            ->filter()
            ->all();

        Notifier::requestAwaitingApproval($approvable, $employeeIds);
    }

    /**
     * Every employee a step routes to, named or by group.
     *
     * @return array<int, int>
     */
    private static function approverEmployeeIds(ApprovalStep $step, ?Employee $subject): array
    {
        if ($subject === null) {
            return [];
        }

        $tenantId = (int) $subject->tenant_id;

        return match ($step->approver_type) {
            'direct_manager' => $subject->manager_id !== null ? [(int) $subject->manager_id] : [],
            'specific_user' => $step->approver_user_id !== null ? [(int) $step->approver_user_id] : [],
            'role' => $step->approver_role_id === null ? [] : Employee::forTenant($tenantId)
                ->whereHas('user.roles', fn ($query) => $query->where('roles.id', $step->approver_role_id))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
            'department' => $step->approver_department_id === null ? [] : Employee::forTenant($tenantId)
                ->where('department_id', $step->approver_department_id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
            'position' => $step->approver_position_id === null ? [] : Employee::forTenant($tenantId)
                ->where('position_id', $step->approver_position_id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
            default => [],
        };
    }

    /**
     * A step whose approver is a group (role / department / position) rather than
     * one named person. Group steps are surfaced to every eligible holder via
     * {@see pendingApprovableIdsFor} instead of owning a single `current_approver_id`.
     */
    private static function isGroupStep(ApprovalStep $step): bool
    {
        return in_array($step->approver_type, ['role', 'department', 'position'], true);
    }

    /**
     * Resolve the single named approver for a concrete step (direct manager or a
     * specific employee). Group steps have no single owner and return null.
     */
    private static function resolveConcreteApprover(ApprovalStep $step, ?Employee $subject): ?Employee
    {
        if ($subject === null) {
            return null;
        }

        $tenantId = $subject->tenant_id;

        $approver = match ($step->approver_type) {
            'direct_manager' => $subject->manager_id !== null
                ? Employee::forTenant($tenantId)->find($subject->manager_id)
                : null,
            'specific_user' => $step->approver_user_id !== null
                ? Employee::forTenant($tenantId)->find($step->approver_user_id)
                : null,
            default => null,
        };

        // Nobody approves their own request. A workflow that names one employee
        // for a whole module lands on that employee when they file it too, so
        // the step is treated as unresolvable and falls back to their manager —
        // the same fallback an unnamed approver already takes.
        if ($approver !== null && (int) $approver->getKey() === (int) $subject->getKey()) {
            return null;
        }

        return $approver;
    }

    /**
     * Where a still-pending request sits in its workflow, or null when it is
     * not workflow-driven.
     *
     * The approval screen needs this to say "tahap 1 dari 2" out loud: a
     * two-level flow leaves the request in the queue after the first approval,
     * and without naming the step that reads as a button that did nothing.
     *
     * @return array{step: int, total: int}|null
     */
    public static function progress(Model $approvable): ?array
    {
        $instance = ApprovalRequest::query()
            ->where('approvable_type', $approvable::class)
            ->where('approvable_id', $approvable->getKey())
            ->where('status', 'pending')
            ->with('workflow.steps')
            ->first();

        if ($instance === null || $instance->workflow === null) {
            return null;
        }

        $total = self::effectiveSteps($instance->workflow, $approvable)->count();

        if ($total === 0) {
            return null;
        }

        return [
            'step' => min((int) $instance->current_step, $total),
            'total' => $total,
        ];
    }

    /**
     * Approvable ids of pending, workflow-driven requests of the given type the
     * manager may act on but which are not routed to them by `current_approver_id`.
     * Sequential: the current step is a group (role/department/position) they
     * belong to. Parallel: they match any step they have not already approved.
     * This lets every holder of a role — not just one representative — see the
     * request in their queue.
     *
     * @param  class-string<Model>  $approvableType
     * @return array<int, int>
     */
    public static function pendingApprovableIdsFor(string $approvableType, Employee $manager): array
    {
        $roleIds = $manager->user?->roles()->pluck('roles.id')->all() ?? [];

        $instances = ApprovalRequest::query()
            ->where('tenant_id', $manager->tenant_id)
            ->where('approvable_type', $approvableType)
            ->where('status', 'pending')
            ->with('workflow.steps')
            ->get();

        $ids = [];

        foreach ($instances as $instance) {
            $workflow = $instance->workflow;

            if ($workflow === null) {
                continue;
            }

            $approvable = $approvableType::query()
                ->where('tenant_id', $instance->tenant_id)
                ->find($instance->approvable_id);

            if ($approvable === null) {
                continue;
            }

            $subject = Employee::forTenant((int) $approvable->getAttribute('tenant_id'))
                ->find((int) $approvable->getAttribute('employee_id'));
            $subjectManagerId = $subject?->manager_id !== null ? (int) $subject->manager_id : null;
            $subjectId = $subject?->getKey() !== null ? (int) $subject->getKey() : null;
            $effective = self::effectiveSteps($workflow, $approvable);

            if (self::isParallel($workflow)) {
                $alreadyApproved = $manager->user_id !== null && ApprovalLog::query()
                    ->where('approval_request_id', $instance->id)
                    ->where('approver_id', $manager->user_id)
                    ->where('action', 'approve')
                    ->exists();

                $eligible = ! $alreadyApproved && $effective->contains(
                    fn (ApprovalStep $step): bool => self::viewerMatchesStep($step, $manager, $roleIds, $subjectManagerId, $subjectId),
                );
            } else {
                // The step currently awaiting approval (1-based current_step).
                $step = $effective->get($instance->current_step - 1);
                $eligible = $step !== null
                    && self::isGroupStep($step)
                    && self::viewerMatchesStep($step, $manager, $roleIds, $subjectManagerId, $subjectId);
            }

            if ($eligible) {
                $ids[] = (int) $instance->approvable_id;
            }
        }

        return $ids;
    }

    /**
     * Make sure the person acting on this step is the one it is routed to.
     *
     * Returns true when the actor is not that approver but is allowed to step in
     * anyway (HR or super admin), so the caller can mark the log as an override.
     * Anyone else is refused — otherwise a single holder of the module's approve
     * permission could click through every level of a multi-step workflow alone.
     */
    private static function guardActor(
        ApprovalRequest $instance,
        Collection $effective,
        ?Employee $subject,
        ?int $actorUserId,
        ?ApprovalWorkflow $workflow,
    ): bool {
        // System-driven decisions (a top approver's auto-approval) carry no actor.
        if ($actorUserId === null) {
            return false;
        }

        $actor = User::find($actorUserId);

        abort_if($actor === null, 403, 'Penyetuju tidak dikenali.');

        if (self::actorMatchesInstance($instance, $effective, $subject, $actor, $workflow)) {
            return false;
        }

        abort_unless(
            $actor->roles()->whereIn('code', ['super_admin', 'admin_tenant_hr'])->exists(),
            403,
            'Anda bukan penyetuju untuk tahap ini.',
        );

        return true;
    }

    /**
     * Whether the acting user is the approver the instance currently awaits —
     * by the engine's own user-id cursor, or by matching the step's approver
     * definition (direct manager / specific employee / role / department / position).
     */
    private static function actorMatchesInstance(
        ApprovalRequest $instance,
        Collection $effective,
        ?Employee $subject,
        User $actor,
        ?ApprovalWorkflow $workflow,
    ): bool {
        if ($instance->current_approver_id !== null
            && (int) $instance->current_approver_id === (int) $actor->id) {
            return true;
        }

        $employee = Employee::forTenant((int) $instance->tenant_id)
            ->where('user_id', $actor->id)
            ->first();

        if ($employee === null) {
            return false;
        }

        $roleIds = $actor->roles()->pluck('roles.id')->all();
        $subjectManagerId = $subject?->manager_id !== null ? (int) $subject->manager_id : null;
        $subjectId = $subject?->getKey() !== null ? (int) $subject->getKey() : null;

        if ($workflow !== null && self::isParallel($workflow)) {
            return $effective->contains(
                fn (ApprovalStep $step): bool => self::viewerMatchesStep($step, $employee, $roleIds, $subjectManagerId, $subjectId),
            );
        }

        $step = $effective->get($instance->current_step - 1);

        // Nothing left to match against (a workflow whose steps were removed):
        // fall back to letting the decision through rather than deadlocking it.
        if ($step === null) {
            return true;
        }

        if (self::viewerMatchesStep($step, $employee, $roleIds, $subjectManagerId, $subjectId)) {
            return true;
        }

        // A concrete step whose approver cannot be resolved is routed to the
        // subject's manager by `start()`/`decide()` — honour that same fallback.
        if (! self::isGroupStep($step) && self::resolveConcreteApprover($step, $subject) === null) {
            return $subjectManagerId !== null && (int) $employee->getKey() === $subjectManagerId;
        }

        return false;
    }

    /**
     * Whether a workflow runs its steps in parallel (all must approve) rather
     * than sequentially.
     */
    private static function isParallel(ApprovalWorkflow $workflow): bool
    {
        return $workflow->approval_mode === 'parallel';
    }

    /**
     * Whether the viewing employee is one of a step's approvers.
     *
     * @param  array<int, mixed>  $roleIds
     */
    private static function viewerMatchesStep(ApprovalStep $step, Employee $manager, array $roleIds, ?int $subjectManagerId, ?int $subjectId = null): bool
    {
        // The requester is never one of their own approvers, whether a step
        // names them outright or names a role they happen to hold.
        if ($subjectId !== null && (int) $manager->getKey() === $subjectId) {
            return false;
        }

        return match ($step->approver_type) {
            'direct_manager' => $subjectManagerId !== null && (int) $manager->getKey() === $subjectManagerId,
            'specific_user' => $step->approver_user_id !== null && (int) $manager->getKey() === (int) $step->approver_user_id,
            'role' => in_array($step->approver_role_id, $roleIds, true),
            'department' => $step->approver_department_id !== null
                && (int) $manager->department_id === (int) $step->approver_department_id,
            'position' => $step->approver_position_id !== null
                && (int) $manager->position_id === (int) $step->approver_position_id,
            default => false,
        };
    }

    /**
     * The workflow's base steps plus any extra approver step whose "Kondisi
     * Tambahan" matches this request (e.g. amount > 5.000.000 → add Finance).
     * The values a condition checks are fixed once the request is submitted, so
     * this list is stable across every step of the approval.
     *
     * @return Collection<int, ApprovalStep>
     */
    private static function effectiveSteps(ApprovalWorkflow $workflow, Model $approvable): Collection
    {
        $steps = $workflow->steps->values();
        $rawConditions = $workflow->getAttribute('conditions');
        $conditions = is_array($rawConditions) ? $rawConditions : [];
        $extra = [];

        foreach ($conditions as $condition) {
            if (is_array($condition) && self::conditionMatches($condition, $approvable)) {
                $extra[] = self::syntheticStep($workflow, $condition);
            }
        }

        return $steps->concat($extra)->values();
    }

    /**
     * Evaluate one "Kondisi Tambahan" against the request's own values.
     *
     * @param  array<string, mixed>  $condition
     */
    private static function conditionMatches(array $condition, Model $approvable): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (! is_string($field) || ! is_string($operator) || $value === null) {
            return false;
        }

        if ($field === 'leave_type') {
            $leaveTypeId = $approvable->getAttribute('leave_type_id');

            return $leaveTypeId !== null && (int) $leaveTypeId === (int) $value;
        }

        $actual = match ($field) {
            'days' => $approvable->getAttribute('total_days'),
            'amount' => $approvable->getAttribute('amount'),
            default => null,
        };

        if ($actual === null) {
            return false;
        }

        $a = (float) $actual;
        $b = (float) $value;

        return match ($operator) {
            '>' => $a > $b,
            '>=' => $a >= $b,
            '=' => $a === $b,
            '<' => $a < $b,
            '<=' => $a <= $b,
            default => false,
        };
    }

    /**
     * Build an unsaved step from a matched condition's extra approver.
     *
     * @param  array<string, mixed>  $condition
     */
    private static function syntheticStep(ApprovalWorkflow $workflow, array $condition): ApprovalStep
    {
        $type = is_string($condition['extra_approver_type'] ?? null)
            ? $condition['extra_approver_type']
            : 'direct_manager';
        $ref = isset($condition['extra_approver_ref']) ? (int) $condition['extra_approver_ref'] : null;

        $refColumn = match ($type) {
            'role' => 'approver_role_id',
            'department' => 'approver_department_id',
            'position' => 'approver_position_id',
            'specific_user' => 'approver_user_id',
            default => null,
        };

        $attributes = [
            'tenant_id' => $workflow->tenant_id,
            'approval_workflow_id' => $workflow->id,
            'approver_type' => $type,
        ];

        if ($refColumn !== null) {
            $attributes[$refColumn] = $ref;
        }

        return new ApprovalStep($attributes);
    }

    /**
     * Append an approval-log entry for a decision.
     */
    private static function log(ApprovalRequest $instance, ?int $approverId, string $action, ?int $stepOrder, ?string $note): void
    {
        ApprovalLog::create([
            'tenant_id' => $instance->tenant_id,
            'approval_request_id' => $instance->id,
            'approver_id' => $approverId,
            'action' => $action,
            'step_order' => $stepOrder,
            'note' => $note,
        ]);
    }

    /**
     * Run the request type's own approval side effects on the final step, so a
     * workflow-finalized request is identical to a hand-approved one.
     */
    private static function finalize(Model $approvable, ?int $actorUserId): void
    {
        match (true) {
            $approvable instanceof LeaveRequest => LeaveApproval::finalize($approvable, $actorUserId),
            $approvable instanceof OvertimeRequest => AutoApproval::overtime($approvable),
            $approvable instanceof Reimbursement => AutoApproval::reimbursement($approvable, $actorUserId),
            $approvable instanceof PermissionRequest => $approvable->update(['status' => 'approved']),
            $approvable instanceof AttendanceCorrection => AttendanceCorrectionApproval::finalize($approvable, $actorUserId),
            // `approved_by` is a USER id, matching the duty-travel screen.
            $approvable instanceof DutyTravel => $approvable->update([
                'status' => 'approved',
                'approved_by' => $actorUserId,
            ]),
            $approvable instanceof DataChangeRequest => DataChangeApproval::finalize($approvable, $actorUserId),
            default => null,
        };
    }
}
