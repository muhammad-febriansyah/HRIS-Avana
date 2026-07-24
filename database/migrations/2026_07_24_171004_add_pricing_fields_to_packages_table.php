<?php

use App\Models\Package;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            // A plain text list of features shown on the pricing tier.
            $table->json('feature_list')->nullable()->after('is_active');
            $table->boolean('is_popular')->default(false)->after('feature_list');
        });

        // Seed the marketing pricing tiers into the DB (super-admin editable).
        foreach ($this->tiers() as $tier) {
            Package::updateOrCreate(['code' => $tier['code']], $tier);
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'feature_list', 'is_popular']);
        });
    }

    /**
     * The three subscription tiers and their feature lists.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tiers(): array
    {
        $core = [
            'Organization Structure',
            'Database Employee',
            'Time & Attendance',
            'Leave Management',
            'Payroll Management',
            'Employee Self Service',
            'Manager Self Service',
            'Mobile Apps',
            'Reporting & Dashboard',
        ];

        return [
            [
                'code' => 'hc_starter',
                'name' => 'HC Starter',
                'tagline' => 'Essential',
                'price' => 2_500_000,
                'billing_cycle' => 'monthly',
                'max_users' => 25,
                'max_employees' => 50,
                'max_branches' => 2,
                'ai_token_quota' => 500_000,
                'is_active' => true,
                'is_popular' => false,
                'feature_list' => [
                    'Organization Structure',
                    'Database Employee',
                    'Employee Management',
                    ...array_slice($core, 2),
                ],
            ],
            [
                'code' => 'hc_growth',
                'name' => 'HC Growth',
                'tagline' => 'Professional',
                'price' => 5_000_000,
                'billing_cycle' => 'monthly',
                'max_users' => 100,
                'max_employees' => 300,
                'max_branches' => 8,
                'ai_token_quota' => 2_000_000,
                'is_active' => true,
                'is_popular' => true,
                'feature_list' => [
                    'Organization Structure',
                    'Database Employee',
                    'Employee Movement Management',
                    ...array_slice($core, 2),
                    'Contract Management',
                    'Benefit Management',
                    'Duty Travel Management',
                ],
            ],
            [
                'code' => 'hc_strategic',
                'name' => 'HC Strategic',
                'tagline' => 'Enterprise 360',
                'price' => 10_000_000,
                'billing_cycle' => 'monthly',
                'max_users' => 500,
                'max_employees' => 2000,
                'max_branches' => 25,
                'ai_token_quota' => 10_000_000,
                'is_active' => true,
                'is_popular' => false,
                'feature_list' => [
                    'Organization Structure',
                    'Database Employee',
                    'Employee Movement Management',
                    ...array_slice($core, 2),
                    'Performance Management',
                    'Benefit Management',
                    'Recruitment Management',
                    'Talent Management',
                    'AI Features & Assistant Tools',
                    'Contract Management',
                    'Duty Travel Management',
                    'Training Management',
                    'Calendar Management',
                ],
            ],
        ];
    }
};
