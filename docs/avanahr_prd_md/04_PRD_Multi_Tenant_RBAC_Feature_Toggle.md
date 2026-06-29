# PRD 04 — Multi-Tenant, RBAC, Branch Access, dan Feature Toggle

## 1. Tujuan

AvanaHR adalah sistem SaaS multi-client. Karena itu, foundation akses harus dibangun sejak awal. Kebutuhan utama:

1. Super Admin dapat mengelola client/tenant.
2. Super Admin dapat mengaktifkan fitur per client.
3. Admin Tenant / HR dapat mengelola permission, user, branch access, dan workflow di tenant masing-masing.
4. User dapat dibatasi aksesnya berdasarkan cabang, department, team, atau data sendiri.
5. Data antar tenant tidak boleh tercampur.

## 2. Struktur Akses

```txt
Platform AvanaHR
├── Super Admin
│   ├── Kelola tenant/client
│   ├── Kelola package
│   ├── Kelola feature
│   └── Aktif/nonaktifkan feature per client
│
└── Tenant / Client
    ├── Admin Tenant / HR
    ├── Manager
    └── Karyawan
```

## 3. Tenant / Client Management

### Functional Requirement

- Super Admin dapat membuat client baru.
- Super Admin dapat mengubah status client: trial, active, suspended, inactive.
- Super Admin dapat set package dan limit.
- Super Admin dapat aktif/nonaktifkan fitur per client.
- Super Admin dapat melihat ringkasan jumlah user, employee, branch, dan fitur aktif.

### Data Client

```txt
Tenant Name
Slug
Company Name
Package
Status
Max Users
Max Karyawans
Max Branches
Active Modules
Billing Status
Start Date
End Date
```

## 4. Feature Toggle per Client

### Contoh Feature

```txt
hr_core
organization
attendance
leave
overtime
wfh
payroll
bpjs
pph21
recruitment
onboarding
claim
loan
performance
okr
lms
survey
helpdesk
analytics
```

### Rule

- Feature aktif menentukan menu yang muncul di sidebar.
- Feature aktif menentukan route yang bisa diakses.
- Feature aktif menentukan action yang bisa dipakai.
- Feature nonaktif harus diblokir di backend middleware.
- Feature bisa berasal dari package default, lalu dioverride per tenant.

### Flow

```txt
Super Admin
→ Client Detail
→ Tab Features
→ Centang fitur aktif
→ Save
→ Cache tenant feature di-refresh
→ Sidebar dan route client menyesuaikan
```

## 5. Role & Permission

### Role Default

Total role akses MVP: **4 role utama**, terdiri dari 1 role platform dan 3 role tenant/client.

```txt
Super Admin
Admin Tenant / HR
Manager
Karyawan
```

### Mapping Role Lama ke 4 Role Baru

| Kebutuhan lama | Dipetakan ke role | Cara membatasi akses |
|---|---|---|
| Admin Tenant / HR | Admin Tenant / HR | Permission `settings.*`, `company.*`, `user.*` |
| HR Admin Pusat | Admin Tenant / HR | Data scope `company` |
| Admin Tenant / HR cabang | Admin Tenant / HR | Data scope `branch` + `user_branch_access` |
| Payroll Officer | Admin Tenant / HR | Permission `payroll.*`, `bpjs.*`, `pph21.*` |
| Finance | Admin Tenant / HR | Permission `claim.verify`, `loan.manage`, `payroll.view` |
| Recruiter | Admin Tenant / HR | Permission `recruitment.*`, hanya jika fitur recruitment aktif |
| Supervisor | Manager | Data scope `team` |
| Karyawan | Karyawan | Data scope `own` |

Dengan konsep ini, role tetap sederhana, tetapi hak akses tetap detail karena dikontrol oleh permission, feature toggle, dan data scope.

### Permission Naming Convention

Gunakan format:

```txt
module.action
```

Contoh:

