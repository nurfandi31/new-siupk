# Perbandingan Database SIUPK Legacy vs. SIUPK Next (Modern)

Dokumen ini berisi panduan komparasi arsitektur database, pemetaan tabel (*table mapping*), transformasi kolom (*column transformation*), dan relasi data antara **SIUPK Legacy** (sistem monolitik tabel dinamis) dan **SIUPK Next** (arsitektur multi-tenant modern dengan database platform dan sharding).

---

## 1. Ringkasan Perubahan Paradigma Arsitektur

| Aspek | SIUPK Legacy (`siupk`) | SIUPK Next (`siupknext`) | Rationale & Dampak |
|---|---|---|---|
| **Model Tenancy** | **Tabel Dinamis Per-Tenant**<br>`anggota_1`, `transaksi_1`, `pinjaman_kelompok_1`, dst. dalam 1 database tunggal. | **Platform DB + Shared/Dedicated Shard DB**<br>Semua tabel tenant distandarisasi dan diisolasi dengan kolom `tenant_id` pada database shard. | Menghilangkan puluhan ribu tabel dinamis, mencegah *schema drift*, dan mempermudah migrasi struktur database. |
| **Integritas Relasional** | **Tidak ada Foreign Key (FK)**.<br>Relasi hanya dijaga pada level aplikasi atau trigger MySQL. | **Foreign Key Constraint Ketat**.<br>Menggunakan *composite foreign key* `[tenant_id, parent_id]` untuk menjamin data tidak bocor antar-tenant. | Menghilangkan *orphan records*, inkonsistensi transaksi, dan *ghost data*. |
| **Pencatatan Akuntansi** | **Format Flat 1 Baris** (`transaksi_{id}`).<br>Satu baris memuat `rekening_debit` dan `rekening_kredit` sekaligus. | **Double-Entry Ledger Murni**.<br>Header `journal_entries` + banyak baris `journal_lines` (debit/kredit terpisah). | Memenuhi standar akuntansi internasional (SAK), mendukung multi-baris jurnal (split ledger), dan audit trail mutlak. |
| **Format Angka / Saldo** | **VARCHAR / String Uang**.<br>Format teks seperti `"1.500.000,00"` atau string tanpa desimal pasti. | **DECIMAL(19, 2) / Strict Precision**.<br>Format numerik presisi tinggi untuk perhitungan finansial. | Menghindari *floating point error* dan kegagalan agregasi matematis `SUM()` di database. |
| **Penghitungan Saldo** | **MySQL Triggers** pada tabel `saldo_{id}`.<br>Rawan deadlock dan penguncian tabel. | **Application Ledger Engine & Snapshots** (`account_monthly_balances`, `loan_installment_tracking`). | Mengurangi beban lock MySQL, performa I/O jauh lebih stabil, dan mudah diverifikasi ulang (*reconcile*). |
| **Pemisahan Identitas** | Biodata nasabah bercampur langsung di tabel `anggota_{id}`. | **Pemisahan `people` (identitas NIK) vs `members` (keanggotaan tenant)**. | Satu orang warga dapat memiliki keanggotaan terpisah atau penjaminan tanpa duplikasi master identitas. |
| **Identitas Record** | `id` Auto-increment murni yang rawan bentrok saat digabungkan. | **Tri-Identity System**:<br>1. `row_id`: PK internal fisik shard.<br>2. `id`: Nomor urut sekuensial per tenant.<br>3. `public_id`: ULID/UUID 26-char unik global untuk API/URL. | Keamanan data lebih kuat (ID tidak mudah ditebak) dan data aman saat migrasi antar-shard. |

---

## 2. Tabel Pemetaan Lengkap (Master Table Mapping)

Tabel berikut memetakan setiap tabel/model pada SIUPK Legacy ke entitas tabel pada SIUPK Next, beserta penempatan basis datanya (**Platform DB** atau **Shard DB**):

