# Roadmap Fitur & Status Implementasi (siupk Next vs Legacy)

Tujuan: **siupk Next sepenuhnya menggantikan siupk legacy dalam operasional harian tenant dan tingkat Kabupaten.**

Referensi legacy: F:\Workspace\laragon\www\siupk
Dokumentasi Arsitektur: PROJECT_OVERVIEW.md, DATABASE_STRUCTURE.md, CUTOVER_RUNBOOK.md, PERBANDINGAN_siupk_LEGACY_VS_NEXT.md.

---

## Status Fitur & Zona (Update 2026-09-22)

| Zona | Status Next | Keterangan |
|---|---|---|
| Landing Page & Auth | ✅ Selesai | Redesain halaman depan profesional (hero, features, steps, FAQ accordion, smooth scroll, CTA), halaman login clean & modern tanpa kebocoran stack teknis. |
| Master Data | ✅ Selesai | Anggota, Kelompok, Lembaga, Desa + Riwayat Pinjaman + Import/Export CSV |
| Alur Perguliran Pinjaman (Kelompok) | ✅ Selesai | Proposal → Verifikasi → Alokasi → Pencairan → Angsuran → Reschedule/Write-off |
| Alur Perguliran Pinjaman (Individu) | ✅ Selesai (paritas pacuan) | P/V/W/A/L/R/H/T lengkap dengan kalkulator pacuan-style (`MemberLoanScheduleCalculator`); lihat tabel di bawah |
| Jurnal & Akuntansi | ✅ Selesai | Jurnal Umum, Jurnal Angsuran, Reversal, **Edit Jurnal (Reverse + Recreate Atomik)**, COA Read-only |
| Cetak Struk & Kuitansi | ✅ Selesai | Struk/Kuitansi PDF langsung dari posting jurnal |
| Laporan Akuntansi Core | ✅ Selesai | Neraca, Laba Rugi, Buku Besar, Arus Kas, Perubahan Ekuitas, CALK, Neraca Saldo, Jurnal Transaksi |
| Laporan Piutang & Kolektibilitas | ✅ Selesai | Portofolio Pinjaman (Semua/Kelompok/Individu), Rencana vs Realisasi (Semua/Kelompok/Individu), LPP Rekap Desa, LPP Rincian Kelompok, LPP Rincian Individu, Kolektibilitas Desa, Kolektibilitas Rincian Individu, CKPN (Semua/Kelompok/Individu) |
| Analisis Kinerja & Aset | ✅ Selesai | Penilaian Tingkat Kesehatan Usaha, Rekap Aset Tetap, Rekap Aset Tak Berwujud |
| Paket LPJ Tahunan | ✅ Selesai | Cover Buku LPJ, Surat Pengantar, Berita Acara, MoU, Hub LPJ Pack |
| Dokumen Perguliran Kelompok (37 Dokumen) | ✅ Selesai | SPK, Cover Proposal, Rekomendasi Kredit, Surat Kuasa, Tanggung Renteng, Jadwal Angsuran, Kartu Pinjaman, dll. |
| Dokumen Perguliran Individu | ✅ Sebagian | Kartu Angsuran (`card`) & Keterangan Lunas (`settlement-letter`) selesai; 30+ dokumen PDF (cover proposal, BA musyawarah, verifikasi, SPK, kuitansi, dll.) belum dimigrasi |
| Tutup Buku & Alokasi Laba | ✅ Selesai | Tutup/Buka periode fiskal + Jurnal alokasi laba otomatis |
| Inventaris / Aset Tetap | ✅ Selesai | Register aset, Jurnal pembelian inventaris, Nilai buku & akumulasi penyusutan |
| E-Budgeting / RAPB | ✅ Selesai | Input anggaran per akun per bulan, navigasi tahun, simpan RAPB |
| Global Search | ✅ Selesai | Omnibox search header (anggota, kelompok, pinjaman, jurnal, aset) |
| SaaS Billing & Payment Gateway | ✅ Selesai | Multi Payment Gateway (Duitku & Tripay) dengan In-app Payment Channel (QRIS, E-Wallet, VA Bank, Credit Card), Auto-Invoice Scheduler, Active Gateway Switcher, dan Overdue Grace Period Suspension |
| Monitoring Kabupaten (Regency) | ✅ Selesai | Dashboard Kabupaten, Laporan Konsolidasi Multi-Kecamatan (Neraca, LR, BB, Arus Kas, CALK, PDF) |
| Monitoring Provinsi (Province) | ✅ Selesai | Dashboard Provinsi, Laporan Konsolidasi Multi-Kabupaten & Multi-Kecamatan (Neraca, LR, BB, Arus Kas, CALK, Paket PDF) |
| Pembatasan Operator Desa (Village Scope) | ✅ Selesai | Restriksi data anggota, kelompok, dan proposal pinjaman berbasis village_row_id dengan global scope VillageScope |
| AI Assistant & RAG | ✅ Selesai | Asisten AI (enpii/assistant) dengan Vector RAG (pgvector), Ollama, & Komponen Chat Interaktif |
| Redis Infrastructure | ✅ Selesai | Redis Cache Store, Redis Session Driver, & Dedicated Redis Queue Worker |
| Automated Testing Suite | ✅ Selesai | PHPUnit 258 tests (1.779 assertions) + Playwright 47 E2E page tests + Playwright 25 Interactive CRUD tests + **MemberLoanLifecycleTest 14 tests (111 assertions) untuk paritas pinjaman individu** |

