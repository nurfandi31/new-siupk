# Roadmap & Arsitektur siupk Next Desktop App (NativePHP + SQLite Read-Only Sync)

Dokumen ini memetakan rancangan arsitektur, strategi sinkronisasi data, pembagian tanggung jawab sistem, dan tahapan implementasi aplikasi desktop **siupk Next**.

---

## 1. Arsitektur Inti: *Cloud Source of Truth + Desktop Read-Only SQLite*

```
   +---------------------------------------------------------+
   |             CLOUD SERVER (SOURCE OF TRUTH)              |
   |  - Multi-Tenant Sharding (MySQL / MariaDB)              |
   |  - Master Business Logic, Ledger, All Mutations         |
   |  - Snapshot & Delta Exporter Engine                     |
   +----------------------------+----------------------------+
                                |
                                | HTTPS Pull Sync (Token Auth)
                                | JSON Snapshot with SHA256
                                v
   +---------------------------------------------------------+
   |                siupk DESKTOP CLIENT APP                 |
   |  - Framework : Electron + Embedded PHP/Laravel          |
   |  - Database  : Local SQLite (Single Tenant Lock)        |
   |  - Mode      : Hybrid (Online Read/Write + Offline Read)|
   +---------------------------------------------------------+
```

### Prinsip Utama Sistem:
1. **Single Source of Truth:** Seluruh data master, mutasi transaksi (`POST`, `PUT`, `DELETE`), dan penutupan buku hanya valid jika disahkan oleh Cloud Server.
2. **Desktop SQLite Storage:** Desktop memiliki database SQLite lokal yang dikunci untuk 1 tenant aktif per instalasi perangkat.
3. **Strict Read-Only saat Offline:** Ketika koneksi internet terputus (`is_offline = true`), pengguna tetap dapat membuka dashboard, melihat nasabah, membaca buku kas/jurnal, dan mencetak laporan PDF dari SQLite lokal. Semua tombol/aksi mutasi dikunci demi mencegah risiko *merge-conflict*.
4. **Sinkronisasi Satu Arah (*One-Way Pull Sync*):** Data selalu mengalir dari Server Cloud $\rightarrow$ Desktop SQLite lokal.
5. **Native OS Push Notifications (Zero Firebase):** Memanfaatkan Electron Native Notification API dan integrasi Windows Action Center dengan atribusi pelaku *"oleh {siapa}"*.

---

## 2. Fitur Desktop & Offline

| Fitur | Online (Server Cloud) | Offline (Local SQLite) |
|---|:---:|:---:|
| Login & Autentikasi Pengguna | ✅ Server Auth | ✅ Sesi Tersimpan / Offline Cache |
| Lihat Dashboard & Statistik | ✅ Realtime Cloud | ✅ Dari SQLite Lokal |
| Lihat Daftar Pinjaman & Angsuran | ✅ Realtime Cloud | ✅ Dari SQLite Lokal |
| Cetak Laporan Keuangan (PDF / Excel) | ✅ Realtime Cloud | ✅ Dari SQLite Lokal |
| Cari Nasabah & Kelompok | ✅ Realtime Cloud | ✅ Dari SQLite Lokal |
| Ajukan Pinjaman / Catat Angsuran | ✅ Diizinkan | 🔒 Dikunci (Hanya Baca) |
| Input Jurnal Umum / Mutasi Kas | ✅ Diizinkan | 🔒 Dikunci (Hanya Baca) |
| Tutup Buku Bulanan / Tahunan | ✅ Diizinkan | 🔒 Dikunci (Hanya Baca) |
| Native OS Notifications | ✅ Action Center | ✅ Sesuai Data Terakhir |
| Atribusi Pelaku ("oleh {siapa}") | ✅ Tercatat User Realtime | ✅ Terarsip dalam Notifikasi |

---

## 3. Rencana Kerja & Tahapan Implementasi (Roadmap)

### FASE 1: Server-Side Sync Engine & Snapshot API
> Tujuan: Menyediakan endpoint aman di cloud server untuk mengekspor data tenant ke desktop client.

- [x] **1.1. Service Snapshot Exporter**
  - Implementasi `app/Domain/Sync/Services/TenantSnapshotService.php`.
  - Ekspor seluruh tabel data tenant (43 tabel shard) secara topologis aman dengan checksum SHA256.
  - Mendukung ekspor penuh (*snapshot*) dan ekspor perubahan berkala (*delta sync* berbasis timestamp `updated_at`).
- [x] **1.2. Controller & Routing Sync API**
  - Implementasi `app/Http/Controllers/Api/Desktop/DesktopSyncController.php`.
  - Endpoint: `GET /api/v1/desktop/sync/tenants/{tenant}/status`, `GET /api/v1/desktop/sync/tenants/{tenant}/snapshot`, `GET /api/v1/desktop/sync/tenants/{tenant}/delta`.
