# PRD 05 — Payroll, BPJS, dan PPh 21 Tanpa API

## 1. Tujuan

Pada MVP, AvanaHR belum menggunakan API resmi BPJS atau DJP. Sistem tetap harus dapat membantu Admin Tenant / HR melakukan perhitungan internal, menghasilkan slip gaji, laporan, export Excel/PDF, dan status manual reporting.

## 2. Prinsip MVP

- Tidak ada integrasi API resmi BPJS.
- Tidak ada integrasi API resmi DJP.
- Sistem menghitung berdasarkan konfigurasi internal.
- Rate tidak boleh di-hardcode.
- Semua hasil payroll yang sudah final harus disimpan sebagai snapshot.
- Laporan dapat diexport untuk kebutuhan upload/manual input ke sistem resmi.

## 3. Flow Payroll

```txt
Admin Tenant / HR dengan payroll permission
→ Buat payroll period
→ Pilih branch/entity jika diperlukan
→ Lock attendance periode tersebut
→ Ambil employee aktif
→ Ambil salary component employee
→ Ambil attendance, leave, overtime, deduction
→ Hitung gross salary
→ Hitung BPJS internal
→ Hitung PPh 21 internal
→ Hitung net salary / take home pay
→ Review payroll
→ Approve payroll
→ Generate payslip
→ Export Excel/PDF
→ Publish payslip
→ Tandai reported manually jika sudah dilaporkan
→ Lock payroll
```

## 4. Status Payroll

```txt
Draft
Calculated
Reviewed
Approved
Exported
Reported Manually
Paid
Published
Locked
```

### Rule Status

| Status | Rule |
|---|---|
| Draft | Masih bisa diubah |
| Calculated | Hasil perhitungan sudah dibuat |
| Reviewed | Admin Tenant / HR sudah review |
| Approved | Approver payroll sudah menyetujui |
| Exported | File laporan sudah diexport |
| Reported Manually | Sudah dilaporkan manual ke sistem resmi |
| Paid | Pembayaran sudah dilakukan |
| Published | Payslip sudah terlihat employee |
| Locked | Tidak boleh diubah lagi |

## 5. BPJS Tanpa API

### Flow BPJS

```txt
Admin Tenant / HR dengan payroll permission
→ Setup BPJS program
→ Setup BPJS rate dengan effective date
→ Input BPJS profile employee
→ Sistem hitung porsi employee dan company
→ Hasil masuk payroll run
→ Hasil tampil di payslip
→ Export laporan BPJS
→ Admin Tenant / HR dengan payroll permission lapor/upload manual
→ Simpan status reported manually
```

### Data BPJS Employee

```txt
NIK
No BPJS Kesehatan
No KPJ / BPJS Ketenagakerjaan
Status kepesertaan
Tanggal mulai
Tanggal nonaktif
Upah dasar BPJS
JHT enabled
JKK enabled
JKM enabled
JP enabled
BPJS Kesehatan enabled
```

### Tabel BPJS

```txt
bpjs_programs
- id
- code
- name
- type
- description
- is_active

bpjs_rates
- id
- program_id
- employee_rate
- company_rate
- max_wage
- min_wage
- risk_level
- effective_start_date
- effective_end_date
- is_active

employee_bpjs_profiles
- id
- tenant_id
- employee_id
- bpjs_kesehatan_number
- bpjs_ketenagakerjaan_number
- registered_wage
- jht_enabled
- jkk_enabled
- jkm_enabled
- jp_enabled
- kesehatan_enabled
- effective_start_date
- effective_end_date
```

## 6. PPh 21 Tanpa API

### Flow PPh 21

```txt
Admin Tenant / HR dengan payroll permission
→ Setup tax profile employee
→ Setup TER rate table
→ Sistem ambil gross income payroll
→ Sistem tentukan kategori pajak
→ Sistem hitung PPh 21 internal
→ Hasil masuk payroll run
→ Hasil tampil di payslip
→ Export laporan pajak
→ Admin Tenant / HR dengan payroll permission lapor manual
→ Tandai reported manually
```

### Data Tax Profile

```txt
NIK
NPWP
PTKP Status
Tax Method: gross, net, gross up
Employment Tax Type: tetap, tidak tetap
Tax Category
Effective Start Date
Effective End Date
```

### Tabel PPh 21

```txt
tax_profiles
- id
- tenant_id
- employee_id
- nik
- npwp
- ptkp_status
- tax_method
- employment_tax_type
- effective_start_date
- effective_end_date

pph21_ter_rates
- id
- category
- income_min
- income_max
- rate
- effective_start_date
- effective_end_date
- is_active

pph21_calculation_results
- id
- tenant_id
- payroll_run_id
- employee_id
- gross_income
- ter_category
- tax_rate
- pph21_amount
- calculation_snapshot
```

## 7. Calculation Snapshot

Setiap hasil payroll wajib menyimpan snapshot agar data lama tidak berubah saat rate/config diubah.

### Contoh Snapshot

```json
{
  "employee_id": 10,
  "period": "2026-06",
  "gross_income": 8500000,
  "bpjs": {
    "jht_employee": 170000,
    "jht_company": 314500,
    "jp_employee": 85000,
    "jp_company": 170000,
    "kesehatan_employee": 85000,
    "kesehatan_company": 340000
  },
  "tax": {
    "ptkp_status": "TK/0",
    "tax_method": "gross",
    "ter_category": "A",
    "tax_rate": 0.02,
    "pph21_amount": 170000
  },
  "net_salary": 8160000
}
```

## 8. Payslip Requirement

Payslip harus menampilkan:

- Employee info.
- Payroll period.
- Earnings/tunjangan.
- Deductions/potongan.
- BPJS employee portion.
- PPh 21.
- Take home pay.
- Status published.
- Download PDF.

## 9. Export Requirement

### Export Payroll

- Excel payroll summary.
- Excel payroll detail per employee.
- PDF payslip per employee.
- ZIP payslip bulk opsional.

### Export BPJS

- Employee BPJS summary.
- BPJS component employee/company.
- Status participant.
- Registered wage.

### Export PPh 21

- Employee tax profile.
- Gross income.
- Tax category.
- PPh 21 amount.
- Calculation status.

## 10. UI Requirement Payroll

- Payroll run menggunakan custom stepper.
- Setiap step dibungkus Card.
- Review payroll menggunakan DataTable.
- Semua angka rupiah memakai format rupiah.
- Detail payroll employee dibuka via Sheet.
- Approve/Reject memakai AlertDialog.
- Publish payslip memakai confirmation dialog.
- Payroll locked menampilkan Alert bahwa data tidak bisa diubah.

## 11. Acceptance Criteria

- Payroll dapat dibuat tanpa API BPJS/DJP.
- BPJS dan PPh 21 dihitung dari konfigurasi internal.
- Rate bisa punya effective date.
- Hasil payroll locked tidak berubah saat config rate diubah.
- Export Excel/PDF tersedia.
- Status manual reporting tersedia.
- Semua nominal tampil format rupiah.
