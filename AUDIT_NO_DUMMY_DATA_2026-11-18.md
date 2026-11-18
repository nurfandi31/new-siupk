# Audit Data Dummy / Hardcoded — SIUPK Next

Tanggal: 18 November 2026
Ruang lingkup: seluruh aplikasi (`app/Domain/**`, `app/Http/**`, `app/Services/**`, `resources/js/**`, `resources/views/**`, `database/seeders/**`, `tests/**`).

## Kesimpulan

Tidak ditemukan lagi data dummy/sampel/mock yang tampil sebagai data operasional pada halaman, dokumen, atau laporan. Seluruh angka, nama organisasi, pejabat, nomor dokumen, dan data wilayah kini berasal dari database/konfigurasi tenant — atau ditampilkan kosong (`—`/string kosong) bila data memang belum ada.

**Tidak ada database baru, migration baru, tabel baru, atau perubahan schema.** Perubahan hanya pada kode aplikasi, tampilan, dan ekspektasi test.

## Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` (seluruh suite, non-paralel) | **691 passed, 13 skipped, 0 failed** (9858 assertions) |
| Test terarah pasca-perbaikan (`Regency`, `RegencyGeoService`) | 13 passed (64 assertions) |
| `npm run build` | Berhasil (hanya warning chunk-size & aset gambar lama) |
| `php artisan view:cache` | Seluruh Blade berhasil dikompilasi |
| `git status -- database/migrations` | Kosong (tidak ada migration baru) |

## Temuan yang Diperbaiki

### 1. Identitas hukum pada laporan (severity: tinggi)
Sebanyak **35 fallback `'BUMDesma LKD'` di 26 file** service/laporan diganti string kosong. Laporan tidak lagi menampilkan nama aplikasi atau nama generik sebagai nama badan hukum.

- `app/Domain/Accounting/Services/Reports/**`
- `app/Domain/Lending/Services/Reports/**`
- `app/Domain/Regulatory/Services/**`
- `app/Domain/Assets/Services/AssetReportService.php`
- `app/Domain/Documents/Services/ReportSignatureService.php`
- `resources/views/reports/pdf/partials/letterhead.blade.php`
- Seluruh `resources/views/reports/pdf/**` (tidak ada lagi `config('app.name')` sebagai identitas organisasi)

### 2. Nomor pinjaman sintetis (severity: tinggi)
Fallback `PINJ-{id}` dihapus dari **18 template Blade** dan **3 endpoint** `MobileCollectionController`; kini memakai `loan_number` asli atau string kosong.

### 3. Paket laporan tahunan (severity: tinggi)
`AnnualReportPackService` + template `annual/surat_pengantar`, `annual/ba_pergantian`, `annual/cover`:

- Nama pejabat, sekretaris, bendahara, verifikator, kepala desa diambil dari `OrganizationProfile`/`OrganizationUnit`.
- `province_name` diambil dari tenant/shard, bukan kolom yang tidak ada.
- Nomor dokumen palsu `001` dihapus.
- Tidak ada fallback nama organisasi fiktif.

### 4. Laporan kesehatan keuangan (severity: tinggi)
`FinancialHealthService` + `penilaian_kesehatan.blade.php`: tanggal dan nama pejabat kini nyata (`date_formatted`, manager/secretary/treasurer/verifier), bukan placeholder.

### 5. Invoice (severity: tinggi)
`InvoiceService`:

- Nomor rekening bank fiktif dihapus.
- Jalur `adhoc` palsu dihapus.
- Fallback payer dihapus; sumber invoice tidak dikenal/missing melempar `DomainException`.
- `invoice.blade.php` tidak lagi memakai judul sebagai nama.

### 6. Data wilayah fiktif (severity: tinggi)
- `RegionalCodeApi::villages()` tidak lagi membuat desa palsu.
- `RegionalCodeFallback` tidak lagi mengembalikan kabupaten/kecamatan generik palsu.
- `TenantVillageProvisioner` tidak menyisipkan desa palsu saat API kosong.
- `RegencyGeoService::resolveDistrictCoordinate()` mengembalikan `null` untuk kode kecamatan tak dikenal (tidak lagi menyebar offset acak).
- Test `RegencyGeoServiceTest` diperbarui: `test_unknown_district_does_not_fabricate_coordinate`.

### 7. Peta & koordinat (severity: sedang)
- Default peta Jawa Tengah dihapus dari `Regency/Dashboard.vue`, `RegencyMap.vue`, `LocationMapPicker.vue`, `Admin/Tenants/Create.vue`, `Admin/Tenants/Edit.vue`.
- Default netral: pusat Indonesia / pusat kabupaten yang terverifikasi.
- `validateWithinRegency` tidak menolak koordinat bila kabupaten belum terverifikasi.

### 8. Laporan holding & provinsi (severity: sedang)
- `HoldingReportController` tidak lagi memakai `'Holding BUMDesma'`; provinsi diturunkan dari request/tenant/shard.
- `OjkProfileService` dan `YearEndCalkService`: `province_name` dari `TenantContext` (tenant/shard), bukan `organization_profiles.province_name` yang tidak ada.

