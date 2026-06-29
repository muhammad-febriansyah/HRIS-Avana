# UI Component Map — shadcn/ui untuk AvanaHR

## 1. Component Install List

Install komponen dasar:

```bash
npx shadcn@latest add button card badge avatar breadcrumb separator dropdown-menu
npx shadcn@latest add table checkbox input label textarea select popover calendar switch radio-group
npx shadcn@latest add dialog alert-dialog sheet drawer tabs accordion collapsible
npx shadcn@latest add alert sonner skeleton progress tooltip scroll-area command
npx shadcn@latest add pagination chart
npm install @tanstack/react-table lucide-react date-fns
```

Untuk editor:

```bash
npm install @tiptap/react @tiptap/starter-kit @tiptap/extension-link @tiptap/extension-underline
```

## 2. Reusable Layout Components

```txt
AppLayout
SidebarNav
Topbar
PageBreadcrumb
PageHeader
PageSection
CardShell
```

### AppLayout

Kebutuhan:

- Sidebar collapsible.
- Topbar berisi breadcrumb singkat/search/notification/user dropdown.
- Content area responsive.
- Sidebar menu filter berdasarkan feature dan permission.

### PageHeader

Props:

```txt
title
description
actions
```

### CardShell

Dipakai untuk membungkus semua page content.

Style default:

```txt
rounded-xl border border-slate-200/70 shadow-sm bg-white
```

## 3. DataTable Foundation

Buat folder:

```txt
resources/js/Components/DataTable/
├── DataTable.tsx
├── DataTableToolbar.tsx
├── DataTablePagination.tsx
├── DataTableColumnHeader.tsx
├── DataTableViewOptions.tsx
├── DataTableFacetedFilter.tsx
├── DataTableBulkActions.tsx
├── DataTableEmptyState.tsx
└── DataTableRowActions.tsx
```

### Fitur Wajib DataTable

```txt
Server-side pagination
Search sync URL
Filter sync URL
Sort sync URL
Rows per page
Column visibility
Row selection
Bulk action
Row action dropdown
Export button
Loading skeleton
Empty state
Permission-based action
Responsive horizontal scroll
```

### Query URL Standard

```txt
?page=1
&per_page=10
&search=febri
&sort=name
&direction=asc
&status=active
&branch_id=1
&department_id=2
```

## 4. Custom UI Components AvanaHR

```txt
StatusBadge
RupiahInput
DatePickerField
DateRangePickerField
RequiredLabel
FormError
FormSection
FileUploadDropzone
RichTextEditor
ConfirmActionDialog
DeleteButton
ActionButton
EmployeeAvatar
EmployeeQuickView
ApprovalTimeline
AuditLogTimeline
FeatureToggleGrid
PermissionMatrix
BranchAccessSelector
PayrollStepper
PayslipPreview
LeaveBalanceCard
AttendanceStatusBadge
EmptyState
```

## 5. Button Component Standard

### ActionButton

Props:

```txt
icon
children
variant
permission
onClick
href
```

Contoh mapping:

```txt
Lihat    → Eye + Lihat + outline
Edit     → Pencil + Edit + outline
Hapus    → Trash2 + Hapus + destructive
Tambah   → Plus + Tambah + default
Export   → Download + Export + outline
Import   → Upload + Import + outline
Approve  → CheckCircle + Approve + default/success
Reject   → XCircle + Reject + destructive
```

## 6. Form Component Standard

### RequiredLabel

```tsx
<RequiredLabel>Nama Karyawan</RequiredLabel>
```

Output:

```txt
Nama Karyawan *
```

### FormField Wrapper

Harus memuat:

```txt
Label
Input
Helper text optional
Error message
```

### RupiahInput

Dipakai untuk:

```txt
Gaji pokok
Tunjangan
Potongan
Klaim
Pinjaman
Budget training
Payroll component
```

### DatePickerField

Berbasis:

```txt
Button
Popover
Calendar
```

### RichTextEditor

Dipakai untuk:

```txt
Pengumuman
Kebijakan
Job description
Training content
Email template
```

## 7. Mapping Component per Modul

### Super Admin — Client/Tenant