---

## Status Pinjaman Individu vs Kelompok (Detail Per Tahap, Update 2026-09-22)

| Tahap Alur (Paritas pacuan) | Kelompok (Next) | Individu (Next) | Pacuan |
|---|---|---|---|
| 1. Pilih + Form proposal | ✅ `createProposal` | ✅ `createMemberProposal` + collateral | ✅ |
| 2. Simpan proposal (status P/draft) | ✅ | ✅ | ✅ |
| 3. Generate Rencana Angsuran (RA) | ⚠️ Generic flat-rata-rata | ✅ **Pacuan-style** (`MemberLoanScheduleCalculator`) | ✅ |
| 4. Verifikasi proposal (V) | ✅ `verify` | ✅ `individualVerify` | ✅ |
| 5. Persetujuan pencairan (W) | ✅ `approve` + regenerate | ✅ `individualApprove` + regenerate pacuan-style | ✅ |
| 6. Pencairan (A) | ✅ `disburse` + jurnal | ✅ `individualDisburse` + jurnal | ✅ |
| 7. Bayar angsuran | ✅ `recordInstallmentPayment` (transaksi 1 baris gabungan) | ✅ Sama (satu angsuran = satu peminjam) | ✅ |
| 8. Pelunasan (L) | ✅ `complete` | ✅ `individualComplete` | ✅ |
| 9. Kembali ke Proposal (revert) | ✅ `revertToDraft` | ✅ `individualRevert` | ✅ |
| 10. Tolak / Tidak Layak (T) | ❌ Belum ada | ✅ `individualReject` (status='rejected') | ✅ |
| 11. Reschedule (R) | ✅ `reschedule` | ✅ `individualReschedule` (regenerate pacuan-style) | ✅ |
| 12. Hapus / Write-off (H) | ✅ `writeOff` | ✅ `individualWriteOff` | ✅ |
| 13. Cetak Kartu Angsuran | ✅ `card` | ✅ `individualCard` | ✅ |
| 14. Cetak Keterangan Lunas | ✅ `settlementLetter` | ✅ `individualSettlementLetter` | ✅ |
| 15. 30+ dokumen PDF (cover/BA/SPK/kuitansi) | ✅ `LoanDocumentService` | ⚠️ Sebagian — card & settlement letter selesai; sisanya belum |

