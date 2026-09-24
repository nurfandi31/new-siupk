# Audit Menu Pelaporan: SIUPK vs SIUPKNext

**Tanggal Audit:** 24 September 2026  
**Auditor:** OpenCode Agent  
**Lokasi SIUPK:** `C:\laragon\www\siupk` (Laravel 10 + Blade)  
**Lokasi SIUPKNext:** `C:\laragon\www\siupknext` (Laravel 13 + Vue 3 + Inertia.js)

---

## Ringkasan Eksekutif

| Aspek | SIUPK (Legacy) | SIUPKNext (Target) |
|---|---|---|
| **Backend** | Laravel 10 | Laravel 13 |
| **Frontend** | Blade + AdminLTE | Vue 3 + Inertia.js + Vite |
| **Total Laporan** | ~75+ laporan | ~118 route laporan |
| **Library PDF** | `barryvdh/laravel-dompdf` v2 | `barryvdh/laravel-dompdf` v3.1 |
| **Library Excel** | `maatwebsite/excel` (tidak aktif) | Custom `XlsxWriter` (native PHP) |
| **Arsitektur DB** | Multi-tenant suffix per kecamatan | Single DB dengan `tenant_id` filter |
| **Sidebar** | Dinamis dari DB (`menu` table) | Hardcoded di Vue components |
| **Konsolidasi** | Level Kabupaten | Level Provinsi + Kabupaten |

### Persentase Kesiapan SIUPKNext

- **Laporan Keuangan (Akuntansi):** ~95% ✅ (kurang: Simpanan, beberapa tutup buku)
- **Laporan Pinjaman (Kelompok):** ~90% ✅ (kurang: proposal/verifikasi/waiting, beberapa sub-varian)
- **Laporan Pinjaman (Individu):** ~80% ✅ (kurang: beberapa sub-varian, rencana_realisasi_i)
- **Laporan OJK:** ❌ 0% (belum ada)
- **Laporan Mingguan:** ❌ 0% (belum ada)
- **Dokumen Cetak (Surat/dokumen pinjaman):** ~95% ✅ (60+ dokumen)
- **Laporan Tutup Buku:** ❌ 0% (belum ada)
- **Laporan Aset:** ✅ 100% (ada, format PDF + Excel)
- **E-Budgeting:** ❌ 0% (belum ada)
- **Laporan Pengawas:** ❌ 0% (belum ada)
- **Master Data Export:** ✅ 100% (CSV anggota, kelompok, lembaga)
- **Penilaian Kesehatan:** ✅ 100%

---

## BAGIAN 1: DAFTAR LENGKAP LAPORAN SIUPK (Source of Truth)

### A. LAPORAN KEUANGAN (Financial Reports) — 11 Laporan

| # | Kode File | Nama Laporan | View | Orientasi | Tipe |
|---|---|---|---|---|---|
| 1 | `cover` | Cover Laporan Keuangan | `view/cover.blade.php` | Portrait | - |
| 2 | `surat_pengantar` | Surat Pengantar | `view/surat_pengantar.blade.php` | Portrait | - |
| 3 | `BB` | Buku Besar per kode akun (sub-laporan) | `view/buku_besar.blade.php` | Portrait | - |
| 4 | `neraca` | Neraca | `view/neraca.blade.php` | Portrait | - |
| 5 | `laba_rugi` | Laba Rugi | `view/laba_rugi.blade.php` | Portrait | - |
| 6 | `perubahan_modal` | Laporan Perubahan Modal (LPM) | `view/perubahan_modal.blade.php` | Portrait | - |
| 7 | `arus_kas` | Arus Kas | `view/arus_kas.blade.php` | Portrait | - |
| 8 | `neraca_saldo` | Neraca Saldo (Trial Balance) | `view/neraca_saldo.blade.php` | Landscape | - |
| 9 | `CALK` | Catatan Atas Laporan Keuangan (Quill editor) | `view/calk.blade.php` | Portrait | - |
| 10 | `jurnal_transaksi` | Jurnal Transaksi | `view/jurnal_transaksi.blade.php` | Portrait | - |
| 11 | `simpanan` | Daftar Simpanan | `view/simpanan.blade.php` | Landscape | - |

### B. PERKEMBANGAN PIUTANG (Receivables) — 27 Laporan

