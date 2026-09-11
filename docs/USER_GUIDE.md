# Panduan Pengguna siupk Next
**Sistem Informasi Dana Bergulir Masyarakat & Akuntansi BUMDesma / LKD**
*Dokumen Resmi Operasional & Manual Penggunaan Aplikasi*

---

## Daftar Isi

1. [Pendahuluan & Gambaran Umum](#1-pendahuluan--gambaran-umum)
2. [Login, Keamanan & Navigasi Antarmuka](#2-login-keamanan--navigasi-antarmuka)
3. [Dashboard Operasional Eksekutif](#3-dashboard-operasional-eksekutif)
4. [Manajemen Master Data](#4-manajemen-master-data)
5. [Pengelolaan Dana Bergulir & Pinjaman (Lending)](#5-pengelolaan-dana-bergulir--pinjaman-lending)
6. [Akuntansi & Jurnal Keuangan](#6-akuntansi--jurnal-keuangan)
7. [Inventaris & Aset Tetap](#7-inventaris--aset-tetap)
8. [Perencanaan Anggaran (E-Budgeting)](#8-perencanaan-anggaran-e-budgeting)
9. [Pelaporan Keuangan (Financial Reports)](#9-pelaporan-keuangan-financial-reports)
10. [Laporan Pinjaman & Analisis Piutang](#10-laporan-pinjaman--analisis-piutang)
11. [Prosedur Periodik (Tutup Buku & Taksiran Pajak)](#11-prosedur-periodik-tutup-buku--taksiran-pajak)
12. [Tagihan & Langganan SaaS (Billing)](#12-tagihan--langganan-saas-billing)
13. [Pusat Notifikasi & WhatsApp Gateway](#13-pusat-notifikasi--whatsapp-gateway)
14. [Pengaturan Lembaga (Settings)](#14-pengaturan-lembaga-settings)
15. [Manajemen Pengguna, Peran & Hak Akses (RBAC)](#15-manajemen-pengguna-peran--hak-akses-rbac)
16. [Profil Pengguna & Personalisasi](#16-profil-pengguna--personalisasi)
17. [Wizard Onboarding & Migrasi Data Mandiri](#17-wizard-onboarding--migrasi-data-mandiri)
18. [Portal Supervisi Kabupaten](#18-portal-supervisi-kabupaten)
19. [Portal Supervisi Provinsi](#19-portal-supervisi-provinsi)
20. [Panel Superadmin Platform SaaS](#20-panel-superadmin-platform-saas)
21. [Asisten Kecerdasan Buatan (AI Assistant - Ariel)](#21-asisten-kecerdasan-buatan-ai-assistant---ariel)
22. [Pintasan Keyboard, Tips & Panduan Troubleshooting](#22-pintasan-keyboard-tips--panduan-troubleshooting)
23. [Lampiran A — Matriks Hak Akses (RBAC)](#lampiran-a--matriks-hak-akses-rbac)
24. [Lampiran B — Katalog 36 Dokumen Cetak Pinjaman PDF](#lampiran-b--katalog-36-dokumen-cetak-pinjaman-pdf)

---

## 1. Pendahuluan & Gambaran Umum

### 1.1 Mengenal siupk Next
**siupk Next** adalah aplikasi web modern berbasis komputasi awan (*Multi-Tenant Cloud SaaS*) yang dirancang khusus untuk mengelola operasional **Dana Bergulir Masyarakat (DBM)** pada Badan Usaha Milik Desa Bersama (**BUMDesma**) dan Lembaga Keuangan Desa (**LKD**) di seluruh Indonesia.

Aplikasi ini mengintegrasikan seluruh rantai proses bisnis dalam satu ekosistem terpadu:
- **Perguliran Pinjaman Terpadu**: Dari pendaftaran proposal, verifikasi lapangan, musyawarah pendanaan (MAD), akad kredit (SPK), pencairan, penagihan angsuran, hingga pelunasan atau restrukturisasi.
- **Akuntansi Standar SAK EP / ETAP**: Sistem pembukuan berpasangan (*double-entry*) otomatis dan *immutable* yang menjamin akuntabilitas tanpa selisih.
- **Laporan Keuangan & Piutang Lengkap**: 16+ laporan keuangan standar dan 36 dokumen cetak resmi berstandar hukum.
- **Pengawasan Bertingkat**: Portal monitoring real-time untuk Dinas PMD Kabupaten dan Dinas PMD Provinsi.
- **Automasi Notifikasi & Billing**: Integrasi WhatsApp Gateway dan Multi-Payment Gateway (QRIS & Virtual Account).
- **Asisten Cerdas (Ariel)**: Konsultasi regulasi (PP No. 11/2021, Permendesa No. 15/2021) dan analisis data berbasis AI.

### 1.2 Landasan Hukum & Kepatuhan
siupk Next disusun mengikuti ketentuan perundang-undangan Republik Indonesia:
1. **PP No. 11 Tahun 2021** tentang Badan Usaha Milik Desa.
2. **Permendesa PDTT No. 15 Tahun 2021** tentang Tata Cara Pembentukan Pengelola Kegiatan DBM eks PNPM-MPd menjadi BUMDesma.
3. **Standar Akuntansi Keuangan Entitas Privat (SAK EP)** dan SAK ETAP.

---

## 2. Login, Keamanan & Navigasi Antarmuka

### 2.1 Halaman Masuk (Login)
Untuk mengakses sistem:
1. Buka alamat URL instansi Anda di peramban web (Google Chrome, Microsoft Edge, Mozilla Firefox, atau Safari).
2. Masukkan **Nama Pengguna (Username)** atau **Alamat Email** yang terdaftar.
3. Masukkan **Kata Sandi (Password)** Anda.
4. *(Opsional)* Centang kotak **Ingat Saya** untuk menyimpan sesi login pada perangkat pribadi.
5. Klik tombol **Masuk ke Sistem**.

> **Keamanan Sesi**: Sistem secara otomatis mengamankan sesi Anda. Login dari perangkat baru akan memutus sesi aktif di perangkat sebelumnya (*Single Session Enforcement*).

### 2.2 Struktur Navigasi & Sidebar
Antarmuka siupk Next terdiri dari:
- **Sidebar Kiri**: Menu navigasi hierarkis berdasarkan modul kerja. Sidebar dapat diperkecil (*collapse*) untuk memperluas area kerja.
- **Header Atas**:
  - **Pencarian Cepat / Command Palette** (`Ctrl+K` atau `Cmd+K`).
  - **Ikon Notifikasi**: Peringatan tagihan jatuh tempo, pinjaman baru yang butuh verifikasi, dan pembaruan sistem.
  - **Menu Profil & Tema**: Ganti foto, ubah password, dan pilih dari **7 Tema Warna Antarmuka**.
- **Breadcrumb**: Petunjuk lokasi halaman aktif saat ini.
- **Area Konten Utama**: Tempat formulir data, tabel interaktif, dan grafik visual ditampilkan.

### 2.3 Command Palette (`Ctrl+K`)
Tekan tombol keyboard `Ctrl+K` (Windows/Linux) atau `Cmd+K` (Mac) dari halaman mana saja:
- Cari nama anggota, NIK, nama kelompok, atau nomor pinjaman seketika.
- Lompat ke menu laporan atau halaman transaksi secara instan tanpa perlu mencari di sidebar.

### 2.4 Personalisasi Tema Warna
Tersedia 7 preset warna tema elegan: *Modern Indigo*, *Emerald Forest*, *Sunset Amber*, *Midnight Blue*, *Slate Corporate*, *Ocean Breeze*, dan *Rose Ruby*. Preferensi tersimpan otomatis pada peramban Anda.

---

## 3. Dashboard Operasional Eksekutif

**Menu:** `Dashboard` (Akses: `/dashboard`)

Dashboard menyajikan ikhtisar kondisi finansial dan operasional lembaga secara langsung:

### 3.1 Kartu Indikator Kinerja Utama (KPI Cards)
- **Total Outstanding Piutang**: Total sisa pokok pinjaman aktif di masyarakat.
- **Jumlah Pemanfaat Aktif**: Total anggota yang sedang memanfaatkan dana bergulir.
- **Posisi Saldo Kas & Bank**: Saldo riil likuiditas keuangan per hari ini.
- **Rasio NPL (Non-Performing Loan)**: Persentase kredit macet terhadap total portofolio (disertai indikator warna hijau/kuning/merah).
- **Laba Rugi Berjalan**: Surplus/defisit usaha periode tahun berjalan.
- **Penerimaan Jasa Bulan Ini**: Realisasi penerimaan bunga/jasa pinjaman bulan ini.

### 3.2 Pipeline Perguliran Dana (Interactive Drilldown Modal)
Menampilkan tahapan pinjaman yang sedang berjalan:
- *Proposal / Draft* $ightarrow$ *Verifikasi* $ightarrow$ *Menunggu Persetujuan (Waiting)* $ightarrow$ *Aktif* $ightarrow$ *Lunas*.
- **Interaksi**: Klik pada salah satu kotak tahapan untuk membuka jendela *Drilldown Modal*, yang menampilkan daftar rinci pinjaman pada tahapan tersebut lengkap dengan nama kelompok, plafon, dan tanggal pengajuan.

### 3.3 Grafik Tren & Portofolio
- Grafik tren pencairan vs pengembalian angsuran dalam 12 bulan terakhir.
- Grafik komposisi pinjaman per sektor usaha (Perdagangan, Pertanian, Peternakan, Jasa, Industri Rumah Tangga).

### 3.4 Ringkasan Jatuh Tempo & Peringatan Dini
- **Tagihan Jatuh Tempo 7 Hari Mendatang**: Daftar kelompok yang harus membayar angsuran dalam pekan ini.
- **Tunggakan Kritis**: Peringatan kelompok yang menunggak lebih dari 30/60/90 hari untuk segera ditindaklanjuti.

---

## 4. Manajemen Master Data

Modul Master Data adalah fondasi data anggota, kelompok, wilayah desa, dan institusi mitra.

### 4.1 Data Desa
**Menu:** `Master -> Data Desa` (`/master-data/villages`)
- **Daftar Desa**: Menampilkan seluruh desa/kelurahan dalam wilayah kerja BUMDesma/LKD.
- **Informasi Desa**: Kode wilayah, nama desa, nama Kepala Desa, nomor telepon kantor desa, dan status aktif.
- **Fitur Scoped Operator Desa**: Jika pengguna berstatus *Operator Desa*, aplikasi secara otomatis membatasi data hanya untuk desa yang bersangkutan (*VillageScope*).

### 4.2 Data Anggota / Pemanfaat
**Menu:** `Master -> Anggota` (`/master-data/members`)
- **Pendaftaran Anggota Baru**:
  - Formulir input data: Nomor Identitas Kependudukan (NIK 16 Digit dengan validasi otomatis), Nomor Anggota, Nama Lengkap, Tempat & Tanggal Lahir, Jenis Kelamin, Alamat Lengkap, RT/RW, Desa, Nomor WhatsApp/HP, Status Ekonomi (RTM / Non-RTM), dan Pekerjaan.
- **Pencarian & Filter Cepat**: Cari berdasarkan NIK, Nama, atau filter per Desa dan Kategori RTM.
- **Halaman Detail Anggota**: Menampilkan biodata lengkap, kartu identitas, kelompok yang diikuti, serta **Riwayat Seluruh Pinjaman** yang pernah diterima.
- **Fitur Ekspor & Impor Massal**:
  - Ekspor seluruh data anggota ke format **Excel (.xlsx)** atau **CSV**.
  - Impor data anggota dari file Excel menggunakan template standar yang disediakan sistem.

### 4.3 Data Kelompok
**Menu:** `Master -> Kelompok` (`/master-data/groups`)
- **Pendaftaran Kelompok**: Nama kelompok, kode kelompok, jenis kelompok (SPP - Simpan Pinjam Perempuan / UEP - Usaha Ekonomi Produktif), desa domisili, dan tanggal pendirian.
- **Susunan Pengurus Kelompok**: Mengatur Ketua, Sekretaris, dan Bendahara (KSB) yang dipilih langsung dari daftar anggota kelompok.
- **Fitur Tambah Anggota Cepat (Quick Add Member)**: Menambahkan anggota baru ke dalam kelompok langsung dari formulir kelompok tanpa harus membuka menu anggota terlebih dahulu.
- **Riwayat Pinjaman Kelompok**: Memantau seluruh perguliran yang pernah diajukan kelompok dari awal berdiri hingga pinjaman aktif saat ini.

### 4.4 Lembaga Lain / Mitra
**Menu:** `Master -> Lembaga Lain` (`/master-data/institutions`)
- Pencatatan data mitra BPD, Dinas PMD, Bank penyalur kas, koperasi mitra, dan vendor.
- Fitur CRUD, Impor dan Ekspor data mitra.

---

## 5. Pengelolaan Dana Bergulir & Pinjaman (Lending)

Siklus perguliran pinjaman di siupk Next dirancang sesuai prosedur standar operasional (SOP) DBM eks PNPM-MPd / BUMDesma LKD.

### 5.1 Siklus Hidup Perguliran Pinjaman (Workflow)
```
[1. Proposal (Draft)] ──► [2. Verifikasi Lapangan] ──► [3. Rapat Pendanaan (MAD)]
                                                               │
[6. Selesai (Lunas)] ◄── [5. Angsuran Aktif] ◄── [4. Pencairan Dana (SPK)]
```

Setiap tahapan dilengkapi validasi hak akses dan pencatatan audit (*audit trail*). Bila terjadi kekeliruan entri, sistem menyediakan fungsi **Revert Status** (mundur 1 tahap ke belakang).

---

### 5.2 Tahap 1: Registrasi Proposal Pinjaman
**Menu:** `siupk -> Register Proposal` (`/lending/loans/create`)

Langkah-langkah pendaftaran proposal:
1. Pilih **Kelompok** peminjam (data pengurus dan desa akan terisi otomatis).
2. Pilih **Produk Pinjaman** (SPP / UEP / Khusus).
3. Masukkan **Plafon Pengajuan Proposal** dan **Jangka Waktu (Tenor)** dalam bulan.
4. Tentukan **Sistem Perhitungan Bunga**:
   - *Flat (Tetap)*: Angsuran pokok dan jasa bernilai sama setiap bulan.
   - *Menurun (Efektif)*: Bunga dihitung dari sisa saldo pokok pinjaman.
   - *Anuitas*: Total angsuran tetap dengan komposisi pokok dan bunga yang bergeser tiap bulan.
5. Tentukan **Tingkat Suku Bunga (% per tahun atau per bulan)** dan **Siklus Pembayaran** (Bulanan / Musiman / Tempo).
6. **Daftar Pemanfaat & Alokasi**: Masukkan anggota kelompok yang menerima dana beserta nominal alokasi per individu. Sistem memastikan jumlah alokasi pemanfaat sama dengan total plafon proposal.
7. Klik tombol **Simpan Proposal**. Status pinjaman menjadi `Draft`.

---

### 5.3 Tahap 2: Verifikasi Lapangan
**Menu:** `siupk -> Tahapan Perguliran -> Tab Verifikasi`

Tim Verifikasi melakukan pengecekan faktual di lapangan:
1. Buka detail pinjaman, klik tombol **Verifikasi Pinjaman**.
2. Isi catatan verifikasi kelayakan usaha, kondisi jaminan sosial kelompok, dan rekomendasi nominal alokasi yang disetujui.
3. Cetak dokumen pendukung: **Surat Kelayakan Piutang**, **Form Verifikasi Kelompok**, dan **BA Musyawarah Desa**.
4. Simpan hasil verifikasi $ightarrow$ Status beralih menjadi `Verified`.

---

### 5.4 Tahap 3: Persetujuan & Alokasi Pendanaan (MAD)
**Menu:** `siupk -> Tahapan Perguliran -> Tab Waiting`

Forum Musyawarah Antar Desa (MAD) atau Tim Pendanaan menetapkan keputusan pendanaan:
1. Klik tombol **Persetujuan (Approve)** pada pinjaman yang telah diverifikasi.
2. Masukkan plafon final yang disetujui, tanggal penetapan, dan susunan Tim Pendanaan.
3. Status beralih menjadi `Approved` (Siap Dicairkan).

---

### 5.5 Tahap 4: Pencairan Pinjaman (Disbursement)
**Menu:** `siupk -> Detail Pinjaman -> Tombol Cairkan Dana`

1. Klik tombol **Cairkan Pinjaman**.
2. Masukkan **Tanggal Pencairan Riil** dan pilih **Rekening Kas/Bank Penyalur** (misal: *Kas Operasional* atau *Bank BPD*).
3. **Automasi Akuntansi**: Sistem secara otomatis memposting jurnal pencairan:
   - *(Debit)* Piutang Pinjaman Kelompok (SPP/UEP)
   - *(Kredit)* Kas atau Bank Penyalur
4. Cetak dokumen akad: **Surat Perjanjian Kredit (SPK)**, **Kuitansi Pencairan**, **Berita Acara Pencairan**, dan **Kartu Angsuran**.
5. Status pinjaman beralih menjadi `Active` (Aktif Berjalan).

---

### 5.6 Tahap 5: Pembayaran Angsuran
Penerimaan angsuran dicatat melalui menu **Jurnal Angsuran** (lihat [Bab 6.3](#63-jurnal-angsuran-pinjaman)). Sistem secara otomatis memperbarui sisa saldo pokok, akumulasi jasa yang dibayar, dan kartu angsuran pinjaman.

---

### 5.7 Fitur Khusus Penanganan Pinjaman
- **Reschedule (Restrukturisasi Tenor)**: Untuk kelompok yang mengalami musibah/gagal panen. Mengatur ulang jadwal angsuran baru dengan persetujuan pengelola.
- **Write-Off (Penghapusan Piutang Macet)**: Prosedur hapus buku piutang macet yang telah disetujui dalam forum MAD. Sistem otomatis membuat jurnal penghapusan terhadap Cadangan Kerugian Piutang (CKPN).
- **Hapus / Edit Pemanfaat**: Penyesuaian daftar pemanfaat sebelum pencairan dilakukan.

---

### 5.8 Cetak 36 Dokumen Resmi Pinjaman
Aplikasi menyediakan **36 format dokumen resmi siap cetak (PDF)** dari halaman detail pinjaman:
- Dokumen Proposal: *Cover Proposal, Surat Pengajuan Kredit, Profil Kelompok, Susunan Pengurus, Daftar Pemanfaat & Alokasi, Pernyataan Tanggung Renteng, Checklist Proposal, Daftar Anggota, Cetak KTP, Catatan Bimbingan.*
- Dokumen Verifikasi: *Surat Rekomendasi Kredit, BA Musyawarah Desa, Undangan Verifikasi, Surat Kelayakan Piutang, Form Verifikasi Kelompok, Form Verifikasi Anggota, Daftar Hadir Verifikasi.*
- Dokumen Pencairan & Perjanjian: *Surat Perjanjian Kredit (SPK), Berita Acara Pencairan, Kuitansi Pencairan, Cover Pencairan, Rencana Angsuran, Kartu Angsuran Anggota, Tanda Terima Dana, Surat Pemberitahuan ke Desa, BA Rapat Pendanaan, Daftar Asuransi, Kuitansi per Anggota, Surat Tagihan, Surat Pernyataan Ahli Waris, Surat Kuasa SPK, Surat Tanggung Renteng Kematian, Rekening Koran Pinjaman, Surat Pengakuan Utang, Daftar Hadir Pencairan.*

*(Lihat [Lampiran B](#lampiran-b--katalog-36-dokumen-cetak-pinjaman-pdf) untuk daftar lengkap dan orientasi cetak).*

---

## 6. Akuntansi & Jurnal Keuangan

siupk Next menerapkan standar akuntansi berpasangan (*double-entry*) murni. Setiap transaksi tercatat secara seimbang (*Debit = Kredit*) dan dilengkapi jejak audit (*audit trail*).

### 6.1 Bagan Akun (Chart of Accounts / CoA)
**Menu:** `Keuangan -> Bagan Akun` (`/accounting/chart-of-accounts`)

Struktur akun telah disesuaikan dengan SAK EP / BUMDesma:
- **1. Aset**: Kas, Bank BPD, Bank BRI, Piutang Pinjaman SPP, Piutang Pinjaman UEP, Cadangan Kerugian Piutang, Perlengkapan, Aset Tetap, Akumulasi Penyusutan.
- **2. Kewajiban**: Simpanan Sukarela Anggota, Titipan Angsuran, Utang Pajak, Utang Pihak Ketiga.
- **3. Ekuitas**: Modal Awal Pendirian, Dana Bergulir BUMDesma, Cadangan Umum, Cadangan Tujuan, Laba Ditahan, Laba Rugi Tahun Berjalan.
- **4. Pendapatan**: Pendapatan Jasa Pinjaman SPP, Pendapatan Jasa UEP, Pendapatan Administrasi, Pendapatan Bunga Bank, Pendapatan Non-Operasional.
- **5. Beban / Biaya**: Beban Operasional, Beban Gaji Pengelola, Beban ATK, Beban Rapat MAD, Beban Penyusutan Aset, Beban Cadangan Piutang, Beban Pajak.

### 6.2 Jurnal Umum
**Menu:** `Transaksi -> Jurnal Umum` (`/accounting/journal-entries/create`)

1. **Pilih Preset Transaksi (Opsi Cepat)**: Tersedia template otomatis untuk transaksi rutin (misal: *Biaya Operasional Kantor, Pembelian ATK, Penerimaan Bunga Bank, Pembelian Inventaris, Setoran Modal*).
2. **Entri Transaksi Bebas (Manual Mode)**:
   - Tentukan **Tanggal Transaksi** dan **Keterangan / Uraian**.
   - Masukkan baris akun: Pilih akun Debit dan akun Kredit.
   - Masukkan nominal. Sistem memvalidasi bahwa total Debit harus sama persis dengan total Kredit sebelum tombol simpan dapat ditekan.
   - Fitur Pembelian Aset: Jika memilih akun aset tetap, sistem otomatis membuatkan master data inventaris yang bersangkutan.

### 6.3 Jurnal Angsuran Pinjaman
**Menu:** `Transaksi -> Jurnal Angsuran` (`/accounting/journal-entries/installment`)

1. Pilih **Pinjaman Kelompok** yang melakukan pembayaran.
2. Sistem otomatis memuat nomor angsuran ke berapa, sisa tunggakan pokok, dan tunggakan jasa.
3. Masukkan nominal **Pembayaran Pokok**, **Pembayaran Jasa**, dan **Denda Keterlambatan** (bila ada).
4. Pilih akun kas penerima (*Kas Kasir* atau *Rekening Bank*).
5. Klik **Posting Angsuran**:
   - Sistem memposting jurnal penerimaan kas vs piutang & pendapatan jasa.
   - Kartu angsuran kelompok dan pemanfaat terupdate seketika.
   - Tersedia tombol cetak **Kuitansi Pembayaran Angsuran (PDF)** dan opsi **Kirim Kuitansi WhatsApp** langsung ke nomor HP pengurus kelompok.

### 6.4 Koreksi Jurnal (Immutable Reverse & Recreate)
**Menu:** `Transaksi -> Daftar Jurnal` (`/accounting/journals`)

Demi kepatuhan audit akuntansi, jurnal yang sudah diposting tidak dapat dihapus sembarangan.
- **Koreksi Jurnal**: Buka jurnal, klik tombol **Koreksi**. Sistem secara otomatis membuat **Jurnal Pembalik (Reversal Entry)** untuk membatalkan jurnal lama, lalu membuka form untuk menerbitkan jurnal baru yang telah diperbaiki.
- Semua riwayat koreksi tercatat lengkap beserta identitas pengguna dan waktu perubahan.

### 6.5 Bukti Kas Masuk & Kas Keluar (BKM / BKK / BM)
Setiap jurnal transaksi dapat dicetak sebagai bukti fisik kas:
- **BKM (Bukti Kas Masuk)**: Untuk penerimaan kas/bank.
- **BKK (Bukti Kas Keluar)**: Untuk pengeluaran biaya/pembelian.
- **BM (Bukti Memorial)**: Untuk transaksi non-kas/penyesuaian.

Format cetak dirancang berukuran standar ringkas (14 cm x 9 cm) lengkap dengan kolom tanda tangan kasir, pembukuan, dan penerima dana.

---

## 7. Inventaris & Aset Tetap

**Menu:** `Transaksi -> Daftar Inventaris` (`/accounting/assets`)

### 7.1 Pencatatan Aset
Mencatat seluruh kekayaan aset tetap lembaga (Gedung kantor, Komputer, Laptop, Sepeda Motor operasional, Meja/Kursi kantor, Brankas):
- Nama aset, kode barang, tanggal perolehan, harga perolehan.
- Nilai residu/sisa dan umur ekonomis (dalam tahun/bulan).
- Lokasi dan penanggung jawab fisik aset.

### 7.2 Metode Penyusutan
Sistem mendukung 2 metode standar akuntansi:
1. **Garis Lurus (*Straight-Line*)**: Beban depresiasi bernilai sama setiap bulan sepanjang masa manfaat.
2. **Saldo Menurun (*Declining Balance*)**: Beban depresiasi lebih besar di tahun-tahun awal.

### 7.3 Batch Penyusutan Bulanan Otomatis
Setiap akhir bulan, pengelola dapat menjalankan eksekusi **Penyusutan Batch**. Sistem secara otomatis menghitung nilai depresiasi seluruh aset aktif dan menerbitkan Jurnal Penyusutan:
- *(Debit)* Beban Penyusutan Aset Tetap
- *(Kredit)* Akumulasi Penyusutan Aset Tetap

---

## 8. Perencanaan Anggaran (E-Budgeting)

**Menu:** `Periodik -> E-Budgeting` (`/budgeting`)

### 8.1 Penyusunan Rencana Kerja & Anggaran (RKA)
- Modul anggaran 12 bulan untuk merencanakan seluruh target pendapatan jasa dan plafon batas pengeluaran operasional per akun buku besar.
- **Fitur Salin Bulan Sebelumnya (*Copy Previous*)**: Menghemat waktu penyusunan anggaran dengan menyalin pola anggaran bulan lalu.

### 8.2 Siklus Persetujuan Anggaran
```
Draft Anggaran ──► Disetujui (Approved / Terkunci) ──► Buka Kunci (Reopen)
```
- Anggaran yang telah disetujui forum MAD dikunci (*Locked*) agar menjadi pedoman baku.
- Jika terdapat revisi APB/MAD Perubahan, pengguna dengan wewenang `budgeting.manage` dapat membuka kunci (*Reopen*) untuk melakukan penyesuaian.

### 8.3 Monitoring Realisasi vs Anggaran
Halaman menampilkan perbandingan interaktif antara nilai anggaran vs realisasi riil pembukuan, lengkap dengan deviasi nominal dan persentase capaian (% Varian).

---

## 9. Pelaporan Keuangan (Financial Reports)

**Menu:** `Pelaporan -> Laporan Keuangan` (`/accounting/reports`)

Seluruh laporan keuangan dapat difilter berdasarkan bulan/tahun, ditampilkan di layar, diekspor ke **Microsoft Excel (.xlsx)**, dan dicetak ke dokumen **PDF resmi ber-kop surat**.

| No | Laporan Keuangan | Deskripsi & Kegunaan |
|---|---|---|
| 1 | **Neraca (*Balance Sheet*)** | Posisi Aset (Lancar & Tetap), Kewajiban/Utang, dan Ekuitas BUMDesma per tanggal tertentu. Disertai komparasi periode lalu. |
| 2 | **Laba Rugi (*Income Statement*)** | Pendapatan Operasional, Beban Operasional, Pendapatan/Beban Non-Operasional, dan Hasil Usaha Bersih (Surplus/Defisit Berjalan). |
| 3 | **Arus Kas (*Cash Flow*)** | Arus kas masuk dan keluar yang dikelompokkan ke dalam 3 aktivitas utama: Operasi, Investasi, dan Pendanaan. |
| 4 | **Perubahan Ekuitas (*Equity Change*)** | Mutasi permodalan, penambahan surplus berjalan, alokasi cadangan, dan saldo akhir ekuitas. |
| 5 | **Buku Besar (*General Ledger*)** | Rincian seluruh mutasi debit, kredit, dan saldo berjalan per akun buku besar. |
| 6 | **Neraca Saldo (*Trial Balance*)** | Ringkasan saldo awal, total mutasi debit/kredit, dan saldo akhir seluruh akun untuk verifikasi keseimbangan. |
| 7 | **Catatan atas Lap. Keuangan (CALK)** | Dokumen narasi penjelasan kebijakan akuntansi, profil lembaga, dan rincian pos laporan keuangan. Dilengkapi fitur **Rich Text Editor** untuk menyimpan narasi pengelola. |
| 8 | **Jurnal Transaksi** | Rekapitulasi seluruh jurnal yang diposting dalam periode terpilih. |
| 9 | **Dokumen LPJ & Cover Tahunan (*Annual Pack*)** | Bundel lengkap Laporan Pertanggungjawaban Tahunan MAD (Cover resmi, Daftar Isi, Surat Pengantar, Berita Acara, dan seluruh lampiran laporan keuangan) dalam satu berkas PDF siap cetak. |
| 10 | **Penilaian Tingkat Kesehatan Keuangan** | Analisis rasio Likuiditas, Solvabilitas, Rentabilitas, dan Kualitas Portofolio sesuai parameter standar Kemendesa PDTT dengan predikat: *Sehat, Cukup Sehat, Kurang Sehat, atau Tidak Sehat*. |

---

## 10. Laporan Pinjaman & Analisis Piutang

**Menu:** `Pelaporan -> Laporan Pinjaman` (`/lending/reports/*`)

### 10.1 Portofolio Pinjaman
Analisis sebaran seluruh pinjaman aktif berdasarkan umur piutang (*aging report*), wilayah desa, dan jenis produk pinjaman.

### 10.2 Rencana vs Realisasi Pinjaman
Tabel perbandingan target penerimaan angsuran pokok dan jasa sesuai jadwal akad terhadap penerimaan riil di kasir.

### 10.3 LPP Rekap Desa & Rincian Kelompok
- **LPP Rekap Desa**: Laporan Perkembangan Pinjaman ringkas per desa (Saldo awal, pencairan baru, pengembalian pokok/jasa, tunggakan, dan saldo akhir).
- **LPP Rincian Kelompok**: Rincian LPP tingkat kelompok dalam satu desa.

### 10.4 Klasifikasi Kolektibilitas (Kolek 1 s/d 5)
Pengelompokan kualitas pinjaman sesuai standar perbankan/LKD:
- **Kolek 1 (Lancar)**: Tunggakan 0 hari (tepat waktu).
- **Kolek 2 (Dalam Perhatian Khusus / DPK)**: Menunggak 1 - 30 hari.
- **Kolek 3 (Kurang Lancar)**: Menunggak 31 - 90 hari.
- **Kolek 4 (Diragukan)**: Menunggak 91 - 180 hari.
- **Kolek 5 (Macet)**: Menunggak > 180 hari.

### 10.5 Cadangan Kerugian Penurunan Nilai (CKPN)
Perhitungan otomatis cadangan penghapusan piutang berdasarkan bobot risiko per tingkat kolektibilitas untuk disajikan pada pos neraca.

---

## 11. Prosedur Periodik (Tutup Buku & Taksiran Pajak)

### 11.1 Tutup Buku Bulanan
**Menu:** `Periodik -> Tutup Buku` (`/accounting/period-close`)
1. Pilih **Tahun** dan **Bulan** yang akan ditutup.
2. Klik tombol **Tutup Bulan**.
3. Sistem mengunci seluruh transaksi pada bulan tersebut agar tidak dapat diubah oleh staf kasir tanpa izin khusus (*Reopen*).

### 11.2 Tutup Buku Tahunan & Alokasi Surplus (SHU MAD)
1. Setelah bulan ke-12 ditutup, klik tombol **Tutup Tahun**.
2. Sistem otomatis menutup akun pendapatan dan beban ke akun *Ikhtisar Laba Rugi*.
3. **Form Alokasi Surplus Hasil Usaha (SHU)**: Masukkan persentase pembagian surplus sesuai keputusan MAD:
   - Dana Cadangan Umum (Modal BUMDesma)
   - Pembagian Bagian Hasil Desa (PADes)
   - Dana Pendidikan & Sosial Masyarakat
   - Bonus / Jasa Pengelola & Pengawas
4. Sistem otomatis memposting Jurnal Pembagian Surplus dan membentuk Saldo Awal Neraca untuk tahun buku berikutnya.

### 11.3 Taksiran Pajak
**Menu:** `Periodik -> Taksiran Pajak` (`/accounting/tax-estimate`)
Perhitungan estimasi Pajak Penghasilan (PPh Badan / UMKM) berdasarkan laba kena pajak berjalan.

---

## 12. Tagihan & Langganan SaaS (Billing)

**Menu:** `Tagihan -> Daftar Tagihan` (`/billing/invoices`)

Mengelola langganan lisensi operasional siupk Next untuk lembaga BUMDesma Anda.

### 12.1 Cara Pembayaran Tagihan Otomatis
1. Buka tagihan dengan status *Belum Dibayar*.
2. Klik tombol **Bayar Sekarang**.
3. Pilih metode pembayaran yang diinginkan:
   - **QRIS Dinamis**: Tampilkan kode QR di layar, lalu pindai (*scan*) menggunakan aplikasi BCA Mobile, Livin Mandiri, BRImo, BNI Mobile, GoPay, OVO, Dana, atau ShopeePay.
   - **Virtual Account (VA)**: Dapatkan nomor VA otomatis dari bank pilihan (BRI, Mandiri, BNI, BCA, BSI, Permata, CIMB Niaga). Lakukan transfer via ATM/Mobile Banking.
4. Setelah transaksi berhasil, status tagihan otomatis berubah menjadi **Lunas (Paid)** dan masa aktif langganan instansi Anda diperpanjang seketika tanpa perlu konfirmasi manual.

### 12.2 Konfirmasi Manual
Jika melakukan pembayaran via transfer rekening penampung manual, unggah foto/berkas bukti transfer pada form konfirmasi untuk divalidasi oleh administrator platform.

---

## 13. Pusat Notifikasi & WhatsApp Gateway

**Menu:** `Periodik -> Notifikasi Tagihan` (`/notifications/billing`)

Kirim pesan pengingat tagihan kepada pengurus kelompok secara massal melalui WhatsApp.

### 13.1 Pengiriman Notifikasi Tagihan
1. Pilih **Tanggal Jatuh Tempo Target**.
2. Sistem menampilkan daftar kelompok yang memiliki jadwal pembayaran pada tanggal tersebut.
3. Pilih kelompok yang akan dikirimi pesan.
4. Klik tombol **Kirim Notifikasi WhatsApp**. Pesan terkirim otomatis berisi rincian sisa tagihan pokok, bunga, jatuh tempo, dan nomor rekening penampung BUMDesma.

---

## 14. Pengaturan Lembaga (Settings)

**Menu:** `Pengaturan` (`/settings`) — *Hak Akses: `settings.manage`*

Terdiri dari **5 Tab Konfigurasi**:

1. **Tab Identitas Lembaga**: Nama resmi BUMDesma/LKD, Nomor SK Pendirian, Alamat Kantor, Desa/Kecamatan/Kabupaten, Email, Nomor Telepon, dan Nama Direktur/Ketua Pengelola.
2. **Tab Sistem Pinjaman**: Konfigurasi produk pinjaman, persentase default suku bunga, metode perhitungan bunga default, toleransi hari keterlambatan, dan tarif denda.
3. **Tab Logo Lembaga**: Unggah file logo instansi resolusi tinggi (.PNG/.JPG). Logo ini otomatis disematkan pada seluruh dokumen cetak SPK, Kuitansi, dan Laporan Keuangan PDF.
4. **Tab WhatsApp Gateway**: Menghubungkan nomor WhatsApp BUMDesma ke server gateway:
   - Klik *Buat Sesi WhatsApp*, scan QR code yang muncul menggunakan WhatsApp di HP pengelola.
   - Uji coba koneksi dengan tombol *Kirim Pesan Tes*.
5. **Tab Tanda Tangan Dokumen**: Mengatur nama lengkap, NIK, dan jabatan resmi untuk format tanda tangan di lembar dokumen cetak (Kepala Desa, Direktur BUMDesma, Sekretaris, Bendahara, Tim Verifikasi, Tim Pendanaan).

---

## 15. Manajemen Pengguna, Peran & Hak Akses (RBAC)

**Menu:** `Akses Pengguna` (`/access/users` & `/access/roles`)

### 15.1 Manajemen Akun Pengguna
- **Tambah Pengguna Baru**: Daftarkan staf dengan mengisi Nama, Username, Email, Password, dan menetapkan **Peran (Role)**.
- **Akun Operator Desa**: Centang opsi *Operator Desa* dan pilih desa penugasan. Pengguna tersebut hanya dapat mengelola data warga dan kelompok di desanya sendiri.
- **Reset Password**: Administrator dapat mereset kata sandi staf yang lupa password.

### 15.2 Manajemen Peran (Roles) & 37 Hak Akses (Permissions)
- Administrator dapat membuat **Role Kustom** baru dan mencentang hak akses granular sesuai tupoksi kerja staf (misal: *Staf Verifikasi, Kasir Lapangan, Kolektor, Akuntan*).

---

## 16. Profil Pengguna & Personalisasi

**Menu:** `Profil (Pojok Kanan Atas)` (`/profile`)

1. **Informasi Pribadi**: Perbarui nama lengkap, NIK, alamat email, dan nomor kontak.
2. **Ubah Password**: Ganti kata sandi secara berkala demi keamanan akun.
3. **Foto Profil**: Unggah foto profil formal Anda.

---

## 17. Wizard Onboarding & Migrasi Data Mandiri

**Menu:** `Onboarding & Migrasi` (`/onboarding/import`)

Disediakan khusus untuk instansi BUMDesma baru yang ingin bermigrasi dari sistem manual/Excel ke siupk Next:
- **Langkah 1 (Master Anggota & Kelompok)**: Unduh template Excel, isi data warga dan kelompok, lalu unggah ke sistem.
- **Langkah 2 (Saldo Pinjaman Berjalan)**: Impor data pinjaman yang sedang aktif berjalan beserta sisa saldo pokok dan jadwal angsuran lama.
- **Langkah 3 (Saldo Awal Neraca Keuangan)**: Masukkan saldo awal kas, bank, piutang, aset inventaris, dan modal awal pembentukan BUMDesma. Sistem memverifikasi keseimbangan sebelum saldo awal dibukukan.

---

## 18. Portal Supervisi Kabupaten

**Akses:** `/regency/*` (Khusus Akun Dinas PMD Kabupaten / Tenaga Ahli)

- **Dashboard Supervisi Kabupaten**: Peta sebaran BUMDesma di seluruh kecamatan dalam 1 kabupaten, total perputaran dana, akumulasi aset gabungan, dan rasio NPL rata-rata kabupaten.
- **Laporan Konsolidasi Kabupaten**: Neraca Konsolidasi Kabupaten, Laba Rugi Konsolidasi, Arus Kas Konsolidasi, Buku Besar Konsolidasi, dan CALK Konsolidasi.

---

## 19. Portal Supervisi Provinsi

**Akses:** `/province/*` (Khusus Akun Dinas PMD Provinsi / Koordinator Wilayah)

- **Dashboard Supervisi Provinsi**: Monitoring makro kinerja dana bergulir seluruh kabupaten dalam satu provinsi.
- **Paket 5 Laporan Keuangan Konsolidasi Provinsi**: Laporan gabungan tingkat provinsi siap unduh (PDF/Excel) untuk keperluan evaluasi gubernur dan pelaporan kementerian.

---

## 20. Panel Superadmin Platform SaaS

**Akses:** `/admin/*` (Khusus Pengelola Platform Teknis)

- **Manajemen Shard Tenant**: Pembuatan tenant baru, perbaikan struktur database (*Tenant Repair*), dan manajemen domain kustom.
- **Data Purifier**: Alat sanitasi otomatis untuk mendeteksi data piutang ganda atau selisih pembukuan pada tenant.
- **Manajemen Gateway Pembayaran**: Konfigurasi Tripay, Duitku, dan Xendit API.
- **Alat Cutover Migrasi Database Legacy**: Migrasi instan database siupk versi lama (PHP murni/MySQL) ke siupk Next.

---

## 21. Asisten Kecerdasan Buatan (AI Assistant - Ariel)

**Widget Chat:** Terletak di pojok kanan bawah layar.

**Ariel** adalah asisten AI berbasis regulasi dan konteks data lembaga:
- **Konsultasi SOP & Regulasi**: Tanyakan seputar aturan Permendesa 15/2021, prosedur perguliran pinjaman, standar pembukuan SAK EP, atau pembagian surplus SHU.
- **Analisis Data Cepat**: Ajukan pertanyaan praktis seperti analisis tunggakan, draf pengantar LPJ tahunan, atau jurnal transaksi.

---

## 22. Pintasan Keyboard, Tips & Panduan Troubleshooting

### 22.1 Tabel Pintasan Keyboard (Shortcuts)
| Tombol | Fungsi |
|---|---|
| `Ctrl + K` / `Cmd + K` | Membuka Command Palette (Pencarian Global) |
| `Escape` | Menutup Jendela Modal / Dialog Aktif |

### 22.2 Panduan Penanganan Masalah (Troubleshooting)
- **Error 403 (Akses Ditolak)**: Akun Anda tidak memiliki hak akses untuk fitur tersebut. Hubungi administrator instansi Anda untuk penyesuaian Role.
- **Error 419 (Sesi Berakhir / Page Expired)**: Sesi login telah habis karena tidak ada aktivitas. Muat ulang halaman (*Refresh*) dan masuk kembali.
- **Error 500 / 503 (Layanan Terkendala)**: Periksa koneksi internet Anda atau hubungi admin teknis lembaga.

---

## Lampiran A — Matriks Hak Akses (RBAC)

### Role Standar Sistem
1. **`admin`**: Akses penuh ke seluruh fitur dan pengaturan tenant.
2. **`kasir`**: Akses modul kasir, pencatatan jurnal, pencatatan angsuran, cetak kuitansi, notifikasi WhatsApp, dan laporan keuangan.
3. **`verifikator`**: Akses modul verifikasi lapangan proposal pinjaman dan pemeriksaan berkas.
4. **`viewer`**: Akses melihat data (*Read-Only*) untuk Badan Pengawas / BPD.
5. **`village_operator`**: Akses terbatas untuk input data warga dan proposal di wilayah desanya.
6. **`regency_supervisor`**: Akses portal pengawasan konsolidasi tingkat kabupaten.
7. **`province_supervisor`**: Akses portal pengawasan konsolidasi tingkat provinsi.

---

## Lampiran B — Katalog 36 Dokumen Cetak Pinjaman PDF

| No | Nama Dokumen | Tahapan | Orientasi Kertas |
|---|---|---|---|
| 1 | Cover Proposal Pinjaman | Proposal | Portrait |
| 2 | Surat Pengajuan Kredit | Proposal | Portrait |
| 3 | Profil Kelompok Peminjam | Proposal | Portrait |
| 4 | Susunan Pengurus Kelompok | Proposal | Portrait |
| 5 | Daftar Pemanfaat & Alokasi Dana | Proposal | Landscape |
| 6 | Surat Pernyataan Tanggung Renteng | Proposal | Portrait |
| 7 | Checklist Kelengkapan Proposal | Proposal | Portrait |
| 8 | Daftar Anggota Kelompok | Proposal | Portrait |
| 9 | Cetak KTP Pemanfaat | Proposal | Portrait |
| 10 | Catatan Bimbingan Kelompok | Proposal | Portrait |
| 11 | Surat Rekomendasi Kredit | Verifikasi | Portrait |
| 12 | Berita Acara Musyawarah Desa | Verifikasi | Portrait |
| 13 | Surat Undangan Verifikasi | Verifikasi | Portrait |
| 14 | Surat Kelayakan Piutang | Verifikasi | Portrait |
| 15 | Form Verifikasi Kelompok | Verifikasi | Portrait |
| 16 | Form Verifikasi Anggota | Verifikasi | Portrait |
| 17 | Daftar Hadir Verifikasi Lapangan | Verifikasi | Portrait |
| 18 | Surat Perjanjian Kredit (SPK) | Pencairan | Portrait |
| 19 | Berita Acara Pencairan Dana | Pencairan | Portrait |
| 20 | Kuitansi Pencairan Pinjaman | Pencairan | Portrait |
| 21 | Cover Berkas Pencairan | Pencairan | Portrait |
| 22 | Rencana Jadwal Angsuran | Pencairan | Portrait |
| 23 | Kartu Angsuran per Anggota | Pencairan | Portrait |
| 24 | Tanda Terima Dana Pinjaman | Pencairan | Portrait |
| 25 | Surat Pemberitahuan ke Pemerintah Desa | Pencairan | Portrait |
| 26 | BA Rapat Penetapan Pendanaan (MAD) | Pencairan | Landscape |
| 27 | Daftar Peserta Asuransi Pinjaman | Pencairan | Landscape |
| 28 | Kuitansi Pencairan per Anggota | Pencairan | Portrait |
| 29 | Surat Tagihan Pembayaran | Pencairan | Portrait |
| 30 | Surat Pernyataan Ahli Waris | Pencairan | Portrait |
| 31 | Surat Kuasa Penandatanganan SPK | Pencairan | Portrait |
| 32 | Surat Pernyataan Tanggung Renteng Kematian | Pencairan | Portrait |
| 33 | Daftar Penerima Insentif Pengembalian (IPTW) | Pencairan | Portrait |
| 34 | Rekening Koran Riwayat Pinjaman | Pencairan | Portrait |
| 35 | Surat Pengakuan Utang Peminjam | Pencairan | Portrait |
| 36 | Daftar Hadir Pencairan Dana | Pencairan | Portrait |

---
*Dokumentasi Resmi siupk Next — Hak Cipta Terlindungi.*
