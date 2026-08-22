<?php

namespace App\Console\Commands;

use App\Models\PayrollComponent;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Set, in one pass, which earnings feed the two payroll bases a payslip is
 * checked against: the PPh 21 bruto (TER) and the BPJS contribution wage.
 *
 * The two are not the same figure and that is the whole point. Under PMK
 * 168/2023 the monthly TER rate is applied to the month's whole bruto —
 * incentives and bonuses included — while the BPJS wage is the fixed monthly
 * pay only, so a good month cannot inflate a premium the employee keeps paying
 * after it. Doing this by hand means opening every component in Master
 * Komponen and remembering which of the two boxes each one needs; miss one
 * allowance and the bruto lands in a lower TER bracket, which is exactly the
 * kind of error nobody spots until the payslip is compared with a spreadsheet.
 *
 * Reports by default. Nothing is written without --apply.
 */
class AlignTaxBasis extends Command
{
    protected $signature = 'avana:align-tax-basis
        {--tenant= : Tenant id (required with --apply; omit to report every tenant)}
        {--exclude-tax= : Component codes to leave out of the PPh 21 bruto, comma separated}
        {--exclude-bpjs= : Extra component codes to leave out of the BPJS wage, comma separated}
        {--employer-premium= : exclude | include — whether the company BPJS premium joins the TER bruto}
        {--apply : Write the changes (otherwise the command only reports)}';

    protected $description = 'Align which earning components feed the PPh 21 bruto (TER) and the BPJS contribution wage';

    /**
     * Earnings that are variable by nature, so they belong in the tax bruto but
     * not in the BPJS wage. Matched against the component code and name.
     *
     * @var list<string>
     */
    private const VARIABLE_KEYWORDS = [
        'insentif', 'incentive', 'bonus', 'thr', 'lembur', 'overtime', 'komisi', 'commission',
    ];

    public function handle(): int
    {
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $apply = (bool) $this->option('apply');

        if ($apply && $tenantId === null) {
            $this->error('--apply butuh --tenant: mengubah seluruh tenant sekaligus bukan sesuatu yang bisa dibatalkan.');

            return self::FAILURE;
        }

        $excludeTax = $this->codeList($this->option('exclude-tax'));
        $excludeBpjs = $this->codeList($this->option('exclude-bpjs'));

        $components = PayrollComponent::query()
            ->where('type', 'earning')
            ->where('status', 'active')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get();

        if ($components->isEmpty()) {
            $this->warn('Tidak ada komponen pendapatan aktif untuk cakupan ini.');

            return self::SUCCESS;
        }

        $rows = [];
        $changed = 0;

        foreach ($components as $component) {
            $label = strtolower(trim(($component->code ?? '').' '.($component->name ?? '')));

            // Variable by name (incentive, bonus, THR) or by how it is counted:
            // a per-present-day or per-overtime-hour component changes with the
            // month, and the BPJS wage is meant to be the steady figure.
            $isVariable = $this->matchesVariable($label)
                || in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true);

            $wantTaxable = ! $this->excluded($component, $excludeTax);
            $wantBpjsBase = ! $isVariable && ! $this->excluded($component, $excludeBpjs);

            $taxMoves = (bool) $component->is_taxable !== $wantTaxable;
            $bpjsMoves = (bool) $component->is_bpjs_base !== $wantBpjsBase;

            if ($taxMoves || $bpjsMoves) {
                $changed++;
            }

            $rows[] = [
                $component->tenant_id,
                $component->code ?? '—',
                $component->name,
                $this->flag((bool) $component->is_taxable, $wantTaxable),
                $this->flag((bool) $component->is_bpjs_base, $wantBpjsBase),
            ];

            if ($apply && ($taxMoves || $bpjsMoves)) {
                $component->update([
                    'is_taxable' => $wantTaxable,
                    'is_bpjs_base' => $wantBpjsBase,
                ]);
            }
        }

        $this->table(['Tenant', 'Kode', 'Komponen', 'PPh 21', 'Basis BPJS'], $rows);

        $premium = $this->applyEmployerPremium($tenantId, $apply);

        if (! $apply) {
            $this->newLine();
            $this->info("Pratinjau saja. {$changed} komponen akan berubah. Tambahkan --apply untuk menyimpan.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Selesai. {$changed} komponen diperbarui.".($premium !== null ? " Premi BPJS perusahaan: {$premium}." : ''));
        $this->line('Status PTKP tiap karyawan tidak disentuh — itu data per orang, isi di BPJS & Pajak → Profil Pajak.');

        return self::SUCCESS;
    }

    /**
     * Whether the company-paid premium joins the TER bruto. Left alone unless
     * the option says otherwise, because it is a tax-treatment decision, not a
     * component setting.
     */
    private function applyEmployerPremium(?int $tenantId, bool $apply): ?string
    {
        $choice = $this->option('employer-premium');

        if ($choice === null) {
            return null;
        }

        if (! in_array($choice, ['include', 'exclude'], true)) {
            $this->error('--employer-premium hanya menerima "include" atau "exclude".');

            return null;
        }

        if ($tenantId === null) {
            $this->warn('--employer-premium diabaikan: butuh --tenant.');

            return null;
        }

        if ($apply) {
            Tenant::whereKey($tenantId)->update([
                'tax_includes_employer_bpjs' => $choice === 'include',
            ]);
        }

        return $choice === 'include' ? 'ikut bruto pajak' : 'tidak ikut bruto pajak';
    }

    /**
     * @param  list<string>  $excluded
     */
    private function excluded(PayrollComponent $component, array $excluded): bool
    {
        return in_array(strtolower((string) $component->code), $excluded, true);
    }

    private function matchesVariable(string $label): bool
    {
        foreach (self::VARIABLE_KEYWORDS as $keyword) {
            if (str_contains($label, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function codeList(mixed $option): array
    {
        if (! is_string($option) || trim($option) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $code): string => strtolower(trim($code)),
            explode(',', $option),
        )));
    }

    private function flag(bool $before, bool $after): string
    {
        $render = static fn (bool $value): string => $value ? 'Ya' : 'Tidak';

        return $before === $after
            ? $render($after)
            : $render($before).' → '.$render($after);
    }
}
