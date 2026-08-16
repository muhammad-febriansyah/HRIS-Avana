<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeEmergencyContact;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * The employee data a "Perubahan Data Pribadi" request may touch: what it is
 * called, how it is validated, where its current value is read from, and where
 * an approved value is written back to.
 *
 * Only personal data lives here. Anything the company owns — name, employee
 * number, department, position, salary, employment status — stays out, because
 * an employee asking to change it is not a data correction but a promotion or a
 * transfer, which have their own screens.
 */
final class DataChangeFields
{
    /**
     * Employee columns the employee maintains about themselves.
     *
     * @var array<string, array{label: string, rules: array<int, string>, group: string}>
     */
    private const EMPLOYEE_FIELDS = [
        'phone' => ['label' => 'Nomor Telepon', 'rules' => ['nullable', 'string', 'max:50'], 'group' => 'Kontak'],
        'email' => ['label' => 'Email Pribadi', 'rules' => ['nullable', 'email', 'max:255'], 'group' => 'Kontak'],
        'address' => ['label' => 'Alamat', 'rules' => ['nullable', 'string', 'max:1000'], 'group' => 'Kontak'],
        'nik' => ['label' => 'NIK (KTP)', 'rules' => ['nullable', 'digits:16'], 'group' => 'Data Pribadi'],
        'birth_place' => ['label' => 'Tempat Lahir', 'rules' => ['nullable', 'string', 'max:255'], 'group' => 'Data Pribadi'],
        'birth_date' => ['label' => 'Tanggal Lahir', 'rules' => ['nullable', 'date'], 'group' => 'Data Pribadi'],
        'religion' => ['label' => 'Agama', 'rules' => ['nullable', 'string', 'max:255'], 'group' => 'Data Pribadi'],
        'marital_status' => ['label' => 'Status Pernikahan', 'rules' => ['nullable', 'string'], 'group' => 'Data Pribadi'],
    ];

    /**
     * Fields the employee picks from a fixed list rather than typing freely, so
     * the request cannot smuggle a value the admin console would reject.
     *
     * @var array<string, array<int, string>>
     */
    private const FIELD_OPTIONS = [
        'marital_status' => MaritalStatus::OPTIONS,
    ];

    /**
     * The employee's primary bank account, which payroll pays into — the single
     * most worthwhile thing on this screen to put behind an approval.
     *
     * @var array<string, array{label: string, column: string, rules: array<int, string>, group: string}>
     */
    private const BANK_FIELDS = [
        'bank_name' => ['label' => 'Nama Bank', 'column' => 'bank_name', 'rules' => ['nullable', 'string', 'max:255'], 'group' => 'Rekening Bank'],
        'bank_account_number' => ['label' => 'Nomor Rekening', 'column' => 'account_number', 'rules' => ['nullable', 'string', 'max:50'], 'group' => 'Rekening Bank'],
        'bank_account_holder' => ['label' => 'Atas Nama', 'column' => 'account_holder', 'rules' => ['nullable', 'string', 'max:255'], 'group' => 'Rekening Bank'],
    ];

    /**
     * The employee's first emergency contact.
     *
     * @var array<string, array{label: string, column: string, rules: array<int, string>, group: string}>
     */
    private const EMERGENCY_FIELDS = [
        'emergency_name' => ['label' => 'Nama Kontak Darurat', 'column' => 'name', 'rules' => ['nullable', 'string', 'max:255'], 'group' => 'Kontak Darurat'],
        'emergency_relationship' => ['label' => 'Hubungan', 'column' => 'relationship', 'rules' => ['nullable', 'string', 'max:255'], 'group' => 'Kontak Darurat'],
        'emergency_phone' => ['label' => 'Telepon Kontak Darurat', 'column' => 'phone', 'rules' => ['nullable', 'string', 'max:50'], 'group' => 'Kontak Darurat'],
    ];

