# Task Tracker: Implementasi Menu Pelaporan SIUPKNext

**Tanggal Mulai:** 24 September 2026  
**Target:** Semua 25+ laporan SIUPK yang belum ada di SIUPKNext

---

## PHASE 1: Laporan Operasional yang Hilang (11 Laporan)

### 1.1 Modul Simpanan
- [ ] Daftar Simpanan → `/accounting/reports/simpanan`
  - Controller: `app/Domain/Accounting/Controllers/Reports/SimpananController.php`
  - Service: `app/Domain/Accounting/Services/Reports/SimpananReportService.php`
  - Vue: `resources/js/Pages/Accounting/Reports/Simpanan.vue`
  - PDF: `resources/views/reports/pdf/simpanan.blade.php`

### 1.2 Laporan Pinjaman Stage-Based (6 Laporan)
- [ ] Daftar Proposal → `/lending/reports/proposals`
- [ ] Daftar Verifikasi → `/lending/reports/verifications`
- [ ] Daftar Waiting List → `/lending/reports/waiting-list`
- [ ] Daftar Pinjaman Lunas → `/lending/reports/paid`
- [ ] Kelompok Aktif → `/lending/reports/groups/active`
- [ ] Pemanfaat Aktif → `/lending/reports/members/active`

### 1.3 Laporan Pinjaman Individu
- [ ] Rencana & Realisasi Individu → `/lending/reports/schedule-vs-actual-individu`

### 1.4 Laporan Tunggakan & Jatuh Tempo
- [ ] Tagihan Jatuh Tempo Hari Ini → `/lending/reports/due-today`
- [ ] Daftar Tunggakan → `/lending/reports/overdue`

### 1.5 Laporan Write-off
- [ ] Pinjaman Dihapusbukukan Kelompok → `/lending/reports/write-offs`
- [ ] Pinjaman Dihapusbukukan Individu → `/lending/reports/write-offs-individu`

---

## PHASE 2: Modul OJK - Regulatory Reporting (14 Laporan)

### 2.1 Cover & Profil
- [ ] Cover OJK → `/regulatory/ojk/cover`
- [ ] Profil Kelembagaan OJK → `/regulatory/ojk/profile`

### 2.2 Laporan Keuangan OJK
- [ ] Neraca OJK → `/regulatory/ojk/balance-sheet`
- [ ] Laba Rugi OJK → `/regulatory/ojk/income-statement`

### 2.3 Laporan Pinjaman OJK
- [ ] Daftar Rincian Pinjaman Aktif (DRP) → `/regulatory/ojk/active-loans`
- [ ] Rincian Pinjaman Lunas Kelompok (DRPL) → `/regulatory/ojk/paid-group`
- [ ] Rincian Pinjaman Lunas Individu (DRPLi) → `/regulatory/ojk/paid-individu`

### 2.4 Laporan Simpanan OJK
- [ ] Daftar Rincian Tabungan (DRT) → `/regulatory/ojk/savings`
- [ ] Simpanan & Piutang (SMPN) → `/regulatory/ojk/savings-receivables`
- [ ] Daftar Bunga Simpanan → `/regulatory/ojk/interest`

### 2.5 Laporan Pinjaman Diterima
- [ ] Rincian Pinjaman Diterima (DRPY) → `/regulatory/ojk/loans-received`

### 2.6 Kolektibilitas OJK
- [ ] Kolektibilitas OJK v1 → `/regulatory/ojk/collectibility`
- [ ] Kolektibilitas OJK v2 → `/regulatory/ojk/collectibility-v2`
- [ ] Penyisihan Cadangan Penghapusan Piutang (PCPP) → `/regulatory/ojk/allowance`

---

## PHASE 3: Modul Tutup Buku - Year-End Closing (5 Laporan)

- [ ] Alokasi Laba → `/accounting/year-end/allocation`
- [ ] Jurnal Tutup Buku → `/accounting/year-end/journal`
- [ ] Neraca Tutup Buku → `/accounting/year-end/balance-sheet`
- [ ] Laba Rugi Tutup Buku → `/accounting/year-end/income-statement`
- [ ] CALK Tutup Buku → `/accounting/year-end/calk`

---

## PHASE 4: Operasional Tambahan

### 4.1 E-Budgeting
- [ ] E-Budgeting per Triwulan → `/accounting/reports/budgeting`

### 4.2 Pengawas
- [ ] Catatan Pengawas → `/supervisor/notes`

### 4.3 Invoice
- [ ] Invoice Cetak → `/accounting/invoice/{id}`

---

## PHASE 5: Polish - Laporan Mingguan & Sub-varian (5 Laporan)

- [ ] LPP Mingguan Individu → `/lending/reports/weekly/lpp-individu`
- [ ] LPP Mingguan Kelompok → `/lending/reports/weekly/lpp-kelompok`
- [ ] Kolek Mingguan Individu → `/lending/reports/weekly/kolek-individu`
- [ ] Kolek Mingguan Kelompok → `/lending/reports/weekly/kolek-kelompok`
- [ ] Pinjaman Mendekati Jatuh Tempo → `/lending/reports/near-settlement`

---

## Total: 36 Laporan Baru

### Format Output Standar per Laporan
```
app/Domain/{Module}/Controllers/Reports/{Name}Controller.php
app/Domain/{Module}/Services/Reports/{Name}Service.php
resources/js/Pages/{Module}/Reports/{Name}.vue
resources/views/reports/pdf/{name}.blade.php
routes/web.php (entries di group yang sesuai)
```

### Pola Route
- HTML Preview: `GET /path/{report}`
- PDF Download: `GET /path/{report}/pdf`
- Excel Download: `GET /path/{report}/excel` (jika applicable)

### Middleware/Permission
- `auth` — semua route laporan
- `permission:reports.view` — laporan akuntansi
- `permission:loans.view` — laporan lending
- `permission:regulatory.view` — OJK
- `permission:year-end.execute` — tutup buku
- `tenant.context` — semua (multi-tenant isolation)