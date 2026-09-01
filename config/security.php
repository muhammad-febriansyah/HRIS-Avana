<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Response Security Headers
    |--------------------------------------------------------------------------
    |
    | Applied to every HTML response by App\Http\Middleware\SecurityHeaders.
    | JSON responses only receive the headers that make sense for them (nosniff
    | and the referrer policy); a Content-Security-Policy on an API payload
    | protects nothing and only costs bytes.
    |
    */

    'headers' => [
        // Sent only over HTTPS, and only in production: a browser that sees
        // HSTS on http://localhost pins localhost to HTTPS for the max-age,
        // which breaks every other local project on the machine.
        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
            'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
            'include_subdomains' => (bool) env('SECURITY_HSTS_SUBDOMAINS', true),
            'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
        ],

        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),

        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

        // Geolocation stays allowed for the same origin: attendance check-in and
        // field-visit tracking read it from the browser.
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'accelerometer=(), autoplay=(), camera=(self), display-capture=(), '
            .'encrypted-media=(), fullscreen=(self), geolocation=(self), gyroscope=(), '
            .'magnetometer=(), microphone=(self), midi=(), payment=(), usb=()',
        ),

        'cross_origin_opener_policy' => env('SECURITY_COOP', 'same-origin'),

        'cross_origin_resource_policy' => env('SECURITY_CORP', 'same-site'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | `enforce` decides which header carries the policy. Report-only is the
    | default so a missed third-party host shows up in the browser console
    | instead of breaking a page in front of a client; flip
    | SECURITY_CSP_ENFORCE=true once the console is clean on a real tenant.
    |
    | `report_uri`, when set, is where the browser posts violations.
    |
    */

    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'enforce' => (bool) env('SECURITY_CSP_ENFORCE', false),
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),

        /*
         * Hosts the application legitimately reaches. Keep this list honest:
         * every entry is a place a compromised page is allowed to send data.
         *
         *  - fonts.googleapis.com / fonts.gstatic.com — the Poppins webfont
         *  - *.tile.openstreetmap.org — Leaflet map tiles
         *  - routing.openstreetmap.de — the walking-route API on tracking maps
         *  - ik.imagekit.io — marketing page imagery
         */
        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'script-src' => ["'self'"],
            // 'unsafe-inline' is unavoidable here: the root Blade view paints
            // the surface colour before first paint with an inline <style>, and
            // Leaflet writes inline styles on every marker it positions.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
            'img-src' => ["'self'", 'data:', 'blob:', 'https://*.tile.openstreetmap.org', 'https://ik.imagekit.io'],
            'connect-src' => ["'self'", 'https://routing.openstreetmap.de', 'https://*.tile.openstreetmap.org'],
            'media-src' => ["'self'", 'blob:', 'data:'],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
        ],

        /*
         * Extra sources merged in outside production, where Vite serves the
         * bundle from its own origin over HTTP and hot-reloads over a websocket.
         */
        'development_directives' => [
            'script-src' => ["'unsafe-inline'", "'unsafe-eval'", 'http://localhost:5173', 'http://127.0.0.1:5173'],
            'style-src' => ['http://localhost:5173', 'http://127.0.0.1:5173'],
            'connect-src' => [
                'http://localhost:5173', 'http://127.0.0.1:5173',
                'ws://localhost:5173', 'ws://127.0.0.1:5173',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Lockout
    |--------------------------------------------------------------------------
    |
    | Fortify throttles login by email+IP (see FortifyServiceProvider). When it
    | locks an account out, LoginSecurity records the lockout and —
    | when the address belongs to a real account — emails the owner, because a
    | lockout they did not cause is the first visible sign of someone guessing
    | their password.
    |
    */

    'lockout' => [
        'notify_account_owner' => (bool) env('SECURITY_NOTIFY_LOCKOUT', true),

        // At most one lockout email per account per window (minutes), so a
        // sustained guessing run does not turn into a mail flood.
        'notify_cooldown_minutes' => (int) env('SECURITY_LOCKOUT_COOLDOWN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | How long the security and activity trails are kept before
    | `avana:prune-security-data` deletes them. UU PDP 27/2022 expects personal
    | data to be held no longer than it is needed, and an activity log is
    | personal data: it records where a named person was and when.
    |
    | Audit rows are kept far longer than activity rows on purpose — they are
    | the record of who changed payroll, which is what an audit asks for.
    | A value of 0 disables pruning for that trail.
    |
    */

    'retention' => [
        'activity_log_days' => (int) env('SECURITY_RETENTION_ACTIVITY', 180),
        'audit_log_days' => (int) env('SECURITY_RETENTION_AUDIT', 1095),
        'login_device_days' => (int) env('SECURITY_RETENTION_DEVICES', 365),
        'notification_days' => (int) env('SECURITY_RETENTION_NOTIFICATIONS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Backup
    |--------------------------------------------------------------------------
    |
    | `avana:backup-database` writes a compressed dump here every night.
    |
    | Point BACKUP_DISK at an off-site disk in production. A dump sitting on the
    | same server as the database it copies survives a mistake, not a fire.
    |
    */

    'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'directory' => env('BACKUP_DIRECTORY', 'backups'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'alert_on_failure' => (bool) env('BACKUP_ALERT_ON_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anomaly Detection
    |--------------------------------------------------------------------------
    |
    | `avana:scan-security-anomalies` reads the activity trail once a day and
    | raises what looks wrong. Thresholds are conservative on purpose: an alert
    | that fires every day is an alert nobody reads.
    |
    */

    'anomaly' => [
        'enabled' => (bool) env('SECURITY_ANOMALY_ENABLED', true),

        // Failed sign-ins against one address within the window before it
        // counts as someone guessing rather than someone forgetting.
        'failed_login_threshold' => (int) env('SECURITY_ANOMALY_FAILED_LOGINS', 10),

        // Sign-ins outside this local-time window are reported as off-hours.
        'work_hours_start' => (int) env('SECURITY_ANOMALY_HOURS_START', 5),
        'work_hours_end' => (int) env('SECURITY_ANOMALY_HOURS_END', 22),

        // Distinct source addresses one account signed in from within the
        // window before the pair is reported as a shared or stolen session.
        'distinct_ip_threshold' => (int) env('SECURITY_ANOMALY_DISTINCT_IPS', 4),

        // Exports or downloads by one user within the window before it reads as
        // someone carrying data out rather than doing their job.
        'export_threshold' => (int) env('SECURITY_ANOMALY_EXPORTS', 15),

        'window_hours' => (int) env('SECURITY_ANOMALY_WINDOW_HOURS', 24),
    ],

];