    /**
     * Every changeable field key.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_merge(
            array_keys(self::EMPLOYEE_FIELDS),
            array_keys(self::BANK_FIELDS),
            array_keys(self::EMERGENCY_FIELDS),
        );
    }

    /**
     * The catalogue the form renders: key, label, group and the employee's
     * current value, so the screen can show "dari → menjadi".
     *
     * @return array<int, array{key: string, label: string, group: string, current: string|null, options: array<int, string>|null}>
     */
    public static function catalogue(Employee $employee): array
    {
        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => self::label($key),
            'group' => self::group($key),
            'current' => self::currentValue($employee, $key),
            'options' => self::FIELD_OPTIONS[$key] ?? null,
        ], self::keys());
    }

    /**
     * The validation rules for one field's proposed value.
     *
     * @return array<int, string|In>
     */
    public static function rulesFor(string $key): array
    {
        $rules = self::EMPLOYEE_FIELDS[$key]['rules']
            ?? self::BANK_FIELDS[$key]['rules']
            ?? self::EMERGENCY_FIELDS[$key]['rules']
            ?? ['nullable', 'string', 'max:255'];

        if (array_key_exists($key, self::FIELD_OPTIONS)) {
            $rules[] = Rule::in(self::FIELD_OPTIONS[$key]);
        }

        // The age floor is relative to today, so it cannot live in the constant.
        if ($key === 'birth_date') {
            $rules = [...$rules, ...WorkingAge::birthDateRules()];
        }

        return $rules;
    }

    public static function label(string $key): string
    {
        return self::EMPLOYEE_FIELDS[$key]['label']
            ?? self::BANK_FIELDS[$key]['label']
            ?? self::EMERGENCY_FIELDS[$key]['label']
            ?? $key;
    }

    public static function group(string $key): string
    {
        return self::EMPLOYEE_FIELDS[$key]['group']
            ?? self::BANK_FIELDS[$key]['group']
            ?? self::EMERGENCY_FIELDS[$key]['group']
            ?? 'Lainnya';
    }

    /**
     * What the employee's record says today, as a plain string.
     */
    public static function currentValue(Employee $employee, string $key): ?string
    {
        if (array_key_exists($key, self::EMPLOYEE_FIELDS)) {
            $value = $employee->getAttribute($key);

            return $key === 'birth_date'
                ? $employee->birth_date?->toDateString()
                : ($value === null ? null : (string) $value);
        }

        if (array_key_exists($key, self::BANK_FIELDS)) {
            $account = self::primaryBankAccount($employee);
            $value = $account?->getAttribute(self::BANK_FIELDS[$key]['column']);

            return $value === null ? null : (string) $value;
        }

        if (array_key_exists($key, self::EMERGENCY_FIELDS)) {
            $contact = self::firstEmergencyContact($employee);
            $value = $contact?->getAttribute(self::EMERGENCY_FIELDS[$key]['column']);

            return $value === null ? null : (string) $value;
        }

        return null;
    }

    /**
     * Write one approved value onto the employee's record, creating the related
     * row (bank account / emergency contact) when they had none.
     */
    public static function apply(Employee $employee, string $key, ?string $value): void
    {
        if (array_key_exists($key, self::EMPLOYEE_FIELDS)) {
            $employee->update([$key => $value]);

            return;
        }

        if (array_key_exists($key, self::BANK_FIELDS)) {
            $account = self::primaryBankAccount($employee);
            $column = self::BANK_FIELDS[$key]['column'];

            if ($account === null) {
                EmployeeBankAccount::create([
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->getKey(),
                    // Both columns are NOT NULL, so the two the request did not
                    // touch start empty rather than missing.
                    'bank_name' => $column === 'bank_name' ? (string) $value : '',
                    'account_number' => $column === 'account_number' ? (string) $value : '',
                    'account_holder' => $column === 'account_holder' ? $value : $employee->full_name,
                    'is_primary' => true,
                ]);

                return;
            }

            $account->update([$column => $value]);

            return;
        }

        if (array_key_exists($key, self::EMERGENCY_FIELDS)) {
            $contact = self::firstEmergencyContact($employee);
            $column = self::EMERGENCY_FIELDS[$key]['column'];

            if ($contact === null) {
                EmployeeEmergencyContact::create([
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->getKey(),
                    'name' => $column === 'name' ? (string) $value : $employee->full_name,
                    'relationship' => $column === 'relationship' ? $value : null,
                    'phone' => $column === 'phone' ? $value : null,
                ]);

                return;
            }

            $contact->update([$column => $value]);
        }
    }

    private static function primaryBankAccount(Employee $employee): ?EmployeeBankAccount
    {
        return EmployeeBankAccount::query()
            ->where('employee_id', $employee->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    private static function firstEmergencyContact(Employee $employee): ?EmployeeEmergencyContact
    {
        return EmployeeEmergencyContact::query()
            ->where('employee_id', $employee->getKey())
            ->orderBy('id')
            ->first();
    }
}
