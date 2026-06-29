# PRD 02 — Flow Sistem AvanaHR

## 1. Flow Setup Client oleh Super Admin

```txt
Super Admin SaaS
→ Login ke dashboard platform
→ Buat client/tenant baru
→ Isi profil perusahaan
→ Pilih package/subscription
→ Aktifkan fitur/modul per client
→ Set limit employee/user/branch
→ Buat akun Admin Tenant / HR
→ Kirim akses ke Admin Tenant / HR
→ Client siap onboarding
```

### Output

- Tenant/client aktif.
- Package terpasang.
- Feature toggle aktif sesuai paket.
- Admin Tenant / HR dapat login.

## 2. Flow Setup Perusahaan oleh Admin Tenant / HR

```txt
Admin Tenant / HR
→ Login
→ Setup company profile
→ Setup branch/cabang
→ Setup work location dan geofence
→ Setup department/division
→ Setup position/jabatan
→ Setup job level/grade
→ Setup role dan permission
→ Setup approval workflow
→ Setup shift dan jam kerja
→ Invite HR Admin dan user internal
```

### Output

- Struktur perusahaan siap digunakan.
- User internal memiliki akses sesuai role.
- Approval cuti/lembur/koreksi sudah punya alur.

## 3. Flow Employee Database / Database Karyawan

```txt
HR Admin
→ Buka Karyawan
→ Klik Tambah Karyawan
→ Isi personal data
→ Isi employment data
→ Pilih cabang, department, posisi, atasan
→ Isi kontrak dan tanggal efektif
→ Isi data payroll dasar
→ Isi BPJS dan tax profile bila ada
→ Upload dokumen
→ Buat akun login employee
→ Karyawan aktif
```

### Status Karyawan

```txt
Draft
Active
Probation
Contract
Permanent
Suspended
Resigned
Terminated
```

### Output

- Profil karyawan terpusat.
- Data siap dipakai absensi, cuti, approval, dan payroll.

## 4. Flow Absensi Clock In/Out

```txt
Karyawan
→ Login
→ Buka Attendance
→ Klik Clock In
→ Sistem minta akses GPS
→ Sistem validasi geofence lokasi kerja
→ Karyawan mengambil selfie
→ Sistem simpan clock in
→ Karyawan bekerja
→ Klik Clock Out
→ Sistem hitung durasi kerja
→ Sistem set status attendance
→ Attendance masuk rekap HR
```

### Validasi

- GPS wajib.
- Selfie wajib/opsional sesuai setting client.
- Geofence sesuai work location.
- Shift aktif harus tersedia.
- Toleransi telat mengikuti shift policy.
- Clock out kosong ditandai `Incomplete`.

### Status Attendance

```txt
Present
Late
Early Leave
Absent
Leave
Sick
Business Trip
WFH
Incomplete
Need Correction
```

## 5. Flow Koreksi Absensi

```txt
Karyawan
→ Buka Attendance Correction
→ Pilih tanggal
→ Pilih jenis koreksi: lupa clock in, lupa clock out, salah lokasi, lainnya
→ Isi jam koreksi
→ Isi alasan
→ Upload bukti bila diperlukan
→ Submit
→ Manager review
→ Approve/Reject
→ HR review bila workflow membutuhkan
→ Attendance record diperbarui
→ Audit log tersimpan
```

### Rule

- Record attendance hanya berubah setelah koreksi disetujui.
- Semua perubahan wajib menyimpan audit log.
- Request yang ditolak tidak mengubah attendance.

## 6. Flow Cuti

```txt
Karyawan
→ Buka Leave Request
→ Pilih leave type
→ Pilih tanggal mulai dan selesai
→ Sistem cek saldo cuti
→ Isi alasan
→ Submit
→ Manager approve/reject
→ HR approve bila dibutuhkan
→ Saldo cuti berkurang
→ Attendance otomatis menjadi Leave
→ Kalender tim terupdate
```

### Rule

