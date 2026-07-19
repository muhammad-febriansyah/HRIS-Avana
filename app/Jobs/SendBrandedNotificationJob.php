<?php

namespace App\Jobs;

use App\Mail\BrandedNotification;
use App\Support\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delivers a branded notification email off the request cycle. The SMTP config
 * is resolved inside {@see Mailer::sendBranded()} at send time, so it works
 * correctly on a queue worker where the triggering request's runtime config no
 * longer exists.
 */
class SendBrandedNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ?int $tenantId,
        private ?string $toEmail,
        private ?string $toName,
        private BrandedNotification $mail,
    ) {}

    public function handle(): void
    {
        Mailer::sendBranded($this->tenantId, $this->toEmail, $this->toName, $this->mail);
    }
}