| # | Tabel Legacy (`siupk`) | Tabel Target SIUPK Next (`siupknext`) | Lokasi DB | Kategori / Keterangan Transformasi |
|---|---|---|---|---|
| **A** | **Kelembagaan, Tenant & Wilayah** | | | |
| 1 | `kecamatan` | `tenants` | `Platform DB` | Master tenant di tingkat platform SaaS (berisi kode kecamatan, nama, status langganan, dsb). |
| 2 | `kecamatan` (replika lokal) | `tenant_registry` | `Shard DB` | Registry sinkronisasi tenant di dalam shard database yang bersangkutan. |
| 3 | `desa` | `organization_units` / `village_namings` | `Shard DB` | Desa/Kelurahan dikonversi menjadi unit organisasi tingkat desa beserta sebutan resminya. |
| 4 | `sebutan_desa` | `village_namings` | `Shard DB` | Konfigurasi sebutan kepala desa, badan permusyawaratan, dsb. per tenant. |
| 5 | `kabupaten` | `tenants` (`regency_code`) / `Platform Settings` | `Platform DB` | Menjadi atribut kode kabupaten pada tenant dan modul supervisi platform. |
| 6 | `wilayah` | Dikelola via Master Referensi Regional API / Enums | `App / Shard` | Dinormalisasi menggunakan standarisasi kode Kemendagri / BPS. |
| **B** | **Pengguna, Autentikasi & Otorisasi** | | | |
| 7 | `user` | `users` + `tenant_memberships` | `Platform DB` | Pengguna tenant dimigrasi ke master `users` platform terpadu dengan relasi `tenant_memberships`. |
| 8 | `admin_users` | `users` (`role: superadmin / district_admin`) | `Platform DB` | User administrator pusat dan kabupaten disatukan dalam tabel `users` berstatus hak akses khusus. |
| 9 | `level` | `roles` | `Shard DB` | Master tingkatan otorisasi (Manager, Teller, Verifikator, dsb). |
| 10 | `jabatan` & `personalia` | `organization_units` / `user_roles` | `Shard DB` | Posisi jabatan struktural UPK/BUMDesma. |
| 11 | `user_token` | Laravel Sanctum / Redis Tokens | `Redis / Platform` | Token autentikasi modern berbasis hash aman. |
| **C** | **Kependudukan, Anggota & Kelompok** | | | |
| 12 | `anggota_{id}` (biodata) | `people` | `Shard DB` | Biodata kependudukan (NIK, nama, tempat/tgl lahir, jenis kelamin, foto). |
| 13 | `anggota_{id}` (keanggotaan) | `members` | `Shard DB` | Status keanggotaan tenant, nomor register (NIA), tanggal bergabung. |
| 14 | `anggota_{id}` (alamat) | `member_addresses` | `Shard DB` | Normalisasi alamat lengkap (RT/RW, desa/kelurahan, kode pos). |
| 15 | `anggota_{id}` (usaha) | `member_businesses` | `Shard DB` | Detail jenis usaha anggota dan estimasi omzet. |
| 16 | `data_pemanfaat` | `member_guarantors` + `people` | `Shard DB` | Data keluarga penjamin / pemanfaat kredit dinormalisasi ke entitas orang terhubung. |
| 17 | `kelompok_{id}` | `groups` | `Shard DB` | Master kelompok peminjam simpan pinjam (SPP/UEP). |
| 18 | `kelompok_{id}` (pengurus) | `group_officers` | `Shard DB` | Data Ketua, Sekretaris, Bendahara kelompok dipisahkan ke tabel relasi terstruktur. |
| 19 | Relasi anggota-kelompok | `group_members` | `Shard DB` | Many-to-many relationship anggota yang tergabung dalam suatu kelompok. |
| 20 | `fungsi_kelompok` | `group_functions` | `Shard DB` | Master fungsi kelompok peminjam. |
| 21 | `tingkat_kelompok` | `group_levels` | `Shard DB` | Master tingkatan / kelas kelompok. |
| 22 | `jenis_usaha` / `usaha` | `business_types` | `Shard DB` | Master klasifikasi bidang usaha anggota. |
| 23 | `jenis_kegiatan` | `activity_types` | `Shard DB` | Master jenis aktivitas kelompok. |
| 24 | `keluarga` / `pendidikan` | Enums / DTO Fields pada `people` | `Shard DB` | Dinormalisasi sebagai atribut terstruktur pada data kependudukan. |
| **D** | **Akuntansi, Kas & Buku Besar** | | | |
| 25 | `akun_level_1` | `accounts` (`level: 1`) | `Shard DB` | Master Chart of Accounts (COA) level 1 (Aset, Kewajiban, Ekuitas, Pendapatan, Beban). |
| 26 | `akun_level_2` | `accounts` (`level: 2`) | `Shard DB` | Master COA level 2 (Kas & Setara Kas, Piutang Pinjaman, dsb). |
| 27 | `akun_level_3` | `accounts` (`level: 3`) | `Shard DB` | Master COA level 3 (Sub-kategori akun). |
| 28 | `rekening_{id}` | `accounts` (`level: 4 / postable`) | `Shard DB` | Rekening buku besar operasional per tenant dengan `normal_balance` ('D' / 'C'). |
| 29 | `transaksi_{id}` (header) | `journal_entries` | `Shard DB` | Header bukti transaksi akuntansi (`journal_number`, `transaction_date`, `status`). |
| 30 | `transaksi_{id}` (debit/kredit) | `journal_lines` | `Shard DB` | Detail baris jurnal double-entry (`debit_amount`, `credit_amount`, `memo`). |
| 31 | `saldo_{id}` (saldo awal) | `account_opening_balances` | `Shard DB` | Saldo awal akun buku besar per tahun buku / periode fiskal. |
| 32 | `saldo_{id}` (saldo bulanan) | `account_monthly_balances` | `Shard DB` | Snapshot saldo akhir per bulan yang terindeks cepat tanpa trigger MySQL. |
| 33 | `ebudgeting_{id}` | `budgets` + `budget_lines` | `Shard DB` | Rencana Anggaran Pendapatan & Belanja (RAPB) tahunan per rekening. |
| 34 | `arus_kas`, `calk`, `jenis_laporan` | Dynamic Financial Reporting Query Engine | `App Logic` | Laporan Laba Rugi, Neraca, Arus Kas, dan CALK dihitung dinamis dari `journal_lines`. |
| 35 | `jenis_transaksi` | `transaction_type` pada `journal_entries` | `Shard DB` | Kategori transaksi (Kas Masuk, Kas Keluar, Jurnal Memorial, Realisasi Pinjaman, dsb). |
| **E** | **Pinjaman, Pembiayaan & Angsuran (Lending)** | | | |
| 36 | `jenis_produk_pinjaman` | `loan_products` | `Shard DB` | Definisi produk pinjaman parametrik (SPP, UEP, Individual) beserta suku bunga dan aturan. |
| 37 | `pinjaman_kelompok_{id}` | `loans` + `loan_borrowers` | `Shard DB` | Kontrak kredit induk kelompok (`principal_amount`, `interest_rate`, `term_months`, status). |
| 38 | `pinjaman_anggota_{id}` | `loan_beneficiaries` | `Shard DB` | Porsi alokasi pencairan dan tanggung renteng per anggota di dalam kelompok. |
| 39 | `pinjaman_kelompok_{id}` (verifikasi) | `loan_committee` + `loan_status_histories` | `Shard DB` | Catatan tim verifikasi, persetujuan pinjaman, dan audit riwayat status proposal pinjaman. |
| 40 | `rencana_angsuran_{id}` | `loan_installments` | `Shard DB` | Jadwal amortisasi angsuran (pokok, jasa/bunga, tanggal jatuh tempo). |
| 41 | Pelacakan status cicilan | `loan_installment_tracking` | `Shard DB` | Status pelunasan pokok dan bunga per bulan angsuran. |
| 42 | `real_angsuran_{id}` | `loan_payments` | `Shard DB` | Transaksi penerimaan setoran angsuran (terhubung langsung ke `journal_entries`). |
| 43 | `real_angsuran_{id}` (alokasi) | `loan_payment_allocations` | `Shard DB` | Pemecahan setoran ke komponen pokok, jasa, dan denda per cicilan. |
| 44 | `penghapusan_{id}` | `loan_write_offs` | `Shard DB` | Catatan penghapusan pinjaman macet beserta persetujuan dan jurnal penghapusbukuan. |
| 45 | `dokumen_pinjaman` | `documents` / Template PDF Engine | `Shard DB` | Pengelolaan berkas SPK, Surat Perjanjian Kredit, dan Berita Acara. |
| 46 | `jenis_jasa` & `sistem_angsuran` | Enums / Parameter pada `loan_products` | `Shard DB` | Skema bunga (Flat, Menurun, Anuitas) dan frekuensi cicilan (Bulanan, Musiman). |
| 47 | `status_pinjaman` | Enums Status (`loans.status`) | `Shard DB` | Status siklus pinjaman: `proposal`, `verified`, `approved`, `disbursed`, `active`, `closed`, `written_off`. |
| **F** | **Inventaris & Aset Kantor** | | | |
| 48 | `inventaris_{id}` (kategori) | `asset_categories` | `Shard DB` | Kategori aset tetap (Peralatan Kantor, Kendaraan, Gedung) dan aturan penyusutan. |
| 49 | `inventaris_{id}` (barang) | `assets` | `Shard DB` | Data aset, harga perolehan, umur ekonomis, akumulasi depresiasi, dan nilai buku. |
| 50 | Riwayat kondisi/mutasi aset | `asset_status_histories` | `Shard DB` | Catatan kondisi (baik/rusak/hilang) dan mutasi inventaris. |
| **G** | **Billing Platform, Lisensi & Integrasi Pembayaran** | | | |
| 51 | `licenses` | `licenses` / `subscriptions` | `Platform DB` | Lisensi aktif per tenant dengan tanggal kedaluwarsa dan batasan kuota. |
| 52 | `admin_invoice` | `invoices` | `Platform DB` | Tagihan biaya langganan aplikasi SIUPK Next yang dibuat otomatis per siklus. |
| 53 | `admin_transaksi` | `invoice_payments` | `Platform DB` | Pencatatan pelunasan tagihan terintegrasi Payment Gateway (Tripay QRIS, VA, Bank Transfer). |
| 54 | `admin_jenis_pembayaran` & `admin_rekening` | `Platform Settings` / Payment Channels | `Platform DB` | Konfigurasi metode pembayaran gateway dan rekening penampung platform. |
| **H** | **Entitas Khusus Baru pada SIUPK Next (Next-Only Entities)** | | | |
| 55 | *Tidak ada di legacy* | `database_shards` | `Platform DB` | Manajemen server/koneksi database shard multi-tenant. |
| 56 | *Tidak ada di legacy* | `tenant_placements` | `Platform DB` | Pemetaan tenant ke database shard target. |
| 57 | *Tidak ada di legacy* | `plans` | `Platform DB` | Master paket fitur & harga langganan SaaS. |
| 58 | *Tidak ada di legacy* | `tenant_sequences` | `Shard DB` | Sequence atomic per-tenant untuk nomor dokumen, nomor anggota, dan nomor pinjaman. |
| 59 | *Tidak ada di legacy* | `legacy_migration_batches` | `Shard DB` | Tracking batch eksekusi migrasi data cutover dari sistem legacy. |
| 60 | *Tidak ada di legacy* | `legacy_record_mappings` | `Shard DB` | Tabel jembatan (*cross-reference mapping*) antara ID record legacy dan `row_id` Next. |
| 61 | *Tidak ada di legacy* | `migration_reconciliation_results` | `Shard DB` | Log verifikasi rekonsiliasi data migrasi (jumlah baris, total nominal, baki debet). |
| 62 | *Tidak ada di legacy* | `audit_logs` | `Shard DB` | Audit trail lengkap seluruh aksi CRUD data pengguna. |
| 63 | *Tidak ada di legacy* | `ai_conversations` & `ai_messages` | `Shard DB` | Riwayat percakapan dengan AI Assistant SIUPK. |
| 64 | *Tidak ada di legacy* | `ai_knowledge_sources` & `ai_document_chunks` | `Shard DB / Vector DB` | Basis pengetahuan SOP BUMDesma/UPK untuk RAG (Retrieval-Augmented Generation). |