### 9. Statistik landing page (severity: sedang)
`Home.vue`: angka fabrikasi (`98,6%`, `38 Kabupaten`, badge `Live`, statistik `100%`) diganti label kualitatif. Statistik tenant di `PublicSiteController`/`TenantHome.vue` tetap query langsung ke database.

### 10. Portofolio OJK (severity: sedang)
`Regulatory/Ojk/Collectibility.vue`: total `% Portofolio` dihitung dari `share_pct` bucket; menampilkan `—` bila tidak ada data (bukan `100.00%` hardcoded).

### 11. Buku besar konsolidasi kabupaten (severity: rendah)
`RegencyConsolidatedReportService`: fallback nama `Kecamatan #<id>` diganti string kosong. `tenant_id` tetap tersedia sebagai referensi teknis.

### 12. Preview pesan WhatsApp (severity: rendah)
`Settings/Whatsapp/Hub.vue`: nama contoh `Mawar Jaya`/`Sumiati` dan nomor `#<row_id>` dihapus. Preview memakai data baris nyata atau `—`.

### 13. Seeder (severity: tinggi)
- `DatabaseSeeder` default hanya memanggil `UserSeeder`.
- `DemoAdminSeeder` hanya berjalan bila `SEED_DEMO_TENANT=true` dan tidak menimpa password akun yang sudah ada.
- `siupk:seed-sample-loans` dibatasi `local`/`testing` dan melempar `RuntimeException` di environment lain.

## Temuan yang Sengaja Dipertahankan (bukan data dummy)

| Item | Alasan |
|---|---|
| Label peran `Direktur`, `Bendahara`, `Kepala Desa`, `Ketua`, `Sekretaris`, `Verifikator` | Label jabatan, bukan data orang |
| Penanda isian `........................` / `________________` | Menandai kolom yang belum diisi, bukan data palsu |
| Label tipe `Kabupaten`, `Provinsi`, `Desa`, `Pinjaman`, `BADAN KERJASAMA ANTAR DESA (BUMDESMA)` | Label kategori pada kop laporan kabupaten |
| Narasi kebijakan akuntansi di `CalkService` | Teks kebijakan SAK EP, bukan angka/entitas fiktif |
| Branding vendor `BUMDesma LKD Platform`, `BUMDesma LKD Financial System` di halaman publik/login/error | Identitas produk/platform, bukan identitas tenant |
| `TenantLoginAccountProvisioner` (`@tenant.local`, `pending-wa-*`) | Sentinel internal yang didokumentasikan; kolom `users.email`/`users.phone` non-null dan unik |
| Tabel koordinat kabupaten/kecamatan di `RegencyGeoService` | Data geografis nyata (kode BPS), bukan data karangan |
| `SeedSampleLoans` | Perintah pengembangan, diblokir di production |
| `DemoAdminSeeder` | Opt-in eksplisit via environment |
| Template CSV onboarding | Hanya header, tanpa baris contoh |
| Header sidebar `BUMDesma/LKD` di `AuthenticatedLayout.vue` | Label tipe lembaga generik; nama tenant tetap dari database bila tersedia |

## Status Database Saat Ini (audit read-only, 18 Nov 2026)

Kode sudah bersih, tetapi **database yang sedang aktif masih berisi data demo/sampel**. Ini berasal dari data, bukan dari kode, sehingga tidak ikut terhapus oleh perbaikan kode.

| Tenant | Domain | Pinjaman | Anggota | Kelompok | Catatan |
|---|---|---|---|---|---|
| `local` | localhost | 13 | 15 | 2 | 13 pinjaman berprefix `SAMPLE-` |
| `demo` | demo.siupknext.test | 13 | 15 | 2 | 13 pinjaman berprefix `SAMPLE-`, 1 profil organisasi |
| `asta` | asta.siupknext.test | 10 | 18 | 0 | data operasional |
| `kec-320630` | — | 127 | 2313 | 1165 | "Tegalrejo (Verifikasi Migrasi)" |

Akun platform yang menandakan data demo/placeholder:

- `admin` / "Administrator Demo" / `admin@demo.siupknext.test`
- `superadmin` / `superadmin@example.test`
- `dev` / `dev@example.test`

Implikasi:

- Selama tenant `demo` dan `local` masih aktif, halaman serta laporan tenant tersebut akan menampilkan data sampel.
- Untuk instalasi produksi, tenant demo/sampel dan akun demo perlu dinonaktifkan atau dihapus sebagai **operasi data**, bukan perubahan kode.
- Domain berakhiran `.test` menunjukkan lingkungan non-produksi, tetapi tetap perlu dipastikan tidak ikut ter-deploy.

## Catatan

- `git status` menunjukkan 117 file berubah, semuanya kode/tampilan/test. Tidak ada file di `database/migrations`.
- Peringatan build `/assets/img/login.png` sudah ada sebelum audit dan bukan kegagalan build.
- Suite penuh dijalankan tanpa `--parallel` karena ParaTest 7.x tidak terpasang.
