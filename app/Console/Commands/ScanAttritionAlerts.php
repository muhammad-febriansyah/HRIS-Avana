<?php

namespace App\Console\Commands;

use App\Models\AttritionSetting;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttritionScorer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Signature('avana:scan-attrition-alerts')]
#[Description('Scan resign-risk scores and notify HR of high-risk employees per each tenant\'s attrition alert config.')]
class ScanAttritionAlerts extends Command
{
    public function __construct(private AttritionScorer $scorer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $settings = AttritionSetting::resolve($tenantId);

            if (! $settings->alerts_enabled || $settings->scan_frequency === 'off') {
                continue;
            }

            // A weekly scan only fires on Mondays.
            if ($settings->scan_frequency === 'weekly' && ! $today->isMonday()) {
                continue;
            }

            $scored = Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'employee' => $employee,
                    'result' => $this->scorer->score($employee, $settings),
                ]);

            $highRisk = $scored->filter(
                fn (array $row): bool => $row['result']['score'] >= $settings->alert_threshold
            );

            $sent += $this->notifyHighRisk($tenantId, $settings, $highRisk->pluck('employee'), $today);

            if ($settings->weekly_summary && $today->isMonday()) {
                $sent += $this->notifyWeeklySummary($tenantId, $settings, $scored, $today);
            }
        }

        $this->info("Attrition alert notifications sent: {$sent}");

        return self::SUCCESS;
    }

    /**
     * Notify the routed role about the tenant's high-risk employees.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function notifyHighRisk(int $tenantId, AttritionSetting $settings, $employees, Carbon $today): int
    {
        if ($employees->isEmpty()) {
            return 0;
        }

        $roleCode = $settings->notify_roles['high'] ?? 'admin_tenant_hr';

        return $this->dispatch(
            $tenantId,
            $roleCode,
            'attrition_high_risk',
            $today,
            'Karyawan berisiko resign tinggi',
            $employees->count().' karyawan berada di risiko resign tinggi (skor ≥ '.$settings->alert_threshold.'). Tinjau di Prediksi Resign.',
            [
                'count' => $employees->count(),
                'employee_ids' => $employees->pluck('id')->take(10)->values()->all(),
                'link' => ['type' => 'attrition'],
            ],
        );
    }

    /**
     * Weekly risk-mix summary to the routed role.
     *
     * @param  Collection<int, array{employee: Employee, result: array<string, mixed>}>  $scored
     */
    private function notifyWeeklySummary(int $tenantId, AttritionSetting $settings, $scored, Carbon $today): int
    {
        $high = $scored->filter(fn (array $r): bool => $r['result']['category'] === 'high')->count();
        $medium = $scored->filter(fn (array $r): bool => $r['result']['category'] === 'medium')->count();
        $low = $scored->filter(fn (array $r): bool => $r['result']['category'] === 'low')->count();

        $roleCode = $settings->notify_roles['high'] ?? 'admin_tenant_hr';

        return $this->dispatch(
            $tenantId,
            $roleCode,
            'attrition_weekly_summary',
            $today,
            'Ringkasan risiko resign mingguan',
            "Risiko tinggi: {$high} · sedang: {$medium} · rendah: {$low}.",
            ['high' => $high, 'medium' => $medium, 'low' => $low, 'link' => ['type' => 'attrition']],
        );
    }

    /**
     * Insert one notification per recipient of the role, deduped for the day.
     *
     * @param  array<string, mixed>  $data
     */
    private function dispatch(int $tenantId, ?string $roleCode, string $type, Carbon $today, string $title, string $body, array $data): int
    {
        if ($roleCode === null || $roleCode === '') {
            return 0;
        }

        $recipients = User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($query) => $query->where('code', $roleCode))
            ->pluck('id');

        $sent = 0;
        foreach ($recipients as $userId) {
            $already = Notification::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('type', $type)
                ->whereDate('created_at', $today)
                ->exists();

            if ($already) {
                continue;
            }

            Notification::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
            $sent++;
        }

        return $sent;
    }
}
