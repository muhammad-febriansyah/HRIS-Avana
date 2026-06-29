# PRD 03 — Modul MVP AvanaHR

## 1. Prinsip Prioritas MVP

MVP AvanaHR harus cukup kuat untuk demo dan implementasi awal. Fokus utama adalah modul yang langsung terasa manfaatnya untuk HR dan karyawan: employee database, absensi, cuti, approval, akses cabang, dan payroll basic.

## 2. Daftar Modul MVP

| Prioritas | Modul | Deskripsi | Status |
|---|---|---|---|
| P0 | Auth & User Management | Login, register internal, reset password, profile | Wajib |
| P0 | Multi Tenant | Satu platform untuk banyak client/perusahaan | Wajib |
| P0 | Feature Toggle | Super Admin dapat aktif/nonaktifkan fitur per client | Wajib |
| P0 | Role & Permission | Hak akses per user dan per role | Wajib |
| P0 | Branch Access | User dapat dibatasi per cabang/lokasi | Wajib |
| P0 | Company Setup | Profil perusahaan, cabang, lokasi kerja, geofence | Wajib |
| P0 | Organization Structure | Department, division, position, job level, reporting line | Wajib |
| P0 | Employee Database | Data karyawan lengkap dan dokumen basic | Wajib |
| P0 | Attendance | Clock in/out, GPS, selfie, geofence | Wajib |
| P0 | Leave Management | Leave type, saldo, request, approval | Wajib |
| P0 | Approval Center | Pending approval, history, approve/reject | Wajib |
| P1 | Attendance Correction | Koreksi absen dengan approval dan audit log | Wajib setelah P0 |
| P1 | Overtime | Pengajuan lembur dan perhitungan dasar | Wajib setelah P0 |
| P1 | WFH / Izin Jam | Request WFH dan izin dalam jam kerja | Wajib setelah P0 |
| P1 | Payroll Basic | Komponen gaji, payroll run, payslip | Wajib setelah attendance stabil |
| P1 | BPJS Internal | Setup rate dan hitung iuran internal | Wajib untuk payroll Indonesia |
| P1 | PPh 21 Internal | Setup tax profile dan hitung PPh 21 internal | Wajib untuk payroll Indonesia |
| P1 | Reports | Export Excel/PDF untuk attendance, leave, payroll | Wajib |
| P2 | Recruitment | Job vacancy, candidate, pipeline | Roadmap |
| P2 | Onboarding | Checklist dan aktivasi employee | Roadmap |
| P2 | Claim/Reimbursement | Pengajuan klaim dan approval finance | Roadmap |
| P2 | Performance | Appraisal, KPI, 360 feedback | Roadmap |
| P2 | LMS | Training, course, certificate | Roadmap |

## 3. Detail Modul P0

### 3.1 Auth & User Management

#### Functional Requirement

- User dapat login menggunakan email dan password.
- User dapat melihat profile sendiri.
- Admin dapat membuat user baru.
- Admin dapat assign role ke user.
- User inactive tidak boleh login.

#### Acceptance Criteria

- Login berhasil hanya untuk user aktif.
- User melihat menu sesuai permission.
- User tidak bisa akses route yang tidak punya permission.

### 3.2 Multi Tenant

#### Functional Requirement

- Super Admin dapat membuat tenant/client.
- Setiap data client harus terisolasi menggunakan `tenant_id`.
- Admin Tenant / HR hanya bisa mengakses tenant miliknya.
- Super Admin dapat melihat semua tenant.

#### Acceptance Criteria

- Data client A tidak muncul di client B.
- Query transaksi selalu memakai tenant scope.

### 3.3 Feature Toggle per Client

#### Functional Requirement

- Super Admin dapat mengaktifkan modul per client.
- Sidebar menampilkan menu berdasarkan fitur aktif.
- Route backend mengecek feature aktif.

#### Acceptance Criteria

- Modul nonaktif tidak muncul di sidebar.
- Akses langsung via URL ke modul nonaktif ditolak.

### 3.4 Role & Permission

#### Functional Requirement

- Admin Tenant / HR dapat membuat role custom.
- Admin Tenant / HR dapat memilih permission per role.
- User dapat memiliki satu atau lebih role.
- Permission mengontrol tombol aksi dan akses halaman.

#### Acceptance Criteria

- Tombol edit tidak tampil jika user tidak punya permission update.
- Tombol hapus tidak tampil jika user tidak punya permission delete.

