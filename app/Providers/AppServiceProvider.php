<?php

namespace App\Providers;

use App\Models\PayrollPeriod;
use App\Models\PositionPayrollComponent;
use App\Models\WebsiteSetting;
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
        $this->shareBranding();
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