---

## 3. Detail Transformasi Kolom pada Modul Kunci

Berikut adalah perbandingan struktur kolom secara mendalam untuk modul-modul bisnis paling vital:

### 3.1 Modul Kependudukan & Anggota: `anggota_{id}` $\rightarrow$ `people` + `members` + `member_addresses`

Di sistem legacy, semua data bercampur dalam satu baris `anggota_{id}`. Pada SIUPK Next, entitas kependudukan (`people`) dipisahkan dari entitas keanggotaan tenant (`members`).

```
+------------------------------------+
|         LEGACY (anggota_1)         |
+------------------------------------+
| id                                 |
| nik                                | -----> +------------------------------------+
| namadepan                          |        |            NEXT (people)           |
| jk, tempat_lahir, tgl_lahir        |        +------------------------------------+
| hp, foto, status                   |        | row_id, public_id, tenant_id       |
|                                    |        | national_identity_number (NIK)     |
| desa, alamat                       |        | full_name, gender, birth_place     |
| usaha, penghasilan                 |        | birth_date, phone, photo_path      |
| hubungan, penjamin                 |        +------------------------------------+
+------------------------------------+                         | 1
                                                               |
                                      +------------------------+------------------------+
                                      | 1                                               | 1..N
                                      v                                                 v
                       +-------------------------------+                 +-------------------------------+
                       |         NEXT (members)        |                 |     NEXT (member_addresses)   |
                       +-------------------------------+                 +-------------------------------+
                       | row_id, public_id, tenant_id  |                 | row_id, member_row_id         |
                       | person_row_id                 |                 | street_address, rt, rw        |
                       | member_number (NIA)           |                 | village_code, postal_code     |
                       | status, joined_at             |                 +-------------------------------+
                       +-------------------------------+
```

