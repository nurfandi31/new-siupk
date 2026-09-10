# Roadmap & Spesifikasi Migrasi Laporan Legacy ke Sistem Modern (SIUPK Next)

**Status:** âœ… **100% IMPLEMENTED (Seluruh Laporan Legacy Selesai Di-migrasi ke Arsitektur Modern)**

Seluruh laporan legacy SIUPK kini telah di-implementasikan secara penuh pada arsitektur modern (Multi-tenant, Clean Domain Service, Inertia Vue 3, Tailwind CSS, dan PDF Rendering Engine berstandar SAK Entitas Privat / PP No. 11/2021). Tampilan dan format data mengacu pada standar visual resmi dengan akurasi dan kecepatan komputasi modern.

---

## Matriks Komprehensif Laporan

### A. Laporan Keuangan Pokok & Akuntansi (SAK EP)
| No | Laporan | File Legacy | File Modern (PDF) | Endpoint Web & PDF | Status |
|---|---|---|---|---|:---:|
| 1 | **Neraca (Balance Sheet)** | `neraca/neraca1.blade.php`, `neraca2.blade.php` | `reports/pdf/balance_sheet.blade.php` | `/accounting/reports/balance-sheet` | âœ… Selesai |
| 2 | **Laba Rugi (Income Statement)** | `view/laba_rugi.blade.php` | `reports/pdf/income_statement.blade.php` | `/accounting/reports/income-statement` | âœ… Selesai |
| 3 | **Arus Kas (Cash Flow)** | `view/arus_kas.blade.php` | `reports/pdf/cash_flow.blade.php` | `/accounting/reports/cash-flow` | âœ… Selesai |
| 4 | **Perubahan Ekuitas (Equity Change)** | `view/perubahan_modal.blade.php` | `reports/pdf/equity_change.blade.php` | `/accounting/reports/equity-change` | âœ… Selesai |
| 5 | **CALK (Catatan Atas Lap. Keuangan)** | `view/calk.blade.php`, `calk_c.blade.php` | `reports/pdf/calk.blade.php` | `/accounting/reports/calk` | âœ… Selesai |
| 6 | **Neraca Saldo (Trial Balance)** | `view/neraca_saldo.blade.php` | `reports/pdf/trial_balance.blade.php` | `/accounting/reports/trial-balance` | âœ… Selesai |
| 7 | **Buku Besar (General Ledger)** | `view/buku_besar.blade.php` | `reports/pdf/general_ledger.blade.php` | `/accounting/reports/general-ledger` | âœ… Selesai |
| 8 | **Jurnal Transaksi (Journal Listing)** | `view/jurnal_transaksi.blade.php` | `reports/pdf/journal.blade.php` | `/accounting/reports/journals` | âœ… Selesai |
| 9 | **Bukti Kas (BKM, BKK, BM)** | - | `reports/pdf/cash_evidence/*` | `/accounting/journals/{id}/cash-evidence` | âœ… Selesai |
| 10 | **Kuitansi Angsuran** | `legacy_pinjaman/kuitansi.blade.php` | `reports/pdf/installment_receipt.blade.php` | `/accounting/journal-entries/{id}/installment-receipt` | âœ… Selesai |

---

