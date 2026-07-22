<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Reimbursement;
use Illuminate\Database\Eloquent\Model;
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

        $workflow = ApprovalWorkflow::forTenant($subject->tenant_id)
            ->where('request_type', $type)
            ->where('is_active', true)
            ->with('steps')
            ->orderByDesc('id')
            ->first();

        $firstStep = $workflow?->steps->first();

        if ($workflow === null || $firstStep === null) {
            return false;
        }

        $group = self::isGroupStep($firstStep);
        $concrete = $group ? null : self::resolveConcreteApprover($firstStep, $subject);

        DB::transaction(function () use ($approvable, $subject, $workflow, $group, $concrete): void {
            // A group step (role/department/position) has no single owner — it is
            // surfaced to every eligible holder via the MSS queue, so the request
            // carries no `current_approver_id`.
            $approvable->update([
                'current_approver_id' => $group
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
            ->first();

        if ($instance === null) {
            return false;
        }

        $subject = Employee::find((int) $approvable->getAttribute('employee_id'));

        return DB::transaction(function () use ($instance, $approvable, $subject, $actorUserId, $action, $note): bool {
            ApprovalLog::create([
                'tenant_id' => $instance->tenant_id,
                'approval_request_id' => $instance->id,
                'approver_id' => $actorUserId,
                'action' => $action,
                'step_order' => $instance->current_step,
                'note' => $note,
            ]);

            if ($action === 'reject') {
                $instance->update(['status' => 'rejected']);
                $approvable->update(['status' => 'rejected']);

                return true;
            }

            $steps = $instance->workflow?->steps()->orderBy('step_order')->get() ?? collect();

            // Last step reached (or the workflow lost its steps): finalize.
            if ($instance->current_step >= $steps->count()) {
                $instance->update(['status' => 'approved']);
                self::finalize($approvable, $actorUserId);

                return true;
            }

            // Advance to the next step and hand off to its approver.
            $nextStep = $steps->get($instance->current_step); // 0-indexed: current_step is 1-based
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

            return true;
        });
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

        return match ($step->approver_type) {
            'direct_manager' => $subject->manager_id !== null
                ? Employee::forTenant($tenantId)->find($subject->manager_id)
                : null,
            'specific_user' => $step->approver_user_id !== null
                ? Employee::forTenant($tenantId)->find($step->approver_user_id)
                : null,
            default => null,
        };
    }

    /**
     * Approvable ids of pending, workflow-driven requests of the given type whose
     * CURRENT step is a group (role/department/position) the manager belongs to.
     * This is what lets every holder of a role — not just one representative —
     * see and act on a group-step request in their approval queue.
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
            $step = $instance->workflow?->steps->firstWhere('step_order', $instance->current_step);

            if ($step === null) {
                continue;
            }

            $eligible = match ($step->approver_type) {
                'role' => in_array($step->approver_role_id, $roleIds, true),
                'department' => $step->approver_department_id !== null
                    && (int) $manager->department_id === (int) $step->approver_department_id,
                'position' => $step->approver_position_id !== null
                    && (int) $manager->position_id === (int) $step->approver_position_id,
                default => false,
            };

            if ($eligible) {
                $ids[] = (int) $instance->approvable_id;
            }
        }

        return $ids;
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
            default => null,
        };
    }
}