- [x] **1.3. Middleware Keamanan Desktop API Token**
  - Middleware `app/Http/Middleware/VerifyDesktopApiToken.php` (`desktop.auth`) memvalidasi Bearer Token / Header `X-Desktop-Key` / `X-API-Key`.
- [x] **1.4. Pengujian Automated Backend**
  - Feature test `Tests\Feature\Api\DesktopSyncApiTest` (8 tests passed 100%).

---

### FASE 2: Desktop Foundation & Single Tenant Lock
> Tujuan: Menyiapkan fondasi runtime desktop, driver database SQLite lokal, dan isolasi tenant tunggal.

- [x] **2.1. Konfigurasi & Service Provider Desktop**
  - File konfigurasi `config/desktop.php` dan template environment `.env.desktop.example`.
  - Service provider `app/Providers/DesktopAppServiceProvider.php` (auto-create SQLite database, session file driver, queue sync).
- [x] **2.2. Command Artisan Inisialisasi Desktop**
  - `php artisan desktop:init` (`app/Console/Commands/DesktopInitCommand.php`): otomatis membuat file SQLite dan menjalankan 27 migrasi shard tenant.
  - `php artisan desktop:status` (`app/Console/Commands/DesktopStatusCommand.php`): cek status koneksi database SQLite lokal dan ringkasan data.
- [x] **2.3. Pengujian Automated Backend**
  - Feature test `Tests\Feature\Desktop\DesktopFoundationTest` (5 tests passed 100%).

---

### FASE 3: Desktop Sync Engine & Local SQLite Ingestion
> Tujuan: Memproses snapshot dari cloud server dan mengimpornya ke database SQLite lokal secara transaksional.

- [x] **3.1. Ingestion Engine Service**
  - Implementasi `app/Domain/Sync/Services/DesktopSnapshotIngestionService.php`.
  - Membaca snapshot data, memvalidasi SHA256 checksum, dan melakukan *upsert* aman ke tabel SQLite lokal dalam transaksi database.
- [x] **3.2. Sync Client Service**
  - Implementasi `app/Domain/Sync/Services/DesktopSyncClientService.php`.
  - Menghubungi endpoint server cloud, mengunduh data snapshot/delta, mengukur latensi ping server.
- [x] **3.3. Command & Controller Sync Client**
  - Command `php artisan desktop:sync` (`app/Console/Commands/DesktopSyncCommand.php`).
  - Endpoint `GET /desktop/sync/status` dan `POST /desktop/sync/trigger` (`app/Http/Controllers/DesktopClientController.php`).
- [x] **3.4. Pengujian Automated Backend**
  - Feature test `Tests\Feature\Desktop\DesktopSyncEngineTest` (5 tests passed 100%).

---

### FASE 4: Read-Only Guard, Offline UX, Titlebar & Push Notifications
> Tujuan: Memastikan pengguna nyaman saat offline, mencegah aksi mutasi secara elegan, custom titlebar, serta notifikasi native OS.

- [x] **4.1. Middleware `BlockOfflineMutations`**
  - Middleware `app/Http/Middleware/BlockOfflineMutations.php` terdaftar di web stack dan alias `offline.guard`.
  - Mengizinkan safe read (`GET`, `HEAD`, `OPTIONS`) dan whitelist auth/sync, namun menolak mutasi (`POST`, `PUT`, `PATCH`, `DELETE`) saat offline dengan kode error `OFFLINE_READ_ONLY_GUARD` (HTTP 403 / redirect flash error).
- [x] **4.2. Inertia Shared Props Desktop**
  - Shared props `desktop` (`is_desktop`, `app_version`, `server_url`, `is_offline`) di `app/Http/Middleware/HandleInertiaRequests.php`.
- [x] **4.3. UI Composable & Banner Mode Offline**
  - Composable `resources/js/composables/useAppMode.js` menyediakan state reaktif `isOffline`, `isDesktop`, dan `isReadOnly`.
  - Komponen `resources/js/Components/AppOfflineBanner.vue` memberikan notifikasi visual *"Mode Offline (Hanya Baca)"* yang elegan.
- [x] **4.4. Custom Frameless Titlebar & Auto-Logout saat Close**
  - Komponen `resources/js/Components/DesktopTitleBar.vue` menggantikan menu bar bawaan Electron.
  - Sisi kiri: status koneksi 🟢 Online / 🟡 Offline dan tombol Sinkron Cepat.
  - Sisi tengah: judul tenant yang dapat di-drag.
  - Sisi kanan: tombol minimize, maximize/restore, dan close yang secara otomatis melakukan logout pengguna sebelum jendela aplikasi ditutup.