| Kolom Legacy `anggota_{id}` | Tabel & Kolom Target Next | Tipe Data & Transformasi |
|---|---|---|
| `id` | `legacy_record_mappings.source_id` $\rightarrow$ `members.id` | Disimpan sebagai ID urut tenant dan dicatat pada tabel mapping migrasi. |
| `nik` | `people.national_identity_number` | `CHAR(16)`, diindeks per tenant. |
| `namadepan` | `people.full_name` | `VARCHAR(180)`. |
| `jk` | `people.gender` | `CHAR(1)` ('M' / 'F' / 'L' / 'P'). |
| `tempat_lahir`, `tgl_lahir` | `people.birth_place`, `people.birth_date` | `VARCHAR(100)`, `DATE`. |
| `hp` | `people.phone` | `VARCHAR(20)`. |
| `foto` | `people.photo_path` | `VARCHAR(500)`. |
| `nia` | `members.member_number` | `VARCHAR(50)`, nomor induk anggota resmi. |
| `tgl_bergabung` | `members.joined_at` | `DATE`. |
| `status` | `members.status` | `VARCHAR(30)` ('active', 'inactive', 'deceased'). |
| `alamat` | `member_addresses.street_address` | `TEXT`. |
| `desa` | `member_addresses.village_code` | `VARCHAR(50)` referensi ke unit organisasi desa. |
| `penjamin`, `hubungan` | `member_guarantors` | Dinormalisasi ke data penjamin independen. |