### 3.5 Branch Access

#### Functional Requirement

- User dapat diberi akses ke satu atau banyak branch.
- Admin Tenant / HR dengan branch scope hanya melihat data karyawan cabangnya.
- Admin Tenant / HR dengan company scope dapat melihat semua cabang jika diberikan scope company.

#### Acceptance Criteria

- Filter data employee, attendance, leave, payroll mengikuti branch access.

### 3.6 Employee Database

#### Functional Requirement

- HR dapat create, update, view, dan archive employee.
- Detail karyawan memakai tab: overview, personal, employment, attendance, leave, payroll, documents, audit log.
- Karyawan dapat dikaitkan ke branch, department, position, manager, employment status.

#### Acceptance Criteria

- Karyawan wajib punya branch, department, position, dan employment status.
- Perubahan data sensitif masuk audit log.

### 3.7 Attendance

#### Functional Requirement

- Karyawan dapat clock in/out.
- Sistem menyimpan GPS, selfie, waktu, device info.
- Sistem validasi geofence.
- HR dapat melihat attendance log.

#### Acceptance Criteria

- Clock in di luar geofence ditandai need review.
- Clock out kosong ditandai incomplete.

### 3.8 Leave Management

#### Functional Requirement

- HR dapat membuat leave type.
- Sistem menyimpan leave balance.
- Karyawan dapat submit leave request.
- Manager dapat approve/reject.

#### Acceptance Criteria

- Saldo cuti berkurang setelah approved.
- Request rejected tidak memotong saldo.

### 3.9 Approval Center

#### Functional Requirement

- Manager melihat pending approval.
- Detail request muncul di sheet/drawer.
- Approve/reject wajib menyimpan komentar jika reject.
- Riwayat approval tersimpan.

#### Acceptance Criteria

- Semua approval tersimpan dengan approver, timestamp, action, dan note.

## 4. Detail Modul P1

### 4.1 Payroll Basic

#### Functional Requirement

- Admin Tenant / HR dengan payroll permission membuat payroll period.
- Sistem menarik employee aktif, attendance, leave, overtime, salary component.
- Sistem menghitung gross, deduction, BPJS, PPh 21, net salary.
- Payroll dapat direview, approve, publish, lock.

#### Acceptance Criteria

- Payroll locked tidak bisa diubah.
- Payslip hanya terlihat karyawan yang bersangkutan.

### 4.2 BPJS Internal

#### Functional Requirement

- Admin dapat setup BPJS program dan rate.
- Rate harus punya effective date.
- Karyawan punya BPJS profile.
- Hasil BPJS masuk payroll run.

#### Acceptance Criteria

- Rate tidak di-hardcode.
- Payroll menyimpan calculation snapshot.

### 4.3 PPh 21 Internal

#### Functional Requirement

- Admin dapat setup tax profile employee.
- Admin dapat setup TER rate table.
- Sistem menghitung PPh 21 internal.
- Hasil pajak tampil di payroll dan payslip.

#### Acceptance Criteria

- Hasil payroll lama tidak berubah walau rate terbaru diubah.

## 5. Entity Data Utama

```txt
tenants
packages
features
tenant_features
users
roles
permissions
role_permissions
user_roles
branches
work_locations
departments
positions
job_levels
employees
employee_contracts
employee_documents
employee_salary_components
shifts
shift_schedules
attendances
attendance_corrections
leave_types
leave_balances
leave_requests
overtime_requests
wfh_requests
permission_requests
approval_workflows
approval_steps
approval_requests
approval_logs
payroll_periods
payroll_components
payroll_runs
payroll_run_items
payslips
bpjs_programs
bpjs_rates
employee_bpjs_profiles
tax_profiles
pph21_ter_rates
audit_logs
notifications
```

## 6. Definition of Done per Modul

Setiap modul dianggap selesai jika:

- Punya migration, model, controller/action, request validation, policy/middleware.
- Punya halaman index dengan DataTable.
- Punya create/edit form dengan placeholder dan required indicator.
- Punya view/detail page atau sheet.
- Punya delete/archive confirmation dialog.
- Punya toast success/error dengan Sonner.
- Punya loading state dan empty state.
- Punya filter tenant, branch, permission, dan feature toggle.
- Punya breadcrumb di setiap page.
- Seluruh content utama dibungkus Card.
