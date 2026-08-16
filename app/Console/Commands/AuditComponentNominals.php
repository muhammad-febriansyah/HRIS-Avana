<?php

namespace App\Console\Commands;

use App\Models\PayrollComponent;
use App\Models\SalaryMasterComponent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rupiah nominals left on Master Komponen from before Master Gaji became the
 * only place a nominal lives.
 *
 * Payroll no longer reads `payroll_components.basis_value` for a rupiah figure,
 * so a component whose nominal was only ever typed there now pays nothing. This
 * finds those components before anyone notices on a payslip, and clears the
 * stale column only where every template that includes the component already
 * carries its own nominal — nothing is dropped until the figure exists
 * somewhere else.
 */
class AuditComponentNominals extends Command
{
    protected $signature = 'avana:audit-component-nominals
        {--tenant= : Limit to one tenant id}
        {--clear : Clear basis_value on components already covered by every Master Gaji that includes them}';

    protected $description = 'Report (and optionally clear) rupiah nominals still stored on Master Komponen';

    public function handle(): int
    {
        $components = PayrollComponent::query()
            ->whereNotNull('basis_value')
            ->where('calc_basis', '!=', 'percentage')
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('tenant_id', $tenant))
            ->orderBy('tenant_id')
            ->orderBy('code')
            ->get();

        if ($components->isEmpty()) {
            $this->info('Tidak ada nominal rupiah tersisa di Master Komponen.');

            return self::SUCCESS;
        }

        $rows = [];
        $safe = [];

        foreach ($components as $component) {
            $including = SalaryMasterComponent::query()
                ->where('payroll_component_id', $component->id)
                ->where('included', true)
                ->count();

            $withNominal = SalaryMasterComponent::query()
                ->where('payroll_component_id', $component->id)
                ->where('included', true)
                ->where('amount', '>', 0)
                ->count();

            $onEmployees = DB::table('employee_salary_components')
                ->where('payroll_component_id', $component->id)
                ->where('amount', '>', 0)
                ->distinct()
                ->count('employee_id');

            // Covered when every template that uses it carries its own figure.
            // A component no template uses is not covered: clearing it would
            // leave the nominal nowhere.
            $covered = $including > 0 && $including === $withNominal;

            if ($covered) {
                $safe[] = $component->id;
            }

            $rows[] = [
                $component->tenant_id,
                $component->code,
                $component->name,
                number_format((float) $component->basis_value, 0, ',', '.'),
                $component->calc_basis ?? '-',
                $including,
                $withNominal,
                $onEmployees,
                $covered ? 'aman' : 'PINDAHKAN DULU',
            ];
        }

        $this->table(
            ['Tenant', 'Kode', 'Nama', 'Nominal', 'Basis', 'Master pakai', 'Master ada nominal', 'Karyawan', 'Status'],
            $rows,
        );

        if (! $this->option('clear')) {
            $this->comment('Jalankan ulang dengan --clear untuk mengosongkan yang berstatus "aman".');
            $this->comment('Yang berstatus "PINDAHKAN DULU" harus diisi nominalnya di Master Gaji lebih dahulu — tidak akan disentuh.');

            return self::SUCCESS;
        }

        if ($safe === []) {
            $this->warn('Tidak ada komponen yang aman dikosongkan. Isi nominalnya di Master Gaji lebih dahulu.');

            return self::SUCCESS;
        }

        PayrollComponent::whereIn('id', $safe)->update(['basis_value' => null]);

        $this->info(count($safe).' komponen dikosongkan basis_value-nya; '.($components->count() - count($safe)).' dilewati.');

        return self::SUCCESS;
    }
}
