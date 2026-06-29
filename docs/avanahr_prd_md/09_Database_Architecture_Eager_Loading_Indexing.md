# PRD 09 — Rancangan Database, Eager Loading, dan Indexing AvanaHR

## 1. Tujuan

Dokumen ini menjadi pedoman database AvanaHR agar sistem cepat, aman, dan scalable untuk HRIS SaaS multi-tenant. Fokus utama:

1. Semua data client terisolasi dengan `tenant_id`.
2. Query list memakai server-side pagination.
3. Semua halaman DataTable memakai eager loading agar tidak terjadi N+1 query.
4. Kolom yang sering dipakai filter, sorting, dan lookup wajib diberi index.
5. Payroll dan attendance wajib punya snapshot agar data historis tidak berubah saat konfigurasi berubah.

## 2. Prinsip Database Wajib

```txt
- Hampir semua tabel bisnis wajib punya tenant_id.
- Tabel yang berkaitan cabang wajib punya branch_id atau work_location_id.
- Gunakan UUID/ULID atau big integer sesuai standar project, tapi konsisten.
- Semua relasi penting wajib dibuat foreign key.
- Gunakan soft delete untuk master data yang tidak boleh hilang historinya.
- Gunakan audit log untuk perubahan data sensitif.
- Gunakan status enum/string yang konsisten.
- Jangan hardcode rate BPJS, PPh 21, leave rule, approval rule, dan payroll component.
- Semua index harus mengikuti pola query DataTable.
```

## 3. Domain Database Utama

```txt
Platform & Tenant
- tenants
- packages
- features
- package_features
- tenant_features
- subscriptions

Access Control
- users
- roles
- permissions
- role_permissions
- user_roles
- user_branch_accesses
- approval_workflows
- approval_steps
- approval_requests
- approval_logs

Organization
- companies
- branches
- work_locations
- departments
- positions
- job_levels
- cost_centers

Karyawan Core
- employees
- employee_contracts
- employee_documents
- employee_career_histories
- employee_bank_accounts
- employee_emergency_contacts
- employee_dependents

Time & Attendance
- shifts
- shift_schedules
- attendances
- attendance_corrections
- leave_types
- leave_balances
- leave_requests
- overtime_requests
- wfh_requests
- permission_requests

Payroll
- payroll_periods
- payroll_components
- employee_salary_components
- payroll_runs
- payroll_run_items
- payslips
- bpjs_programs
- bpjs_rates
- employee_bpjs_profiles
- tax_profiles
- pph21_ter_rates
- pph21_calculation_results

System
- notifications
- activity_logs
- audit_logs
- attachments
```

## 4. Indexing Strategy

### 4.1 Index Global Multi-Tenant

Semua tabel bisnis besar wajib memiliki index:

```txt
INDEX tenant_id
INDEX tenant_id, created_at
INDEX tenant_id, status
```

Contoh migration Laravel:

```php
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
$table->index(['tenant_id', 'created_at']);
$table->index(['tenant_id', 'status']);
```

### 4.2 Index Branch Scope

Untuk data yang sering difilter per cabang:

```txt
INDEX tenant_id, branch_id
INDEX tenant_id, branch_id, status
INDEX tenant_id, branch_id, created_at
```

Dipakai di:

```txt
employees
attendances
leave_requests
overtime_requests
payroll_runs
payroll_run_items
```

### 4.3 Index Karyawan & Date

Untuk attendance, leave, payroll, dan payslip:

```txt
INDEX tenant_id, employee_id
INDEX tenant_id, employee_id, date
INDEX tenant_id, employee_id, period_id
INDEX tenant_id, date
INDEX tenant_id, date, status
```

Contoh:

```php
$table->index(['tenant_id', 'employee_id', 'date']);
$table->index(['tenant_id', 'date', 'status']);
```

### 4.4 Unique Constraint Penting

```txt
tenants.slug unique
users.email unique atau tenant_id + email unique sesuai konsep login
employees tenant_id + employee_number unique
branches tenant_id + code unique
departments tenant_id + code unique
positions tenant_id + code unique
attendance tenant_id + employee_id + date + shift_schedule_id unique
leave_balances tenant_id + employee_id + leave_type_id + year unique
payroll_periods tenant_id + code unique
payroll_run_items payroll_run_id + employee_id unique
payslips payroll_run_id + employee_id unique
```

## 5. Rekomendasi Index per Tabel Utama

### employees

```txt
INDEX tenant_id, branch_id, status
INDEX tenant_id, department_id
INDEX tenant_id, position_id
INDEX tenant_id, manager_id
INDEX tenant_id, employee_number
INDEX tenant_id, full_name
UNIQUE tenant_id, employee_number
```

### attendances

```txt
INDEX tenant_id, employee_id, date
INDEX tenant_id, branch_id, date
INDEX tenant_id, date, status
INDEX tenant_id, status
UNIQUE tenant_id, employee_id, date, shift_schedule_id
```

### leave_requests

```txt
INDEX tenant_id, employee_id, status
INDEX tenant_id, branch_id, status
INDEX tenant_id, start_date, end_date
INDEX tenant_id, current_approver_id, status
```

### approval_requests

```txt
INDEX tenant_id, approvable_type, approvable_id
INDEX tenant_id, requester_id, status
INDEX tenant_id, current_approver_id, status
INDEX tenant_id, status, created_at
```

### payroll_runs

```txt
INDEX tenant_id, payroll_period_id, status
INDEX tenant_id, branch_id, status
INDEX tenant_id, status, created_at
```

### payroll_run_items