#### B.1 Kelompok (Group Lending)
| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 12 | `proposal` | Daftar Proposal | Landscape |
| 13 | `verifikasi` | Daftar Verifikasi | Landscape |
| 14 | `waiting` | Daftar Waiting List | Landscape |
| 15 | `pinjaman_per_kelompok` | LPP per Kelompok | Landscape |
| 16 | `lunas` | Daftar Pinjaman Lunas | Landscape |
| 17 | `kelompok_aktif` | Pinjaman Kelompok Aktif | Landscape |
| 18 | `pinjaman_per_desa` | LPP per Desa | Landscape |
| 19 | `kolek_per_kelompok` | Kolektibilitas per Kelompok | Landscape |
| 20 | `kolek_per_desa` | Kolektibilitas per Desa | Landscape |
| 21 | `cadangan_penghapusan` | Cadangan Penghapusan | Landscape |
| 22 | `rencana_realisasi` | Rencana & Realisasi | Landscape |
| 23 | `_rencana_realisasi` | Rencana Realisasi (Triwulan) | Landscape |
| 24 | `menunggak` | Daftar Tunggakan | Landscape |
| 25 | `pinjaman_hapus` | Pinjaman Dihapusbukukan | Landscape |
| 26 | `pinjaman_anggota_hapus` | Pinjaman Anggota Dihapus | Landscape |

#### B.2 Individu (Individual Lending)
| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 27 | `pemanfaat_aktif` | Pemanfaat Aktif | Landscape |
| 28 | `pinjaman_individu` | LPP Individu | Landscape |
| 29 | `individu_aktif` | Pinjaman Individu Aktif | Landscape |
| 30 | `pinjaman_individu_per_desa` | LPP Individu per Desa | Landscape |
| 31 | `pinjaman_individu_per_desaaa` | LPP Individu per Desa (varian) | Landscape |
| 32 | `kolek_individu` | Kolektibilitas Individu | Landscape |
| 33 | `kolek_per_desa_individu` | Kolektibilitas per Desa (individu) | Landscape |
| 34 | `rencana_realisasi_i` | Rencana & Realisasi Individu | Landscape |
| 35 | `pinjaman_hapus_individu` | Pinjaman Dihapusbukukan Individu | Landscape |

#### B.3 Mingguan (Weekly)
| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 36 | `pinjaman_individu_mingguan` | LPP Individu Mingguan | Landscape |
| 37 | `pinjaman_per_kelompok_mingguan` | LPP Kelompok Mingguan | Landscape |
| 38 | `kolek_individu_mingguan` | Kolektibilitas Individu Mingguan | Landscape |
| 39 | `kolek_per_kelompok_mingguan` | Kolektibilitas Kelompok Mingguan | Landscape |

#### B.4 Lainnya
| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 40 | `tagihan_hari_ini` | Tagihan Jatuh Tempo Hari Ini | Landscape |
| 41 | `pelunasan` | Pinjaman Mendekati Jatuh Tempo | Landscape |

### C. BASIS DATA (Database Reports) — 3 Laporan

| # | Kode | Nama | Tipe | Orientasi |
|---|---|---|---|---|
| 42 | `penduduk` | Daftar Penduduk/Anggota | Individu | Landscape |
| 43 | `kelompok` | Daftar Kelompok | Kelompok | Landscape |
| 44 | `lembaga_lain` | Daftar Lembaga Lain | Kelompok | Landscape |

### D. ASET (Asset Reports) — 2 Laporan

| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 45 | `ati` | Aset Tetap & Inventaris | Landscape |
| 46 | `atb` | Aset Tak Berwujud | Landscape |

### E. PENILAIAN KESEHATAN — 2 Laporan

| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 47 | `tingkat_kesehatan` | Penilaian Tingkat Kesehatan | Landscape |
| 48 | `Sehat` | Penilaian Kesehatan (OJK) | Landscape |

### F. E-BUDGETING — 1 Laporan (dengan 4 triwulan)

| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 49 | `EB` | E-Budgeting per Triwulan (Q1-Q4) | Landscape |

### G. LAPORAN OJK — 14 Laporan

| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 50 | `CV` | Cover OJK | Portrait |
| 51 | `PF` | Profil Kelembagaan OJK | Portrait |
| 52 | `OJKP` | Neraca OJK | Portrait |
| 53 | `LRL` | Laba Rugi OJK | Portrait |
| 54 | `DRP` | Daftar Rincian Pinjaman Aktif | Landscape |
| 55 | `DRPL` | Rincian Pinjaman Lunas Kelompok | Landscape |
| 56 | `DRPLi` | Rincian Pinjaman Lunas Individu | Landscape |
| 57 | `DRT` | Daftar Rincian Tabungan | Portrait |
| 58 | `SMPN` | Simpanan & Piutang | Portrait |
| 59 | `bunga` | Daftar Bunga Simpanan | Portrait |
| 60 | `DRPY` | Rincian Pinjaman Diterima | Portrait |
| 61 | `KBP` | Kolektibilitas Pinjaman OJK | Landscape |
| 62 | `KBP2` | Kolektibilitas Pinjaman OJK v2 | Landscape |
| 63 | `pcpp` | Penyisihan Cadangan Penghapusan Piutang | Landscape |

