<?php

namespace App\Http\Resources\Avana;

use App\Models\Attendance;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Attendance
 */
final class AttendanceResource extends JsonResource
{
    /**
     * Indonesian labels for the attendance status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'absent' => 'Alpa',
        'leave' => 'Cuti',
        'incomplete' => 'Belum Lengkap',
        'need_correction' => 'Perlu Koreksi',
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
            'employee' => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'name' => $this->employee->full_name,
                'employee_number' => $this->employee->employee_number,
                'initials' => $this->initials($this->employee->full_name),
                'avatar_color' => $this->avatarColor($this->employee->full_name),
            ],
            'shift' => $this->whenLoaded('shift', fn () => $this->shift === null ? null : [
                'id' => $this->shift->id,
                'name' => $this->shift->name,
                'label' => $this->shiftLabel($this->shift),
            ]),
            'date' => $this->date?->format('d M Y'),
            'date_raw' => $this->date?->format('Y-m-d'),
            'clock_in' => $this->clock_in_at?->format('H:i') ?? '—',
            'clock_out' => $this->clock_out_at?->format('H:i') ?? '—',
            'late_minutes' => (int) $this->late_minutes,
            'telat' => (int) $this->late_minutes > 0 ? (int) $this->late_minutes.' mnt' : '—',
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? $this->status,
            'location_status' => $this->location_status,
            'clock_in_coords' => $this->coords($this->clock_in_lat, $this->clock_in_lng),
            'clock_out_coords' => $this->coords($this->clock_out_lat, $this->clock_out_lng),
            'work_location' => $this->whenLoaded('workLocation', fn () => $this->workLocation === null ? null : [
                'id' => $this->workLocation->id,
                'name' => $this->workLocation->name,
                'latitude' => $this->workLocation->latitude === null ? null : (float) $this->workLocation->latitude,
                'longitude' => $this->workLocation->longitude === null ? null : (float) $this->workLocation->longitude,
                'radius_meter' => (int) ($this->workLocation->radius_meter ?? 0),
            ]),
            'distance_meter' => $this->distanceMeter(),
        ];
    }

    /**
     * Compact {lat, lng} float pair, or null when either component is unset.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function coords(mixed $lat, mixed $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    /**
     * Distance in meters between the clock-in point and the assigned work
     * location pin, rounded. Null when either coordinate is missing.
     */
    private function distanceMeter(): ?int
    {
        if (! $this->relationLoaded('workLocation') || $this->workLocation === null) {
            return null;
        }

        if ($this->clock_in_lat === null || $this->clock_in_lng === null) {
            return null;
        }

        $distance = $this->workLocation->distanceMeters((float) $this->clock_in_lat, (float) $this->clock_in_lng);

        return $distance === null ? null : (int) round($distance);
    }

    /**
     * Build a human readable shift label: "Pagi (08:00–17:00)".
     */
    private function shiftLabel(Shift $shift): string
    {
        $start = $shift->start_time !== null ? Carbon::parse($shift->start_time)->format('H:i') : null;
        $end = $shift->end_time !== null ? Carbon::parse($shift->end_time)->format('H:i') : null;

        if ($start === null || $end === null) {
            return (string) $shift->name;
        }

        return $shift->name.' ('.$start.'–'.$end.')';
    }

    /**
     * Build up to two uppercase initials from a full name.
     */
    private function initials(?string $fullName): string
    {
        $words = preg_split('/\s+/', trim((string) $fullName)) ?: [];

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
    private function avatarColor(?string $fullName): string
    {
        $index = crc32((string) $fullName) % count(self::AVATAR_PALETTE);

        return self::AVATAR_PALETTE[$index];
    }
}