---

### 3.2 Modul Akuntansi: `transaksi_{id}` $\rightarrow$ `journal_entries` + `journal_lines`

Sistem legacy menggunakan satu baris per transaksi dengan menyebutkan rekening debit dan rekening kredit secara horizontal. SIUPK Next mentransformasikan setiap transaksi menjadi satu header jurnal dan minimal dua baris jurnal (double-entry).

```
LEGACY: transaksi_1 (Flat Row)
+-----+------------+----------------+-----------------+------------+--------------------------+
| idt | tgl_trans  | rekening_debit | rekening_kredit | jumlah     | keterangan               |
+-----+------------+----------------+-----------------+------------+--------------------------+
| 101 | 2026-07-20 | 1.1.01.01      | 4.1.01.01       | 500.000,00 | Penerimaan Jasa Pinjaman |
+-----+------------+----------------+-----------------+------------+--------------------------+

                                        │ TRANSFORMASI
                                        ▼
NEXT: journal_entries (Header)
+--------+-----------+----------------+------------------+--------------------------+--------+
| row_id | tenant_id | journal_number | transaction_date | description              | status |
+--------+-----------+----------------+------------------+--------------------------+--------+
| 5001   | 1         | JRN-202607-001 | 2026-07-20       | Penerimaan Jasa Pinjaman | posted |
+--------+-----------+----------------+------------------+--------------------------+--------+
    │
    ├─► NEXT: journal_lines (Line 1 - Debit)
    │   +--------+------------------+------------+--------------+---------------+
    │   | row_id | journal_entry_id | account_id | debit_amount | credit_amount |
    │   +--------+------------------+------------+--------------+---------------+
    │   | 10001  | 5001             | [Kas Kasir]| 500000.00    | 0.00          |
    │   +--------+------------------+------------+--------------+---------------+
    │
    └─► NEXT: journal_lines (Line 2 - Credit)
        +--------+------------------+------------+--------------+---------------+
        | row_id | journal_entry_id | account_id | debit_amount | credit_amount |
        +--------+------------------+------------+--------------+---------------+
        | 10002  | 5001             | [Pend Jasa]| 0.00         | 500000.00     |
        +--------+------------------+------------+--------------+---------------+
```

