<?php

namespace App\Http\Resources\Avana;

use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\PayrollRunItem;
use App\Support\ContractType;
use App\Support\PrivateFile;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Employee
 */
final class EmployeeResource extends JsonResource
{
    /**
     * Relations holding fields the employee form writes but the employees table
     * does not store.
     *
     * A page that renders one employee must load all of them. The resource drops
     * the key of a relation that was never loaded, so a page that forgets one
     * shows those boxes empty — which reads as "my entry was never saved", and
     * re-typing a contract number to mend it opens a second contract instead.
     * Named here so a page asks the resource what it needs rather than keeping
     * its own copy of the list that can fall behind.
     *
     * @var list<string>
     */
    public const OFF_ROW_RELATIONS = ['bpjsProfile', 'taxProfile', 'contracts'];

    /**
     * Indonesian labels for the employment_status enum.
     *
     * @var array<string, string>
     */
    private const EMPLOYMENT_LABELS = [
        'probation' => 'Masa Percobaan',
        'contract' => 'Kontrak',
        'permanent' => 'Tetap',
        'resigned' => 'Resign',
    ];

    /**
     * Indonesian labels for the status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
    ];

    /**
     * Indonesian labels for the asset condition enum.
     *
     * @var array<string, string>
     */
    private const ASSET_CONDITION_LABELS = [
        'good' => 'Baik',
        'fair' => 'Cukup',
        'damaged' => 'Rusak',
    ];

    /**
     * Indonesian labels for the contract status enum.
     *
     * @var array<string, string>
     */
    private const CONTRACT_STATUS_LABELS = [
        'active' => 'Aktif',
        'expired' => 'Berakhir',
        'terminated' => 'Diberhentikan',
    ];

