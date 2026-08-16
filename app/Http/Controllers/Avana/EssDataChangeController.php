<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Services\ApprovalEngine;
use App\Support\DataChangeFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Perubahan Data Pribadi Saya" — the employee proposes a correction to their
 * own record instead of changing it unannounced, and it takes effect only once
 * the approval it is routed to says so.
 */
class EssDataChangeController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    /**
     * The employee's own change requests, newest first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $requests = DataChangeRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->get();

        return Inertia::render('avana/saya/perubahan-data', [
            'requests' => $requests->map(fn (DataChangeRequest $row): array => $this->shape($row))->all(),
            'summary' => [
                'total' => $requests->count(),
                'pending' => $requests->where('status', 'pending')->count(),
                'approved' => $requests->where('status', 'approved')->count(),
                'rejected' => $requests->where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * The submission form, with the employee's current values to change from.
     */
    public function create(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        return Inertia::render('avana/saya/perubahan-data-ajukan', [
            'fields' => DataChangeFields::catalogue($employee),
        ]);
    }

    /**
     * File a change request for the signed-in employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.field' => ['required', 'string', Rule::in(DataChangeFields::keys())],
            'changes.*.value' => ['present', 'nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'changes.required' => 'Pilih minimal satu data yang ingin diubah.',
            'changes.min' => 'Pilih minimal satu data yang ingin diubah.',
            'changes.*.field.in' => 'Data itu tidak bisa diajukan perubahannya.',
        ]);

        // Each proposed value is checked against its own field's rules, so a
        // 16-digit NIK stays a 16-digit NIK and an email stays an email.
        foreach ($data['changes'] as $index => $change) {
            validator(
                [$change['field'] => $change['value']],
                [$change['field'] => DataChangeFields::rulesFor($change['field'], $employee)],
                [],
                [$change['field'] => DataChangeFields::label($change['field'])],
            )->validateWithBag('default');

            unset($index);
        }

        $changes = [];

        foreach ($data['changes'] as $change) {
            $field = $change['field'];
            $new = $change['value'];
            $old = DataChangeFields::currentValue($employee, $field);

            // Nothing to decide when the "new" value is what is already stored.
            if ((string) $old === (string) $new) {
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $new];
        }

        if ($changes === []) {
            return back()->withErrors(['changes' => 'Tidak ada nilai yang berbeda dari data saat ini.']);
        }

        $changeRequest = DataChangeRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'changes' => $changes,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'current_approver_id' => $employee->manager_id,
        ]);

        // A tenant that configured a "Perubahan Data Pribadi" workflow gets its
        // steps; otherwise the request stays with the employee's own manager.
        ApprovalEngine::start($changeRequest, $employee);

        return redirect()
            ->route('avana.saya.perubahan-data')
            ->with('success', 'Pengajuan perubahan data terkirim');
    }

    /**
     * Shape one request for the list.
     *
     * @return array<string, mixed>
     */
    private function shape(DataChangeRequest $row): array
    {
        $changes = collect((array) $row->changes)
            ->map(fn (mixed $change, string $field): array => [
                'field' => $field,
                'label' => DataChangeFields::label($field),
                'group' => DataChangeFields::group($field),
                'old' => is_array($change) ? ($change['old'] ?? null) : null,
                'new' => is_array($change) ? ($change['new'] ?? null) : null,
            ])
            ->values()
            ->all();

        return [
            'id' => $row->id,
            'changes' => $changes,
            'reason' => $row->reason,
            'status' => $row->status,
            'status_label' => self::STATUS_LABELS[$row->status] ?? $row->status,
            'rejection_reason' => $row->rejection_reason,
            'requested_at' => $row->created_at?->format('d M Y H:i'),
            'decided_at' => $row->decided_at?->format('d M Y H:i'),
            'approver' => $row->approver_id === null
                ? null
                : Employee::query()->where('user_id', $row->approver_id)->value('full_name'),
        ];
    }
}
