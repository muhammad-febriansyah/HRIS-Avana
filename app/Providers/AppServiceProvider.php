<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AttendanceCorrection;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PermissionRequest;
use App\Models\PositionPayrollComponent;
use App\Models\WebsiteSetting;
use App\Models\WfhRequest;
use App\Observers\AnnouncementObserver;
use App\Observers\EmployeeObserver;
use App\Observers\PayrollRunObserver;
use App\Observers\RequestDecisionObserver;
use App\Policies\PayrollPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }

    /**
     * Wire the in-app notification glue: a decision on any approvable request
     * notifies its filer, a published announcement notifies the tenant, and a
     * locked payroll run notifies each paid employee.
     */
    protected function registerNotificationObservers(): void
    {
        foreach ([
            LeaveRequest::class,
            OvertimeRequest::class,
            PermissionRequest::class,
            WfhRequest::class,
            AttendanceCorrection::class,
            Claim::class,
        ] as $requestModel) {
            $requestModel::observe(RequestDecisionObserver::class);
        }

        Announcement::observe(AnnouncementObserver::class);
        PayrollRun::observe(PayrollRunObserver::class);
        Employee::observe(EmployeeObserver::class);
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
