<?php

namespace App\Support;

use App\Models\LeaveRequest;
use App\Models\PermissionRequest;
use App\Models\WfhRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One employee, one answer per day.
 *
 * Cuti, izin and WFH all claim the same working day, so a day that is already
 * settled — or already waiting for a decision — may not be claimed twice. Two
 * approved requests over the same date leave payroll and attendance with two
 * contradictory truths for that day, and the second one is always a mistake:
 * either a double submission or a request that should have been cancelled first.
 */
final class RequestDateClash
{
    /**
     * Request type → [model, human label].
     *
     * @var array<string, array{0: class-string<Model>, 1: string}>
     */
    private const SOURCES = [
        'cuti' => [LeaveRequest::class, 'cuti'],
        'izin' => [PermissionRequest::class, 'izin'],
        'wfh' => [WfhRequest::class, 'WFH'],
    ];

    /**
     * The refusal for a request covering this date range, or null when the range
     * is free.
     *
     * `$ignore` skips one row by type and id, so editing an existing request
     * does not clash with itself.
     *
     * @param  array{0: string, 1: int}|null  $ignore
     */
    public static function check(
        int $tenantId,
        int $employeeId,
        string $start,
        string $end,
        ?array $ignore = null,
    ): ?string {
        foreach (self::SOURCES as $key => [$modelClass, $label]) {
            /** @var Builder<Model> $query */
            $query = $modelClass::query()
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start);

            if ($ignore !== null && $ignore[0] === $key) {
                $query->whereKeyNot($ignore[1]);
            }

            $clash = $query->orderBy('start_date')->first(['start_date', 'end_date', 'status']);

            if ($clash === null) {
                continue;
            }

            $range = self::range($clash);

            return $clash->getAttribute('status') === 'approved'
                ? 'Tanggal ini bentrok dengan pengajuan '.$label.' yang sudah disetujui ('.$range.'). Ajukan di tanggal lain.'
                : 'Tanggal ini bentrok dengan pengajuan '.$label.' yang masih menunggu persetujuan ('.$range.'). Tunggu keputusannya atau batalkan dulu.';
        }

        return null;
    }

    private static function range(Model $clash): string
    {
        $start = $clash->getAttribute('start_date');
        $end = $clash->getAttribute('end_date');
        $from = $start?->translatedFormat('d M Y') ?? '-';
        $to = $end?->translatedFormat('d M Y') ?? $from;

        return $from === $to ? $from : $from.' – '.$to;
    }
}