### Catatan Paritas:
- **Individu 1:1 dengan pacuan `PinjamanIndividuController::generate()`** (line 2317-2607) — magic formula `jangka==24`, pembulatan per kecamatan, sistem 11/12/14/15/20, override jadwal desa, `batas_angsuran`, mode mingguan (+x*7 days), end-of-month handling.
- **Kelompok belum migrasi ke kalkulator pacuan-style** — masih pakai `generatePrincipalSchedule`/`generateInterestSchedule` generik. Aggregate total paritas dengan individu (lihat `test_individual_schedule_parity_with_single_member_group_loan`), tapi nominal per-bulan bisa berbeda.
- **Produk `pi` (Pinjaman Individu)** default di-`TenantLoanProductProvisioner` dengan `borrower_scope='member'`.
- **Akun COA perorangan** sudah ada: `1.1.03.09` (Piutang Pokok), `1.1.04.08`/`09` (CKPN), `4.1.01.07` (Jasa), `4.1.01.08` (Denda).

## Statistik Pengujian Automated (Terakhir: 2026-09-22)

| Suite Pengujian | Total Tests | Assertions | Durasi | Status |
|---|:---:|:---:|:---:|:---:|
| **MemberLoanLifecycleTest** (Pinjaman Individu paritas pacuan) | 14 | 111 | ~3 menit | ✅ 100% Pass |
| **Backend PHPUnit** (Unit & Feature) | 258+ | 1.779+ | ~5.5 menit | ✅ 100% Pass |
| **Playwright E2E Page & Route** (`all_features.spec.ts`) | 47 | — | ~4 menit | ✅ 100% Pass |
| **Playwright Interactive CRUD** (`all_interactive_crud.spec.ts`) | 25 | — | ~5 menit | ✅ 100% Pass |
| **Total** | **344+** | **1.890+** | **~17.5 menit** | **✅ 100% Pass** |

---

## Statistik Pengujian Automated (Terakhir: 2026-08-18)

| Suite Pengujian | Total Tests | Assertions | Durasi | Status |
|---|:---:|:---:|:---:|:---:|
| **Backend PHPUnit** (Unit & Feature) | 258 | 1.779 | ~5.5 menit | ✅ 100% Pass |
| **Playwright E2E Page & Route** (`all_features.spec.ts`) | 47 | — | ~4 menit | ✅ 100% Pass |
| **Playwright Interactive CRUD** (`all_interactive_crud.spec.ts`) | 25 | — | ~5 menit | ✅ 100% Pass |
| **Total** | **330** | **1.779+** | **~14.5 menit** | **✅ 100% Pass** |

---

## Prioritas Eksekusi & Track Record

### P0 — Blocking Cutover Harian (DONE)
- **P0.1** Cetak bukti angsuran (Struk & Kuitansi PDF)
- **P0.2** Daftar jurnal + Reversal UI (JournalReversalService)
- **P0.3** Laporan piutang inti (Portofolio aging per desa + Rencana vs Realisasi)

### P1 — Paritas Pimpinan & Laporan Bulanan (DONE)
- **P1.1** Arus Kas (/accounting/reports/cash-flow) — Metode langsung + rekonsiliasi
- **P1.2** Perubahan Ekuitas (/accounting/reports/equity-change)
- **P1.3** CALK (/accounting/reports/calk) — Highlight otomatis + catatan kualitatif
- **P1.4** Kartu Angsuran (/lending/loans/{id}/card)

### P2 — Periodik & Infrastruktur Multi-Tenant (DONE)
- **P2.1** Tutup buku & Alokasi Laba (ProfitAllocationService)
- **P2.2** Bagan Akun Standar (COA) UI Read-Only
- **P2.3** Inventaris & Aset Tetap (AssetService)
- **P2.4** Billing Automatisasi Multi-Gateway (Duitku & Tripay QRIS/VA/CC) & Suspension Middleware
- **P2.5** Portal Supervisi & Konsolidasi Keuangan Kabupaten (Regency Multi-Kecamatan)

