# Dokumen Analisis Perbandingan Komprehensif: siupk Legacy vs siupk Next

**Dokumen ID**: docs/PERBANDINGAN_siupk_LEGACY_VS_NEXT.md  
**Tanggal**: 10 Agustus 2026  
**Penulis**: Team Engineering & System Architecture  
**Status**: Dokumentasi Resmi Transformasi Arsitektur  

---

## 1. Ringkasan Eksekutif

Aplikasi **siupk (Sistem Informasi Dana Bergulir Masyarakat)** telah menjadi tulang punggung operasional pengolahan dana bergulir (UPK / BUMDesma LKD) selama bertahun-tahun. Namun, seiring dengan pertumbuhan entitas, kebutuhan pengawasan tingkat Kabupaten, dan tuntutan standar keandalan perangkat lunak modern, arsitektur **siupk Legacy** (berada pada repositori F:\Workspace\laragon\www\siupk) menghadapi batas kemampuan teknis (*technical ceiling*) yang menghambat skalabilitas, keamanan, dan pemeliharaan kode.

**siupk Next** (repositori F:\Workspace\laragon\www\new_siupk) dibangun kembali secara fundamental (*ground-up architectural rewrite*) untuk menyelesaikan seluruh hambatan tersebut tanpa menghilangkan data historis maupun alur bisnis inti.

---

## 2. Alasan Utama Perlunya Upgrade (The "Why")

Mengapa pembaruan aplikasi ini **wajib** dilakukan dan tidak cukup hanya dengan menambal kode legacy?

### 2.1 Hambatan Utama pada siupk Legacy:
1. **Schema Drift & Tabel Dinamis Per-Tenant (	ransaksi_1, 	ransaksi_2, nggota_1, ...)**
   Pada sistem legacy, setiap tenant baru memicu pembuatan puluhan tabel baru dengan suffix angka ID tenant. Ketika terdapat ratusan tenant, database memiliki **puluhan ribu tabel**. Mengubah struktur kolom (*migration*) mengharuskan eksekusi perintah SQL ke ribuan tabel satu per satu, yang sangat rawan memicu ketidakseragaman skema (*schema drift*) dan kegagalan migrasi pertengahan.
2. **Ketiadaan Foreign Key & Integritas Data Rentan**
   Sistem legacy tidak menggunakan *Foreign Key Constraints* pada level database. Relasi antar data hanya dijaga pada level aplikasi atau trigger. Hal ini sering mengakibatkan *orphan records* (data anggota/pinjaman terhapus tetapi jurnal transaksi tetap ada, atau sebaliknya).
3. **Penghitungan Saldo Berbasis Trigger & Teks (VARCHAR Uang)**
   Nilai angka finansial pada beberapa tabel legacy disimpan dalam format teks string (VARCHAR). Penghitungan saldo akun dipelihara melalui *MySQL Triggers* yang kompleks. Jika terjadi koreksi transaksi historis, trigger sering memicu penguncian tabel (*table lock*) dan inkonsistensi saldo.
4. **Performa Aggregasi & Keterbatasan Portal Kabupaten Legacy**
   Meskipun siupk Legacy telah memiliki modul level Kabupaten (`/kab` dengan guard `auth:kabupaten`), fitur ini terkendala oleh performa aggregasi yang lambat karena harus meloop puluhan tabel dinamis tenant per kecamatan secara runtime, serta keterbatasan visualisasi interaktif & laporan konsolidasi mendalam (seperti CALK konsolidasi kabupaten).
5. **Proses Bisnis Manual (Tidak Ada Pembayaran Online & Billing Automation)**
   Tagihan biaya langganan aplikasi atau pencatatan pembayaran masih dilakukan secara manual tanpa integrasi Payment Gateway, tanpa penanganan otomatis untuk tenant yang menunggak (*overdue*).
6. **Keterbatasan Pengujian Automated & Risiko Human Error**
   Legacy tidak memiliki *automated test suite* (seperti unit test/feature test/E2E test). Setiap perubahan kode harus diuji manual satu per satu pada ratusan halaman Blade + jQuery.

---

## 3. Perbandingan Arsitektur & Teknologi Core

