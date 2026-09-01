<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeCareerHistory;
use App\Models\EmployeeContract;
use App\Models\EmployeeDependent;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEmergencyContact;
use App\Models\LeaveRequest;
use App\Models\PayrollRunItem;
use App\Models\UserActivityLog;

/**
 * Assembles everything the application holds about one employee.
 *
 * This is the data-portability half of UU PDP 27/2022: a person may ask for a
 * copy of their personal data, and HR has to be able to produce it without a
 * developer running queries by hand.
 *
 * Identifiers come out in the clear on purpose — the export is the person's own
 * data, handed to them — so the file itself is sensitive and the download is
 * audited by the controller that serves it.
 */
class PersonalDataExporter
{
    /**
     * @return array<string, mixed>
     */
    public function export(Employee $employee): array
    {
        $employee->loadMissing([
            'branch:id,name',
            'department:id,name',
            'position:id,name',
            'jobLevel:id,name',
            'workLocation:id,name',
            'manager:id,full_name',
            'user:id,name,email,status,created_at',
            'taxProfile',
            'bpjsProfile',
        ]);

        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'employee_id' => $employee->public_id,
                'tenant_id' => $employee->tenant_id,
                'notice' => 'Berkas ini memuat data pribadi. Simpan dan kirimkan lewat kanal yang aman.',
            ],
            'identitas' => [
                'nomor_karyawan' => $employee->employee_number,
                'nama_lengkap' => $employee->full_name,
                'email' => $employee->email,
                'telepon' => $employee->phone,
                'nik' => $employee->nik,
                'jenis_kelamin' => $employee->gender,
                'tempat_lahir' => $employee->birth_place,
                'tanggal_lahir' => $employee->birth_date?->toDateString(),
                'agama' => $employee->religion,
                'status_pernikahan' => $employee->marital_status,
                'alamat' => $employee->address,
                'data_tambahan' => $employee->custom_data,
            ],
            'kepegawaian' => [
                'cabang' => $employee->branch?->name,
                'departemen' => $employee->department?->name,
                'jabatan' => $employee->position?->name,
                'level' => $employee->jobLevel?->name,
                'lokasi_kerja' => $employee->workLocation?->name,
                'atasan' => $employee->manager?->full_name,
                'status_kepegawaian' => $employee->employment_status,
                'tanggal_masuk' => $employee->join_date?->toDateString(),
                'tanggal_keluar' => $employee->resign_date?->toDateString(),
                'status' => $employee->status,
            ],
            'akun' => $employee->user === null ? null : [
                'nama' => $employee->user->name,
                'email' => $employee->user->email,
                'status' => $employee->user->status,
                'dibuat' => $employee->user->created_at?->toIso8601String(),
            ],
            'pajak' => $employee->taxProfile === null ? null : [
                'npwp' => $employee->taxProfile->npwp,
                'nik' => $employee->taxProfile->nik,
                'status_ptkp' => $employee->taxProfile->ptkp_status,
            ],
            'bpjs' => $employee->bpjsProfile === null ? null : $employee->bpjsProfile->only([
                'bpjs_kesehatan_number', 'bpjs_ketenagakerjaan_number',
            ]),
            'rekening_bank' => EmployeeBankAccount::query()
                ->where('employee_id', $employee->id)
                ->get()
                ->map(fn (EmployeeBankAccount $account): array => [
                    'bank' => $account->bank_name,
                    'nomor_rekening' => $account->account_number,
                    'atas_nama' => $account->account_holder,
                    'utama' => (bool) $account->is_primary,
                ])->all(),
            'kontrak' => EmployeeContract::query()
                ->where('employee_id', $employee->id)
                ->get()
                ->map(fn (EmployeeContract $contract): array => $contract->only([
                    'contract_number', 'contract_type', 'start_date', 'end_date', 'status',
                ]))->all(),
            'dokumen' => EmployeeDocument::query()
                ->where('employee_id', $employee->id)
                ->get()
                ->map(fn (EmployeeDocument $document): array => $document->only([
                    'name', 'type', 'uploaded_at', 'created_at',
                ]))->all(),
            'kontak_darurat' => EmployeeEmergencyContact::query()
                ->where('employee_id', $employee->id)->get()->toArray(),
            'tanggungan' => EmployeeDependent::query()
                ->where('employee_id', $employee->id)->get()->toArray(),
            'riwayat_karier' => EmployeeCareerHistory::query()
                ->where('employee_id', $employee->id)->get()->toArray(),
            'cuti' => LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->latest('id')
                ->limit(500)
                ->get(['id', 'leave_type_id', 'start_date', 'end_date', 'total_days', 'status', 'reason'])
                ->toArray(),
            'absensi' => Attendance::query()
                ->where('employee_id', $employee->id)
                ->latest('date')
                ->limit(1000)
                ->get(['date', 'clock_in_at', 'clock_out_at', 'status', 'work_mode'])
                ->toArray(),
            'penggajian' => PayrollRunItem::query()
                ->where('employee_id', $employee->id)
                ->latest('id')
                ->limit(120)
                ->get(['id', 'payroll_run_id', 'gross_salary', 'net_salary', 'pph21_total'])
                ->toArray(),
            'aktivitas_akun' => $employee->user_id === null ? [] : UserActivityLog::query()
                ->where('user_id', $employee->user_id)
                ->latest('id')
                ->limit(200)
                ->get(['event', 'description', 'ip_address', 'created_at'])
                ->toArray(),
        ];
    }
}
