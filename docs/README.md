# Dokumentasi Proyek SIUPK Next

Indeks dokumentasi lengkap untuk arsitektur, panduan pengguna, basis data, billing, modul supervisi, RBAC, asisten AI, dan pengujian SIUPK Next:

---

## 1. Panduan Pengguna & Operasional

Dokumentasi penggunaan aplikasi untuk pengguna akhir, pengelola BUMDesma/LKD, operator desa, supervisor wilayah, dan administrator:

- [USER_GUIDE.md](USER_GUIDE.md) – **Panduan Pengguna Lengkap (User Manual)**: Mencakup seluruh 86 halaman dan fitur aplikasi (Dashboard, Master Data, Lending Lifecycle, Akuntansi & Jurnal, Inventaris, E-Budgeting, Pelaporan Keuangan & Piutang, Prosedur Periodik, Billing SaaS, WhatsApp Gateway, RBAC, Onboarding, Portal Pengawasan, Superadmin, AI Assistant, dan 36 Dokumen Cetak PDF).
- [CUTOVER_RUNBOOK.md](CUTOVER_RUNBOOK.md) – Panduan teknis migrasi dan *cutover* data per tenant dari database legacy ke SIUPK Next.
- [VALIDATION.md](VALIDATION.md) – Panduan verifikasi statis, pengujian backend PHPUnit, dan pengujian frontend Playwright browser (E2E).

---

## 2. Arsitektur & Spesifikasi Sistem

Dokumentasi teknis arsitektur, skema basis data, keamanan hak akses, billing, integrasi holding, dan integrasi AI:

- [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) – Gambaran umum proyek, arsitektur multi-tenant sharding, sasaran, risiko, dan kriteria penyelesaian (*definition of done*).
- [HOLDING_API_INTEGRATION_GUIDE.md](HOLDING_API_INTEGRATION_GUIDE.md) – **Panduan Integrasi API Holding & Konsolidasi**: Spesifikasi endpoint RESTful API laporan keuangan (Neraca, Laba Rugi, Arus Kas, CALK, Perubahan Ekuitas, Paket 5-in-1, dan Konsolidasi Multi-Tenant) untuk integrasi dengan aplikasi holding / enterprise luar.
- [DATABASE_STRUCTURE.md](DATABASE_STRUCTURE.md) – Struktur detail skema platform dan database shard multi-tenant, pemetaan tabel legacy, pembentukan identitas ganda (`row_id` vs `id`), hierarki supervisi, dan portabilitas MySQL/SQLite.
- [RBAC_MATRIX.md](RBAC_MATRIX.md) – Matriks hak akses (*Role-Based Access Control*) dan 37 permission granular modul tenant, supervisor provinsi/kabupaten, operator desa, dan platform superadmin.
- [BILLING_PAYMENT_AUTOMATION.md](BILLING_PAYMENT_AUTOMATION.md) – Spesifikasi integrasi Multi-Payment Gateway (Tripay, Duitku, Xendit) (QRIS & Virtual Accounts), automatisasi invoice perpanjangan, dan middleware pembatasan tenant overdue.
- [ASSISTANT_INTEGRATION.md](ASSISTANT_INTEGRATION.md) – Spesifikasi integrasi Asisten AI (`enpii/assistant`), pgvector store RAG, Ollama embedding server, dan komponen chat interaktif.
- [ai-assistant-project-guide.md](ai-assistant-project-guide.md) – Panduan teknis proyek implementasi modul AI Assistant dan ekosistem pendukungnya.

---

## 3. Analisis Komparatif & Migrasi Legacy

Dokumentasi perbandingan mendalam antara sistem versi legacy (PHP Native) dengan arsitektur modern SIUPK Next:

- [PERBANDINGAN_SIUPK_LEGACY_VS_NEXT.md](PERBANDINGAN_SIUPK_LEGACY_VS_NEXT.md) – Analisis komparatif menyeluruh SIUPK Legacy (`/siupk`) vs SIUPK Next (`/siupknext`), alasan upgrade, arsitektur, SaaS billing, supervisi wilayah, dan infrastruktur.
- [PERBANDINGAN_DATABASE_LEGACY_VS_NEXT.md](PERBANDINGAN_DATABASE_LEGACY_VS_NEXT.md) – Perbandingan skema tabel database legacy vs normalisasi tabel modern multi-tenant.
- [LEGACY_REPORTS_MIGRATION_ROADMAP.md](LEGACY_REPORTS_MIGRATION_ROADMAP.md) – Matriks spesifikasi dan status 100% implementasi laporan akuntansi, laporan piutang, paket LPJ tahunan MAD, dan dokumen perguliran pinjaman.

---

## 4. Roadmap & Riwayat Pengujian

- [FLUTTER_MOBILE_ROADMAP.md](FLUTTER_MOBILE_ROADMAP.md) – **Roadmap Mobile App (Flutter Native Companion)**: Panduan arsitektur Clean Architecture, pemisahan fitur Mobile vs Web/Desktop, integrasi printer thermal bluetooth, GPS, survei 5C, approval mobile, dan checklist fase kerja.
- [DESKTOP_ROADMAP.md](DESKTOP_ROADMAP.md) – **Roadmap Desktop App (NativePHP + SQLite Read-Only Offline)**: Panduan arsitektur, strategi pull-sync satu arah, matriks online vs offline, dan checklist implementasi desktop installer.
- [FEATURE_ROADMAP.md](FEATURE_ROADMAP.md) – Status implementasi fitur harian, modul supervisi, pembatasan operator desa, suite pengujian, dan changelog rilis.
- [TEST_AUDIT_LOG.md](TEST_AUDIT_LOG.md) – Log audit hasil pengujian backend PHPUnit (258 tests) dan Playwright E2E browser tests.

---

## Keputusan Arsitektur Inti

- **Topologi**: Platform Database + Multi-Tenant Shard Database (`tenant_id` column-based isolation) dengan opsi MySQL 8.4 dan SQLite support.
- **Identitas**: `row_id` sebagai PK internal teknis, `id` lama dipertahankan utuh untuk laporan & audit.
- **Akuntansi**: Double-entry journal (`journal_entries` & `journal_lines`) yang seimbang dan bersifat *immutable* ? koreksi jurnal posted melalui reverse + recreate atomik (`JournalEditService`).
- **Supervisi Berjenjang (Kabupaten & Provinsi)**: Dashboard & laporan keuangan konsolidasi real-time lintas kecamatan & kabupaten (Neraca, LR, BB, Arus Kas, CALK, PDF Pack).
- **Pembatasan Operator Desa (Village Scope)**: Pengguna level desa (`is_village_user`) hanya dapat melihat dan mengelola data anggota/kelompok/proposal pinjaman milik desa bersangkutan via global scope `VillageScope`.
- **SaaS Billing**: Integrasi Multi-Payment Gateway (Tripay, Duitku, Xendit) dengan auto-invoice scheduler & penangguhan otomatis tenant overdue.
- **Automated Testing**: 100% Passed across layers (PHPUnit 258 tests/1779 assertions + Playwright 47 E2E page tests + Playwright 25 Interactive CRUD tests).

