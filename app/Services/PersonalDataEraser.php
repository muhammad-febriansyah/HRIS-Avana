<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeDependent;
use App\Models\EmployeeEmergencyContact;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\UserLoginDevice;
use App\Support\PrivateFile;
use Illuminate\Support\Facades\DB;

/**
 * Erases an ex-employee's personal data while keeping the records the company
 * is legally required to retain.
 *
 * UU PDP 27/2022 gives a person the right to have their data erased, and the
 * tax and labour rules oblige the employer to keep payroll and attendance
 * history for years. Deleting the employee row outright would satisfy neither:
 * it strands every payslip, leave balance and approval that points at it.
 *
 * So this anonymises instead. Identifiers, contact details and uploaded
 * documents go; the row keeps its key, its dates and its financial history, and
 * is marked so nobody mistakes the placeholder name for a real person.
 */
class PersonalDataEraser
{
    /**
     * Scrub the employee's identifying data.
     *
     * @return array<string, int> what was removed, for the audit entry
     */
    public function erase(Employee $employee): array
    {
        $summary = [];

        DB::transaction(function () use ($employee, &$summary): void {
            $summary['dokumen'] = $this->purgeDocuments($employee);
            $summary['rekening'] = EmployeeBankAccount::query()->where('employee_id', $employee->id)->delete();
            $summary['kontak_darurat'] = EmployeeEmergencyContact::query()->where('employee_id', $employee->id)->delete();
            $summary['tanggungan'] = EmployeeDependent::query()->where('employee_id', $employee->id)->delete();

            TaxProfile::query()
                ->where('employee_id', $employee->id)
                ->update(['npwp' => null, 'nik' => null]);

            $this->purgePhoto($employee);

            $reference = 'ANON-'.$employee->id;

            $employee->forceFill([
                'full_name' => 'Data Dihapus ('.$reference.')',
                'email' => null,
                'phone' => null,
                'nik' => null,
                'nik_hash' => null,
                'birth_place' => null,
                'address' => null,
                'religion' => null,
                'marital_status' => null,
                'photo_path' => null,
                'custom_data' => null,
                'status' => 'inactive',
                'anonymized_at' => now(),
            ])->save();

            $summary['akun'] = $this->disableAccount($employee);
        });

        return $summary;
    }

    /**
     * Whether the employee may be erased at all.
     *
     * An active employee is still working here, and their data is being used
     * for the employment itself — erasing it is not a right that applies.
     */
    public function eligible(Employee $employee): bool
    {
        return $employee->status !== 'active' || $employee->resign_date !== null;
    }

    /**
     * Delete the uploaded documents along with their files: a scan of a KTP is
     * the identifier, not just a pointer to it.
     */
    private function purgeDocuments(Employee $employee): int
    {
        $documents = DB::table('employee_documents')->where('employee_id', $employee->id)->get(['id', 'file_path']);

        foreach ($documents as $document) {
            PrivateFile::delete($document->file_path);
        }

        return DB::table('employee_documents')->where('employee_id', $employee->id)->delete();
    }

    private function purgePhoto(Employee $employee): void
    {
        if ($employee->photo_path !== null) {
            PrivateFile::delete($employee->photo_path);
        }
    }

    /**
     * Close the login: the account keeps its key so past approvals still name
     * an actor, but it can no longer be signed in to and carries no address.
     */
    private function disableAccount(Employee $employee): int
    {
        if ($employee->user_id === null) {
            return 0;
        }

        $user = User::query()->find($employee->user_id);

        if ($user === null) {
            return 0;
        }

        UserLoginDevice::query()->where('user_id', $user->id)->delete();

        $user->forceFill([
            'name' => 'Data Dihapus',
            'email' => 'anon-'.$user->id.'@dihapus.invalid',
            'password' => bcrypt(str()->random(64)),
            'status' => 'inactive',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => null,
            'token_version' => (int) ($user->token_version ?? 0) + 1,
        ])->save();

        return 1;
    }
}
