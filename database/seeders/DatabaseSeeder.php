<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AvanaDemoSeeder::class);
        // Payroll config (Master Gaji, UMR, grades, payday, BPJS) for tenant 1.
        $this->call(AvanaPayrollDemoSeeder::class);
        // Fills the remaining module pages (benefit, klaim, aset, CRM, OKR,
        // talenta, onboarding, employee detail, ...) for the demo tenant.
        $this->call(ClientModuleDataSeeder::class);
        $this->call(OnboardingSlideSeeder::class);
        $this->call(WebsiteSettingSeeder::class);
    }
}