### H. TUTUP BUKU (Year-End Closing) — 5 Laporan

| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 64 | `alokasi_laba` | Pengalokasian Laba | Portrait |
| 65 | `jurnal_tutup_buku` | Jurnal Tutup Buku | Portrait |
| 66 | `neraca_tutup_buku` | Neraca Tutup Buku | Portrait |
| 67 | `laba_rugi_tutup_buku` | Laba Rugi Tutup Buku | Portrait |
| 68 | `CALK_tutup_buku` | CALK Tutup Buku | Portrait |

### I. DOKUMEN SPESIAL — 4 Laporan

| # | Kode | Nama | Orientasi |
|---|---|---|---|
| 69 | `mou` | MoU (Nota Kesepahaman) | Portrait |
| 70 | `ts` | Tanda Tangan/Surat | Custom (595x352) |
| 71 | `invoice` | Invoice | Portrait |
| 72 | `catatan_pengawas` | Catatan Pengawas | Portrait |

---

## BAGIAN 2: DAFTAR LENGKAP LAPORAN SIUPKNEXT (Current State)

### A. PELAPORAN TENANT (Accounting Module) — 16 Menu Utama

| # | Nama | Route | Format |
|---|---|---|---|
| 1 | Ringkasan Laporan (Index) | `GET /accounting/reports` | HTML |
| 2 | Jurnal Transaksi | `/accounting/reports/journals` + `/excel` | HTML, PDF, XLSX |
| 3 | Neraca Saldo (Trial Balance) | `/accounting/reports/trial-balance` + `/excel` | HTML, PDF, XLSX |
| 4 | Neraca | `/accounting/reports/balance-sheet` + `/excel` | HTML, PDF, XLSX |
| 5 | Laba Rugi | `/accounting/reports/income-statement` + `/excel` | HTML, PDF, XLSX |
| 6 | Arus Kas | `/accounting/reports/cash-flow` + `/excel` | HTML, PDF, XLSX |
| 7 | Perubahan Ekuitas | `/accounting/reports/equity-change` + `/excel` | HTML, PDF, XLSX |
| 8 | CALK | `/accounting/reports/calk` | HTML, PDF |
| 9 | Buku Besar | `/accounting/reports/general-ledger` + `/excel` | HTML, PDF, XLSX |
| 10 | Penilaian Kesehatan Keuangan | `/accounting/reports/financial-health` | HTML, PDF |
| 11 | Rekap Aset Tetap | `/accounting/reports/assets/fixed/{pdf,excel}` | PDF, XLSX |
| 12 | Rekap Aset Tak Berwujud | `/accounting/reports/assets/intangible/{pdf,excel}` | PDF, XLSX |
| 13 | Paket Dokumen LPJ (Index) | `/accounting/reports/annual-pack` | HTML |
| 14 | Cover Buku Laporan Tahunan | `/accounting/reports/annual-pack/cover/pdf` | PDF |
| 15 | Surat Pengantar LPJ | `/accounting/reports/annual-pack/surat-pengantar/pdf` | PDF |
| 16 | Berita Acara Pengesahan | `/accounting/reports/annual-pack/ba-pergantian/pdf` | PDF |
| 17 | Naskah Kerjasama MoU | `/accounting/reports/annual-pack/mou/pdf` | PDF |
| 18 | Bundle ZIP Laporan | `/accounting/reports/bundle/pdf` | ZIP |

### B. PELAPORAN LENDING — 9 Laporan

| # | Nama | Tipe | Route |
|---|---|---|---|
| 19 | Portofolio Pinjaman | Gabungan | `/lending/reports/portfolio` |
| 20 | Rencana vs Realisasi | Gabungan | `/lending/reports/schedule-vs-actual` |
| 21 | LPP Rekap Desa | Kelompok | `/lending/reports/lpp-desa` |
| 22 | LPP Rincian Kelompok | Kelompok | `/lending/reports/lpp-kelompok` |
| 23 | LPP Rincian Individu | Individu | `/lending/reports/lpp-individu` |
| 24 | Kolektibilitas Rekap Desa | Kelompok | `/lending/reports/kolek-desa` |
| 25 | Kolektibilitas Rincian Individu | Individu | `/lending/reports/kolek-individu` |
| 26 | Cadangan Penghapusan (CKPN) | Kelompok | `/lending/reports/cadangan-penghapusan` |
| 27 | CKPN Pinjaman Individu | Individu | `/lending/reports/cadangan-penghapusan-individu` |

