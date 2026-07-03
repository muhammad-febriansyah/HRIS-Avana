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
                'email' => 'info@'.Str::of($row['brand'])->lower()->replace(' ', '').'.co.id',
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
            $url = 'https://ui-avatars.com/api/?'.http_build_query([
                'name' => $row['brand'],
                'background' => $row['color'],
                'color' => 'ffffff',
                'bold' => 'true',
                'size' => 200,
                'length' => 2,
                'format' => 'png',
            ]);
            $bytes = $this->download($url);
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
     * @return array<int, array{name: string, legal: string, brand: string, color: string, industry: string, city: string}>
     */
    private function companies(): array
    {
        return [
            ['name' => 'PT Sinar Mas Digital', 'legal' => 'PT Sinar Mas Digital Tbk', 'brand' => 'Sinar Mas', 'color' => '1E40AF', 'industry' => 'Teknologi', 'city' => 'Jakarta'],
            ['name' => 'PT Boga Nusantara', 'legal' => 'PT Boga Nusantara Pangan', 'brand' => 'Boga Nusantara', 'color' => 'DC2626', 'industry' => 'Makanan & Minuman', 'city' => 'Bekasi'],
            ['name' => 'PT Mitra Logistik Prima', 'legal' => 'PT Mitra Logistik Prima', 'brand' => 'Mitra Logistik', 'color' => '059669', 'industry' => 'Logistik', 'city' => 'Tangerang'],
            ['name' => 'PT Cahaya Tekstil Indonesia', 'legal' => 'PT Cahaya Tekstil Indonesia', 'brand' => 'Cahaya Tekstil', 'color' => '7C3AED', 'industry' => 'Tekstil', 'city' => 'Bandung'],
            ['name' => 'PT Bumi Energi Lestari', 'legal' => 'PT Bumi Energi Lestari Tbk', 'brand' => 'Bumi Energi', 'color' => 'EA580C', 'industry' => 'Energi', 'city' => 'Balikpapan'],
            ['name' => 'PT Griya Properti Sejahtera', 'legal' => 'PT Griya Properti Sejahtera', 'brand' => 'Griya Properti', 'color' => '0891B2', 'industry' => 'Properti', 'city' => 'Jakarta'],
            ['name' => 'PT Sehat Farma Utama', 'legal' => 'PT Sehat Farma Utama', 'brand' => 'Sehat Farma', 'color' => '16A34A', 'industry' => 'Farmasi', 'city' => 'Surabaya'],
            ['name' => 'PT Trans Media Kreasi', 'legal' => 'PT Trans Media Kreasi', 'brand' => 'Trans Media', 'color' => 'DB2777', 'industry' => 'Media', 'city' => 'Jakarta'],
            ['name' => 'PT Karya Baja Konstruksi', 'legal' => 'PT Karya Baja Konstruksi', 'brand' => 'Karya Baja', 'color' => '78716C', 'industry' => 'Konstruksi', 'city' => 'Semarang'],
            ['name' => 'PT Adi Wangsa Finansial', 'legal' => 'PT Adi Wangsa Finansial', 'brand' => 'Adi Wangsa', 'color' => '1D4ED8', 'industry' => 'Keuangan', 'city' => 'Jakarta'],
            ['name' => 'PT Samudra Perikanan Jaya', 'legal' => 'PT Samudra Perikanan Jaya', 'brand' => 'Samudra Perikanan', 'color' => '0E7490', 'industry' => 'Perikanan', 'city' => 'Makassar'],
            ['name' => 'PT Hijau Agro Nusantara', 'legal' => 'PT Hijau Agro Nusantara', 'brand' => 'Hijau Agro', 'color' => '15803D', 'industry' => 'Agrikultur', 'city' => 'Medan'],
            ['name' => 'PT Cipta Otomotif Mandiri', 'legal' => 'PT Cipta Otomotif Mandiri', 'brand' => 'Cipta Otomotif', 'color' => 'B91C1C', 'industry' => 'Otomotif', 'city' => 'Karawang'],
            ['name' => 'PT Global Edukasi Cerdas', 'legal' => 'PT Global Edukasi Cerdas', 'brand' => 'Global Edukasi', 'color' => '9333EA', 'industry' => 'Pendidikan', 'city' => 'Yogyakarta'],
            ['name' => 'PT Prima Retail Indonesia', 'legal' => 'PT Prima Retail Indonesia Tbk', 'brand' => 'Prima Retail', 'color' => 'C2410C', 'industry' => 'Retail', 'city' => 'Jakarta'],
            ['name' => 'PT Nirwana Hospitality Group', 'legal' => 'PT Nirwana Hospitality Group', 'brand' => 'Nirwana Hospitality', 'color' => '0F766E', 'industry' => 'Perhotelan', 'city' => 'Denpasar'],
            ['name' => 'PT Damai Asuransi Sentosa', 'legal' => 'PT Damai Asuransi Sentosa', 'brand' => 'Damai Asuransi', 'color' => '2563EB', 'industry' => 'Asuransi', 'city' => 'Jakarta'],
            ['name' => 'PT Elektronusa Teknik', 'legal' => 'PT Elektronusa Teknik', 'brand' => 'Elektronusa', 'color' => '4338CA', 'industry' => 'Elektronik', 'city' => 'Batam'],
            ['name' => 'PT Sentosa Manufaktur Indonesia', 'legal' => 'PT Sentosa Manufaktur Indonesia', 'brand' => 'Sentosa Manufaktur', 'color' => '6D28D9', 'industry' => 'Manufaktur', 'city' => 'Cikarang'],
            ['name' => 'PT Angkasa Telekomunikasi', 'legal' => 'PT Angkasa Telekomunikasi Tbk', 'brand' => 'Angkasa Telkom', 'color' => '0369A1', 'industry' => 'Telekomunikasi', 'city' => 'Jakarta'],
        ];
    }
}
