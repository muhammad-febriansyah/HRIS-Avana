# UI Requirement — AvanaHR

## 1. Tujuan UI

UI AvanaHR harus terlihat clean, modern, rapi, dan enterprise. Karena sistem HRIS memiliki banyak data dan form, fokus utama UI adalah konsistensi, keterbacaan, navigasi jelas, DataTable kuat, dan form yang nyaman digunakan.

## 2. Stack UI

```txt
React Inertia TypeScript
Tailwind CSS 4
shadcn/ui
lucide-react
@tanstack/react-table
Sonner
TipTap / Plate Editor
shadcn Calendar + Popover
```

## 3. Global UI Rules

### 3.1 Setiap Page Wajib Pakai Breadcrumb

Setiap halaman wajib punya breadcrumb di bagian atas.

Contoh:

```txt
Dashboard / HR Core / Employees / Detail
Dashboard / Payroll / Payroll Run / Review
Dashboard / Settings / Roles / Edit
```

### 3.2 Struktur Page Wajib Konsisten

Setiap page wajib menggunakan struktur:

```txt
Page
├── Breadcrumb
├── PageHeader
│   ├── Title
│   ├── Description
│   └── Action Button
└── Card Wrapper
    └── Page Content
```

### 3.3 Semua Content Utama Wajib Dibungkus Card

Semua halaman, table, form, detail, dan dashboard section wajib dibungkus dengan `Card` agar clean.

Style Card:

```txt
rounded-xl
border border-slate-200/70
shadow-sm atau tanpa shadow
bg-white
p-4 atau p-6
```

Jangan gunakan shadow terlalu tebal.

Dilarang:

```txt
shadow-xl
shadow-2xl
border terlalu gelap
background terlalu ramai
```

Direkomendasikan:

```txt
shadow-sm
border-slate-200/70
bg-white
rounded-xl
```

## 4. Button Rules

### 4.1 Semua Button Aksi Wajib Pakai Icon + Text

Semua button aksi harus punya icon dari `lucide-react` dan teks.

Contoh benar:

```txt
Eye + Lihat
Pencil + Edit
Trash2 + Hapus
Plus + Tambah
Download + Export
Upload + Import
Check + Approve
X + Reject
Save + Simpan
```

### 4.2 Warna Button Aksi

| Aksi | Icon | Variant/Warna |
|---|---|---|
| Tambah | Plus | default / primary |
| Lihat | Eye | outline |
| Edit | Pencil | secondary / outline |
| Hapus | Trash2 | destructive / merah |
| Export | Download | outline |
| Import | Upload | outline |
| Approve | CheckCircle | default / success custom |
| Reject | XCircle | destructive |
| Simpan | Save | default |
| Batal | X | outline |

Catatan: Untuk tombol **Hapus**, gunakan icon `Trash2`, bukan `Eye`. Icon `Eye` dipakai untuk tombol **Lihat**.

### 4.3 Button Size

Gunakan button yang clean dan tidak terlalu besar:

```txt
h-9
text-sm
gap-2
rounded-lg
```

## 5. Form Rules

### 5.1 Field Required Wajib Pakai Bintang Merah

Semua field required harus ada tanda `*` merah di label.

Contoh:

```tsx
<Label>
  Nama Karyawan <span className="text-red-500">*</span>
</Label>
```

### 5.2 Semua Input Wajib Punya Placeholder

Contoh placeholder:

```txt
Masukkan nama karyawan
Masukkan email aktif
Pilih cabang
Pilih tanggal mulai
Masukkan nominal gaji pokok
```

### 5.3 Semua Form Wajib Punya Error Message

Setiap field harus menampilkan error dari Laravel validation/Inertia.

Contoh:

```txt
Nama karyawan wajib diisi.
Format email tidak valid.
Nominal gaji pokok wajib lebih dari 0.
```

### 5.4 Form Panjang Wajib Dipisah Section

Untuk form panjang seperti Employee, gunakan Card section atau Tabs.

Contoh Employee Form:

```txt
Card: Personal Information
Card: Employment Information
Card: Organization Placement
Card: Payroll Information
Card: BPJS & Tax Information
Card: Documents
```

Atau:

```txt
Tabs:
- Personal
- Employment
- Payroll
- Documents
- Access
```

## 6. Rupiah Input Rules

Semua input nominal uang wajib memakai format Rupiah.

### Tampilan UI

```txt
Rp 5.000.000
Rp 250.000
Rp 0
```

### Rule Teknis

- UI menampilkan format rupiah.
- Database menyimpan angka numeric/integer/decimal tanpa format.
- Saat user mengetik, otomatis diformat.
- Saat submit, kirim nilai numeric ke backend.

### Component Custom

Buat component:

```txt
RupiahInput.tsx
```

Fitur:

- Prefix `Rp`.
- Thousand separator titik.
- Decimal optional.
- Placeholder wajib.
- Bisa menerima error state.