```txt
INDEX tenant_id, payroll_run_id
INDEX tenant_id, employee_id
INDEX tenant_id, payroll_period_id, employee_id
UNIQUE payroll_run_id, employee_id
```

## 6. Eager Loading Wajib per Halaman

### 6.1 Karyawan DataTable

Relasi wajib:

```php
Karyawan::query()
    ->with([
        'user:id,employee_id,name,email,status',
        'branch:id,name,code',
        'department:id,name,code',
        'position:id,name,code',
        'jobLevel:id,name',
        'manager:id,full_name,employee_number',
    ])
    ->select([
        'id', 'tenant_id', 'user_id', 'branch_id', 'department_id',
        'position_id', 'job_level_id', 'manager_id', 'employee_number',
        'full_name', 'email', 'employment_status', 'join_date', 'status'
    ]);
```

### 6.2 Attendance DataTable

```php
Attendance::query()
    ->with([
        'employee:id,employee_number,full_name,branch_id,department_id,position_id',
        'employee.branch:id,name,code',
        'employee.department:id,name,code',
        'shift:id,name,start_time,end_time',
        'workLocation:id,name,latitude,longitude,radius_meter',
    ])
    ->select([
        'id', 'tenant_id', 'employee_id', 'shift_id', 'work_location_id',
        'date', 'clock_in_at', 'clock_out_at', 'status', 'location_status'
    ]);
```

### 6.3 Leave Request DataTable

```php
LeaveRequest::query()
    ->with([
        'employee:id,employee_number,full_name,branch_id,department_id,position_id',
        'employee.branch:id,name,code',
        'leaveType:id,name,code',
        'currentApprover:id,name,email',
        'approvalRequest:id,approvable_type,approvable_id,status,current_approver_id',
    ])
    ->select([
        'id', 'tenant_id', 'employee_id', 'leave_type_id', 'start_date',
        'end_date', 'total_days', 'status', 'current_approver_id', 'created_at'
    ]);
```

### 6.4 Payroll Review DataTable

```php
PayrollRunItem::query()
    ->with([
        'employee:id,employee_number,full_name,branch_id,department_id,position_id',
        'employee.branch:id,name,code',
        'employee.department:id,name,code',
        'payrollRun:id,tenant_id,payroll_period_id,status',
        'payrollRun.period:id,name,start_date,end_date',
    ])
    ->select([
        'id', 'tenant_id', 'payroll_run_id', 'payroll_period_id', 'employee_id',
        'gross_salary', 'total_allowance', 'total_deduction', 'bpjs_employee_total',
        'pph21_total', 'net_salary', 'status'
    ]);
```

## 7. Query Scope Laravel

### 7.1 Tenant Scope

Semua model bisnis memakai scope:

```php
public function scopeForTenant($query, int|string $tenantId)
{
    return $query->where('tenant_id', $tenantId);
}
```

### 7.2 Branch Scope

```php
public function scopeForBranches($query, array $branchIds)
{
    return $query->whereIn('branch_id', $branchIds);
}
```

### 7.3 Data Scope Access

```txt
own     → employee_id = current employee id
team    → manager_id in subordinate tree
branch  → branch_id in user_branch_accesses
company → tenant_id only
```

## 8. Pattern Controller untuk DataTable

```php
public function index(KaryawanIndexRequest $request)
{
    $user = $request->user();

    $query = Karyawan::query()
        ->forTenant($user->tenant_id)
        ->with(['branch:id,name', 'department:id,name', 'position:id,name'])
        ->when($request->search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
        ->when($request->status, fn ($q, $status) => $q->where('status', $status))
        ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc');

    $query = app(DataScopeService::class)->apply($query, $user, 'employees');

    return inertia('Karyawans/Index', [
        'employees' => KaryawanResource::collection(
            $query->paginate($request->integer('per_page', 10))->withQueryString()
        ),
        'filters' => $request->only(['search', 'branch_id', 'status', 'sort', 'direction', 'per_page']),
    ]);
}
```

## 9. Performance Rules

```txt
- Semua index page wajib server-side pagination.
- Jangan load semua data ke frontend.
- Jangan pakai ->all() untuk list besar.
- Gunakan ->paginate() atau cursorPaginate() untuk log besar.
- Gunakan select kolom yang dibutuhkan saja.
- Gunakan withCount untuk jumlah relasi, bukan load relasi penuh.
- Gunakan exists untuk validasi cepat.
- Gunakan cache untuk feature tenant, permission user, dan setting company.
- Audit log dan attendance log besar boleh pakai partitioning per bulan jika data sudah sangat besar.
```

## 10. Cache yang Disarankan

```txt
tenant_features:{tenant_id}
user_permissions:{user_id}
user_branch_access:{user_id}
company_settings:{tenant_id}
payroll_settings:{tenant_id}
leave_policy:{tenant_id}
```

Cache harus di-refresh saat Super Admin/Admin Tenant / HR mengubah fitur, role, permission, branch access, atau setting policy.

## 11. Checklist Claude Code

```txt
[ ] Semua migration punya tenant_id jika termasuk data client.
[ ] Semua FK dibuat dengan constrained.
[ ] Semua tabel besar punya index tenant_id + filter utama.
[ ] Semua DataTable memakai server-side pagination.
[ ] Semua controller memakai eager loading.
[ ] Semua controller memakai select kolom yang dibutuhkan.
[ ] Semua controller memakai tenant scope.
[ ] Semua controller memakai data scope.
[ ] Semua route protected by feature + permission middleware.
[ ] Semua export query menggunakan chunking.
[ ] Semua payroll result menyimpan snapshot JSON.
```
