<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seed 20 realistic client companies (tenants) for the super-admin "Klien"
 * list, each with a downloaded brand logo, subscription, invoices, branches,
 * departments and a plausible headcount. These are showcase tenants only; the
 * primary demo tenant (PT Nusantara Jaya) is seeded elsewhere.
 */
class ClientTenantsSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('public')->makeDirectory('branding');

        $packages = $this->packages();
        $features = Feature::query()->pluck('id')->all();

        foreach ($this->companies() as $row) {
            $this->seedTenant($row, $packages, $features);
        }

        $this->command?->info('Client tenants seeded: '.count($this->companies()).' companies with logos.');
    }

    /**
     * @param  array{name: string, legal: string, brand: string, color: string, industry: string, city: string}  $row
     * @param  Collection<int, Package>  $packages
     * @param  array<int, int>  $features
     */
    private function seedTenant(array $row, mixed $packages, array $features): void
    {
        $slug = Str::slug($row['name']);
        $package = $packages->random();

        $status = $this->pick(['active', 'active', 'active', 'active', 'trial', 'suspended']);
        $billing = $status === 'suspended' ? 'past_due' : $this->pick(['active', 'active', 'active', 'past_due']);
        $start = Carbon::now()->subDays(mt_rand(90, 900))->startOfDay();

        $tenant = Tenant::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $row['name'],
                'company_name' => $row['legal'],
                'package_id' => $package->id,
                'status' => $status,
                'billing_status' => $billing,
                'max_users' => $package->max_users,
                'max_employees' => $package->max_employees,
                'max_branches' => $package->max_branches,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addYear()->toDateString(),
            ],
        );

        // Already fully seeded on a previous run — leave it untouched.
        if ($tenant->employees()->exists()) {
            return;
        }

        $company = Company::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'name' => $row['name'],
                'legal_name' => $row['legal'],
                'npwp' => $this->npwp(),
                'email' => 'info@'.$row['domain'],
                'phone' => '021-'.mt_rand(3000, 8999).'-'.mt_rand(1000, 9999),
                'address' => 'Jl. '.$row['brand'].' No. '.mt_rand(1, 120).', '.$row['city'],
                'status' => 'active',
            ],
        );

        $this->seedLogo($company, $row);
        $branches = $this->seedBranches($tenant, $row);
        $departments = $this->seedDepartments($tenant);
        $this->seedEmployees($tenant, $branches, $departments);
        $this->seedBilling($tenant, $package, $start);
        $this->seedFeatures($tenant, $features);
    }

    private function seedLogo(Company $company, array $row): void
    {
        $path = 'branding/tenant-'.$company->tenant_id.'-logo.png';

        if (! Storage::disk('public')->exists($path)) {
            // Real brand mark: the company's own favicon, resolved by domain.
            $bytes = $this->download('https://www.google.com/s2/favicons?'.http_build_query([
                'sz' => 256,
                'domain' => $row['domain'],
            ]));

            // Fallback to a coloured monogram if the favicon can't be fetched.
            if ($bytes === null) {
                $bytes = $this->download('https://ui-avatars.com/api/?'.http_build_query([
                    'name' => $row['brand'],
                    'background' => $row['color'],
                    'color' => 'ffffff',
                    'bold' => 'true',
                    'size' => 200,
                    'length' => 2,
                    'format' => 'png',
                ]));
            }

            if ($bytes === null) {
                return;
            }
            Storage::disk('public')->put($path, $bytes);
        }

        $company->forceFill(['logo_path' => $path])->save();
    }

    /**
     * @return array<int, Branch>
     */
    private function seedBranches(Tenant $tenant, array $row): array
    {
        $cities = collect(['Jakarta Pusat', 'Bandung', 'Surabaya', 'Medan', 'Semarang'])
            ->shuffle()
            ->take(mt_rand(1, 3))
            ->values();

        $branches = [];
        foreach ($cities as $i => $city) {
            $branches[] = Branch::create([
                'tenant_id' => $tenant->id,
                'code' => 'BR-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'name' => $i === 0 ? 'Kantor Pusat '.$city : 'Cabang '.$city,
                'timezone' => 'Asia/Jakarta',
                'status' => 'active',
            ]);
        }

        return $branches;
    }

    /**
     * @return array<int, Department>
     */
    private function seedDepartments(Tenant $tenant): array
    {
        $names = ['Human Resources', 'Finance', 'Engineering', 'Sales', 'Marketing', 'Operations'];

        $departments = [];
        foreach ($names as $i => $name) {
            $departments[] = Department::create([
                'tenant_id' => $tenant->id,
                'code' => 'DEP-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'name' => $name,
                'status' => 'active',
            ]);
        }

        return $departments;
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<int, Department>  $departments
     */
    private function seedEmployees(Tenant $tenant, array $branches, array $departments): void
    {
        $count = mt_rand(8, 46);
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $branch = $branches[array_rand($branches)];
            $department = $departments[array_rand($departments)];
            $join = Carbon::now()->subDays(mt_rand(30, 1800));

            $rows[] = [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'employee_number' => 'EMP-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'full_name' => $this->name(),
                'email' => null,
                'gender' => $this->pick(['male', 'female']),
                'employment_status' => $this->pick(['permanent', 'permanent', 'contract', 'probation']),
                'status' => $this->pick(['active', 'active', 'active', 'active', 'resigned']),
                'join_date' => $join->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Employee::insert($rows);
    }

    private function seedBilling(Tenant $tenant, Package $package, Carbon $start): void
    {
        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'status' => $tenant->status === 'suspended' ? 'past_due' : 'active',
            'billing_cycle' => 'monthly',
            'price' => $package->price,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
        ]);

        $months = mt_rand(2, 6);
        for ($m = $months; $m >= 1; $m--) {
            $periodStart = Carbon::now()->subMonthsNoOverflow($m)->startOfMonth();
            $subtotal = (float) $package->price;
            $tax = round($subtotal * 0.11, 2);
            $isCurrent = $m === 1;
            $paid = ! ($isCurrent && $tenant->billing_status === 'past_due');

            Invoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'invoice_number' => 'INV-'.$tenant->id.'-'.$periodStart->format('Ym'),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
                'issue_date' => $periodStart->toDateString(),
                'due_date' => $periodStart->copy()->addDays(14)->toDateString(),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'status' => $paid ? 'paid' : 'unpaid',
                'paid_at' => $paid ? $periodStart->copy()->addDays(mt_rand(1, 10)) : null,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $features
     */
    private function seedFeatures(Tenant $tenant, array $features): void
    {
        // Enable a broad, slightly varied slice of the catalogue per tenant.
        $enabled = collect($features)->shuffle()->take((int) ceil(count($features) * 0.8));

        foreach ($enabled as $featureId) {
            TenantFeature::firstOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $featureId],
                ['is_enabled' => true],
            );
        }
    }

    /**
     * Ensure a small catalogue of packages exists to vary the client plans.
     *
     * @return Collection<int, Package>
     */
    private function packages(): mixed
    {
        $defs = [
            ['name' => 'Starter', 'code' => 'starter', 'price' => 500000, 'max_users' => 15, 'max_employees' => 25, 'max_branches' => 1],
            ['name' => 'Business', 'code' => 'business', 'price' => 1500000, 'max_users' => 60, 'max_employees' => 100, 'max_branches' => 3],
            ['name' => 'Pro', 'code' => 'pro', 'price' => 3000000, 'max_users' => 150, 'max_employees' => 300, 'max_branches' => 8],
            ['name' => 'Enterprise', 'code' => 'enterprise', 'price' => 7500000, 'max_users' => 500, 'max_employees' => 1000, 'max_branches' => 25],
        ];

        foreach ($defs as $def) {
            Package::firstOrCreate(
                ['code' => $def['code']],
                array_merge($def, ['billing_cycle' => 'monthly', 'is_active' => true]),
            );
        }

        return Package::whereIn('code', ['starter', 'business', 'pro', 'enterprise'])->get();
    }

    private function download(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->retry(2, 500)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $options
     */
    private function pick(array $options): string
    {
        return $options[array_rand($options)];
    }

    private function npwp(): string
    {
        return sprintf(
            '%02d.%03d.%03d.%d-%03d.%03d',
            mt_rand(1, 99), mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 9), mt_rand(0, 999), mt_rand(0, 999),
        );
    }

    private function name(): string
    {
        $first = ['Ahmad', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gilang', 'Hana', 'Indra', 'Joko', 'Kartika', 'Lestari', 'Maya', 'Nanda', 'Oscar', 'Putri', 'Rizky', 'Sari', 'Taufik', 'Umar', 'Vina', 'Wahyu', 'Yuni', 'Zaki'];
        $last = ['Santoso', 'Wijaya', 'Pratama', 'Nugroho', 'Hidayat', 'Saputra', 'Lestari', 'Permata', 'Kusuma', 'Maulana', 'Anggraeni', 'Ramadhan', 'Setiawan', 'Halim', 'Firmansyah', 'Puspita', 'Wibowo', 'Utami'];

        return $first[array_rand($first)].' '.$last[array_rand($last)];
    }

    /**
     * Real, well-known Indonesian companies so the client list uses genuine
     * brand logos (fetched from each company's domain).
     *
     * @return array<int, array{name: string, legal: string, brand: string, domain: string, color: string, industry: string, city: string}>
     */
    private function companies(): array
    {
        return [
            ['name' => 'Gojek', 'legal' => 'PT Aplikasi Karya Anak Bangsa', 'brand' => 'Gojek', 'domain' => 'gojek.com', 'color' => '00AA13', 'industry' => 'Teknologi', 'city' => 'Jakarta'],
            ['name' => 'Tokopedia', 'legal' => 'PT Tokopedia', 'brand' => 'Tokopedia', 'domain' => 'tokopedia.com', 'color' => '42B549', 'industry' => 'E-commerce', 'city' => 'Jakarta'],
            ['name' => 'Traveloka', 'legal' => 'PT Trinusa Travelindo', 'brand' => 'Traveloka', 'domain' => 'traveloka.com', 'color' => '1B9DF0', 'industry' => 'Travel', 'city' => 'Jakarta'],
            ['name' => 'Bukalapak', 'legal' => 'PT Bukalapak.com Tbk', 'brand' => 'Bukalapak', 'domain' => 'bukalapak.com', 'color' => 'E31E52', 'industry' => 'E-commerce', 'city' => 'Jakarta'],
            ['name' => 'Bank Central Asia', 'legal' => 'PT Bank Central Asia Tbk', 'brand' => 'BCA', 'domain' => 'bca.co.id', 'color' => '0060AF', 'industry' => 'Perbankan', 'city' => 'Jakarta'],
            ['name' => 'Bank Mandiri', 'legal' => 'PT Bank Mandiri (Persero) Tbk', 'brand' => 'Mandiri', 'domain' => 'bankmandiri.co.id', 'color' => '003D79', 'industry' => 'Perbankan', 'city' => 'Jakarta'],
            ['name' => 'Telkomsel', 'legal' => 'PT Telekomunikasi Selular', 'brand' => 'Telkomsel', 'domain' => 'telkomsel.com', 'color' => 'ED1C24', 'industry' => 'Telekomunikasi', 'city' => 'Jakarta'],
            ['name' => 'Pertamina', 'legal' => 'PT Pertamina (Persero)', 'brand' => 'Pertamina', 'domain' => 'pertamina.co.id', 'color' => '009A44', 'industry' => 'Energi', 'city' => 'Jakarta'],
            ['name' => 'Wings', 'legal' => 'PT Wings Surya', 'brand' => 'Wings', 'domain' => 'wingscorp.com', 'color' => 'E30613', 'industry' => 'Consumer Goods', 'city' => 'Surabaya'],
            ['name' => 'Unilever Indonesia', 'legal' => 'PT Unilever Indonesia Tbk', 'brand' => 'Unilever', 'domain' => 'unilever.co.id', 'color' => '1F36C7', 'industry' => 'Konsumer', 'city' => 'Tangerang'],
            ['name' => 'Astra International', 'legal' => 'PT Astra International Tbk', 'brand' => 'Astra', 'domain' => 'astra.co.id', 'color' => '0033A0', 'industry' => 'Otomotif', 'city' => 'Jakarta'],
            ['name' => 'Garuda Indonesia', 'legal' => 'PT Garuda Indonesia (Persero) Tbk', 'brand' => 'Garuda', 'domain' => 'garuda-indonesia.com', 'color' => '006BB6', 'industry' => 'Penerbangan', 'city' => 'Tangerang'],
            ['name' => 'Kalbe Farma', 'legal' => 'PT Kalbe Farma Tbk', 'brand' => 'Kalbe', 'domain' => 'kalbe.co.id', 'color' => '00A651', 'industry' => 'Farmasi', 'city' => 'Jakarta'],
            ['name' => 'Mayora', 'legal' => 'PT Mayora Indah Tbk', 'brand' => 'Mayora', 'domain' => 'mayora.com', 'color' => '004A93', 'industry' => 'Makanan', 'city' => 'Tangerang'],
            ['name' => 'Ruangguru', 'legal' => 'PT Ruang Raya Indonesia', 'brand' => 'Ruangguru', 'domain' => 'ruangguru.com', 'color' => '2D2CE5', 'industry' => 'Edukasi', 'city' => 'Jakarta'],
            ['name' => 'Blibli', 'legal' => 'PT Global Digital Niaga Tbk', 'brand' => 'Blibli', 'domain' => 'blibli.com', 'color' => '0095DA', 'industry' => 'E-commerce', 'city' => 'Jakarta'],
            ['name' => 'Bank Rakyat Indonesia', 'legal' => 'PT Bank Rakyat Indonesia (Persero) Tbk', 'brand' => 'BRI', 'domain' => 'bri.co.id', 'color' => '00529C', 'industry' => 'Perbankan', 'city' => 'Jakarta'],
            ['name' => 'Sinar Mas', 'legal' => 'Sinar Mas Group', 'brand' => 'Sinar Mas', 'domain' => 'sinarmas.com', 'color' => 'E2231A', 'industry' => 'Konglomerat', 'city' => 'Jakarta'],
            ['name' => 'Kompas Gramedia', 'legal' => 'PT Kompas Media Nusantara', 'brand' => 'Kompas', 'domain' => 'kompas.com', 'color' => '1477C6', 'industry' => 'Media', 'city' => 'Jakarta'],
            ['name' => 'Djarum', 'legal' => 'PT Djarum', 'brand' => 'Djarum', 'domain' => 'djarum.com', 'color' => 'D2232A', 'industry' => 'Consumer Goods', 'city' => 'Kudus'],
        ];
    }
}