### C. DOKUMEN CETAK PINJAMAN — 60+ Dokumen

#### C.1 Pinjaman Kelompok (Group Loan Documents)
- **List:** `/lending/loans/pdf`
- **Card:** `/lending/loans/{loan}/card`, `/card/reprint`
- **Settlement:** `/lending/loans/{loan}/settlement-letter`
- **Proposal Stage (13 dokumen):** cover_proposal, pengajuan_kredit, profil_kelompok, susunan_pengurus, daftar_pemanfaat, pernyataan_tanggung_renteng, check, anggota, ktp, catatan_bimbingan
- **Verification Stage (6 dokumen):** rekomendasi_kredit, ba_musyawarah, surat_verifikasi, surat_kelayakan, form_verifikasi, form_verifikasi_anggota, daftar_hadir_verifikasi
- **Disbursement Stage (15+ dokumen):** cover_pencairan, spk, berita_acara_pencairan, ba_pendanaan, rencana_angsuran, kartu_angsuran_anggota, pemberitahuan_desa, peserta_asuransi, tanda_terima, kuitansi_pencairan, kuitansi_anggota, tagihan, surat_ahli_waris, surat_kuasa, tanggung_renteng_kematian, iptw, rekening_koran, pernyataan_peminjam, daftar_hadir_pencairan

#### C.2 Pinjaman Individu (Member Loan Documents)
- **Card:** `/lending/member-loans/{loan}/card`
- **Settlement:** `/lending/member-loans/{loan}/settlement-letter`, `/bukti_pengembalian_jaminan`
- **Individual Proposal (8 dokumen):** cover_proposal_individu, pengajuan_kredit_individu, rekomendasi_kredit_individu, rekomendasi_verifikator_individu, pernyataan_peminjam_individu, form_verifikasi_individu, surat_persetujuan_kuasa_individu, analisis_keputusan_kredit
- **Individual Verification (1 dokumen):** analisis_keputusan_kredit
- **Individual Disbursement (12 dokumen):** cover_pencairan_individu, kartu_angsuran_individu, rencana_angsuran_individu, ba_pencairan_individu, spk_individu, kuitansi_pencairan_individu, berita_acara_pencairan_individu, tagihan_individu, tanda_terima_jaminan, surat_pemberitahuan, pengikat_diri_sebagai_penjamin, surat_pernyataan_suami, daftar_hadir_pencairan_individu, pemberitahuan_desa_individu, surat_kelayakan_individu

### D. SIMULASI & BUKTI — 3 Laporan

| # | Nama | Route |
|---|---|---|
| 28 | Simulasi Pinjaman (PDF) | `/lending/simulation/pdf` |
| 29 | Bukti Kas (BKM/BKK/BM) | `/accounting/journals/{entry}/cash-evidence/{kind}` |
| 30 | Bukti Angsuran | `/accounting/journal-entries/{entry}/installment-receipt` |

### E. KONSOLIDASI PROVINSI — 5 Laporan + 1 Paket

| # | Nama | Route |
|---|---|---|
| 31 | Paket 5 Laporan | `/province/reports/pack` |
| 32 | Neraca Provinsi | `/province/reports/balance-sheet` |
| 33 | Laba Rugi Provinsi | `/province/reports/income-statement` |
| 34 | Arus Kas Provinsi | `/province/reports/cash-flow` |
| 35 | Perubahan Ekuitas Provinsi | `/province/reports/equity-changes` |
| 36 | CALK Provinsi | `/province/reports/calk` |
| 37 | PDF Paket 5 Laporan | `/province/reports/pdf` |

### F. KONSOLIDASI KABUPATEN — 5 Laporan + 5 PDF

| # | Nama | Route |
|---|---|---|
| 38 | Neraca Kabupaten | `/regency/reports/balance-sheet` |
| 39 | Laba Rugi Kabupaten | `/regency/reports/income-statement` |
| 40 | Buku Besar Kabupaten | `/regency/reports/general-ledger` |
| 41 | Arus Kas Kabupaten | `/regency/reports/cash-flow` |
| 42 | CALK Kabupaten | `/regency/reports/calk` |
| 43-47 | PDF per laporan | `/regency/reports/{type}/pdf` |

