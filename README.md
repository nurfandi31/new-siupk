# siupk Next

Aplikasi pembaruan **siupk (Sistem Informasi Dana Bergulir Masyarakat / BUMDesma LKD)** berbasis **PHP 8.4 + Laravel 13**, **Vue 3.5 + Inertia 2.0**, **Tailwind CSS 4**, **MySQL 8.4 / SQLite multi-tenant shard**, **Redis 8 (Cache, Session, Queue Worker)**, **PostgreSQL 16 (pgvector RAG AI)**, dan **Docker Architecture**.

---

## Stack Utama & Arsitektur

- **Backend Framework**: PHP 8.4 + Laravel 13.x
- **Frontend SPA**: Vue 3.5 + Inertia.js 2.0 + Tailwind CSS 4 + Vite 7
- **Database Utama**: MySQL 8.4 (1 Database Platform SaaS + Multiple Tenant Shards) & Portable SQLite Support
- **Vector Store (AI RAG)**: PostgreSQL 16 + pgvector extension
- **Cache, Session & Antrean**: Redis 8 (predis) + Dedicated Redis Background Queue Worker
- **AI Engine / LLM**: Local Ollama Server (`nomic-embed-text` / `enpii/assistant` orchestrator)
- **Web Server / Proxy**: Nginx 1.29 + PHP-FPM 8.4 (Dockerized)

---

## Modul & Fitur Utama

1. **Clean & Modern Professional Landing & Auth Suite**:
   - Redesain halaman depan (`/`) dan halaman login (`/login`) dengan tampilan profesional, bersih, responsif, animasi smooth scroll, dan FAQ interaktif.
   - Tanpa kebocoran info versi/stack teknis (*security hardening*).
2. **Multi-Tenant Sharding Engine**:
   - Skalabilitas hingga 500+ tenant tanpa menggunakan nama tabel dinamis legacy (`transaksi_1`, `anggota_1`).
   - Isolasi data berbasis `tenant_id` + komposit Foreign Key + Shard Connection Manager.
3. **100% Paritas Laporan Legacy siupk ( SA K EP / PP No. 11/2021)**:
   - **10 Laporan Akuntansi Core**: Neraca, Laba Rugi, Buku Besar, Arus Kas, Perubahan Ekuitas, CALK, Neraca Saldo, Jurnal Transaksi, Bukti Kas (BKM/BKK/BM), Kuitansi Angsuran.
   - **6 Laporan Piutang & Kolektibilitas**: Portofolio Aging (per Desa/Kelompok), Rencana vs Realisasi, LPP Rekap Desa, LPP Rincian Kelompok, Kolektibilitas Desa, Cadangan Penghapusan Piutang (CKPN).
   - **3 Analisis Kinerja & Aset**: Penilaian Tingkat Kesehatan Usaha, Rekap Aset Tetap, Rekap Aset Tak Berwujud.
   - **5 Dokumen Paket LPJ Tahunan**: Cover Buku LPJ, Surat Pengantar, Berita Acara Pengesahan, MoU Kerjasama Antar Desa, Annual LPJ Pack Hub.
   - **37 Dokumen Perguliran Pinjaman**: Form Komite, SPK, Rekomendasi Kredit, Surat Kuasa, Tanggung Renteng, Jadwal Angsuran, Kartu Pinjaman.
4. **Portal Pengawasan & Konsolidasi Keuangan Kabupaten (Regency Supervisor)**:
   - Dashboard supervisi kabupaten dan laporan konsolidasi multi-kecamatan real-time (Neraca, Laba Rugi, Buku Besar, Arus Kas, CALK, streaming PDF).
5. **Otomatisasi SaaS Billing & Multi Payment Gateway (Duitku & Tripay)**:
   - Channel pembayaran QRIS (display QR langsung in-app), Virtual Account Bank (BCA, BRI, BNI, Mandiri, Permata, CIMB, BSI, Danamon), dan Kartu Kredit dengan dukungan tiga payment gateway: Duitku, Xendit, & Tripay yang dapat diganti secara terpusat dari Superadmin.
   - Auto-invoice scheduler (`subscriptions:generate-invoices`), perpanjangan otomatis via webhook HMAC, dan pembatasan otomatis tenant *overdue* via `EnsureSubscriptionActive` middleware.
