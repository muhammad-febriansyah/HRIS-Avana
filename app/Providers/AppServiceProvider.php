<?php

namespace App\Providers;

use App\Concerns\Auditable;
use App\Http\Middleware\LogPageActivity;
use App\Models\Announcement;
use App\Models\AttendanceCorrection;
use App\Models\AuditLog;
use App\Models\DataChangeRequest;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PermissionRequest;
use App\Models\PositionPayrollComponent;
use App\Models\Reimbursement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Models\WfhRequest;
use App\Observers\AnnouncementObserver;
use App\Observers\EmployeeObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PayrollRunObserver;
use App\Observers\RequestDecisionObserver;
use App\Observers\SubscriptionObserver;
use App\Observers\TenantObserver;
use App\Policies\PayrollPolicy;
use App\Services\ActivityLogger;
use App\Services\LoginSecurity;
use App\Support\GeneratedImageBag;
use App\Support\SubscriptionStatusCache;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One bag per request: the AI tool that draws writes into it and the
        // chat controller reads it back after the stream, so it must never be
        // shared between two users' requests.
        $this->app->scoped(GeneratedImageBag::class);

        // The subscription lock gate reads a tenant's term on every request, and
        // the Inertia banner reads it again; memoise it for the request only —
        // never across requests, or a renewal would not unlock anything.
        $this->app->scoped(SubscriptionStatusCache::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertEnvironmentIsLoaded();
        $this->configureDefaults();
        $this->registerPolicies();
        $this->registerNotificationObservers();
        $this->registerActivityLogging();
        $this->shareBranding();
        $this->renderErrorsWithInertia();
    }

    /**
     * Send HTTP failures to the branded `error` page instead of Laravel's bare
     * default, keeping the app's own look (and the tenant's branding, via the
     * shared props the Inertia middleware resolves).
     *
     * A 500 is left to Laravel while developing so the stack trace stays
     * reachable; every other status is branded in every environment. Statuses not
     * listed fall through to the Blade views in `resources/views/errors`.
     */
    protected function renderErrorsWithInertia(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            // The mobile client parses `message` out of the JSON body. Branding
            // its 403 as an HTML page leaves it with nothing to show but a
            // generic "gagal memuat", so the API keeps Laravel's JSON errors.
            $request = request();

            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $branded = [403, 404, 419, 429, 503];

            if (! app()->environment(['local', 'testing'])) {
                $branded[] = 500;
            }

            if (! in_array($response->statusCode(), $branded, true)) {
                return null;
            }

            return $response
                ->render('error', [
                    'status' => $response->statusCode(),
                    'detail' => $this->abortDetail($response),
                ])
                ->withSharedData();
        });
    }

    /**
     * The reason the application itself gave when it aborted, if any.
     *
     * Without it every 403 reads "your role lacks permission", which is often
     * simply untrue — a separation-of-duties refusal, say, has nothing to do
     * with the user's role, and the generic wording sends them to their admin
     * to fix something that is not broken.
     *
     * Only 403 is forwarded: it is the status this application aborts with a
     * written reason. Other statuses carry framework-generated text (a 404 from
     * route-model binding names the model class), which is noise at best and a
     * disclosure at worst.
     */
    protected function abortDetail(ExceptionResponse $response): ?string
    {
        if ($response->statusCode() !== 403) {
            return null;
        }

        $message = trim($response->exception->getMessage());

        return $message === '' ? null : $message;
    }

    /**
     * Wire the in-app notification glue: a decision on any approvable request
     * notifies its filer, a published announcement notifies the tenant, a
     * locked payroll run notifies each paid employee, and platform billing
     * events (invoice paid, subscription past_due) notify super admins.
     */
    protected function registerNotificationObservers(): void
    {
        foreach ([
            LeaveRequest::class,
            OvertimeRequest::class,
            PermissionRequest::class,
            WfhRequest::class,
            AttendanceCorrection::class,
            Reimbursement::class,
            DutyTravel::class,
            DataChangeRequest::class,
        ] as $requestModel) {
            $requestModel::observe(RequestDecisionObserver::class);
        }

        Announcement::observe(AnnouncementObserver::class);
        PayrollRun::observe(PayrollRunObserver::class);
        Employee::observe(EmployeeObserver::class);

        // Platform (super admin) billing + tenant-lifecycle alerts.
        Invoice::observe(InvoiceObserver::class);
        Subscription::observe(SubscriptionObserver::class);
        Tenant::observe(TenantObserver::class);
    }

    /**
     * Feed the "Aktivitas" tab of the audit trail: authentication events
     * straight from Fortify's Login/Logout/Failed events, and data changes
     * mirrored from whatever {@see Auditable} just wrote (page
     * visits are logged separately, by {@see LogPageActivity}).
     */
    protected function registerActivityLogging(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            // The JWT guard fires this same event on every mobile sign-in
            // (guard: 'api'), and AuthController already records that sign-in
            // itself — with the phone's own device id, not the empty user
            // agent a bare JWT request carries. Handling it again here would
            // create a second, web-shaped device row on the very first mobile
            // login ever and immediately alert the owner about it.
            if ($event->guard !== 'web') {
                return;
            }

            /** @var User $user */
            $user = $event->user;

            ActivityLogger::log('login', "{$user->name} masuk ke sistem", user: $user);

            // Remember the browser, and warn the owner when it is one this
            // account has never signed in from.
            LoginSecurity::recordLogin($user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            /** @var User|null $user */
            $user = $event->user;

            if ($user === null) {
                return;
            }

            ActivityLogger::log('logout', "{$user->name} keluar dari sistem", user: $user);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->credentials['email'] ?? null;

            ActivityLogger::log(
                'login_failed',
                'Percobaan login gagal'.($email ? " untuk {$email}" : ''),
                // Kept in properties, not just the sentence, so the anomaly
                // scan can group failures by the account being guessed at.
                properties: array_filter(['email' => is_string($email) ? $email : null]),
                user: null,
            );
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            $email = $event->request->input('email');

            LoginSecurity::recordLockout(is_string($email) ? $email : null, $event->request);
        });

        AuditLog::created(function (AuditLog $log): void {
            $labels = ['created' => 'membuat', 'updated' => 'mengubah', 'deleted' => 'menghapus'];
            $verb = $labels[$log->action] ?? $log->action;
            $entity = class_basename((string) $log->auditable_type);

            ActivityLogger::log(
                'data_'.$log->action,
                "{$verb} data {$entity} #{$log->auditable_id}",
                properties: ['auditable_type' => $log->auditable_type, 'auditable_id' => $log->auditable_id],
                user: $log->user,
            );
        });
    }

    /**
     * Expose the website settings (branding + SEO meta) to the Inertia root
     * view so the document head is rendered from the database.
     */
    protected function shareBranding(): void
    {
        View::composer('app', static function ($view): void {
            $view->with('website', WebsiteSetting::cached());
        });
    }

    /**
     * Map payroll models without a same-named policy to the shared PayrollPolicy.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(PayrollPeriod::class, PayrollPolicy::class);
        Gate::policy(PositionPayrollComponent::class, PayrollPolicy::class);

        // Action-level RBAC: Gate::allows('access', 'employee.update'). Mirrors
        // the frontend usePermission() helper so a button hidden in the UI is
        // also refused on the server.
        Gate::define('access', fn (User $user, string $code): bool => $user->hasPermissionTo($code));
    }

    /**
     * Fail immediately, and legibly, when the environment never loaded.
     *
     * An unreadable `.env` — wrong owner, wrong mode, missing file — makes every
     * `env()` call return null. Without this check the app limps on with
     * whatever defaults each config file happens to carry and dies much later
     * on an unrelated-looking error; the incident this guards against surfaced
     * as "database.sqlite does not exist" on an application that has only ever
     * used MySQL, because the web user could not read `.env`.
     *
     * The database connection is the canary: it has no default any more (see
     * `config/database.php`), and nothing in this application can work without
     * it.
     *
     * @throws RuntimeException
     */
    protected function assertEnvironmentIsLoaded(): void
    {
        if (! empty(config('database.default'))) {
            return;
        }

        throw new RuntimeException(
            'DB_CONNECTION is not set. The environment file was most likely not loaded — '
            .'check that '.base_path('.env').' exists and is readable by the user running PHP '
            .'(`sudo -u www-data cat .env`), then run `php artisan config:clear`.'
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