### G. MASTER DATA EXPORT — 3 Export

| # | Nama | Route |
|---|---|---|
| 48 | Export Anggota | `/master-data/members/export` |
| 49 | Export Kelompok | `/master-data/groups/export` |
| 50 | Export Lembaga | `/master-data/institutions/export` |

---

## BAGIAN 3: GAP ANALYSIS — LAPORAN SIUPK YANG BELUM ADA DI SIUPKNEXT

### A. Laporan Keuangan yang Belum Ada (5 Item)

| # | Kode SIUPK | Nama | Status di SIUPKNext | Prioritas |
|---|---|---|---|---|
| ❌ 1 | `cover` | Cover Laporan Keuangan | ⚠️ Parsial (ada di AnnualPack) | RENDAH |
| ❌ 2 | `surat_pengantar` | Surat Pengantar | ⚠️ Parsial (ada di AnnualPack) | RENDAH |
| ❌ 3 | `simpanan` | Daftar Simpanan | ❌ Belum Ada | **TINGGI** |
| ❌ 4 | `perubahan_modal` | Laporan Perubahan Modal | ✅ Ada (equity-change) | - |
| ⚠️ 5 | - | Invoice | ❌ Belum Ada | SEDANG |

### B. Laporan Perkembangan Piutang (Kelompok) — Banyak yang Belum Ada

| # | Kode SIUPK | Nama | Status di SIUPKNext | Prioritas |
|---|---|---|---|---|
| ❌ 1 | `proposal` | Daftar Proposal | ❌ Belum Ada sebagai laporan agregat | **TINGGI** |
| ❌ 2 | `verifikasi` | Daftar Verifikasi | ❌ Belum Ada sebagai laporan agregat | **TINGGI** |
| ❌ 3 | `waiting` | Daftar Waiting List | ❌ Belum Ada | **TINGGI** |
| ❌ 4 | `lunas` | Daftar Pinjaman Lunas | ❌ Belum Ada | **TINGGI** |
| ❌ 5 | `kelompok_aktif` | Pinjaman Kelompok Aktif | ❌ Belum Ada | **TINGGI** |
| ❌ 6 | `pinjaman_per_kelompok` | LPP per Kelompok | ✅ Ada (lpp-kelompok) | - |
| ⚠️ 7 | `lpp_per_desa` | LPP per Desa | ✅ Ada (lpp-desa) | - |
| ❌ 8 | `pemanfaat_aktif` | Pemanfaat Aktif | ❌ Belum Ada | SEDANG |
| ❌ 9 | `cadangan_penghapusan` (versi SIUPK) | Cadangan Penghapusan (versi SIUPK) | ✅ Ada (cadangan-penghapusan) | - |
| ❌ 10 | `rencana_realisasi` | Rencana & Realisasi Kelompok | ⚠️ Parsial (ada schedule-vs-actual) | SEDANG |
| ❌ 11 | `rencana_realisasi_i` | Rencana & Realisasi Individu | ❌ Belum Ada | **TINGGI** |
| ❌ 12 | `tagihan_hari_ini` | Tagihan Jatuh Tempo Hari Ini | ❌ Belum Ada | **TINGGI** |
| ❌ 13 | `menunggak` | Daftar Tunggakan | ❌ Belum Ada | **TINGGI** |
| ❌ 14 | `pelunasan` | Pinjaman Mendekati Jatuh Tempo | ❌ Belum Ada | SEDANG |
| ❌ 15 | `pinjaman_hapus` | Pinjaman Dihapusbukukan (Kelompok) | ❌ Belum Ada | **TINGGI** |
| ❌ 16 | `pinjaman_hapus_individu` | Pinjaman Dihapusbukukan (Individu) | ❌ Belum Ada | **TINGGI** |
| ❌ 17 | `pinjaman_anggota_hapus` | Pinjaman Anggota Dihapus | ❌ Belum Ada | SEDANG |
| ❌ 18 | `pinjaman_individu_mingguan` | LPP Individu Mingguan | ❌ Belum Ada | RENDAH |
| ❌ 19 | `pinjaman_per_kelompok_mingguan` | LPP Kelompok Mingguan | ❌ Belum Ada | RENDAH |
| ❌ 20 | `kolek_individu_mingguan` | Kolektibilitas Individu Mingguan | ❌ Belum Ada | RENDAH |
| ❌ 21 | `kolek_per_kelompok_mingguan` | Kolektibilitas Kelompok Mingguan | ❌ Belum Ada | RENDAH |