- [x] **4.5. Native OS Push Notifications & Atribusi Pelaku ("oleh {siapa}")**
  - IPC Notifikasi Native Windows Action Center tanpa Firebase (`desktop:send-notification`).
  - Dropdown & Notification Center menyertakan chip badge *"oleh {Nama User / Kasir / Peminjam}"* untuk setiap aksi (pengajuan pinjaman, penerimaan angsuran, pencatatan jurnal umum, tunggakan).
  - Background live notification poller di frontend memunculkan notifikasi pop-up desktop saat ada aksi baru dari user lain.
- [x] **4.6. Pengujian Automated Backend**
  - Feature test `Tests\Feature\Desktop\DesktopReadOnlyGuardTest` (5 tests passed 100%).
  - Feature test `Tests\Feature\Notifications\NotificationCenterTest` (6 tests passed 100%).

---

### FASE 5: Build, Packaging, Distribusi Windows & Pengerasan SQLite
> Tujuan: Menghasilkan file installer desktop yang siap dipasang oleh pengguna tenant dengan perlindungan data lokal dan keamanan token sinkronisasi.

- [ ] **5.1. Build Script & Assets Packaging**
  - Optimasi build frontend (`npm run build`).
  - Konfigurasi metadata aplikasi (`package.json`, nama app, publisher, icon `.ico`).
- [ ] **5.2. Generate Windows Installer (.exe / NSIS)**
  - Konfigurasi electron-builder untuk Windows installer.
  - Pengujian instalasi di mesin Windows bersih.
- [ ] **5.3. Pengujian Skenario Lengkap (Online -> Putus Internet -> Buka Laporan -> Online Kembali)**.
- [x] **5.4. Pengerasan Lokasi SQLite, Pre-Update Backup & Proteksi Token Sync**
  - Pemindahan default database SQLite ke `app.getPath('userData')/database/database.sqlite` beserta migrasi otomatis file legacy saat startup.
  - Backup otomatis file SQLite aktif (`<nama>.bak-YYYYMMDD-HHmmss`) sebelum `quitAndInstall()` dijalankan oleh auto-updater dengan retensi maksimal 3 cadangan terbaru.
  - Pengerasan middleware `VerifyDesktopApiToken`: token via query parameter dihapus (`api_key`/`desktop_key`), mewajibkan header Bearer / `X-Desktop-Key` / `X-API-Key`, serta penolakan wajib (401) di environment `production` bila `DESKTOP_SYNC_API_KEY` tidak dikonfigurasi.

---

## 4. Log Perubahan & Status Pelaksanaan

| Tanggal | Fase | Item Pekerjaan | Status | Catatan |
|---|:---:|---|:---:|---|
| 2026-08-21 | - | Perancangan Roadmap & Arsitektur Desktop | Dokumen Dibuat | Disepakati arsitektur NativePHP + One-Way Pull SQLite Read-Only |
| 2026-08-21 | Fase 1 | Implementasi Endpoint Snapshot & Delta Sync API | Selesai | TenantSnapshotService, DesktopSyncController, VerifyDesktopApiToken, dan 6 tests PHPUnit |
| 2026-08-21 | Fase 2 | Setup NativePHP & Desktop Foundation | Selesai | DesktopAppServiceProvider, Electron bridge, desktop:init SQLite command, .env.desktop.example, dan test suite |
| 2026-08-21 | Fase 3 | Desktop Sync Engine (Client Pull & Ingestion) | Selesai | DesktopSnapshotIngestionService, DesktopSyncClientService, DesktopSyncCommand, DesktopClientController, dan 5 tests PHPUnit |
| 2026-08-21 | Fase 4 | Read-Only Guard & Offline UI/UX Experience | Selesai | BlockOfflineMutations, HandleInertiaRequests desktop props, useAppMode.js, AppOfflineBanner, dan 5 tests PHPUnit |
| 2026-08-21 | UI/UX | Custom Frameless Titlebar & Auto-Logout on Close | Selesai | DesktopTitleBar.vue, frameless window Electron, online/offline badge, kontrol window, auto-logout saat close |
| 2026-08-21 | Notifikasi | Native OS Push Notifications & Atribusi "oleh {siapa}" | Selesai | NotificationCenterController actor attribution, NotificationDropdown chip & auto-push, DesktopTitleBar sync actor, 6 tests PHPUnit |
| 2026-08-21 | UI/UX | Splash Screen & Exit Goodbye Screen Animation | Selesai | DesktopSplashScreen.vue animasi pembuka "Selamat Datang" & penutup "Sampai Jumpa" saat auto-logout |
| 2026-09-04 | Fase 5 | Pengerasan SQLite, Backup Pre-Update, Token Hardening | Selesai | Lokasi SQLite di userData, auto-backup pre-update (max 3), token produksi wajib, query param token dihapus, 8 tests passed |
