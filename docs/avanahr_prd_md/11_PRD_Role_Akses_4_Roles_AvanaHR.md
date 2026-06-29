# PRD 11 — Role Akses 4 Role AvanaHR

## 1. Keputusan Final

AvanaHR menggunakan **4 role utama** agar sistem mudah dipahami client, lebih cepat dibangun, dan tetap fleksibel melalui permission detail.

```txt
1. Super Admin
2. Admin Tenant / HR
3. Manager
4. Karyawan
```

Role tambahan seperti HR Cabang, Payroll Officer, Finance, Recruiter, dan Supervisor **tidak dibuat sebagai role terpisah di MVP**. Kebutuhan tersebut dipenuhi memakai permission, feature toggle, branch access, dan data scope.

---

## 2. Deskripsi Role

| Role | Platform | Scope utama | Deskripsi |
|---|---|---|---|
| Super Admin | Web Admin | Semua tenant | Pemilik platform yang mengatur client, paket, fitur, limit, dan konfigurasi global |
| Admin Tenant / HR | Web Admin | Tenant sendiri, bisa company/branch scope | Admin perusahaan yang mengelola HR core, attendance, leave, payroll, report, user, dan workflow sesuai permission |
| Manager | Web Admin + opsional mobile approval | Team scope | Atasan yang melihat data tim dan menyetujui request bawahan |
| Karyawan | Flutter App | Own data | Karyawan yang melakukan absensi, request cuti/izin/lembur/WFH, dan melihat data pribadi/payslip |

---

## 3. Data Scope

| Scope | Dipakai oleh | Deskripsi |
|---|---|---|
| All Tenant / Company | Admin Tenant / HR pusat | Bisa melihat semua data dalam tenant/client |
| Branch | Admin Tenant / HR cabang | Hanya melihat data cabang tertentu |
| Team | Manager | Hanya melihat data bawahan langsung/tidak langsung |
| Own | Karyawan | Hanya melihat data sendiri |

---

## 4. Mapping Permission per Role

### 4.1 Super Admin

```txt
tenant.view
tenant.create
tenant.update
tenant.suspend
package.view
package.create
package.update
feature.view
feature.manage
tenant.feature.manage
tenant.limit.manage
subscription.view
subscription.update
platform.audit.view
```

### 4.2 Admin Tenant / HR

Permission role ini bisa dikustom per user. Contoh permission penuh:

```txt
company.view
company.update
branch.view
branch.create
branch.update
branch.delete
department.view
department.manage
position.view
position.manage
user.view
user.create
user.update
user.disable
role.view
role.manage
permission.assign
employee.view
employee.create
employee.update
employee.archive
employee.document.manage
attendance.view
attendance.export
attendance.correction.approve
leave.view
leave.manage
leave.approve
overtime.view
overtime.approve
wfh.view
wfh.approve
payroll.view
payroll.run
payroll.approve
payroll.publish
payroll.export
bpjs.manage
pph21.manage
report.view
report.export
audit.view
```

Admin Tenant / HR dengan akses cabang cukup diberi `data_scope = branch` dan branch yang diizinkan di `user_branch_access`.

### 4.3 Manager

```txt
team.employee.view
team.attendance.view
team.leave.view
team.leave.approve
team.overtime.approve
team.wfh.approve
team.attendance.correction.approve
team.report.view
own.profile.view
own.attendance.view
own.leave.request
own.payslip.view
```

### 4.4 Karyawan

```txt
own.profile.view
own.profile.update_limited
own.attendance.clock_in
own.attendance.clock_out
own.attendance.history
own.attendance.correction.request
own.leave.request
own.leave.history
own.overtime.request
own.wfh.request
own.permission.request
own.payslip.view
own.notification.view
```

---

## 5. Contoh Pengaturan Real di Client

| User | Role | Scope | Branch | Permission khusus |
|---|---|---|---|---|
| Owner HR | Admin Tenant / HR | Company | Semua cabang | Semua modul HR |
| HR Bogor | Admin Tenant / HR | Branch | Bogor | Employee, attendance, leave |
| Payroll Staff | Admin Tenant / HR | Company | Semua cabang | Payroll, BPJS, PPh 21 |
| Finance Staff | Admin Tenant / HR | Company | Semua cabang | Claim, reimbursement, payroll view |
| Recruiter | Admin Tenant / HR | Company | Semua cabang | Recruitment, onboarding |
| Manager Sales | Manager | Team | Sesuai tim | Approval tim |
| Staff | Karyawan | Own | Cabang masing-masing | ESS pribadi |

---

## 6. Rule Backend Wajib

```txt
- Role hanya 4, tetapi permission tetap granular.
- Feature nonaktif harus memblokir menu dan route backend.
- Permission tidak boleh hanya disembunyikan di frontend.
- Semua query wajib tenant scoped.
- Admin Tenant / HR dengan branch scope hanya boleh melihat branch yang diberikan.
- Manager hanya boleh melihat team berdasarkan reporting line.
- Karyawan hanya boleh melihat data sendiri.
- Semua perubahan role, permission, scope, dan branch access masuk audit log.
```

---

## 7. Tabel Database Pendukung

```txt
roles
- id
- tenant_id nullable untuk Super Admin/global
- code: super_admin, admin_tenant_hr, manager, employee
- name
- is_system

permissions
- id
- code
- module
- action

role_permissions
- role_id
- permission_id

user_roles
- user_id
- role_id

user_data_scopes
- user_id
- scope_type: company, branch, team, own
- scope_value nullable

user_branch_access
- user_id
- branch_id
- access_type: view, manage
```

---

## 8. Catatan untuk Claude Code

Saat membuat modul access control, jangan membuat 10 role default. Buat hanya 4 role system default:

```txt
super_admin
admin_tenant_hr
manager
employee
```

Untuk variasi akses seperti HR Cabang, Payroll, Finance, dan Recruiter, gunakan permission dan scope, bukan role baru.
