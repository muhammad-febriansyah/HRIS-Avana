<?php

namespace App\Console\Commands;

use App\Support\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avana:notify-released-payslips')]
#[Description('Notify employees whose locked payslips have reached their release date.')]
class NotifyReleasedPayslips extends Command
{
    public function handle(): int
    {
        $notified = Notifier::payslipsReleased();

        $this->info("Notified {$notified} released payslip(s).");

        return self::SUCCESS;
    }
}
