<?php

namespace App\Support;

use App\Mail\BrandedNotification;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends branded notification emails through the SMTP config that applies to a
 * scope. Transport is resolved with a fallback (the tenant's own SMTP if
 * configured, otherwise the platform default); if neither scope has a usable
 * SMTP config the send is skipped silently so a missing setup never breaks the
 * request that triggered it. Every attempt is recorded in {@see EmailLog}.
 */
final class Mailer
{
    /**
     * Deliver a branded notification to a recipient in the given scope. Returns
     * true only when the message was handed to the transport without error.
     * Never throws: transport failures are logged and swallowed.
     *
     * @param  int|null  $tenantId  Branding + history scope (null = platform).
     */
    public static function sendBranded(?int $tenantId, ?string $toEmail, ?string $toName, BrandedNotification $mail): bool
    {
        if (blank($toEmail)) {
            return false;
        }

        $smtp = self::resolveSmtp($tenantId);

        if ($smtp === null) {
            return false;
        }

        config(['mail.mailers.avana_runtime' => $smtp->mailerConfig()]);

        try {
            $message = Mail::mailer('avana_runtime')->to($toEmail, $toName);

            if (filled($smtp->from_email)) {
                $mail->from($smtp->from_email, $smtp->from_name ?: 'AvanaHR');
            }

            $message->send($mail);

            self::log($tenantId, $toEmail, $mail->subjectLine, 'sent', null);

            return true;
        } catch (\Throwable $e) {
            self::log($tenantId, $toEmail, $mail->subjectLine, 'failed', $e->getMessage());
            Log::warning('Branded email failed', ['to' => $toEmail, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * The first usable SMTP config for a scope: the scope's own, else the
     * platform default, else null when nothing is configured.
     */
    private static function resolveSmtp(?int $tenantId): ?EmailSetting
    {
        if ($tenantId !== null) {
            $tenantSmtp = EmailSetting::forScope($tenantId);

            if ($tenantSmtp->isReady()) {
                return $tenantSmtp;
            }
        }

        $platformSmtp = EmailSetting::forScope(null);

        return $platformSmtp->isReady() ? $platformSmtp : null;
    }

    /**
     * Record a send attempt in the history log under the branding scope.
     */
    private static function log(?int $tenantId, string $to, string $subject, string $status, ?string $error): void
    {
        EmailLog::create([
            'tenant_id' => $tenantId,
            'to_email' => $to,
            'subject' => $subject,
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }
}