6. **Asisten AI Internal (Ariel / enpii/assistant)**:
   - Widget chat interaktif dengan RAG Vector Search (pgvector) untuk SOP, analisis jurnal otomatis, dan pencarian data tenant.

---

## Ketersediaan Pengujian Automated (100% Passed)

Aplikasi dilengkapi dengan suite pengujian otomatis yang komprehensif:

### 1. Backend PHPUnit Suite (201 Tests - 1.506 Assertions)
Menguji transaksi akuntansi, isolasi tenancy, lifecycle pinjaman, migrasi data, webhook Tripay, dan konsolidasi kabupaten:
```bash
php artisan test
```

### 2. Playwright E2E Page & Route Suite (47 Tests)
Menguji pemuatan 47 halaman, komponen UI, otentikasi, dan seluruh laporan legacy tanpa crash:
```bash
npx playwright test tests/e2e/all_features.spec.ts
```

### 3. Playwright E2E Interactive CRUD Suite (25 Tests)
Pengujian otomatis interaktif pada browser nyata yang mengeklik tombol, mengisi form, memilih dropdown/select, mengubah toggle switch, submit data, dan melakukan navigasi CRUD:
```bash
npx playwright test tests/e2e/all_interactive_crud.spec.ts
```

Menjalankan tes dengan browser terbuka di layar (Headed Mode):
```bash
npx playwright test tests/e2e/all_interactive_crud.spec.ts --headed --slow-mo=200
```

---

## Menjalankan dengan Docker

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --database=platform --path=database/migrations/platform --force
docker compose exec app php artisan siupk:bootstrap-local --password='change-me'
```

Perintah `siupk:bootstrap-local` bersifat *idempotent*: mendaftarkan shard/tenant local, migrasi shard, sinkronisasi registry, import COA, buka periode fiskal, seed master data/produk pinjaman, dan provision user dev.

- **Aplikasi**: <http://localhost:64080> (atau port `APP_PORT` di `.env`)
- **Login Dev**: `dev` / `password`
- **Login Admin**: `superadmin` / `password`

---

## Indeks Dokumentasi Terkait (`/docs`)

- **Panduan Pengguna Lengkap (User Manual)**: [docs/USER_GUIDE.md](docs/USER_GUIDE.md)
- **Perbandingan Legacy vs Next**: [docs/PERBANDINGAN_siupk_LEGACY_VS_NEXT.md](docs/PERBANDINGAN_siupk_LEGACY_VS_NEXT.md)
- **Roadmap Migrasi Laporan Legacy**: [docs/LEGACY_REPORTS_MIGRATION_ROADMAP.md](docs/LEGACY_REPORTS_MIGRATION_ROADMAP.md)
- **Status Fitur & Roadmap**: [docs/FEATURE_ROADMAP.md](docs/FEATURE_ROADMAP.md)
- **Arsitektur & Topologi Database**: [docs/PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md) & [docs/DATABASE_STRUCTURE.md](docs/DATABASE_STRUCTURE.md)
- **Billing Automation & Multi-Payment Gateway**: [docs/BILLING_PAYMENT_AUTOMATION.md](docs/BILLING_PAYMENT_AUTOMATION.md)
- **Asisten AI & RAG**: [docs/ASSISTANT_INTEGRATION.md](docs/ASSISTANT_INTEGRATION.md)
- **Matrix RBAC & Hak Akses**: [docs/RBAC_MATRIX.md](docs/RBAC_MATRIX.md)
- **Runbook Cutover Migrasi**: [docs/CUTOVER_RUNBOOK.md](docs/CUTOVER_RUNBOOK.md)
- **Panduan Verifikasi & Testing**: [docs/VALIDATION.md](docs/VALIDATION.md)
