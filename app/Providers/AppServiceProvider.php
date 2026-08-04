<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AttendanceCorrection;
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
use App\Support\GeneratedImageBag;
use App\Support\SubscriptionStatusCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

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
        $this->configureDefaults();
        $this->registerPolicies();
        $this->registerNotificationObservers();
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
            $branded = [403, 404, 419, 429, 503];

            if (! app()->environment(['local', 'testing'])) {
                $branded[] = 500;
            }

            if (! in_array($response->statusCode(), $branded, true)) {
                return null;
            }

            return $response
                ->render('error', ['status' => $response->statusCode()])
                ->withSharedData();
        });
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