```txt
employee.view
employee.create
employee.update
employee.delete
attendance.view
attendance.export
attendance.correction.approve
leave.view
leave.create
leave.approve
payroll.view
payroll.run
payroll.approve
payroll.publish
settings.role.manage
settings.branch.manage
```

## 6. Data Scope

AvanaHR harus mendukung 4 level akses data:

| Scope | Deskripsi | Contoh |
|---|---|---|
| Own | Hanya data sendiri | Karyawan melihat payslip sendiri |
| Team | Data bawahan langsung/tidak langsung | Manager melihat attendance tim |
| Branch | Data cabang tertentu | Admin Tenant / HR Bogor melihat karyawan cabang Bogor |
| Company | Semua data dalam tenant | Admin Tenant / HR pusat melihat semua cabang |

## 7. Branch Access

### Functional Requirement

- User dapat diberi akses ke satu atau banyak branch.
- User dapat diberi scope: own, team, branch, company.
- Admin Tenant / HR cabang tidak boleh melihat data cabang lain.
- Manager tetap dapat melihat team walaupun lintas department jika reporting line mengizinkan.

### Contoh

| User | Role | Scope | Branch Access |
|---|---|---|---|
| Admin Tenant / HR Pusat | Admin Tenant / HR | Company | Semua cabang |
| Admin Tenant / HR Bogor | HR Branch Admin | Branch | Bogor |
| Admin Tenant / HR Jakarta | HR Branch Admin | Branch | Jakarta |
| Manager Sales | Manager | Team | Team Sales |
| Karyawan | Karyawan | Own | Data sendiri |

## 8. Middleware yang Dibutuhkan

```txt
EnsureTenantIsActive
EnsureTenantFeatureEnabled
EnsureUserHasPermission
EnsureUserCanAccessBranch
EnsureUserCanAccessKaryawan
ApplyTenantScope
ApplyDataScope
```

## 9. Backend Rule Wajib

- Semua tabel transaksi wajib punya `tenant_id`.
- Query wajib menggunakan tenant scope.
- Branch-sensitive data wajib difilter branch scope.
- Permission tidak boleh hanya di frontend.
- Feature toggle tidak boleh hanya hide menu; backend tetap wajib blokir.
- Super Admin boleh bypass tenant scope hanya di area platform admin.

## 10. Tabel Database Rekomendasi

```txt
tenants
- id
- name
- slug
- package_id
- status
- max_users
- max_employees
- max_branches

packages
- id
- name
- price
- billing_cycle
- max_users
- max_employees
- max_branches

features
- id
- code
- name
- description
- module_group

package_features
- package_id
- feature_id
- is_enabled

tenant_features
- tenant_id
- feature_id
- is_enabled

roles
- id
- tenant_id
- name
- guard_name
- is_system

permissions
- id
- name
- module
- action

role_permissions
- role_id
- permission_id

user_roles
- user_id
- role_id

user_branch_access
- user_id
- branch_id
- access_type

user_data_scopes
- user_id
- scope_type
- scope_value
```

## 11. UI Requirement

### Super Admin

- Client list DataTable.
- Client detail pakai Tabs: Info, Package, Features, Limits, Users, Billing.
- Feature toggle grid pakai Card + Switch.
- Package feature matrix pakai Table + Checkbox.

### Admin Tenant / HR

- Role/permission settings DataTable.
- Permission matrix pakai Accordion + Checkbox.
- Assign branch access pakai Card + Checkbox.
- User detail pakai Tabs.

## 12. Acceptance Criteria

- User tanpa permission tidak bisa melihat tombol aksi.
- User tanpa permission tidak bisa akses route via URL.
- Client tanpa fitur Payroll tidak melihat menu Payroll.
- Client tanpa fitur Payroll tidak bisa akses route Payroll via URL.
- Admin Tenant / HR cabang hanya melihat employee, attendance, leave, dan payroll cabang yang diberikan.
- Semua perubahan permission dan feature tercatat di audit log.
