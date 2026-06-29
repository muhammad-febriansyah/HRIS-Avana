# Claude Code Prompts & Tasklist — AvanaHR

## 1. Cara Pakai

Jangan kasih prompt terlalu besar seperti “buatkan HRIS full”. Pecah per foundation dan modul. Mulai dari UI foundation, multi-tenant, RBAC, DataTable, baru modul HR.

## 2. Prompt 01 — UI Foundation

```txt
Bangun UI foundation AvanaHR menggunakan Laravel 13, React Inertia TypeScript, Tailwind CSS 4, shadcn/ui, dan lucide-react.

Kebutuhan wajib:
1. AppLayout dengan Sidebar collapsible.
2. Topbar sederhana.
3. Setiap page wajib punya Breadcrumb.
4. Reusable PageHeader dengan title, description, actions.
5. Semua content utama wajib dibungkus Card.
6. Card style clean: rounded-xl, border-slate-200/70, shadow-sm, tidak boleh shadow tebal.
7. Button aksi wajib icon + text.
8. Delete button wajib warna merah/destructive, icon Trash2, text Hapus, dan AlertDialog confirmation.
9. Buat reusable RequiredLabel dengan * merah.
10. Semua form field wajib punya placeholder dan error message.
11. Buat RupiahInput untuk nominal uang.
12. Buat DatePickerField dari shadcn Calendar + Popover.
13. Buat RichTextEditor menggunakan TipTap.
14. Buat StatusBadge.
15. Toast success/error pakai Sonner.

Aturan:
- UI clean enterprise.
- Jangan pakai shadow-xl atau shadow-2xl.
- Gunakan TypeScript type yang rapi.
- Semua component reusable simpan di resources/js/Components.
```

## 3. Prompt 02 — DataTable Foundation

```txt
Buat reusable DataTable foundation untuk AvanaHR menggunakan @tanstack/react-table dan shadcn/ui.

Component yang harus dibuat:
1. DataTable.tsx
2. DataTableToolbar.tsx
3. DataTablePagination.tsx
4. DataTableColumnHeader.tsx
5. DataTableViewOptions.tsx
6. DataTableFacetedFilter.tsx
7. DataTableBulkActions.tsx
8. DataTableEmptyState.tsx
9. DataTableRowActions.tsx

Fitur wajib:
- Server-side pagination via Inertia router.get.
- Search sync ke URL.
- Filter sync ke URL.
- Sort sync ke URL.
- Rows per page.
- Column visibility.
- Row selection checkbox.
- Bulk action.
- Row action dropdown.
- Export button placeholder.
- Loading skeleton.
- Empty state.
- Permission-based action rendering.
- Responsive horizontal scroll.

Aturan UI:
- DataTable wajib dibungkus Card.
- Toolbar di atas table.
- Pagination di bawah table.
- Row action wajib icon + text.
- Delete action wajib Trash2 + Hapus + destructive + AlertDialog.
```

## 4. Prompt 03 — Multi Tenant + Feature Toggle

```txt
Bangun foundation multi-tenant dan feature toggle untuk AvanaHR.

Kebutuhan:
1. Tenant management untuk Super Admin.
2. Package management.
3. Feature master.
4. Package features.
5. Tenant feature override.
6. Middleware EnsureTenantIsActive.
7. Middleware EnsureTenantFeatureEnabled.
8. Sidebar menu hide/show berdasarkan feature tenant.
9. Backend route protection untuk feature nonaktif.
10. Audit log untuk perubahan feature.

Aturan:
- Semua data tenant wajib menggunakan tenant_id.
- Super Admin bisa melihat semua tenant.
- Admin Tenant / HR hanya bisa melihat tenant miliknya.
- Feature nonaktif tidak boleh bisa diakses via URL langsung.
```

## 5. Prompt 04 — RBAC + Branch Access

```txt
Bangun Role Based Access Control dan Branch Access untuk AvanaHR.

Kebutuhan:
1. Role management per tenant.
2. Permission master dengan format module.action.
3. Assign permission to role.
4. Assign role to user.
5. Assign branch access to user.
6. Data scope: own, team, branch, company.
7. Middleware EnsureUserHasPermission.
8. Middleware EnsureUserCanAccessBranch.
9. Policy/helper untuk filter query berdasarkan data scope.
10. UI Permission Matrix menggunakan Card, Accordion, Checkbox.
11. UI BranchAccessSelector menggunakan Card dan Checkbox.

Aturan:
- Permission tidak boleh hanya frontend.
- Semua query employee/attendance/leave/payroll harus difilter tenant dan branch scope.
- Tombol aksi hide/show berdasarkan permission.
```

## 6. Prompt 05 — Company Setup