Dipakai di:

```txt
Gaji Pokok
Tunjangan
Potongan
BPJS Wage
Payroll Component
Claim Amount
Loan Amount
Reimbursement
```

## 7. Date Picker Rules

Semua input tanggal wajib menggunakan Date Picker dari shadcn/ui.

Gunakan:

```txt
Calendar
Popover
Button
```

Format tampilan:

```txt
dd MMM yyyy
```

Contoh placeholder:

```txt
Pilih tanggal lahir
Pilih tanggal mulai kontrak
Pilih tanggal akhir cuti
```

Rule:

- Jangan pakai input date browser default untuk UI utama.
- Date picker wajib support disabled date jika diperlukan.
- Range date untuk cuti/payroll pakai DateRangePicker custom berbasis shadcn Calendar.

## 8. Rich Editor Rules

Untuk field konten panjang atau pengumuman, gunakan rich editor.

Rekomendasi:

```txt
TipTap Editor
atau Plate Editor
```

Dipakai di:

```txt
Announcement / Pengumuman
Policy / Kebijakan
Job Description
Onboarding Material
Training Content
Email Template
Notification Template
Helpdesk Knowledge Base
```

Editor minimal punya:

```txt
Bold
Italic
Underline
Bullet list
Numbered list
Link
Heading
Undo/Redo
```

## 9. DataTable Rules

DataTable adalah komponen core AvanaHR.

Wajib ada:

```txt
Search
Server-side pagination
Sorting
Filter
Column visibility
Row action dropdown
Bulk select
Bulk action
Export button
Loading skeleton
Empty state
Status badge
```

DataTable wajib dibungkus Card.

Struktur:

```txt
Card
├── CardHeader
│   ├── Title
│   └── Description
├── CardContent
│   ├── DataTableToolbar
│   ├── DataTable
│   └── DataTablePagination
```

## 10. Status Badge Rules

Gunakan Badge untuk status.

Contoh:

| Status | Style |
|---|---|
| Active | success / green |
| Draft | secondary |
| Pending | warning / amber |
| Approved | success / green |
| Rejected | destructive / red |
| Locked | outline |
| Published | default |
| Inactive | muted |

Buat custom component:

```txt
StatusBadge.tsx
```

## 11. Modal, Sheet, dan Dialog Rules

| Kebutuhan | Component |
|---|---|
| Konfirmasi hapus | AlertDialog |
| Approve/reject | AlertDialog / Dialog |
| Form cepat | Sheet |
| Detail request | Sheet |
| Mobile bottom panel | Drawer |
| Edit data kompleks | Page khusus, bukan modal kecil |

Rule:

- Delete wajib AlertDialog.
- Reject wajib input alasan.
- Detail approval lebih cocok pakai Sheet kanan.
- Form employee penuh jangan diletakkan di Dialog kecil.

## 12. Loading dan Empty State

Setiap page wajib punya loading dan empty state.

Gunakan:

```txt
Skeleton
Spinner
EmptyState custom
```

Empty state minimal menampilkan:

```txt
Icon
Title
Description
Action button jika user punya permission create
```

## 13. Responsive Rules

- Sidebar desktop collapsible.
- Mobile sidebar menggunakan Sheet/Drawer.
- Table harus horizontal scroll di mobile.
- Button action di mobile boleh menjadi DropdownMenu.
- Form grid desktop 2 kolom, mobile 1 kolom.

## 14. Page Template Standard

```tsx
<AppLayout>
  <Head title="Employees" />

  <PageBreadcrumb items={["Dashboard", "HR Core", "Employees"]} />

  <PageHeader
    title="Employees"
    description="Kelola data karyawan, kontrak, dokumen, dan informasi pekerjaan."
    actions={
      <Button>
        <Plus className="mr-2 h-4 w-4" />
        Tambah Employee
      </Button>
    }
  />

  <Card className="rounded-xl border-slate-200/70 shadow-sm">
    <CardHeader>
      <CardTitle>Daftar Karyawan</CardTitle>
      <CardDescription>Data karyawan aktif dan nonaktif.</CardDescription>
    </CardHeader>
    <CardContent>
      <DataTable columns={columns} data={employees.data} meta={employees.meta} />
    </CardContent>
  </Card>
</AppLayout>
```

## 15. UI Acceptance Criteria

Setiap halaman dianggap sesuai standar jika:

- Ada breadcrumb.
- Ada PageHeader.
- Content utama dibungkus Card.
- Shadow tidak tebal.
- Border halus.
- Button aksi pakai icon + text.
- Delete button merah dan pakai confirmation.
- Form required pakai `*` merah.
- Semua input punya placeholder.
- Nominal uang pakai RupiahInput.
- Tanggal pakai shadcn Date Picker.
- Konten panjang pakai rich editor.
- DataTable punya search, filter, pagination, loading, empty state.
- Toast success/error menggunakan Sonner.