### C. Laporan Mingguan — Belum Ada Semuanya

| # | Kode SIUPK | Nama | Status di SIUPKNext | Prioritas |
|---|---|---|---|---|
| ❌ 1 | `pinjaman_individu_mingguan` | LPP Mingguan Individu | ❌ Belum Ada | RENDAH |
| ❌ 2 | `pinjaman_per_kelompok_mingguan` | LPP Mingguan Kelompok | ❌ Belum Ada | RENDAH |
| ❌ 3 | `kolek_individu_mingguan` | Kolek Mingguan Individual | ❌ Belum Ada | RENDAH |
| ❌ 4 | `kolek_per_kelompok_mingguan` | Kolek Mingguan Kelompok | ❌ Belum Ada | RENDAH |

### D. Laporan OJK — Belum Ada Semuanya (14 Laporan)

| # | Kode SIUPK | Nama | Status | Prioritas |
|---|---|---|---|---|
| ❌ 1 | `CV` | Cover OJK | ❌ Belum Ada | RENDAH |
| ❌ 2 | `PF` | Profil Kelembagaan OJK | ❌ Belum Ada | **TINGGI** |
| ❌ 3 | `OJKP` | Neraca OJK | ❌ Belum Ada | **TINGGI** |
| ❌ 4 | `LRL` | Laba Rugi OJK | ❌ Belum Ada | **TINGGI** |
| ❌ 5 | `DRP` | Daftar Rincian Pinjaman Aktif | ❌ Belum Ada | **TINGGI** |
| ❌ 6 | `DRPL` | Rincian Pinjaman Lunas Kelompok | ❌ Belum Ada | **TINGGI** |
| ❌ 7 | `DRPLi` | Rincian Pinjaman Lunas Individu | ❌ Belum Ada | **TINGGI** |
| ❌ 8 | `DRT` | Daftar Rincian Tabungan | ❌ Belum Ada | **TINGGI** |
| ❌ 9 | `SMPN` | Simpanan & Piutang | ❌ Belum Ada | **TINGGI** |
| ❌ 10 | `bunga` | Daftar Bunga Simpanan | ❌ Belum Ada | **TINGGI** |
| ❌ 11 | `DRPY` | Rincian Pinjaman Diterima | ❌ Belum Ada | **TINGGI** |
| ❌ 12 | `KBP` | Kolektibilitas OJK v1 | ❌ Belum Ada | **TINGGI** |
| ❌ 13 | `KBP2` | Kolektibilitas OJK v2 | ❌ Belum Ada | RENDAH |
| ❌ 14 | `pcpp` | Penyisihan Cadangan Penghapusan Piutang | ❌ Belum Ada | **TINGGI** |

### E. Laporan Tutup Buku — Belum Ada Semuanya (5 Laporan)

| # | Kode SIUPK | Nama | Status | Prioritas |
|---|---|---|---|---|
| ❌ 1 | `alokasi_laba` | Pengalokasian Laba | ❌ Belum Ada | **TINGGI** |
| ❌ 2 | `jurnal_tutup_buku` | Jurnal Tutup Buku | ❌ Belum Ada | **TINGGI** |
| ❌ 3 | `neraca_tutup_buku` | Neraca Tutup Buku | ❌ Belum Ada | **TINGGI** |
| ❌ 4 | `laba_rugi_tutup_buku` | Laba Rugi Tutup Buku | ❌ Belum Ada | **TINGGI** |
| ❌ 5 | `CALK_tutup_buku` | CALK Tutup Buku | ❌ Belum Ada | **TINGGI** |

### F. E-Budgeting — Belum Ada

| # | Kode SIUPK | Nama | Status | Prioritas |
|---|---|---|---|---|
| ❌ 1 | `EB` | E-Budgeting per Triwulan | ❌ Belum Ada | **TINGGI** |

### G. Laporan Pengawas — Belum Ada

| # | Kode SIUPK | Nama | Status | Prioritas |
|---|---|---|---|---|
| ❌ 1 | `catatan_pengawas` | Catatan Pengawas | ❌ Belum Ada | **TINGGI** |

### H. Laporan Penduduk — Belum Ada sebagai Laporan

| # | Kode SIUPK | Nama | Status | Prioritas |
|---|---|---|---|---|
| ⚠️ 1 | `penduduk` | Daftar Penduduk (untuk pelaporan) | ⚠️ Ada Export CSV anggota | RENDAH |

