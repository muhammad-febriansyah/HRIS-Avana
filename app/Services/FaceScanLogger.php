<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FaceScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Writes face-scan diagnostics.
 *
 * Every write is best-effort: a diagnostic trail must never be the reason a
 * clock-in or an enrollment fails, so a broken insert is swallowed and reported
 * to the application log instead of surfacing to the employee.
 */
class FaceScanLogger
{
    /**
     * Diagnostic keys accepted from the device. Anything else in the payload is
     * dropped, so a future app version can't grow the row without a review here.
     *
     * @var array<int, string>
     */
    private const METRIC_KEYS = [
        'faces', 'detector', 'yaw', 'roll', 'pitch', 'left_eye_open',
        'right_eye_open', 'smiling', 'face_width_ratio', 'center_x', 'center_y',
        'frame_width', 'frame_height', 'embedding_dimensions', 'score',
        'threshold', 'elapsed_ms', 'fail_streak', 'error', 'face_count',
        'quality_passed', 'quality_reasons', 'model_version',
        'individual_similarities', 'detail',
    ];

    /**
     * Record one scan event.
     *
     * @param  array{context: string, outcome: string, reason: string, step?: int|null, message?: string|null, metrics?: array<string, mixed>|null, device?: array<string, mixed>|null}  $event
     */
    public function record(Employee $employee, array $event, ?Request $request = null): void
    {
        try {
            $device = $event['device'] ?? [];

            FaceScanLog::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'user_id' => $request?->user()?->id,
                'context' => $event['context'],
                'step' => $event['step'] ?? null,
                'outcome' => $event['outcome'],
                'reason' => $event['reason'],
                'message' => $this->trim($event['message'] ?? null, 255),
                'metrics' => $this->metrics($event['metrics'] ?? null),
                'platform' => $this->trim($device['platform'] ?? null, 20),
                'os_version' => $this->trim($device['os_version'] ?? null, 60),
                'device_model' => $this->trim($device['model'] ?? null, 120),
                'app_version' => $this->trim($device['app_version'] ?? null, 30),
                'device_id' => $this->trim($device['device_id'] ?? null, 191),
                'ip_address' => $request?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Face scan log write failed', [
                'employee_id' => $employee->id,
                'reason' => $event['reason'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Keep only known diagnostic keys, with numbers rounded to a sane precision.
     *
     * @param  array<string, mixed>|null  $metrics
     * @return array<string, mixed>|null
     */
    private function metrics(?array $metrics): ?array
    {
        if ($metrics === null || $metrics === []) {
            return null;
        }

        $clean = [];

        foreach (self::METRIC_KEYS as $key) {
            if (! array_key_exists($key, $metrics) || $metrics[$key] === null) {
                continue;
            }

            $value = $metrics[$key];
            $clean[$key] = is_numeric($value) && ! is_int($value)
                ? round((float) $value, 4)
                : (is_string($value) ? $this->trim($value, 255) : $value);
        }

        return $clean === [] ? null : $clean;
    }

    private function trim(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
