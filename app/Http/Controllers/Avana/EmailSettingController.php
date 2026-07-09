<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email (SMTP) settings screen. A super admin edits the platform-wide default
 * (null scope); a tenant HR admin edits their own tenant's override. The
 * outbound SMTP password is never returned to the browser in cleartext. The
 * history tab lists recent send attempts recorded in {@see EmailLog}.
 */
class EmailSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the connection form and recent send history for the caller's scope.
     */
    public function edit(Request $request): Response
    {
        $this->authorize('view', EmailSetting::class);

        $tenantId = $this->scopeTenantId($request->user());
        $settings = EmailSetting::forScope($tenantId);

        $logs = EmailLog::forScope($tenantId)
            ->latest('id')
            ->limit(50)
            ->get(['to_email', 'subject', 'status', 'error', 'sent_at', 'created_at'])
            ->map(fn (EmailLog $log): array => [
                'to' => $log->to_email,
                'subject' => $log->subject,
                'status' => $log->status,
                'error' => $log->error,
                'date' => ($log->sent_at ?? $log->created_at)?->toDateTimeString(),
            ]);

        return Inertia::render('avana/email-settings/index', [
            'scope' => $tenantId === null ? 'platform' : 'tenant',
            'settings' => [
                'from_name' => $settings->from_name,
                'from_email' => $settings->from_email,
                'host' => $settings->host,
                'port' => $settings->port,
                'encryption' => $settings->encryption ?? 'tls',
                'username' => $settings->username,
                'is_enabled' => (bool) $settings->is_enabled,
                'has_password' => $settings->password !== null && $settings->password !== '',
                'password_preview' => $settings->passwordPreview(),
                'is_ready' => $settings->isReady(),
            ],
            'encryptions' => EmailSetting::ENCRYPTIONS,
            'logs' => $logs,
        ]);
    }

    /**
     * Persist the SMTP settings. A blank password keeps the stored one.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', EmailSetting::class);

        $validated = $request->validate([
            'from_name' => ['nullable', 'string', 'max:120'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', Rule::in(array_keys(EmailSetting::ENCRYPTIONS))],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['boolean'],
        ]);

        $settings = EmailSetting::forScope($this->scopeTenantId($request->user()));

        $settings->fill([
            'from_name' => $validated['from_name'] ?? null,
            'from_email' => $validated['from_email'] ?? null,
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? null,
            'encryption' => $validated['encryption'] ?? 'tls',
            'username' => $validated['username'] ?? null,
            'is_enabled' => (bool) ($validated['is_enabled'] ?? false),
        ]);

        if (! empty($validated['password'])) {
            $settings->password = $validated['password'];
        }

        $settings->save();

        return back()->with('success', 'Pengaturan email berhasil disimpan');
    }

    /**
     * Send a test email to the caller using the saved SMTP config, recording the
     * outcome in the history log.
     */
    public function test(Request $request): RedirectResponse
    {
        $this->authorize('update', EmailSetting::class);

        $user = $request->user();
        $tenantId = $this->scopeTenantId($user);
        $settings = EmailSetting::forScope($tenantId);

        if (! $settings->isReady()) {
            return back()->withErrors(['email' => 'Lengkapi & aktifkan konfigurasi SMTP sebelum menguji.']);
        }

        $recipient = (string) $user->email;
        $subject = 'Tes Koneksi Email — AvanaHR';

        try {
            config(['mail.mailers.avana_runtime' => $settings->mailerConfig()]);

            Mail::mailer('avana_runtime')->raw(
                'Ini adalah email percobaan dari AvanaHR untuk memastikan konfigurasi SMTP Anda berfungsi.',
                function ($message) use ($recipient, $subject, $settings): void {
                    $message->to($recipient)->subject($subject);
                    if ($settings->from_email) {
                        $message->from($settings->from_email, $settings->from_name ?? 'AvanaHR');
                    }
                },
            );

            $this->log($tenantId, $recipient, $subject, 'sent', null);

            return back()->with('success', 'Email percobaan terkirim ke '.$recipient);
        } catch (\Throwable $e) {
            $this->log($tenantId, $recipient, $subject, 'failed', $e->getMessage());

            return back()->withErrors(['email' => 'Gagal mengirim: '.$e->getMessage()]);
        }
    }

    /**
     * Record a send attempt in the history log.
     */
    private function log(?int $tenantId, string $to, string $subject, string $status, ?string $error): void
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

    /**
     * The settings scope for a user: null (platform) for a super admin, else
     * their own tenant id.
     */
    private function scopeTenantId(User $user): ?int
    {
        $user->loadMissing('roles');

        if ($user->roles->contains('code', 'super_admin')) {
            return null;
        }

        return (int) $user->tenant_id;
    }
}