| Kolom Legacy `transaksi_{id}` | Tabel & Kolom Target Next | Tipe Data & Transformasi |
|---|---|---|
| `idt` | `journal_entries.legacy_id` + mapping | Disimpan pada `legacy_record_mappings` dan metadata jurnal. |
| `idtp` | `journal_entries.source_row_id` / reference | ID transaksi induk / pengelompokan batch. |
| `tgl_transaksi` | `journal_entries.transaction_date` | `DATE` standar ISO YYYY-MM-DD. |
| `keterangan` | `journal_entries.description` & `journal_lines.memo` | `TEXT`. |
| `relasi` | `journal_entries.legacy_relation` | Informasi relasi peminjam/kelompok legacy. |
| `rekening_debit` | `journal_lines.account_row_id` (Line 1) | FK ke tabel `accounts` berdasarkan kode akun. |
| `rekening_kredit` | `journal_lines.account_row_id` (Line 2) | FK ke tabel `accounts` berdasarkan kode akun. |
| `jumlah` (string) | `journal_lines.debit_amount` / `credit_amount` | Dikonversi dari string/uang ke `DECIMAL(19, 2)`. |
| `user_id` | `journal_entries.created_by_user_id` | `BIGINT` referensi pengguna pembuat jurnal. |

---

### 3.3 Modul Pinjaman & Setoran: `pinjaman_kelompok_{id}` & `real_angsuran_{id}` $\rightarrow$ `loans`, `loan_installments`, `loan_payments`, `loan_payment_allocations`