| Komponen | siupk Legacy (/siupk) | siupk Next (/new_siupk) | Keuntungan & Implikasi siupk Next |
|---|---|---|---|
| **Framework Backend** | PHP 8.1 / Laravel 10.x | **PHP 8.4 / Laravel 13.x** | Menggunakan fitur PHP/Laravel terbaru (Attribute, Enums, Performance improvements, modern container injection). |
| **Arsitektur Frontend** | Monolithic Blade Views + jQuery + DataTables (Server-side rendering tradisional) | **Single Page Application (SPA) via Inertia.js 2.0 + Vue 3.5 + Tailwind CSS 4 + Vite 7** | Pengalaman pengguna instan tanpa reload halaman, komponen UI modular yang konsisten, proses build & HMR kilat via Vite 7. |
| **Model Tenancy** | Dynamic Table Name Suffix (	abel_{tenant_id}) pada 1 database | **Platform DB + Shared/Dedicated Shard Databases (	enant_id column-based isolation)** | Skalabilitas hingga ribuan tenant. Skema tabel seragam per shard. Mengeliminasi puluhan ribu tabel dinamis. |
| **Mesin Cache & State** | File Cache / Basic Store | **Redis 8 (predis) — Cache, Session & Queue Worker** | Performa I/O super cepat untuk Session login, caching laporan, dan pemrosesan antrean latar belakang (*background queues*). |
| **Kecerdasan Buatan (AI)** | WA Gateway Sederhana (External script) | **Embedded enpii/assistant Package + Vector Store (PostgreSQL pgvector) + Local Ollama LLM** | RAG (Retrieval-Augmented Generation) internal untuk query dokumen SOP, analisis jurnal via AI, & interaksi chat dengan widget reaktif. |
| **Pengetesan (Testing)** | Manual Testing | **PHPUnit 12 (Unit & Feature Tests) + Playwright (End-to-End E2E Tests)** | Menjamin keamanan kode, regresi dapat dideteksi secara otomatis sebelum rilis ke produksi. |
| **Infrastruktur Deployment** | Server Tradisional / Laragon Manual | **Dockerized Container Architecture (docker-compose.yml)** | Lingkungan dev dan prod 100% identik (PHP-FPM, Nginx, MySQL 8.4, Redis, PostgreSQL, Ollama, Queue Worker). |

---

## 4. Perbandingan Model Basis Data & Tenancy Internals

### 4.1 siupk Legacy
- **Topologi Database**: Membuka koneksi database tunggal, lalu mengeksekusi query dengan nama tabel dinamis seperti SELECT * FROM transaksi_12.
- **Primary Key**: idt / id bertipe integer sederhana.
- **Isolasi Data**: Mengandalkan penggabungan string nama tabel di PHP Controller ("transaksi_" . ). Sangat rawan error pemanggilan tabel.

### 4.2 siupk Next
- **Topologi Database**:
  - siupk_platform: Menyimpan data SaaS global, pengguna (users), tenant (	enants), shard database (database_shards), penempatan tenant (	enant_placements), dan tagihan (invoices).
  - siupk_shard_XX: Menggunakan skema yang sama untuk seluruh tenant. Setiap baris data operasional memiliki kolom 	enant_id.
- **Sistem Identitas Ganda (Legacy Compatibility)**:
  - 
ow_id: *BIGINT UNSIGNED Auto-Increment* sebagai Primary Key internal teknis database.
  - id: Mempertahankan ID lokal/historis legacy secara utuh untuk kebutuhan audit dan laporan (Constraint: UNIQUE (tenant_id, id)).
  - public_id: ULID 26 karakter untuk akses aman API/URL tanpa mengekspos ID internal.
- **Perpindahan Tenant & Scalability**: Tenant berukuran super besar dapat dipindahkan ke database dedicated hanya dengan mengubah record 	enant_placements tanpa mengubah 1 baris pun kode aplikasi.

---

## 5. Perbandingan Mesin Akuntansi & Transaksi Finansial

### 5.1 Legacy Accounting
- Menggunakan file utilitas prosedural (pp/Utils/Keuangan.php).
- Kolom uang disimpan dalam bentuk teks VARCHAR di beberapa tabel.
- Saldo akun dihitung dan diperbarui menggunakan trigger database pada tabel saldo_{tenant}.
- Reversal transaksi dilakukan dengan menghapus baris transaksi atau mengedit baris historis langsung.

