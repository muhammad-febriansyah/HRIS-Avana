<?php

namespace App\Console\Commands;

use App\Models\EmployeeContract;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:remind-expiring-contracts {--days=30}')]
#[Description('Notify HR of employee contracts expiring within the given window.')]
class RemindExpiringContracts extends Command
{
    /**
     * Roles that receive contract-expiry notifications.
     *
     * @var array<int, string>
     */
    private const RECIPIENT_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Create one notification per HR recipient for each contract nearing its
     * end date, skipping contracts already flagged to that recipient.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $today = Carbon::today();
        $limit = $today->copy()->addDays($days);

        $contracts = EmployeeContract::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $limit->toDateString()])
            ->with('employee:id,full_name,tenant_id')
            ->get();

        $created = 0;

        foreach ($contracts as $contract) {
            $recipients = User::where('tenant_id', $contract->tenant_id)
                ->whereHas('roles', fn ($query) => $query->whereIn('code', self::RECIPIENT_ROLES))
                ->pluck('id');

            foreach ($recipients as $userId) {
                $alreadyNotified = Notification::where('tenant_id', $contract->tenant_id)
                    ->where('user_id', $userId)
                    ->where('type', 'contract_expiring')
                    ->where('data->contract_id', $contract->id)
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                Notification::create([
                    'tenant_id' => $contract->tenant_id,
                    'user_id' => $userId,
                    'type' => 'contract_expiring',
                    'title' => 'Kontrak akan berakhir',
                    'body' => 'Kontrak '.($contract->employee?->full_name ?? 'karyawan').
                        ' berakhir pada '.$contract->end_date->format('d M Y').'.',
                    'data' => [
                        'contract_id' => $contract->id,
                        'employee_id' => $contract->employee_id,
                        'end_date' => $contract->end_date->toDateString(),
                    ],
                ]);

                $created++;
            }
        }

        $this->info("Created {$created} contract-expiry notification(s).");

        return self::SUCCESS;
    }
}