    /**
     * Indonesian labels for the leave request status enum.
     *
     * @var array<string, string>
     */
    private const LEAVE_STATUS_LABELS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    /**
     * Deterministic avatar background palette.
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // What every link to this employee uses; the primary key stays out
            // of the URL so it cannot be counted through.
            'route_key' => $this->public_id,
            'employee_number' => $this->employee_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nik' => $this->nik,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('d M Y'),
            'birth_date_raw' => $this->birth_date?->format('Y-m-d'),
            'birth_place' => $this->birth_place,
            'religion' => $this->religion,
            'marital_status' => $this->marital_status,
            'address' => $this->address,
            'employment_status' => $this->employment_status,
            'employment_label' => self::EMPLOYMENT_LABELS[$this->employment_status] ?? $this->employment_status,
            'join_date' => $this->join_date?->format('d M Y'),
            'join_date_raw' => $this->join_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? $this->status,
            'custom_data' => $this->custom_data ?? [],
            'initials' => $this->initials(),
            'avatar_color' => $this->avatarColor(),
            'photo_url' => PrivateFile::urlFor($this->photo_path),
            'branch_id' => $this->branch_id,
            'work_location_id' => $this->work_location_id,
            'attendance_scope' => $this->attendance_scope,
            'has_login' => $this->user_id !== null,
            // The address the person actually signs in with. It is the account's,
            // not the employee row's, and linking an existing account leaves the
            // two free to differ — so the screens have to be able to say which
            // one is the login, or an admin hands out the wrong address and the
            // employee is told their password is wrong.
            'login_email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'is_top_approver' => (bool) $this->is_top_approver,
            'role_id' => $this->whenLoaded('user', fn () => $this->user?->roles->first()?->id),
            'account_active' => $this->whenLoaded('user', fn () => $this->user?->status === 'active'),
            'device' => $this->whenLoaded('user', function () {
                $device = $this->user?->activeDevice;

                if ($device === null) {
                    return null;
                }

                return [
                    'label' => trim(($device->model ?? '').($device->os_version ? ' · '.$device->os_version : ''))
                        ?: ($device->device_name ?? 'Perangkat terdaftar'),
                    'platform' => $device->platform,
                    'last_login' => $device->last_login_at?->diffForHumans(),
                ];
            }),
            'branch' => $this->whenLoaded('branch', fn () => $this->namedRelation($this->branch)),
            'department' => $this->whenLoaded('department', fn () => $this->namedRelation($this->department)),
            'position' => $this->whenLoaded('position', fn () => $this->namedRelation($this->position)),
            'job_level' => $this->whenLoaded('jobLevel', fn () => $this->namedRelation($this->jobLevel)),
            'work_location' => $this->whenLoaded('workLocation', fn () => $this->workLocation === null ? null : [
                'id' => $this->workLocation->id,
                'name' => $this->workLocation->name,
                'radius_meter' => (int) ($this->workLocation->radius_meter ?? 0),
                'status' => $this->workLocation->status,
            ]),
            'salary_master_id' => $this->salary_master_id,
            'salary_master' => $this->whenLoaded('salaryMaster', fn () => $this->salaryMaster === null ? null : [
                'id' => $this->salaryMaster->id,
                'code' => $this->salaryMaster->code,
                'category' => $this->salaryMaster->category,
            ]),
            // The BPJS membership numbers live on the profile, not the employee
            // row, and are printed on the payslip and every filing.
            'bpjs_kesehatan_number' => $this->whenLoaded('bpjsProfile', fn () => $this->bpjsProfile?->bpjs_kesehatan_number),
            'bpjs_ketenagakerjaan_number' => $this->whenLoaded('bpjsProfile', fn () => $this->bpjsProfile?->bpjs_ketenagakerjaan_number),
            // Every PPh 21 figure is worked out from this, so it belongs with
            // the employee rather than only on a tax screen.
            'ptkp_status' => $this->whenLoaded('taxProfile', fn () => $this->taxProfile?->ptkp_status),
            'contracts' => $this->whenLoaded('contracts', fn () => $this->contracts
                ->map(fn (EmployeeContract $contract): array => $this->contractRow($contract))
                ->values()),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager === null ? null : [
                'id' => $this->manager->id,
                'name' => $this->manager->full_name,
                'employee_number' => $this->manager->employee_number,
            ]),
            'held_assets' => $this->whenLoaded('assetAssignments', fn () => $this->assetAssignments
                ->map(fn (AssetAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'assigned_date' => $assignment->assigned_date?->format('d M Y'),
                    'notes' => $assignment->condition_note,
                    'asset' => $assignment->asset === null ? null : [
                        'id' => $assignment->asset->id,
                        'code' => $assignment->asset->code,
                        'name' => $assignment->asset->name,
                        'category' => $assignment->asset->category,
                        'condition_label' => self::ASSET_CONDITION_LABELS[$assignment->asset->condition] ?? $assignment->asset->condition,
                    ],
                ])
                ->values()),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents
                ->map(fn (EmployeeDocument $document): array => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'type' => $document->type,
                    // The stored path keeps the real extension; the display
                    // name often does not ("KTP"), so the icon reads the path.
                    'extension' => $document->file_path !== null
                        ? mb_strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION))
                        : null,
                    'size_label' => self::fileSize($document->file_size),
                    'uploaded_at' => $document->uploaded_at?->format('d M Y'),
                    'download_url' => $document->file_path !== null
                        ? PrivateFile::urlFor($document->file_path)
                        : null,
                ])
                ->values()),
            'leave_history' => $this->whenLoaded('leaveRequests', fn () => $this->leaveRequests
                ->map(fn (LeaveRequest $leave): array => [
                    'id' => $leave->id,
                    'type' => $leave->leaveType?->name ?? 'Cuti',
                    'date_label' => self::dateRange($leave->start_date, $leave->end_date),
                    'duration_label' => self::daysLabel($leave->total_days),
                    'status' => $leave->status,
                    'status_label' => self::LEAVE_STATUS_LABELS[$leave->status] ?? $leave->status,
                ])
                ->values()),
            'payroll_history' => $this->whenLoaded('payrollItems', fn () => $this->payrollItems
                ->map(fn (PayrollRunItem $item): array => [
                    'id' => $item->id,
                    'period' => $item->period?->name ?? '—',
                    'net_salary_label' => 'Rp '.number_format((float) $item->net_salary, 0, ',', '.'),
                    'status' => $item->run?->status ?? $item->status,
                    'status_label' => PayrollPeriodResource::statusLabel($item->run?->status ?? $item->status),
                ])
                ->values()),
        ];
    }

    /**
     * Render a stored byte count as a human-readable size label.
     */
    private static function fileSize(?int $bytes): ?string
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
     * Collapse a start/end date pair into one label, dropping the repeated
     * month and year when the range stays inside a single month.
     */
    private static function dateRange(?CarbonInterface $start, ?CarbonInterface $end): string
    {
        if ($start === null) {
            return '—';
        }

        if ($end === null || $start->isSameDay($end)) {
            return $start->format('d M Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('d').'–'.$end->format('d M Y');
        }

        return $start->format('d M Y').' – '.$end->format('d M Y');
    }

    /**
     * Render a decimal day count without a trailing `.00`.
     */
    private static function daysLabel(int|float|string|null $days): string
    {
        $value = (float) $days;

        return ($value === floor($value)
            ? number_format($value, 0, ',', '.')
            : rtrim(number_format($value, 2, ',', '.'), '0')).' hari';
    }

    /**
     * One row of the employee's contract history, shaped for the Kontrak tab on
     * the employee page: the same facts the Kontrak list shows, so HR does not
     * have to leave the employee to see whether a contract is signed, filed, or
     * about to run out.
     *
     * @return array<string, mixed>
     */
    private function contractRow(EmployeeContract $contract): array
    {
        $daysToExpiry = $contract->end_date
            ? (int) round(Carbon::today()->diffInDays($contract->end_date, false))
            : null;

        return [
            'id' => $contract->id,
            'route_key' => $contract->public_id,
            'contract_number' => $contract->contract_number,
            'contract_type' => $contract->contract_type,
            'contract_type_label' => ContractType::label($contract->contract_type),
            'start_date' => $contract->start_date?->format('d M Y'),
            'end_date' => $contract->end_date?->format('d M Y'),
            'start_date_raw' => $contract->start_date?->format('Y-m-d'),
            'end_date_raw' => $contract->end_date?->format('Y-m-d'),
            'status' => $contract->status,
            'status_label' => self::CONTRACT_STATUS_LABELS[$contract->status] ?? $contract->status,
            'days_to_expiry' => $daysToExpiry,
            'expiring_soon' => $contract->status === 'active'
                && $daysToExpiry !== null
                && $daysToExpiry >= 0
                && $daysToExpiry <= 30,
            'document' => $contract->document_path === null ? null : [
                'name' => $contract->document_name ?? 'kontrak.pdf',
                'href' => route('avana.kontrak.dokumen', $contract),
            ],
        ];
    }

    /**
     * Build a compact {id, name} shape for a loaded relation, or null.
     *
     * @return array{id: int, name: string|null}|null
     */
    private function namedRelation(mixed $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return ['id' => $model->id, 'name' => $model->name];
    }

    /**
     * Build up to two uppercase initials from the full name.
     */
    private function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->full_name)) ?: [];

        $initials = collect($words)
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Pick a deterministic avatar color derived from the employee name.
     */
    private function avatarColor(): string
    {
        $index = crc32((string) $this->full_name) % count(self::AVATAR_PALETTE);

        return self::AVATAR_PALETTE[$index];
    }
}