### 5.2 Next Accounting Engine (App\Domain\Accounting)
- **Double-Entry Journal Balancing**: Setiap transaksi wajib membentuk pasangan berimbang pada tabel journal_entries dan journal_lines (Total Debit = Total Kredit).
- **Ketat terhadap Tipe Data**: Nilai uang disimpan secara konsisten menggunakan DECIMAL(19,2).
- **Immutability & Audit Trail**: Jurnal yang telah diposting tidak dapat diubah/dihapus (*immutable*). Reversal dilakukan melalui jurnal pembalik resmi (JournalReversalService) dengan tautan relasi jurnal asal.
- **Tutup Buku & Alokasi Laba Otomatis**: Fitur period-close untuk menutup/membuka periode fiskal, pembentukan jurnal alokasi laba otomatis (Alokasi Penambahan Modal, Dana Sosial, Bonus Pengurus, dll.), serta *Year-end Opening Balances*.

---

### 5.3 Implementasi Lengkap 17 Skenario Jurnal Umum SOP (JournalEntryOptionResolver)
Seluruh 17 skenario transaksi jurnal umum dari SOP *Panduan Transaksi SI UPK* telah sepenuhnya diintegrasikan ke dalam resolver jurnal umum (JournalEntryOptionResolver), terbagi menjadi 4 kelompok utama:
1. **Umum**: Aset Masuk (Penerimaan), Aset Keluar (Pengeluaran/Beban), Pemindahan Saldo (Mutasi Kas/Bank), Investasi Unit Usaha.
2. **Pembelian Aset & DP**: Pembelian Tanah, Gedung, Kendaraan, Peralatan/Inventaris, Aset Tak Berwujud (Lisensi/Sewa/Asuransi), Uang Muka / Konstruksi Dalam Pengerjaan, dan Pengakuan Aset Tetap dari DP.
3. **Kewajiban & Modal**: Penerimaan Utang Bank/Pihak ke-3, Pembayaran Utang Bank & Bunga, Penyertaan Modal Desa/Masyarakat, Pembayaran Utang Laba Bagian Desa/Masyarakat, Pembayaran Utang Pajak PPh, dan Pembayaran Utang Bonus Prestasi.
4. **Penyesuaian & Penyusutan**: Beban Penyusutan Gedung/Kendaraan/Inventaris, Amortisasi Aset Tak Berwujud, Penyisihan Cadangan Kerugian Piutang (CKP Pokok & Jasa SPP/UEP/Lembaga Lain), Pengakuan Utang Laba, Pengakuan Taksiran PPh, Pengakuan Utang Bonus Prestasi, dan Penghapusan Aset Tetap (ATI).

## 6. Fitur Baru: Portal Pengawasan & Konsolidasi Kabupaten (Regency Module)

Modul Supervisi Kabupaten pada siupk Next merupakan perombakan total dari portal /kab pada siupk Legacy, menghadirkan performa aggregasi instan dan fitur konsolidasi yang jauh lebih komprehensif.

### Capabilities Modul Kabupaten:
1. **Multi-Kecamatan Shard Aggregation (RegencyConsolidatedReportService)**:
   Sistem secara otomatis mengagregasi seluruh data keuangan dari berbagai kecamatan (tenant) yang berada di bawah 1 kabupaten secara real-time.
2. **Dashboard Kabupaten (/regency/dashboard)**:
   Menampilkan KPI total Kas & Bank kabupaten, sisa pokok pinjaman berjalan seluruh kecamatan, pendapatan operasional gabungan, serta tabel rekapitulasi kinerja per kecamatan.
3. **Laporan Keuangan Konsolidasi Lintas Kecamatan**:
   - **Neraca Konsolidasi** (/regency/reports/balance-sheet) — Menyajikan kolom rincian per kecamatan beserta kolom Total Gabungan.
   - **Laba Rugi Konsolidasi** (/regency/reports/income-statement) — Menyajikan perbandingan YTD, bulan berjalan, dan partisipasi per kecamatan.
   - **Arus Kas Konsolidasi** (/regency/reports/cash-flow) — Rekonsiliasi saldo kas awal, arus kas operasi/investasi/pendanaan, dan saldo akhir.
   - **Buku Besar Konsolidasi** (/regency/reports/general-ledger) — Penelusuran mutasi per akun dengan identifikasi nama kecamatan asal transaksi.
   - **CALK Konsolidasi** (/regency/reports/calk) — Catatan Atas Laporan Keuangan tingkat kabupaten.
