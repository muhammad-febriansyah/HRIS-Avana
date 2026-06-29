# PRD 10 — Flutter Karyawan App AvanaHR

## 1. Keputusan Platform

Untuk karyawan, AvanaHR menggunakan **Flutter mobile app**. Web Laravel + React Inertia tetap menjadi dashboard utama untuk Super Admin, Admin Tenant / HR, dan Manager.

```txt
Web Admin
Laravel 13 + React Inertia TS + Tailwind CSS 4 + shadcn/ui

Karyawan Mobile App
Flutter + GetX atau Riverpod + REST API Laravel Sanctum
```

## 2. Tujuan Flutter Karyawan App

1. Memudahkan karyawan clock in/out dari HP.
2. Mendukung absensi GPS, selfie, dan geofence.
3. Memudahkan pengajuan cuti, izin, WFH, lembur, dan koreksi absensi.
4. Menampilkan profil karyawan, saldo cuti, riwayat kehadiran, dan slip gaji.
5. Memberikan push notification untuk approval, payroll, dan pengumuman.
6. Manager dapat approve/reject request tim dari mobile.

## 3. Role Mobile

### 3.1 Karyawan

```txt
- Login
- Lihat dashboard pribadi
- Clock in/out
- Lihat riwayat attendance
- Ajukan koreksi attendance
- Ajukan cuti
- Ajukan izin jam
- Ajukan WFH
- Ajukan lembur
- Lihat status request
- Lihat saldo cuti
- Lihat slip gaji
- Lihat profil pribadi
- Update data pribadi tertentu sesuai permission
- Terima notifikasi
```

### 3.2 Manager Mobile

```txt
- Semua fitur employee
- Lihat pending approval tim
- Approve/reject cuti
- Approve/reject izin
- Approve/reject WFH
- Approve/reject lembur
- Approve/reject koreksi attendance
- Lihat ringkasan attendance tim
```

## 4. Modul Flutter MVP

| Prioritas | Modul | Keterangan |
|---|---|---|
| P0 | Auth | Login, logout, refresh token, forgot password placeholder |
| P0 | Home Dashboard | Status hari ini, jam kerja, request pending, saldo cuti |
| P0 | Attendance | Clock in/out, GPS, selfie, geofence result |
| P0 | Attendance History | Riwayat absensi dan status |
| P0 | Leave Request | Pengajuan cuti dan saldo cuti |
| P0 | Approval Center | Untuk manager |
| P1 | WFH/Izin/Lembur | Request tambahan |
| P1 | Payslip | Lihat slip gaji digital |
| P1 | Profile | Data pribadi dan dokumen basic |
| P1 | Notification | Push notification dan inbox |

## 5. Flow Clock In/Out Mobile

```txt
Karyawan buka Flutter App
→ Login
→ Buka Home
→ Klik Clock In
→ App request permission lokasi dan kamera
→ Ambil GPS
→ Hit API validate geofence
→ Ambil selfie
→ Submit clock in ke API
→ Backend simpan attendance
→ App tampilkan status berhasil/perlu review
→ Clock Out dilakukan dengan flow serupa
```

## 6. API Backend Laravel untuk Flutter

Gunakan Laravel Sanctum token-based API.

```txt
POST /api/mobile/auth/login
POST /api/mobile/auth/logout
GET  /api/mobile/me
GET  /api/mobile/dashboard

POST /api/mobile/attendance/clock-in
POST /api/mobile/attendance/clock-out
GET  /api/mobile/attendance/history
POST /api/mobile/attendance/corrections

GET  /api/mobile/leave/types
GET  /api/mobile/leave/balances
POST /api/mobile/leave/requests
GET  /api/mobile/leave/requests

GET  /api/mobile/approvals/pending
POST /api/mobile/approvals/{id}/approve
POST /api/mobile/approvals/{id}/reject

GET  /api/mobile/payslips
GET  /api/mobile/payslips/{id}

GET  /api/mobile/notifications
POST /api/mobile/notifications/{id}/read
```

## 7. Security Mobile

```txt
- API wajib menggunakan Sanctum token.
- Semua request wajib tenant scoped dari user login.
- Karyawan hanya boleh akses data sendiri.
- Manager hanya boleh akses approval milik tim/scope-nya.
- Upload selfie wajib validasi file type dan size.
- GPS latitude/longitude wajib divalidasi.
- Device token FCM disimpan per user/device.
- Sensitive payload seperti payslip tidak boleh di-cache sembarangan.
```

## 8. Database Tambahan Mobile

```txt
personal_access_tokens
mobile_devices
- id
- tenant_id
- user_id
- device_id
- device_name
- platform
- fcm_token
- last_login_at
- is_active

attendance_selfies
- id
- tenant_id
- attendance_id
- employee_id
- file_path
- latitude
- longitude
- captured_at
```

## 9. UI Flutter Style

Flutter app mengikuti brand AvanaHR:

```txt
Clean
Modern
Soft card
Minimal shadow
Primary blue/navy
Rounded-xl style
Status badge jelas
Bottom navigation sederhana
```

Menu bawah rekomendasi:

```txt
Home
Attendance
Requests
Payslip
Profile
```

Untuk manager:

```txt
Home
Attendance
Approvals
Team
Profile
```

## 10. Catatan Integrasi dengan Web

```txt
- Backend tetap satu: Laravel 13.
- Database tetap satu: AvanaHR SaaS database.
- Web Admin mengatur tenant, user, role, employee, shift, leave, payroll.
- Flutter hanya consume API untuk kebutuhan employee dan manager.
- Permission backend tetap menjadi sumber kebenaran.
- Jangan duplikasi logic payroll/attendance di Flutter; Flutter hanya input dan menampilkan hasil dari API.
```

## 11. Checklist Claude Code untuk API Mobile

```txt
[ ] Buat route group /api/mobile.
[ ] Gunakan Sanctum auth.
[ ] Buat MobileAuthController.
[ ] Buat MobileDashboardController.
[ ] Buat MobileAttendanceController.
[ ] Buat MobileLeaveController.
[ ] Buat MobileApprovalController.
[ ] Buat MobilePayslipController.
[ ] Buat MobileNotificationController.
[ ] Semua query pakai tenant scope.
[ ] Semua query pakai own/team data scope.
[ ] Semua response pakai API Resource.
[ ] Semua upload selfie masuk storage private/public sesuai kebutuhan.
[ ] Semua API punya validation request.
```