---

## BAGIAN 4: RINGKASAN GAP

### Statistik Gap

| Kategori | Jumlah SIUPK | Jumlah SIUPKNext | Gap |
|---|---|---|---|
| **Laporan Keuangan** | 11 | 16 | +5 (AnnualPack + Bundle) ✅ |
| **Piutang (Kelompok)** | 26 | 4 | **-22** ❌ |
| **Piutang (Individu)** | 9 | 3 | **-6** ❌ |
| **Mingguan** | 4 | 0 | **-4** ❌ |
| **OJK** | 14 | 0 | **-14** ❌ |
| **Tutup Buku** | 5 | 0 | **-5** ❌ |
| **Basis Data** | 3 | 3 (CSV export) | ✅ |
| **Aset** | 2 | 2 | ✅ |
| **E-Budgeting** | 1 | 0 | **-1** ❌ |
| **Penilaian Kesehatan** | 2 | 1 | **-1** ⚠️ |
| **Pengawas** | 1 | 0 | **-1** ❌ |
| **Dokumen Pinjaman** | 4 (route) | 60+ | ✅✅ |
| **TOTAL** | ~75 | ~50 unique + 60+ dokumen | -25 |

### Rekomendasi Prioritas Implementasi

#### PRIORITAS TINGGI (Harus Ada)
1. **Simpanan** (Daftar Simpanan) — modul simpanan/tabungan
2. **Proposal/Verifikasi/Waiting** — pipeline laporan berdasarkan stage
3. **Daftar Lunas** — untuk tracking pelunasan
4. **Kelompok Aktif & Pemanfaat Aktif** — dashboard monitoring
5. **Rencana & Realisasi Individu**
6. **Tagihan Jatuh Tempo Hari Ini** — operasional harian
7. **Daftar Tunggakan** — collection monitoring
8. **Pinjaman Dihapusbukukan** (Kelompok + Individu)
9. **Modul OJK** (14 laporan) — regulatory compliance
10. **Modul Tutup Buku** (5 laporan) — year-end closing
11. **E-Budgeting** — planning & budgeting
12. **Catatan Pengawas**

#### PRIORITAS SEDANG
- Invoice (untuk cetak invoice angsuran)
- Daftar Penduduk (sebagai laporan, bukan hanya export)
- Pinjaman Mendekati Jatuh Tempo

#### PRIORITAS RENDAH
- Laporan Mingguan (4 varian)
- OJK v2 (KBP2)
- Beberapa sub-varian minor

---

## BAGIAN 5: REKOMENDASI STRATEGI PENYAMAKAN

### Pendekatan yang Disarankan

1. **Phase 1 (Critical Missing)** — Implementasi 11 laporan Prioritas Tinggi yang hilang:
   - Simpanan
   - Proposal/Verifikasi/Waiting/Lunas/Kelompok Aktif/Pemanfaat Aktif (6 laporan)
   - Rencana Realisasi Individu
   - Tagihan Jatuh Tempo
   - Daftar Tunggakan
   - Pinjaman Hapus (Kelompok + Individu)

2. **Phase 2 (Regulatory)** — Implementasi Modul OJK (14 laporan):
   - Karena ini untuk compliance ke OJK
   - Bisa copy pattern dari SIUPK langsung

3. **Phase 3 (Year-End)** — Implementasi Modul Tutup Buku (5 laporan)

4. **Phase 4 (Operational)** — E-Budgeting, Catatan Pengawas, Invoice

5. **Phase 5 (Nice-to-have)** — Laporan Mingguan, sub-varian

### Pola Implementasi di SIUPKNext

Setiap laporan SIUPK yang akan diporting mengikuti pola:

```
Controller:    app/Domain/{Module}/Controllers/Reports/{ReportName}Controller.php
Service:       app/Domain/{Module}/Services/Reports/{ReportName}Service.php
Vue Page:      resources/js/Pages/{Module}/Reports/{ReportName}.vue
PDF Blade:     resources/views/reports/pdf/{report_name}.blade.php
Route:         routes/web.php (di group middleware yang sesuai)
Component:     Reuse ReportPeriodFilter.vue + AppCard.vue + AppButton.vue
```

### Catatan tentang Tipe Kelompok vs Individu

SIUPKNext sudah memisahkan dengan jelas lewat:
- Route `/lending/reports/lpp-kelompok` vs `/lending/reports/lpp-individu`
- Service method `LppReportService::buildKelompok()` vs `buildIndividu()`
- Vue component terpisah: `LppKelompok.vue` vs `LppIndividu.vue`
- Controller method `LoanReportController::lppKelompok*` vs `lppIndividu*`