| Page | Component |
|---|---|
| Client List | Breadcrumb, PageHeader, Card, DataTable, Badge, DropdownMenu |
| Create Client | Breadcrumb, PageHeader, Card, FormSection, Input, Select, Switch |
| Client Detail | Breadcrumb, PageHeader, Card, Tabs, Badge, FeatureToggleGrid |
| Package | Card, DataTable, Dialog, AlertDialog |
| Feature Management | Card, Table, Switch, Checkbox |

### Company Setup

| Page | Component |
|---|---|
| Company Profile | Card, FormSection, Input, Textarea, FileUpload |
| Branch | Card, DataTable, Sheet, Input, Switch |
| Work Location | Card, FormSection, Input, RupiahInput optional, Map custom, DatePicker |
| Department | Card, DataTable, Sheet |
| Position | Card, DataTable, Combobox |
| Approval Workflow | Card, Accordion, Select, Badge, custom builder |

### Employee

| Page | Component |
|---|---|
| Employee List | Card, DataTable, Avatar, Badge, DropdownMenu |
| Employee Create | Card, Tabs/FormSection, Input, Select, DatePicker, RupiahInput |
| Employee Detail | Card, Tabs, Avatar, Badge, Table, AuditLogTimeline |
| Documents | Card, FileUploadDropzone, DataTable, Badge |
| Contract | Card, DatePicker, Badge, Alert |

### Attendance

| Page | Component |
|---|---|
| Clock In/Out | Card, Button, Badge, custom Camera, Alert |
| Attendance Logs | Card, DataTable, DateRangePicker, Badge |
| Correction Request | Card, FormSection, DatePicker, Textarea, FileUpload |
| Review Correction | Card, DataTable, Sheet, AlertDialog |

### Leave / WFH / Overtime

| Page | Component |
|---|---|
| Leave Balance | Card, Progress, Badge |
| Leave Request | Card, DateRangePicker, Select, Textarea |
| Leave Approval | Card, DataTable, Sheet, AlertDialog |
| WFH Request | Card, DatePicker, Textarea |
| Overtime Request | Card, DatePicker, Input, Textarea |

### Payroll

| Page | Component |
|---|---|
| Payroll Period | Card, DataTable, Badge |
| Payroll Run | Card, PayrollStepper, Button, Alert |
| Payroll Review | Card, DataTable, Rupiah display, Sheet |
| Payslip | Card, PayslipPreview, Button, Badge |
| BPJS Setup | Card, DataTable, RupiahInput, DatePicker |
| PPh 21 Setup | Card, DataTable, RupiahInput, DatePicker |

### Role & Permission

| Page | Component |
|---|---|
| Role List | Card, DataTable, Badge |
| Role Form | Card, FormSection, Input, Textarea |
| Permission Matrix | Card, Accordion, Checkbox, Table |
| User Branch Access | Card, BranchAccessSelector, Checkbox |

## 8. Standard Page Checklist

Sebelum page dianggap selesai, cek:

```txt
[ ] Ada Breadcrumb
[ ] Ada PageHeader
[ ] Content dibungkus Card
[ ] Shadow halus, tidak tebal
[ ] Border halus
[ ] Button aksi pakai icon + teks
[ ] Delete pakai warna merah + Trash2 + AlertDialog
[ ] DataTable punya search/filter/pagination
[ ] DataTable punya loading skeleton
[ ] DataTable punya empty state
[ ] Form required pakai * merah
[ ] Semua input punya placeholder
[ ] Nominal pakai RupiahInput
[ ] Tanggal pakai shadcn DatePicker
[ ] Text panjang pakai RichTextEditor
[ ] Toast pakai Sonner
[ ] Route/action memperhatikan permission
[ ] Menu memperhatikan feature toggle
```

## 9. Status Badge Mapping

```txt
active            → green
inactive          → muted
pending           → amber
approved          → green
rejected          → red
draft             → secondary
calculated        → blue
reviewed          → purple
published         → green
locked            → outline
need_review       → amber
late              → amber
absent            → red
present           → green
leave             → blue
```

## 10. Recommended Folder Structure

```txt
resources/js/
├── Components/
│   ├── Layout/
│   ├── DataTable/
│   ├── Forms/
│   ├── Buttons/
│   ├── Badges/
│   ├── Dialogs/
│   └── AvanaHR/
├── Pages/
│   ├── Dashboard/
│   ├── SuperAdmin/
│   ├── CompanySetup/
│   ├── Employees/
│   ├── Attendance/
│   ├── Leave/
│   ├── Payroll/
│   └── Settings/
└── types/
```
