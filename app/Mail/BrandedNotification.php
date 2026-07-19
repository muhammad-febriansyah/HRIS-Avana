<?php

namespace App\Mail;

use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A single, reusable branded email for every AvanaHR notification. The header
 * logo and footer are resolved by scope via {@see MailBranding}: a tenant's own
 * company branding, or the platform default for super-admin/system mail.
 *
 * The body is passed in as a heading, a lead paragraph, an optional detail
 * table (label => value rows) and an optional call-to-action button, so one
 * template serves payslip, approval, reimbursement and future notifications.
 */
class BrandedNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{brand_name: string, logo_url: string, address: ?string, phone: ?string, email: ?string, primary_color: string}  $brand
     * @param  array<int, string>  $paragraphs  Body paragraphs shown under the heading.
     * @param  array<string, string>  $details  Label => value rows rendered as a summary table.
     * @param  array{label: string, url: string}|null  $action  Optional CTA button.
     */
    public function __construct(
        public array $brand,
        public string $subjectLine,
        public string $heading,
        public array $paragraphs = [],
        public array $details = [],
        public ?array $action = null,
        public ?string $greetingName = null,
    ) {}

    /**
     * Resolve the branding for a scope and build the message in one call.
     *
     * @param  array<int, string>  $paragraphs
     * @param  array<string, string>  $details
     * @param  array{label: string, url: string}|null  $action
     */
    public static function make(
        ?int $tenantId,
        string $subjectLine,
        string $heading,
        array $paragraphs = [],
        array $details = [],
        ?array $action = null,
        ?string $greetingName = null,
    ): self {
        return new self(
            brand: MailBranding::for($tenantId),
            subjectLine: $subjectLine,
            heading: $heading,
            paragraphs: $paragraphs,
            details: $details,
            action: $action,
            greetingName: $greetingName,
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
        );
    }
}
