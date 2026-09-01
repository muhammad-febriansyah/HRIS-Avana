<?php

namespace App\Services;

use App\Models\UserActivityLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads the activity trail and reports what looks wrong.
 *
 * The trail already records every sign-in, failure and export; nothing was ever
 * looking at it. This is the "internal threat detection" half of a security
 * posture: an audit log that is only read after an incident tells you what
 * happened, not that it is happening.
 *
 * Four signals, each conservative enough to stay quiet on a normal day:
 *
 *  - a run of failed sign-ins against one address (password guessing)
 *  - a sign-in outside working hours (an account being used while its owner sleeps)
 *  - one account signing in from many addresses at once (a shared or stolen session)
 *  - a burst of exports by one user (data being carried out)
 */
class SecurityAnomalyScanner
{
    /**
     * Activity events that mean data left the building.
     *
     * @var array<int, string>
     */
    private const EXPORT_EVENTS = ['data_exported', 'report_exported', 'backup_downloaded'];

    /**
     * @return array<int, array{key: string, kind: string, title: string, body: string, tenant_id: int|null}>
     */
    public function scan(): array
    {
        if (! config('security.anomaly.enabled')) {
            return [];
        }

        $since = now()->subHours(max(1, (int) config('security.anomaly.window_hours', 24)));

        return [
            ...$this->bruteForce($since),
            ...$this->offHoursLogins($since),
            ...$this->scatteredSessions($since),
            ...$this->bulkExports($since),
        ];
    }

    /**
     * Repeated failed sign-ins against one address.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bruteForce(Carbon|CarbonInterface $since): array
    {
        $threshold = (int) config('security.anomaly.failed_login_threshold', 10);

        return UserActivityLog::query()
            ->where('event', 'login_failed')
            ->where('created_at', '>=', $since)
            ->get(['tenant_id', 'properties', 'ip_address'])
            ->groupBy(fn (UserActivityLog $log): string => (string) ($log->properties['email'] ?? 'tidak diketahui'))
            ->filter(fn (Collection $rows): bool => $rows->count() >= $threshold)
            ->map(fn (Collection $rows, string $email): array => [
                'key' => 'brute_force:'.$email,
                'kind' => 'brute_force',
                'tenant_id' => $rows->first()->tenant_id,
                'title' => 'Percobaan login berulang',
                'body' => $rows->count().' percobaan login gagal untuk '.$email
                    .' dari '.$rows->pluck('ip_address')->filter()->unique()->count().' alamat IP.',
            ])
            ->values()
            ->all();
    }

    /**
     * Sign-ins outside the configured working window.
     *
     * @return array<int, array<string, mixed>>
     */
    private function offHoursLogins(Carbon|CarbonInterface $since): array
    {
        $start = (int) config('security.anomaly.work_hours_start', 5);
        $end = (int) config('security.anomaly.work_hours_end', 22);

        return UserActivityLog::query()
            ->where('event', 'login')
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->with('user:id,name')
            ->get(['id', 'tenant_id', 'user_id', 'ip_address', 'created_at'])
            ->filter(function (UserActivityLog $log) use ($start, $end): bool {
                $hour = (int) $log->created_at?->format('G');

                return $hour < $start || $hour >= $end;
            })
            ->map(fn (UserActivityLog $log): array => [
                'key' => 'off_hours:'.$log->user_id.':'.$log->created_at?->format('Y-m-d-H'),
                'kind' => 'off_hours',
                'tenant_id' => $log->tenant_id,
                'title' => 'Login di luar jam kerja',
                'body' => ($log->user?->name ?? 'Pengguna #'.$log->user_id).' masuk pada '
                    .$log->created_at?->translatedFormat('d M Y, H:i').' dari '.($log->ip_address ?? 'IP tidak diketahui').'.',
            ])
            ->values()
            ->all();
    }

    /**
     * One account signing in from an unusual number of addresses.
     *
     * Not "impossible travel" — that needs geolocation this application does not
     * have — but the same signal at a cruder resolution: an account cannot be
     * in five places at once, whatever the distance between them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scatteredSessions(Carbon|CarbonInterface $since): array
    {
        $threshold = (int) config('security.anomaly.distinct_ip_threshold', 4);

        return UserActivityLog::query()
            ->where('event', 'login')
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->whereNotNull('ip_address')
            ->with('user:id,name')
            ->get(['tenant_id', 'user_id', 'ip_address'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows): Collection => $rows)
            ->filter(fn (Collection $rows): bool => $rows->pluck('ip_address')->unique()->count() >= $threshold)
            ->map(fn (Collection $rows, int|string $userId): array => [
                'key' => 'scattered:'.$userId,
                'kind' => 'scattered_sessions',
                'tenant_id' => $rows->first()->tenant_id,
                'title' => 'Satu akun masuk dari banyak alamat',
                'body' => ($rows->first()->user?->name ?? 'Pengguna #'.$userId).' masuk dari '
                    .$rows->pluck('ip_address')->unique()->count().' alamat IP berbeda dalam periode ini.',
            ])
            ->values()
            ->all();
    }

    /**
     * A burst of exports or downloads by one user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bulkExports(Carbon|CarbonInterface $since): array
    {
        $threshold = (int) config('security.anomaly.export_threshold', 15);

        return UserActivityLog::query()
            ->whereIn('event', self::EXPORT_EVENTS)
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->with('user:id,name')
            ->get(['tenant_id', 'user_id', 'event'])
            ->groupBy('user_id')
            ->filter(fn (Collection $rows): bool => $rows->count() >= $threshold)
            ->map(fn (Collection $rows, int|string $userId): array => [
                'key' => 'bulk_export:'.$userId,
                'kind' => 'bulk_export',
                'tenant_id' => $rows->first()->tenant_id,
                'title' => 'Ekspor data dalam jumlah besar',
                'body' => ($rows->first()->user?->name ?? 'Pengguna #'.$userId).' melakukan '
                    .$rows->count().' ekspor/unduhan data dalam periode ini.',
            ])
            ->values()
            ->all();
    }
}