### B. Laporan Perkembangan Pinjaman (LPP & Kolektibilitas)
| No | Laporan | File Legacy | File Modern (PDF) | Endpoint Web & PDF | Status |
|---|---|---|---|---|:---:|
| 1 | **Portofolio Pinjaman (Aging)** | `perkembangan_piutang/kelompok_aktif.blade.php` | `reports/pdf/loan_portfolio.blade.php` | `/lending/reports/portfolio` | âœ… Selesai |
| 2 | **Rencana vs Realisasi** | `perkembangan_piutang/rencana_realisasi.blade.php` | `reports/pdf/loan_schedule_vs_actual.blade.php` | `/lending/reports/schedule-vs-actual` | âœ… Selesai |
| 3 | **LPP Rekap per Desa** | `perkembangan_piutang/lpp_desa.blade.php` | `reports/pdf/lending/lpp_desa.blade.php` | `/lending/reports/lpp-desa` | âœ… Selesai |
| 4 | **LPP Rincian per Kelompok** | `perkembangan_piutang/lpp_kelompok.blade.php` | `reports/pdf/lending/lpp_kelompok.blade.php` | `/lending/reports/lpp-kelompok` | âœ… Selesai |
| 5 | **Kolektibilitas Pinjaman (Rekap Desa)** | `perkembangan_piutang/kolek_desa.blade.php` | `reports/pdf/lending/kolek_desa.blade.php` | `/lending/reports/kolek-desa` | âœ… Selesai |
| 6 | **Cadangan Penghapusan Piutang (CKPN)** | `perkembangan_piutang/cadangan_penghapusan.blade.php` | `reports/pdf/lending/cadangan_penghapusan.blade.php` | `/lending/reports/cadangan-penghapusan` | âœ… Selesai |

---

### C. Analisis Kinerja Keuangan & Rekapitulasi Inventaris
| No | Laporan | File Legacy | File Modern (PDF) | Endpoint Web & PDF | Status |
|---|---|---|---|---|:---:|
| 1 | **Penilaian Tingkat Kesehatan Usaha** | `view/penilaian_kesehatan.blade.php` | `reports/pdf/penilaian_kesehatan.blade.php` | `/accounting/reports/financial-health` | âœ… Selesai |
| 2 | **Rekapitulasi Aset Tetap** | `view/aset_tetap.blade.php` | `reports/pdf/assets/fixed_assets.blade.php` | `/accounting/reports/assets/fixed/pdf` | âœ… Selesai |
| 3 | **Rekapitulasi Aset Tak Berwujud** | `view/aset_tak_berwujud.blade.php` | `reports/pdf/assets/intangible_assets.blade.php` | `/accounting/reports/assets/intangible/pdf` | âœ… Selesai |

---

### D. Dokumen Paket Pelaporan Tahunan & Administratif (LPJ)
| No | Dokumen Administratif | File Legacy | File Modern (PDF) | Endpoint Web & PDF | Status |
|---|---|---|---|---|:---:|
| 1 | **Cover Buku Laporan Tahunan** | `view/cover.blade.php` | `reports/pdf/annual/cover.blade.php` | `/accounting/reports/annual-pack/cover/pdf` | âœ… Selesai |
| 2 | **Surat Pengantar Laporan (LPJ)** | `view/surat_pengantar.blade.php` | `reports/pdf/annual/surat_pengantar.blade.php` | `/accounting/reports/annual-pack/surat-pengantar/pdf` | âœ… Selesai |
| 3 | **Berita Acara Pengesahan (LPJ)** | `view/ba_pergantian_laporan.blade.php` | `reports/pdf/annual/ba_pergantian.blade.php` | `/accounting/reports/annual-pack/ba-pergantian/pdf` | âœ… Selesai |
| 4 | **Naskah Kerjasama Antar Desa (MoU)** | `view/mou.blade.php` | `reports/pdf/annual/mou.blade.php` | `/accounting/reports/annual-pack/mou/pdf` | âœ… Selesai |
| 5 | **Hub Dokumen LPJ & Cover** | `view/index.blade.php` | `Accounting/Reports/AnnualPack.vue` | `/accounting/reports/annual-pack` | âœ… Selesai |

---

### E. Dokumen Perguliran & Akad Pinjaman (37 Dokumen)
- Seluruh 37 dokumen cetak (SPK, Cover Proposal, Rekomendasi Kredit, Surat Kuasa, Berita Acara Pencairan, Tanggung Renteng, Rencana Angsuran, Kartu Angsuran Kelompok/Anggota, dll.) telah aktif melalui `LoanDocumentService` pada route `/lending/loans/{loan}/documents/{type}`.