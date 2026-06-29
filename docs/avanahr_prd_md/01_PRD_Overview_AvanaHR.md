# PRD 01 — Overview Produk AvanaHR

## 1. Identitas Produk

| Item | Detail |
|---|---|
| Nama produk | AvanaHR |
| Jenis produk | HRIS / HCMS SaaS |
| Tagline | Advancing People, Empowering Growth |
| Target pasar | Perusahaan SME sampai menengah di Indonesia |
| Platform | Web Admin responsif + Flutter Employee App |
| Stack | Laravel 13, React Inertia TypeScript, Tailwind CSS 4, shadcn/ui, Flutter untuk aplikasi karyawan |
| Model bisnis | SaaS multi-tenant per client/perusahaan |

## 2. Latar Belakang

Banyak perusahaan masih mengelola HR secara manual, tersebar di banyak file, dan tidak terhubung antara absensi, cuti, payroll, dokumen, dan approval. AvanaHR dibuat sebagai platform HRIS modern yang menyatukan proses HR dari data karyawan, absensi, cuti, lembur, payroll, BPJS, PPh 21, hingga self-service karyawan.

AvanaHR versi awal harus fokus pada modul yang paling sering dibutuhkan client: employee database, absensi, cuti, approval, payroll basic, dan akses user per cabang.

## 3. Tujuan Produk

1. Menjadi **single source of truth** untuk data karyawan.
2. Mengurangi pekerjaan manual HR melalui approval workflow.
3. Menyediakan absensi real-time dengan GPS, selfie, dan geofence.
4. Menyediakan payroll basic yang bisa menarik data absensi, cuti, lembur, BPJS, dan PPh 21.
5. Mendukung client multi-cabang dengan pembatasan akses data.
6. Memberikan Employee Self-Service agar karyawan bisa mengajukan cuti, izin, WFH, lembur, klaim, dan melihat slip gaji.
7. Menjadi foundation SaaS yang bisa dikembangkan ke recruitment, onboarding, performance, LMS, engagement, dan analytics.

## 4. Sasaran Pengguna dan Role Akses

AvanaHR memakai **4 role utama** agar sistem lebih simpel, mudah dipahami client, dan tetap fleksibel melalui permission detail.

| Role | Platform | Deskripsi | Kebutuhan utama |
|---|---|---|---|
| Super Admin | Web Admin | Pemilik platform AvanaHR | Mengelola client/tenant, paket, fitur, limit, subscription, dan konfigurasi global |
| Admin Tenant / HR | Web Admin | Admin utama dari client/perusahaan | Setup perusahaan, cabang, user, role permission, employee, attendance, cuti, payroll, laporan, dan modul HR lainnya sesuai fitur aktif |
| Manager | Web Admin + opsional mobile approval | Atasan karyawan | Melihat data tim, approve/reject cuti, izin, WFH, lembur, koreksi absensi, dan memantau attendance tim |
| Karyawan | Flutter Employee App | Pengguna akhir/karyawan | Clock in/out, ajukan request, lihat saldo cuti, lihat profil, lihat payslip, dan menerima notifikasi |

Catatan: role lama seperti HR Cabang, Payroll Officer, Finance, dan Recruiter **tidak dibuat sebagai role terpisah pada MVP**. Kebutuhan tersebut diatur melalui **permission**, **feature toggle**, dan **branch/data scope** pada role Admin Tenant / HR.

## 5. Scope MVP

### P0 — Wajib Ada untuk Demo dan Operasional Awal

1. Authentication dan role basic.
2. Multi-tenant client/perusahaan.
3. Feature toggle per client.
4. Branch dan work location.
5. Role, permission, branch access.
6. Employee database.
7. Organization structure.
8. Attendance clock in/out dengan GPS dan selfie.
9. Leave management.
10. Approval workflow.
11. Attendance correction.
12. Dashboard HR basic.

### P1 — Setelah P0 Stabil

1. Overtime.
2. WFH / izin jam / izin keluar kantor.
3. Payroll basic.
4. BPJS calculation internal.
5. PPh 21 calculation internal.
6. Payslip digital.
7. Export Excel/PDF.
8. Employee Self-Service dashboard.
9. Flutter Employee App untuk clock in/out, request, approval manager, profile, dan payslip.

### P2 — Roadmap Lanjutan

1. Recruitment ATS.
2. Onboarding.
3. Document management advanced.
4. Claim dan reimbursement.
5. Loan/pinjaman karyawan.
6. Performance appraisal.
7. OKR/KPI.
8. LMS/training.
9. Survey/engagement.
10. HR analytics advanced.

## 6. Out of Scope MVP

1. Integrasi API resmi BPJS.
2. Integrasi API DJP/e-Filing.
3. Multi-negara dan multi-currency.
4. Mobile app advanced untuk seluruh role HR/Admin; MVP Flutter difokuskan untuk karyawan dan manager approval.
5. AI resume reader.
6. Face recognition advanced.
7. Payroll outsourcing.
8. Payment gateway subscription SaaS.

## 7. Success Metrics

| Area | Metric |
|---|---|
| HR Core | HR dapat membuat dan mengelola data karyawan lengkap |
| Absensi | Karyawan dapat clock in/out dengan GPS dan selfie |
| Approval | Manager dapat approve/reject request dari dashboard |
| Multi-tenant | Data client A tidak terlihat oleh client B |
| Branch access | Admin Tenant / HR dengan branch scope hanya melihat data cabangnya |
| Payroll | Payroll basic dapat generate slip dari komponen gaji dan absensi |
| UI | Semua halaman konsisten memakai breadcrumb, card, DataTable, form validation |

## 8. Prinsip Produk

- Clean, enterprise, dan tidak terlalu ramai.
- Modular: client hanya melihat fitur yang aktif.
- Configurable: approval, branch, role, permission, komponen payroll, dan aturan cuti bisa diatur.
- Auditable: perubahan data sensitif dan payroll harus punya audit trail.
- Safe by default: semua akses data harus dibatasi tenant, role, permission, dan scope.