Pada SIUPK Legacy, pinjaman kelompok dan pinjaman anggota sering mengalami selisih karena alur pencatatan yang terpisah. Pada Next, struktur pinjaman diatur secara hierarkis dengan alokasi setoran yang presisi.

```
+---------------------------------------+
|     LEGACY: pinjaman_kelompok_1       |
+---------------------------------------+
| id, id_kel, jenis_pp, alokasi         |
| pros_jasa, jenis_jasa, spk_no, status |
+---------------------------------------+
                    │
                    ▼
+---------------------------------------+       1..N       +------------------------------------+
|              NEXT: loans              | ───────────────► |      NEXT: loan_beneficiaries      |
+---------------------------------------+                  +------------------------------------+
| row_id, tenant_id, public_id          |                  | (Pengganti pinjaman_anggota_1)     |
| loan_number, loan_product_row_id      |                  | member_row_id, allocated_amount    |
| principal_amount, interest_rate       |                  +------------------------------------+
| term_months, status, disbursed_at     |
+---------------------------------------+
       │                             │
       │ 1..N                        │ 1..N
       ▼                             ▼
+------------------------------+   +------------------------------------+
|    NEXT: loan_installments   |   |        NEXT: loan_payments         |
+------------------------------+   +------------------------------------+
| (Pengganti rencana_angsuran) |   | (Pengganti real_angsuran_1)        |
| installment_number           |   | payment_number, payment_date       |
| due_date, principal_due      |   | total_amount_paid, journal_entry_id|
| interest_due                 |   +------------------------------------+
+------------------------------+                     │
                                                     │ 1..N
                                                     ▼
                                   +------------------------------------+
                                   |   NEXT: loan_payment_allocations   |
                                   +------------------------------------+
                                   | installment_row_id                 |
                                   | principal_paid, interest_paid      |
                                   | penalty_paid                       |
                                   +------------------------------------+
```

| Kolom Legacy Pinjaman & Angsuran | Tabel & Kolom Target Next | Keterangan & Transformasi |
|---|---|---|
| `pinjaman_kelompok.id` | `loans.id` + `legacy_record_mappings` | ID pinjaman lama dipertahankan untuk referensi historis. |
| `pinjaman_kelompok.id_kel` | `loan_borrowers.group_row_id` | Menghubungkan pinjaman ke entitas `groups`. |
| `pinjaman_kelompok.alokasi` | `loans.principal_amount` | Nominal pokok pinjaman dalam format `DECIMAL(19, 2)`. |
| `pinjaman_kelompok.pros_jasa` | `loans.interest_rate` | Persentase suku bunga per tahun/periode (`DECIMAL(9, 4)`). |
| `pinjaman_kelompok.jangka` | `loans.term_months` | Jangka waktu pinjaman dalam bulan. |
| `pinjaman_kelompok.spk_no` | `loans.loan_number` | Nomor resmi Surat Perjanjian Kredit. |
| `pinjaman_anggota` | `loan_beneficiaries` | Rincian porsi pinjaman yang diterima masing-masing anggota. |
| `rencana_angsuran` | `loan_installments` | Jadwal kewajiban angsuran pokok dan jasa per termin jatuh tempo. |
| `real_angsuran` | `loan_payments` | Header pembayaran setoran angsuran oleh kelompok/anggota. |
| `real_angsuran.realisasi_pokok` | `loan_payment_allocations.principal_paid` | Porsi dana yang memotong baki debet pokok pinjaman. |
| `real_angsuran.realisasi_jasa` | `loan_payment_allocations.interest_paid` | Porsi dana yang diakui sebagai pendapatan jasa/bunga. |
| `real_angsuran.denda` | `loan_payment_allocations.penalty_paid` | Porsi denda keterlambatan pembayaran. |

---