4. **Export PDF Resmi**: Seluruh laporan kabupaten dapat dicetak ke format PDF berstandar cetak dengan orientasi Landscape/Portrait.

---

## 7. Fitur Baru: Automatisasi SaaS Billing & Tripay Payment Gateway

Pada siupk Legacy, manajemen langganan tenant tidak tersedia. Pada siupk Next, sistem dilengkapi modul **SaaS Billing & Payment Automation** lengkap:

1. **In-App Payment Channel Interface**:
   Tenant dapat memilih metode pembayaran langsung di dalam aplikasi:
   - **QRIS (Scan Langsung)**: Menampilkan QR Code resmi nasional yang dapat di-scan dari aplikasi Mobile Banking (BCA, Mandiri, BRI, BNI, dll.) maupun e-Wallet (GoPay, OVO, Dana, ShopeePay).
   - **Virtual Account Bank (VA)**: BCA VA, BRIVA, BNI VA, Mandiri VA, Permata VA, CIMB VA, BSI VA, dan Danamon VA beserta tombol otomatis salin kode bayar dan instruksi transfer.
2. **Otomatisasi Tagihan Perpanjangan (subscriptions:generate-invoices)**:
   Artisan command scheduler yang berjalan harian untuk menerbitkan tagihan perpanjangan 7 hari sebelum masa langganan berakhir.
3. **Penangguhan Otomatis Tenant Overdue (subscriptions:check-overdue)**:
   Tenant yang menunggak melewati masa tenggang (*grace period* 3 hari) secara otomatis diubah status langganannya menjadi suspended.
4. **Middleware Restriksi Akses (EnsureSubscriptionActive)**:
   Membatasi tenant yang ditangguhkan dari akses modul operasional (transaksi/pinjaman/master data) dan mengarahkannya langsung ke halaman pembayaran billing.
5. **Perpanjangan Langganan Real-Time**:
   Setelah webhook callback Tripay diterima (POST /api/billing/tripay/callback), masa aktif Subscription otomatis bertambah (1 bulan / 1 tahun sesuai paket).

---

## 8. Fitur Baru: Asisten AI Interaktif & Vector RAG (enpii/assistant)

siupk Next mengintegrasikan asisten cerdas internal **Ariel** yang bertindak sebagai *pair assistant* pengguna:

1. **Vector Store RAG (PostgreSQL pgvector)**:
   Membaca dan mencari dokumen SOP, aturan dana bergulir, dan panduan akuntansi menggunakan *Cosine Similarity* sub-milidetik pada indeks HNSW.
2. **Local LLM & Embedding Server (Ollama)**:
   Menggunakan model embedding 
omic-embed-text untuk mengonversi dokumen menjadi vector tanpa mengirim data sensitif keuangan ke layanan pihak ketiga.
3. **Komponen Chat Interaktif (Vue Components)**:
   Mendukung balasan AI berupa **Markdown Tables**, **Interactive Artifacts**, **Tombol Aksi**, dan **Polls (Survei interaktif gaya WhatsApp)**.
4. **Integrasi Domain Tools**:
   Asisten AI dapat mengeksekusi pencarian data anggota, kelompok, status pinjaman, hingga draft jurnal atas izin pengguna (permissions.tool_map).

---

## 9. Infrastruktur & Performa Sistem

| Item Infrastruktur | siupk Legacy | siupk Next |
|---|---|---|
| **Koneksi Database** | Single MySQL connection | Dynamic Shard Connection Manager (ShardConnectionManager) |
| **Cache Engine** | File-based Cache | **Redis 8 Cache (CACHE_STORE=redis)** |
| **Session Handling** | File-based Session | **Redis 8 Session Driver (SESSION_DRIVER=redis)** |
| **Queue & Background Jobs** | Synchronous Execution | **Dedicated Redis Queue Worker (QUEUE_CONNECTION=redis, container 
ew_siupk-queue-1)** |
| **Search Engine** | Query SQL LIKE parsial per tabel | **Omnibox Global Search (GlobalSearchService)** menembus Anggota, Kelompok, Pinjaman, Jurnal, & Aset |
| **UI Components** | HTML Select / Inputs bawaan browser | **Reusable Component Suite** (SmartSelect, ReportPeriodFilter, AppRadioGroup, AppDatePicker, AppCard, AppBadge, AppModal, AppToast) |