```txt
Buat modul Company Setup AvanaHR.

Halaman:
1. Company Profile.
2. Branch.
3. Work Location dengan latitude, longitude, radius geofence.
4. Department.
5. Position.
6. Job Level.
7. Shift master.

UI rules:
- Semua page wajib Breadcrumb.
- Semua page content dibungkus Card.
- Data list pakai reusable DataTable.
- Form wajib placeholder.
- Field required wajib * merah.
- Date memakai DatePickerField.
- Nominal jika ada memakai RupiahInput.
- Action button wajib icon + text.
```

## 7. Prompt 06 — Employee Database

```txt
Buat modul Employee Database AvanaHR.

Fitur:
1. Employee list dengan DataTable.
2. Create employee.
3. Edit employee.
4. Detail employee dengan Tabs:
   - Overview
   - Personal Data
   - Employment
   - Attendance
   - Leave
   - Payroll
   - Documents
   - Audit Log
5. Upload dokumen employee.
6. Archive employee.
7. Filter by branch, department, status.

Field penting:
- Nama
- Email
- NIK
- Employee ID
- Phone
- Gender
- Birth date
- Join date
- Branch
- Department
- Position
- Manager
- Employment status
- Contract start/end
- Basic salary memakai RupiahInput

UI rules:
- Semua form required pakai * merah.
- Semua input punya placeholder.
- Tanggal pakai DatePickerField.
- Nominal pakai RupiahInput.
- Delete/archive pakai AlertDialog.
```

## 8. Prompt 07 — Attendance + Correction

```txt
Buat modul Attendance AvanaHR.

Fitur:
1. Employee clock in.
2. Employee clock out.
3. Simpan GPS latitude longitude.
4. Upload selfie.
5. Validasi geofence berdasarkan work location.
6. Attendance status otomatis.
7. Admin attendance logs DataTable.
8. Attendance correction request.
9. Approval correction by manager/HR.
10. Audit log untuk koreksi.

UI:
- Clock in/out pakai Card besar.
- Attendance logs pakai DataTable.
- Correction form pakai Card.
- Detail correction pakai Sheet.
- Approve/reject pakai AlertDialog.
```

## 9. Prompt 08 — Leave, WFH, Overtime

```txt
Buat modul request AvanaHR: Leave, WFH, Izin Jam, Overtime.

Fitur:
1. Leave type master.
2. Leave balance.
3. Leave request.
4. WFH request.
5. Permission/izin jam request.
6. Overtime request.
7. Approval workflow.
8. Team calendar basic.
9. Request history.

UI:
- Semua request list pakai DataTable.
- Request form pakai DatePicker/DateRangePicker.
- Reject wajib isi alasan.
- Semua request detail pakai Sheet.
- Status pakai StatusBadge.
```

## 10. Prompt 09 — Payroll Basic + BPJS + PPh 21 No API

```txt
Buat modul Payroll Basic AvanaHR tanpa integrasi API BPJS/DJP.

Kebutuhan:
1. Payroll period.
2. Payroll component.
3. Employee salary component.
4. BPJS program master.
5. BPJS rate config dengan effective date.
6. Employee BPJS profile.
7. Tax profile.
8. PPh 21 TER rate table.
9. Payroll run.
10. Pull attendance, leave, overtime, salary component.
11. Calculate gross salary.
12. Calculate BPJS internal.
13. Calculate PPh 21 internal.
14. Calculate net salary.
15. Save calculation snapshot JSON.
16. Review payroll.
17. Approve payroll.
18. Generate payslip.
19. Publish payslip.
20. Export Excel/PDF placeholder.

UI:
- Payroll run pakai PayrollStepper.
- Semua nominal pakai format rupiah.
- Review payroll pakai DataTable.
- Detail payroll employee pakai Sheet.
- Locked payroll tidak bisa diedit.
- Publish dan lock wajib confirmation dialog.
```

## 11. Global Checklist untuk Claude Code

Setiap kali selesai generate modul, pastikan:

```txt
[ ] Migration dibuat rapi.
[ ] Model relationship lengkap.
[ ] Controller/action menggunakan eager loading.
[ ] Request validation ada.
[ ] Policy/middleware akses ada.
[ ] Query pakai tenant scope.
[ ] Query pakai branch/data scope jika relevan.
[ ] Index page pakai DataTable.
[ ] Page punya breadcrumb.
[ ] Page content dibungkus Card.
[ ] Form punya placeholder.
[ ] Required field punya * merah.
[ ] Date pakai DatePickerField.
[ ] Rupiah pakai RupiahInput.
[ ] Action button pakai icon + text.
[ ] Delete pakai AlertDialog.
[ ] Toast pakai Sonner.
[ ] Loading skeleton ada.
[ ] Empty state ada.
[ ] Permission hide/show button.
[ ] Feature toggle hide/show menu dan protect route.
```
