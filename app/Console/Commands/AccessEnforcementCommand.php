<?php

namespace App\Console\Commands;

use App\Support\Access;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('access:enforcement {state? : enable | disable | status (default)}')]
#[Description('Toggle or inspect the action-level permission enforcement kill-switch.')]
class AccessEnforcementCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $state = strtolower((string) ($this->argument('state') ?? 'status'));

        return match ($state) {
            'enable', 'on' => $this->apply(true),
            'disable', 'off' => $this->apply(false),
            'status' => $this->status(),
            default => $this->invalid($state),
        };
    }

    private function apply(bool $enabled): int
    {
        Access::setEnforced($enabled);

        $this->info($enabled
            ? 'Action-level permission enforcement ENABLED.'
            : 'Action-level permission enforcement DISABLED (fail-open — every action allowed).');

        return self::SUCCESS;
    }

    private function status(): int
    {
        $this->info('Action-level permission enforcement is currently '
            .(Access::enforced() ? 'ENABLED.' : 'DISABLED.'));

        return self::SUCCESS;
    }

    private function invalid(string $state): int
    {
        $this->error("Unknown state '{$state}'. Use: enable, disable, or status.");

        return self::FAILURE;
    }
}
