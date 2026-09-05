<?php

namespace App\Support;

use App\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Collection;

/**
 * The column layout of the payroll-import workbook.
 *
 * The middle of the sheet is not fixed: it is one column per salary component
 * the tenant actually keeps in Master Komponen, so a tenant with six components
 * downloads a template with six component columns and the numbers land on the
 * same names their payslip already shows. Everything the system derives for
 * itself — the PTKP status on the employee record, the TER bracket, the payroll
 * period — stays out of the file.
 *
 * The template writes this layout and the importer reads it back, so both sides
 * ask the same class where a column is.
 */
final class PayrollImportLayout
{
    /** Columns that always open a row. */
    public const LEADING = ['nomor_karyawan', 'nama'];

    /** Columns that always close a row, after the component columns. */
    public const TRAILING = ['bpjs_karyawan', 'bpjs_perusahaan', 'pph21', 'take_home_pay'];

    /**
     * The header spellings accepted for each fixed column, normalised.
     *
     * `gaji_bruto`, `tunjangan` and `potongan` are the pre-component layout:
     * still read so a file saved from an older template keeps importing.
     *
     * @var array<string, array<int, string>>
     */
    private const ALIASES = [
        'nomor_karyawan' => ['nomorkaryawan', 'nokaryawan', 'nik', 'employeenumber', 'nomorinduk'],
        'nama' => ['nama', 'namakaryawan'],
        'gaji_bruto' => ['gajibruto', 'bruto', 'totalbruto', 'brutototal', 'penghasilanbruto'],
        'tunjangan' => ['tunjangan', 'totaltunjangan'],
        'potongan' => ['potongan', 'potonganlain', 'totalpotongan'],
        'bpjs_karyawan' => ['bpjskaryawan', 'bpjskry', 'iuranbpjskaryawan'],
        'bpjs_perusahaan' => ['bpjsperusahaan', 'bpjspersh', 'iuranbpjsperusahaan'],
        'pph21' => ['pph21', 'pph', 'pajak', 'pajakpph21'],
        'take_home_pay' => ['takehomepay', 'thp', 'gajibersih', 'netto', 'net'],
    ];

    /**
     * The rolled-up columns of the pre-component template. A component with the
     * same name beats these, since the component says what the money actually is.
     */
    private const LEGACY_TOTALS = ['gaji_bruto', 'tunjangan', 'potongan'];

    /** The positional layout of the pre-component template, for a file with no header row. */
    public const LEGACY_ORDER = [
        'nomor_karyawan',
        'nama',
        'gaji_bruto',
        'tunjangan',
        'potongan',
        'bpjs_karyawan',
        'bpjs_perusahaan',
        'pph21',
        'take_home_pay',
    ];

    /**
     * The bases whose stored amount is a rate rather than a month's money: a
     * percentage of another component, a per-present-day allowance, an hourly
     * overtime rate. Nothing can pre-fill a month from those.
     */
    public const VARIABLE_BASES = ['percentage', 'per_present_day', 'per_overtime_hour'];

    /**
     * The tenant's salary components in template order: Gaji Pokok, the fixed
     * allowances, then the variable pay (lembur, uang makan harian) that closes
     * the earnings, and the deductions last.
     *
     * Variable pay sits at the end because it is the part HR actually types in
     * each month — the columns above it arrive pre-filled from the contract and
     * are usually left alone.
     *
     * @return Collection<int, PayrollComponent>
     */
    public static function components(int $tenantId): Collection
    {
        return PayrollComponent::forTenant($tenantId)
            ->where('status', 'active')
            ->orderByRaw("(type = 'deduction' OR component_group = 'potongan')")
            ->orderByRaw(BasicWageComponent::orderFirstSql())
            ->orderByRaw('(is_fixed = 0 OR calc_basis IN (?, ?, ?))', self::VARIABLE_BASES)
            ->orderBy('id')
            ->get();
    }

    /** Whether a component's amount has to be typed in for the month. */
    public static function isVariable(PayrollComponent $component): bool
    {
        return ! $component->is_fixed || in_array($component->calc_basis, self::VARIABLE_BASES, true);
    }

    /**
     * The header row: the fixed columns with one column per component between them.
     *
     * @param  Collection<int, PayrollComponent>  $components
     * @return array<int, string>
     */
    public static function headings(Collection $components): array
    {
        return [
            ...self::LEADING,
            ...$components->map(static fn (PayrollComponent $component): string => self::heading($component, $components))->all(),
            ...self::TRAILING,
        ];
    }

