# AvanaHR — Paket PRD & UI Requirement

Dokumen ini dibuat untuk membantu pengembangan **AvanaHR**, HRIS/HCMS SaaS berbasis **Laravel 13 + React Inertia TypeScript + Tailwind CSS 4 + shadcn/ui**.

## Daftar Dokumen

1. `01_PRD_Overview_AvanaHR.md`  
   Ringkasan produk, tujuan bisnis, target user, role, scope MVP, roadmap, dan success metrics.

2. `02_PRD_Flow_Sistem_AvanaHR.md`  
   Flow proses utama: setup client, employee database, absensi, cuti, koreksi, lembur, payroll, recruitment, onboarding, offboarding.

3. `03_PRD_Modul_MVP_AvanaHR.md`  
   Detail modul MVP, prioritas P0/P1/P2, acceptance criteria, dan entity data utama.

4. `04_PRD_Multi_Tenant_RBAC_Feature_Toggle.md`  
   Kebutuhan multi-tenant, 4 role utama, hak akses user, branch access, role-permission, data scope, dan fitur per client dari Super Admin.

5. `05_PRD_Payroll_BPJS_PPH21_No_API.md`  
   Pendekatan payroll, BPJS, dan PPh 21 tanpa API: internal calculation, export Excel/PDF, manual reporting, dan calculation snapshot.

6. `06_UI_Requirements_AvanaHR.md`  
   Aturan UI utama: setiap page wajib pakai breadcrumb, content dibungkus Card, shadow/border halus, button icon + text, form required pakai `*` merah, placeholder, rupiah input, date picker shadcn, rich editor.

7. `07_UI_Shadcn_Component_Map.md`  
   Mapping komponen shadcn/ui, DataTable, custom component, component install list, dan mapping per halaman.

8. `08_Claude_Code_Prompts_Tasklist.md`  
   Prompt dan task list bertahap untuk Claude Code Opus 4.8 xhigh agar hasil coding lebih rapi.

## Prinsip Utama Pengembangan

- Bangun **foundation** dulu: multi-tenant, RBAC, feature toggle, DataTable, layout, UI component.
- Jangan langsung bangun semua modul HRIS enterprise.
- Semua halaman harus konsisten: `Breadcrumb → PageHeader → Card → Content`.
- Semua data transaksi wajib punya `tenant_id`.
- Semua akses data wajib difilter berdasarkan role, permission, branch, department, manager/team, dan tenant.
- Modul BPJS dan PPh 21 pada MVP menggunakan perhitungan internal dan export manual, belum API.

## Stack

```txt
Backend     : Laravel 13
Frontend    : React + Inertia.js + TypeScript
Styling     : Tailwind CSS 4
UI Kit      : shadcn/ui
Table       : @tanstack/react-table
Icon        : lucide-react
Editor      : TipTap / Plate Editor
Date        : shadcn Calendar + Popover + date-fns
Currency    : custom RupiahInput
Notification: Sonner
```

9. `09_Database_Architecture_Eager_Loading_Indexing.md`
   Rancangan database, indexing, eager loading, query scope, dan performance checklist.

10. `10_PRD_Flutter_Employee_App.md`
   PRD aplikasi Flutter untuk karyawan dan manager approval.

11. `11_PRD_Role_Akses_4_Roles_AvanaHR.md` — Keputusan final 4 role: Super Admin, Admin Tenant / HR, Manager, Karyawan.