- Cuti tidak bisa submit jika saldo tidak cukup, kecuali leave type mengizinkan minus.
- Cuti yang sudah approved dapat dibatalkan sesuai policy.
- Leave balance harus punya history.

## 7. Flow Izin Jam / Izin Keluar Kantor / WFH

```txt
Karyawan
→ Buka Request
→ Pilih Izin Jam / Keluar Kantor / WFH
→ Isi tanggal dan rentang jam
→ Isi alasan
→ Submit
→ Manager approve/reject
→ Sistem update status attendance atau rule absensi hari tersebut
```

### Perbedaan

| Request | Dampak |
|---|---|
| Cuti | Memotong saldo cuti |
| Izin Jam | Tidak memotong saldo cuti, mempengaruhi durasi kerja |
| Keluar Kantor | Tercatat sebagai izin sementara |
| WFH | Tetap hadir dengan aturan geofence berbeda |

## 8. Flow Overtime

```txt
Karyawan / Manager
→ Buat Overtime Request
→ Isi tanggal
→ Isi estimasi jam lembur
→ Isi alasan
→ Submit
→ Manager approve
→ HR/Payroll review bila dibutuhkan
→ Sistem cocokkan dengan attendance aktual
→ Overtime approved masuk payroll
```

### Rule

- Hanya lembur approved yang masuk payroll.
- Jam lembur dapat dihitung dari attendance aktual.
- Overtime rate dikonfigurasi per client.

## 9. Flow Payroll Basic

```txt
Admin Tenant / HR dengan payroll permission
→ Buat payroll period
→ Lock attendance periode tersebut
→ Tarik data employee aktif
→ Tarik komponen salary
→ Tarik data attendance, leave, overtime, deduction
→ Hitung gross salary
→ Hitung BPJS internal
→ Hitung PPh 21 internal
→ Review hasil payroll
→ Approve payroll
→ Generate payslip
→ Publish payslip ke employee
→ Export laporan Excel/PDF
→ Tandai reported manually jika sudah dilaporkan ke sistem resmi
```

### Status Payroll

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

## 10. Flow Recruitment ke Onboarding

```txt
Admin Tenant / HR dengan recruitment permission
→ Buat job vacancy
→ Kandidat apply
→ Screening CV
→ Interview
→ Assessment
→ Offering
→ Candidate accepted
→ Generate employee draft
→ Onboarding checklist
→ Assign department, position, manager, branch
→ Buat akun employee
→ Karyawan aktif
```

## 11. Flow Offboarding

```txt
Karyawan / Admin Tenant / HR
→ Ajukan resign atau termination
→ Tentukan tanggal efektif
→ Generate exit checklist
→ Cek aset perusahaan
→ Cek pinjaman/klaim outstanding
→ Cek sisa cuti
→ Final payroll
→ Exit interview
→ Nonaktifkan akun
→ Arsipkan data employee
```

## 12. Flow Approval Umum

```txt
User submit request
→ Sistem identifikasi workflow berdasarkan tenant, branch, department, request type
→ Sistem menentukan approver step 1
→ Approver menerima notifikasi
→ Approver approve/reject
→ Jika multi-level, lanjut step berikutnya
→ Jika final approved, sistem menjalankan efek transaksi
→ Jika rejected, status menjadi rejected dan efek transaksi tidak dijalankan
```

### Tipe Approval

- Sequential approval.
- Parallel approval.
- Delegated approval.
- Approval by manager hierarchy.
- Approval by role.
- Approval by specific user.

## 13. Flow Feature Toggle per Client

```txt
Super Admin
→ Buka detail client
→ Buka tab Features
→ Aktifkan/nonaktifkan modul
→ Simpan
→ Sidebar client otomatis menyesuaikan
→ Route modul nonaktif tidak bisa diakses langsung
```

### Rule

- Feature nonaktif tidak muncul di sidebar.
- Feature nonaktif harus diblokir via middleware backend.
- Super Admin tetap bisa melihat daftar feature client.
