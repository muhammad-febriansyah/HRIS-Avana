# Deploy ke VPS

Langkah rilis untuk perubahan langganan (banner, perpanjangan mandiri via Pakasir,
lock saat lewat batas), entitlement fitur per paket, Hak Akses per menu, dan
halaman error berdesain.

## 0. Cek sebelum deploy (penting)

Lock langganan sekarang aktif: tenant yang `end_date`-nya sudah lewat **langsung
terkunci total** (web + API mobile, semua peran) begitu kode ini live.

```sql
SELECT id, name, status, end_date FROM tenants
WHERE end_date IS NOT NULL AND end_date < CURDATE();
```

Untuk tenant yang seharusnya tetap jalan: perpanjang `end_date`, atau set `NULL`
kalau memang tanpa masa berlaku.

## 1. Perintah di VPS

Jalankan dari root project:

```bash
git pull origin feat/finance

composer install --no-dev --optimize-autoloader

# 3 migrasi baru:
# - subscription_orders            (pesanan perpanjangan)
# - seed_langganan_permissions...  (izin `langganan` + menu baru utk semua tenant)
# - role_menu_visibility           (tampil/sembunyi menu per peran)
php artisan migrate --force

npm ci
npm run build:ssr        # WAJIB :ssr — config/inertia.php ssr.enabled = true

php artisan optimize:clear
php artisan optimize     # config + route + view cache

# Proses SSR menyimpan bundle di memori. Tanpa restart, halaman lama tetap
# tersaji dan muncul hydration error di browser.
php artisan inertia:stop-ssr      # supervisor akan menyalakan ulang
# kalau tidak disupervisi: php artisan inertia:start-ssr

php artisan queue:restart         # QUEUE_CONNECTION=database, ada job email/notifikasi
```

## 2. `.env` di VPS

```env
PAKASIR_SLUG=avanahr
PAKASIR_API_KEY=...          # tanpa ini verifikasi pembayaran gagal → langganan tidak pernah diperpanjang
APP_URL=https://domain-anda  # dipakai untuk URL callback Pakasir
```

`PAKASIR_BASE_URL` boleh dibiarkan (default `https://app.pakasir.com`).

## 3. Di luar server

1. **Dashboard Pakasir** — set webhook ke:
   ```
   https://domain-anda/api/v1/pakasir/webhook
   ```
   Endpoint ini melayani dua jenis order: `AIT-` (token AI) dan `SUB-`
   (perpanjangan langganan). Tanpa webhook, perpanjangan hanya diterapkan saat
   pembeli kembali sendiri dari halaman bayar.

2. **Cron scheduler** harus jalan — reminder kedaluwarsa ikut
   `avana:remind-billing` (harian 07:00):
   ```cron
   * * * * * cd /path/project && php artisan schedule:run >> /dev/null 2>&1
   ```

3. **Paket Langganan (opsional)** — Paket Langganan → Edit → centang **Modul yang
   Didapat** kalau fitur mau dibatasi per paket. Paket yang tidak mencentang apa
   pun tetap memberi semua modul, jadi tenant lama tidak kehilangan fitur.

## 4. Verifikasi setelah deploy

- Login admin tenant → sidebar ada menu **Langganan**; halamannya menampilkan
  kartu paket dengan harga per durasi (1 bulan / 3 bulan −5% / 1 tahun −15%).
- Buka URL ngawur (`/halaman-tidak-ada`) → halaman error berdesain (logo + kode),
  bukan tampilan bawaan Laravel.
- **Hak Akses** → ada tab per peran; tab Karyawan menunjukkan jumlah menu yang
  wajar (mis. "22 dari 105 menu terlihat"), bukan hampir semuanya.
- Jalankan sekali manual:
  ```bash
  php artisan avana:remind-billing
  ```
  Output memuat `sent N tenant admin reminder(s)`.
- Tenant yang mendekati habis (≤30 hari) melihat banner di atas konten, dengan
  tombol **Tanya via WhatsApp** dan **Perpanjang**.

## Catatan

- Tidak ada paket Composer/NPM baru pada rilis ini; `composer install` dan
  `npm ci` tetap dijalankan agar lockfile dan `vendor/` sinkron.
- Migrasi `seed_langganan_permissions_and_menu` menulis ulang menu default untuk
  **setiap** tenant (idempoten). Pada instance dengan banyak tenant, migrasi ini
  butuh waktu lebih lama dari biasanya — bukan hang.
- Tidak ada perubahan storage/symlink.
