<?php

namespace App\Http\Resources\Avana;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
final class EmployeeResource extends JsonResource
{
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
            'employee_number' => $this->employee_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nik' => $this->nik,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
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
            'initials' => $this->initials(),
            'avatar_color' => $this->avatarColor(),
            'branch' => $this->whenLoaded('branch', fn () => $this->namedRelation($this->branch)),
            'department' => $this->whenLoaded('department', fn () => $this->namedRelation($this->department)),
            'position' => $this->whenLoaded('position', fn () => $this->namedRelation($this->position)),
            'job_level' => $this->whenLoaded('jobLevel', fn () => $this->namedRelation($this->jobLevel)),
            'work_location' => $this->whenLoaded('workLocation', fn () => $this->namedRelation($this->workLocation)),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager === null ? null : [
                'id' => $this->manager->id,
                'name' => $this->manager->full_name,
                'employee_number' => $this->manager->employee_number,
            ]),
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