Pemisahan ini **lebih baik** dari SIUPK yang menggunakan 1 method dengan parameter tipe. Pertahankan pola ini di SIUPKNext.

---

## BAGIAN 6: FILE-Faktor KUNCI YANG PERLU DICEK UNTUK IMPLEMENTASI

### Dari SIUPK (sumber referensi):
| File | Lokasi |
|---|---|
| PelaporanController | `app/Http/Controllers/PelaporanController.php` (5145+ baris) |
| Kabupaten LaporanController | `app/Http/Controllers/Kabupaten/LaporanController.php` |
| View pelaporan | `resources/views/pelaporan/view/` |
| Tabel jenis_laporan | DB table `jenis_laporan` |
| Sub-laporan | `resources/views/pelaporan/partials/sub_laporan.blade.php` |

### Di SIUPKNext (tujuan):
| File Pattern | Lokasi |
|---|---|
| Accounting Reports | `app/Domain/Accounting/Controllers/Reports/ReportController.php` |
| Lending Reports | `app/Domain/Lending/Controllers/Reports/LoanReportController.php` |
| Province Reports | `app/Domain/Province/Controllers/Reports/ProvinceReportController.php` |
| Regency Reports | `app/Domain/Regency/Controllers/Reports/RegencyReportController.php` |
| Vue Pages | `resources/js/Pages/{Accounting,Lending,Province,Regency}/Reports/*.vue` |
| PDF Blades | `resources/views/reports/pdf/*.blade.php` |
| Services | `app/Domain/{Module}/Services/Reports/*Service.php` |
| Layout | `resources/js/Layouts/{Authenticated,Province,Regency}Layout.vue` |

---

## BAGIAN 7: CHECKLIST IMPLEMENTASI YANG DIREKOMENDASIKAN

### Checklist Endpoint Pelaporan Baru yang Perlu Dibuat

```
[ ] GET /accounting/reports/simpanan (Daftar Simpanan)
[ ] GET /lending/reports/proposals (Daftar Proposal)
[ ] GET /lending/reports/verifications (Daftar Verifikasi)
[ ] GET /lending/reports/waiting-list (Daftar Waiting List)
[ ] GET /lending/reports/loans/paid (Daftar Lunas)
[ ] GET /lending/reports/groups/active (Kelompok Aktif)
[ ] GET /lending/reports/members/active (Pemanfaat Aktif)
[ ] GET /lending/reports/schedule-vs-actual-individu
[ ] GET /lending/reports/due-today (Tagihan Jatuh Tempo)
[ ] GET /lending/reports/overdue (Daftar Tunggakan)
[ ] GET /lending/reports/loans/settlements (Pinjaman Mendekati Lunas)
[ ] GET /lending/reports/write-offs (Pinjaman Hapus Kelompok)
[ ] GET /lending/reports/write-offs-individu
[ ] GET /accounting/reports/budgeting (E-Budgeting)
[ ] GET /supervisor/reports (Catatan Pengawas)
[ ] GET /accounting/reports/year-end/closing/* (Tutup Buku: 5 endpoint)
[ ] GET /regulatory/ojk/* (OJK: 14 endpoint)
[ ] GET /accounting/reports/invoice/{id} (Invoice Cetak)
```

---

## Kesimpulan

SIUPKNext sudah memiliki **fondasi pelaporan yang kuat** dengan:
- ✅ 16 laporan akuntansi (lebih lengkap dari SIUPK dengan Annual Pack)
- ✅ 9 laporan lending utama
- ✅ 60+ dokumen cetak pinjaman
- ✅ Konsolidasi Provinsi + Kabupaten
- ✅ Format PDF + Excel + CSV lengkap
- ✅ Vue 3 + Inertia.js (modern)

**Namun masih ada 25 laporan SIUPK yang hilang**, dengan rincian:
- 5 laporan simpanan/keuangan tambahan
- 22 laporan perkembangan piutang tambahan
- 14 laporan OJK
- 5 laporan tutup buku
- 1 E-Budgeting
- 1 catatan pengawas

**Strategi penyamaan**: Lihat Bagian 5 untuk rekomendasi fase implementasi.

---

**Auditor:** OpenCode Agent  
**Tanggal:** 24 September 2026  
**File Disimpan di:** `C:\laragon\www\siupknext\docs\audit\audit-pelaporan-siupk-vs-siupknext.md`