## 4. Perbandingan Chart of Accounts (COA) & Hirarki Akun

Pada SIUPK Legacy, struktur akun dipecah ke dalam 4 level tabel terpisah (`akun_level_1`, `akun_level_2`, `akun_level_3`, dan `rekening_{id}`). 

Pada SIUPK Next, seluruh hirarki akun disatukan ke dalam satu tabel rekursif `accounts` yang fleksibel dengan relasi `parent_row_id`:

```sql
-- SIUPK Next: Struktur accounts terpadu
CREATE TABLE `accounts` (
    `row_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `parent_row_id` BIGINT UNSIGNED NULL,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(180) NOT NULL,
    `account_type` VARCHAR(30) NOT NULL, -- asset, liability, equity, revenue, expense
    `normal_balance` CHAR(1) NOT NULL,    -- 'D' (Debit) atau 'C' (Credit)
    `level` SMALLINT UNSIGNED NOT NULL,   -- 1, 2, 3, 4, dst.
    `is_postable` BOOLEAN DEFAULT TRUE,   -- FALSE untuk akun Header/Induk, TRUE untuk akun transaksi
    `is_active` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`tenant_id`, `parent_row_id`) REFERENCES `accounts` (`tenant_id`, `row_id`)
);
```

### Keuntungan Penyatuan Akun:
1. **Pohon Akun Tak Terbatas (*Unlimited Depth*)**: Dapat menambah sub-akun level 5 atau 6 tanpa mengubah struktur database.
2. **Kueri Neraca Cepat**: Kueri agregasi saldo per level dapat dilakukan dengan *Recursive Common Table Expressions (CTE)* standar SQL.
3. **Pemberian Tanda Saldo Normal Jelas**: Memastikan validasi debit/kredit berjalan otomatis saat transaksi diinput.

---

## 5. Tooling & Perintah Migrasi Data (Legacy to Next)

Untuk mengeksekusi transformasi data dari tabel legacy ke skema Next, telah disediakan serangkaian perintah artisan otomatis yang aman dan dapat diuji coba (*dry-run*):

```bash
# 1. Discover & Analisis Struktur Data Legacy
php artisan legacy:discover-membership {tenant_id} {lokasi_id}
php artisan legacy:discover-accounting {tenant_id} {lokasi_id}

# 2. Migrasi Data Master & Keanggotaan (anggota & kelompok -> people, members, groups)
php artisan legacy:migrate-membership {tenant_id} {lokasi_id} --dry-run
php artisan legacy:migrate-membership {tenant_id} {lokasi_id}

# 3. Migrasi Akuntansi & Jurnal Transaksi (rekening & transaksi -> accounts, journals)
php artisan legacy:migrate-accounting {tenant_id} {lokasi_id} --dry-run
php artisan legacy:migrate-accounting {tenant_id} {lokasi_id}

# 4. Migrasi Pinjaman & Realisasi Angsuran (pinjaman & angsuran -> loans, payments)
php artisan legacy:migrate-lending {tenant_id} {lokasi_id} --dry-run
php artisan legacy:migrate-lending {tenant_id} {lokasi_id}

# 5. Rekonsiliasi & Validasi Integritas Data Pasca-Migrasi
php artisan legacy:reconcile-lending {tenant_id}
```

---

## 6. Kesimpulan

Transformasi database dari SIUPK Legacy ke SIUPK Next tidak hanya memodernisasi nama tabel, tetapi juga:
1. **Mengeliminasi bottleneck arsitektur tabel dinamis** (dari ribuan tabel per instansi menjadi skema sharding terpadu).
2. **Menjamin keabsahan finansial tingkat tinggi** melalui pembukuan *double-entry* dan tipe data desimal presisi.
3. **Mempersiapkan sistem untuk skalabilitas ribuan kecamatan**, integrasi payment gateway otomatis, serta kecerdasan buatan (*AI Assistant*) terintegrasi.