### P3 — AI, Pengalaman Pengguna & Infrastruktur (DONE)
- **P3.1** Omnibox Global Search Header
- **P3.2** Asisten AI Ariel dengan pgvector + Ollama RAG & Interactive Widgets
- **P3.3** Redis Infrastructure Integration (Cache Store, Session Driver, Background Queue Worker)

### P4 — Migrasi Seluruh Laporan Legacy & Pengujian Menyeluruh (DONE)
- **P4.1** Migrasi 100% laporan legacy (LPP Desa, LPP Kelompok, Kolektibilitas, CKPN, Kesehatan Usaha, Paket LPJ Tahunan)
- **P4.2** Redesain halaman Landing Page & Login (profesional, clean, modern, tanpa bocor info teknis)
- **P4.3** Suite pengujian E2E Playwright menyeluruh (47 Page Tests + 25 Interactive CRUD Tests)
- **P4.4** Suite pengujian Backend PHPUnit komprehensif (201 Tests / 1.506 Assertions)

---

## Catatan Rilis & Changelog

| Tanggal | Fitur Utama yang Dirilis |
|---|---|
| 2026-07-28 | Inisialisasi P0–P3. P0.1 Cetak Struk/Kuitansi, P0.2 Reversal Jurnal, P0.3 Portofolio Pinjaman. |
| 2026-07-29 | Laporan P1 (Arus Kas, Ekuitas, CALK, Kartu Angsuran). P2.1 Tutup Buku, P2.2 COA UI, P2.3 Aset Tetap, P3.1 Global Search. |
| 2026-08-09 | Modul Integrasi Tripay (QRIS & Virtual Accounts), Automated Subscription Invoice Generator, Overdue Grace Period Suspension. |
| 2026-08-12 | Modul Integrasi Duitku Payment Gateway (Inquiry V2, Webhook MD5, E-Wallet/VA/CC), Superadmin Active Gateway Switcher, dan E2E Integration Suite. |
| 2026-08-10 | Modul Portal Kabupaten (Supervisory Dashboard & Real-Time Consolidated Financial Reports untuk Neraca, Laba Rugi, Arus Kas, BB, CALK + PDF Export). |
| 2026-08-10 | Migrasi penuh ke Redis Session Driver, Redis Queue Worker, refactoring Vue UI ke shared composables (useMoney, usePeriodOptions) dan komponen UI terstandar (SmartSelect, ReportPeriodFilter, AppRadioGroup). |
| 2026-08-10 | P4.1 Migrasi 100% seluruh laporan legacy (LPP Desa, LPP Kelompok, Kolektibilitas Desa, CKPN, Penilaian Kesehatan Usaha, Paket LPJ Tahunan Cover/Surat Pengantar/BA/MoU). |
| 2026-08-10 | P4.3 Suite E2E Playwright menyeluruh (47 Page Tests + Smoke UI). P4.4 Suite Backend PHPUnit komprehensif (201 Tests / 1.506 Assertions). |
| 2026-08-11 | P4.2 Redesain halaman Landing Page & Login (hero profesional, FAQ accordion interaktif, smooth scroll, CTA Masuk/Mulai Sekarang terpisah, tanpa bocor versi teknis). |
| 2026-08-11 | P4.3 Suite E2E Playwright Interactive CRUD (25 tests): form fill, radio select, SmartSelect dropdown, toggle switch, submit data, edit, search, tab switching, dan wrong credentials test. |
| 2026-08-18 | **Fitur Edit Jurnal** — Reverse jurnal lama + buat jurnal baru dalam satu DB transaction atomik (`JournalEditService` outer transaction dengan retry). Hanya untuk `source_type=manual` & `asset_purchase`. Alasan wajib (max 500 char). Untuk `asset_purchase`: asset baru terdaftar otomatis, asset lama tetap terkait jurnal lama (audit trail utuh). Lihat `docs/audit/2026-08-18/journal-edit-feature.md`. |