    /**
     * A component's column header: its name, with the code appended only when
     * two components share a name and the header alone would be ambiguous.
     *
     * @param  Collection<int, PayrollComponent>  $components
     */
    public static function heading(PayrollComponent $component, Collection $components): string
    {
        $name = trim((string) $component->name);
        $code = trim((string) $component->code);

        $duplicated = $components
            ->filter(static fn (PayrollComponent $other): bool => self::normalise((string) $other->name) === self::normalise($name))
            ->count() > 1;

        return $duplicated && $code !== '' ? "{$name} ({$code})" : $name;
    }

    /**
     * Read a header row: which column holds which fixed field, and which column
     * holds which component.
     *
     * @param  array<int, string>  $cells
     * @param  Collection<int, PayrollComponent>  $components
     * @return array{fields: array<string, int>, components: array<int, PayrollComponent>}
     */
    public static function resolveHeader(array $cells, Collection $components): array
    {
        $byKey = [];

        foreach ($components as $component) {
            foreach (self::componentKeys($component) as $key) {
                if ($key !== '' && ! isset($byKey[$key])) {
                    $byKey[$key] = $component;
                }
            }
        }

        $fields = [];
        $columns = [];
        $claimed = [];

        foreach ($cells as $index => $cell) {
            $key = self::normalise((string) $cell);

            if ($key === '') {
                continue;
            }

            // The structural columns are read first, so a component that happens
            // to be called "Nama" or "PPh21" cannot take over the column that
            // identifies the employee or states the tax.
            if (self::matchField(self::LEADING, $key, $index, $fields)
                || self::matchField(self::TRAILING, $key, $index, $fields)) {
                continue;
            }

            // A component name then wins over the pre-component roll-up columns:
            // a tenant that named a component "Tunjangan" means that component,
            // not the old rolled-up allowance column.
            $component = $byKey[$key] ?? null;

            if ($component !== null) {
                // One column per component. A duplicated header would otherwise
                // add the same component into the bruto twice.
                if (! isset($claimed[$component->id])) {
                    $claimed[$component->id] = true;
                    $columns[$index] = $component;
                }

                continue;
            }

            self::matchField(self::LEGACY_TOTALS, $key, $index, $fields);
        }

        return ['fields' => $fields, 'components' => $columns];
    }

    /**
     * Record the column when its header spells one of the given fields.
     *
     * @param  array<int, string>  $names
     * @param  array<string, int>  $fields
     */
    private static function matchField(array $names, string $key, int $index, array &$fields): bool
    {
        foreach ($names as $field) {
            if (in_array($key, self::ALIASES[$field], true)) {
                $fields[$field] ??= $index;

                return true;
            }
        }

        return false;
    }

    /** Whether a cell reads as the employee-number header rather than as data. */
    public static function isEmployeeNumberHeader(string $value): bool
    {
        return in_array(self::normalise($value), self::ALIASES['nomor_karyawan'], true);
    }

    /** Whether a component takes money away rather than adding it. */
    public static function isDeduction(PayrollComponent $component): bool
    {
        return $component->type === 'deduction' || $component->component_group === 'potongan';
    }

    /** How to fill a component's column, given how the engine would compute it. */
    public static function fillHint(PayrollComponent $component): string
    {
        return match ($component->calc_basis) {
            'per_present_day' => 'Dihitung per hari hadir — isi total sebulan.',
            'per_overtime_hour' => 'Dihitung per jam lembur — isi total sebulan.',
            'percentage' => 'Persentase dari komponen lain — isi nominal rupiahnya.',
            default => $component->is_fixed
                ? 'Nominal tetap per bulan.'
                : 'Isi nominal periode ini, kosongkan bila tidak ada.',
        };
    }

    /**
     * The header spellings that identify a component: its name, its code, and
     * the "Nama (KODE)" form the template writes for duplicated names.
     *
     * @return array<int, string>
     */
    private static function componentKeys(PayrollComponent $component): array
    {
        $name = trim((string) $component->name);
        $code = trim((string) $component->code);

        return [
            self::normalise($name),
            self::normalise($code),
            self::normalise("{$name} {$code}"),
        ];
    }

    /** A header cell reduced to letters and digits, so spacing and case stop mattering. */
    public static function normalise(string $value): string
    {
        return mb_strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $value));
    }
}