---

## 10. Matriks Perbandingan Fitur Samping-demi-Samping (Head-to-Head)

| Fitur / Kemampuan | siupk Legacy (/siupk) | siupk Next (/new_siupk) |
|---|---|---|
| **Multi-Tenant Sharding** | ❌ Tidak (Tabel dinamis suffix) | ✅ Ya (Platform DB + Tenant Shards) |
| **Pencegahan Schema Drift** | ❌ Tidak (Perlu run SQL per tabel) | ✅ Ya (Migration berjalan per Shard DB) |
| **Pencetakan Struk / Kuitansi PDF** | ⚠️ Terbatas | ✅ Ya (Tersedia langsung setelah posting jurnal) |
| **Reversal / Pembatalan Jurnal** | ⚠️ Hapus / edit manual | ✅ Ya (Jurnal Pembalik Otomatis & Audit Trail) |
| **Portofolio Pinjaman per Desa** | ❌ Tidak terstruktur | ✅ Ya (Pengelompokan desa + aging kolektibilitas) |
| **Laporan Rencana vs Realisasi** | ⚠️ Manual Excel | ✅ Ya (Laporan terintegrasi) |
| **Tutup Buku & Alokasi Laba** | ⚠️ Manual via script | ✅ Ya (Otomatis dengan jurnal alokasi laba) |
| **Manajemen Aset Tetap / Inventaris** | ⚠️ Terpisah | ✅ Ya (Register aset, nilai buku, penyusutan) |
| **Dashboard Supervisi Kabupaten** | ⚠️ Ada (Terbatas & Lambat) | ✅ Ya (Modern SPA, Performa Tinggi, Real-time) |
| **Laporan Konsolidasi Kabupaten (PDF)** | ⚠️ Ada (Terbatas: Neraca, LR, Arus Kas, LPM) | ✅ Ya (Lengkap: Neraca, LR, BB, Arus Kas, LPM, CALK Kabupaten) |
| **Integrasi Tripay Payment Gateway** | ❌ Tidak ada | ✅ Ya (QRIS & 8 Virtual Account Bank) |
| **Otomatisasi Billing & Overdue Lock** | ❌ Tidak ada | ✅ Ya (Scheduler invoice & Suspension Middleware) |
| **Asisten AI (RAG & Chat Tools)** | ❌ Tidak ada | ✅ Ya (pgvector + Ollama + Interactive Chat Widgets) |
| **Global Omnibox Search Header** | ❌ Tidak ada | ✅ Ya (Cari anggota, kelompok, pinjaman, jurnal, aset) |
| **Background Processing (Queues)** | ❌ Sync only | ✅ Ya (Redis Queue Worker Container) |
| **Automated Test Coverage** | ❌ Tidak ada | ✅ Ya (PHPUnit 12 + Playwright E2E) |

---

## 11. Kesimpulan & Rekomendasi

Pembaruan dari **siupk Legacy** ke **siupk Next** bukan sekadar pembaruan tampilan (*facelift*), melainkan **modernisasi total arsitektur sistem informasi**. 

### Rekomendasi Langkah Selanjutnya:
1. **Lakukan Cutover Data Pilot**: Gunakan Artisan orchestrator (php artisan legacy:cutover-tenant local 1) untuk menguji migrasi data tenant dari legacy ke Next sesuai docs/CUTOVER_RUNBOOK.md.
2. **Sosialisasi Portal Kabupaten**: Aktifkan akun supervisor kabupaten (is_regency_user = true) agar pihak pengawas dapat langsung memantau laporan konsolidasi keuangan seluruh kecamatan.
3. **Pengaktifan Tripay Merchant**: Masukkan TRIPAY_API_KEY, TRIPAY_PRIVATE_KEY, dan TRIPAY_MERCHANT_CODE produksi pada .env untuk mengaktifkan penerimaan pembayaran langganan secara otomatis.

Dokumen ini menjadi acuan resmi mengenai keputusan teknis, arsitektur, dan keunggulan siupk Next dibanding versi legacy.

