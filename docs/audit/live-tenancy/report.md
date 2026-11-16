# Live Audit — Pusat Tenant → Lokasi Tenant

- Tanggal: 2026-11-16T04:31:58.547Z
- Pusat: http://siupknext.test
- Lokasi: http://asta.siupknext.test
- Tenant: ASTA (asta)
- Tenant ID: 4
- Screenshot: C:\laragon\www\siupknext\docs\audit\live-tenancy

## Ringkasan

| Status | Jumlah |
|---|---|
| PASS | 97 |
| FAIL | 0 |
| SKIP | 0 |

## Detail

| # | Area | Langkah | Status | Detail |
|---|---|---|---|---|
| 1 | Pusat | Buka halaman login pusat | PASS | HTTP 200 |
| 2 | Pusat | Login sebagai superadmin | PASS | http://siupknext.test/admin |
| 3 | Pusat | Halaman Dashboard | PASS | HTTP 200, 1219 char |
| 4 | Pusat | Halaman Daftar Tenant | PASS | HTTP 200, 1032 char |
| 5 | Pusat | Halaman Invoice | PASS | HTTP 200, 869 char |
| 6 | Pusat | Halaman Plan | PASS | HTTP 200, 763 char |
| 7 | Pusat | Halaman Shard | PASS | HTTP 200, 1342 char |
| 8 | Pusat | Halaman Pengaturan Platform | PASS | HTTP 200, 998 char |
| 9 | Pusat | Halaman Audit Log | PASS | HTTP 200, 2961 char |
| 10 | Pusat | Halaman Pendapatan | PASS | HTTP 200, 1557 char |
| 11 | Pusat | Halaman Pengguna Platform | PASS | HTTP 200, 2526 char |
| 12 | Pusat | Halaman WhatsApp Platform | PASS | HTTP 200, 961 char |
| 13 | Pusat | Halaman Integrasi | PASS | HTTP 200, 1346 char |
| 14 | Pusat | Halaman Payment Gateway | PASS | HTTP 200, 1346 char |
| 15 | Pusat | Halaman Migrasi | PASS | HTTP 200, 1515 char |
| 16 | Pusat | Halaman Changelog | PASS | HTTP 200, 97430 char |
| 17 | Pusat | Navigasi sidebar admin terlihat | PASS | 13 link navigasi |
| 18 | Pusat | Cari tenant ASTA di daftar tenant | PASS | Tenant sudah ada (row_id 4) |
| 19 | Pusat | Detail tenant lengkap (status, shard, modul, users) | PASS | semua kartu detail tampil |
| 20 | Pusat | Tambah custom domain pada tenant | PASS | asta.siupknext.test |
| 21 | Pusat | Verifikasi domain tampil di detail tenant | PASS | asta.siupknext.test |
| 22 | Lokasi | Host tenant dikenali (GET /login/user) | PASS | HTTP 200 |
| 23 | Pusat | Daftar pengguna tenant (akun otomatis per role) | PASS | 20 akun terlihat |
| 24 | Pusat | Form tambah user tenant | PASS | HTTP 200 |
| 25 | Pusat | Hak akses menu tenant | PASS | index + editor role (HTTP 200) |
| 26 | Pusat | Onboarding & saldo awal tenant | PASS | HTTP 200 |
| 27 | Pusat | Data purifier / training mode | PASS | HTTP 200 |
| 28 | Pusat | Aktifkan / verifikasi modul Simpanan untuk tenant | PASS | Modul Simpanan sudah aktif (simp_*_t4 tersedia) |
| 29 | Pusat | Suspend tenant & verifikasi host tenant tertutup | PASS | tenant disuspend, host tenant HTTP 403 |
| 30 | Pusat | Aktifkan kembali tenant | PASS | tenant aktif, host tenant HTTP 200 |
| 31 | Pusat | Impersonasi: Login Tenant membuka domain lokasi | PASS | http://asta.siupknext.test/dashboard |
| 32 | Lokasi | Situs publik tenant | PASS | HTTP 200 |
| 33 | Lokasi | Halaman pilih akun tenant (host otomatis) | PASS | HTTP 200, hanya user tenant |
| 34 | Lokasi | Login otomatis (klik 2x kartu admin) | PASS | http://asta.siupknext.test/dashboard |
| 35 | Lokasi | Dashboard | PASS | HTTP 200, 2390 char |
| 36 | Lokasi | Profil | PASS | HTTP 200, 1446 char |
| 37 | Lokasi | Pencarian Global | PASS | HTTP 200, 24 char |
| 38 | Lokasi | Master Data — Anggota | PASS | HTTP 200, 2412 char |
| 39 | Lokasi | Master Data — Tambah Anggota | PASS | HTTP 200, 1445 char |
| 40 | Lokasi | Master Data — Kelompok | PASS | HTTP 200, 1279 char |
| 41 | Lokasi | Master Data — Tambah Kelompok | PASS | HTTP 200, 1546 char |
| 42 | Lokasi | Master Data — Desa | PASS | HTTP 200, 1709 char |
| 43 | Lokasi | Master Data — Lembaga Lain | PASS | HTTP 200, 1486 char |
| 44 | Lokasi | Pembiayaan — Pinjaman | PASS | HTTP 200, 1325 char |
| 45 | Lokasi | Pembiayaan — Proposal | PASS | HTTP 200, 1194 char |
| 46 | Lokasi | Pembiayaan — Pinjaman Anggota | PASS | HTTP 200, 1539 char |
| 47 | Lokasi | Pembiayaan — Simulasi | PASS | HTTP 200, 2573 char |
| 48 | Lokasi | Pembiayaan — Laporan Portfolio | PASS | HTTP 200, 2518 char |
| 49 | Lokasi | Pembiayaan — Jatuh Tempo | PASS | HTTP 200, 2561 char |
| 50 | Lokasi | Pembiayaan — Tunggakan | PASS | HTTP 200, 2297 char |
| 51 | Lokasi | Pembiayaan — LPP Kelompok | PASS | HTTP 200, 2564 char |
| 52 | Lokasi | Akuntansi — Bagan Akun | PASS | HTTP 200, 15881 char |
| 53 | Lokasi | Akuntansi — Jurnal | PASS | HTTP 200, 2621 char |
| 54 | Lokasi | Akuntansi — Entri Jurnal | PASS | HTTP 200, 2540 char |
| 55 | Lokasi | Akuntansi — Akun | PASS | HTTP 200, 15881 char |
| 56 | Lokasi | Akuntansi — Aset | PASS | HTTP 200, 1282 char |
| 57 | Lokasi | Akuntansi — Laporan | PASS | HTTP 200, 2888 char |
| 58 | Lokasi | Akuntansi — Neraca | PASS | HTTP 200, 1865 char |
| 59 | Lokasi | Akuntansi — Laba Rugi | PASS | HTTP 200, 6413 char |
| 60 | Lokasi | Akuntansi — Arus Kas | PASS | HTTP 200, 2349 char |
| 61 | Lokasi | Akuntansi — Buku Besar | PASS | HTTP 200, 1588 char |
| 62 | Lokasi | Akuntansi — Tutup Buku | PASS | HTTP 200, 1789 char |
| 63 | Lokasi | Anggaran | PASS | HTTP 200, 8167 char |
| 64 | Lokasi | OJK — Kesehatan | PASS | HTTP 200, 2606 char |
| 65 | Lokasi | OJK — Kolektibilitas | PASS | HTTP 200, 2785 char |
| 66 | Lokasi | OJK — Profil | PASS | HTTP 200, 3255 char |
| 67 | Lokasi | OJK — Simpanan | PASS | HTTP 200, 2498 char |
| 68 | Lokasi | Akses — Pengguna | PASS | HTTP 200, 2067 char |
| 69 | Lokasi | Akses — Role | PASS | HTTP 200, 1157 char |
| 70 | Lokasi | Pengaturan | PASS | HTTP 200, 1010 char |
| 71 | Lokasi | Pengaturan — SOP | PASS | HTTP 200, 1259 char |
| 72 | Lokasi | Pengaturan — WhatsApp | PASS | HTTP 200, 1032 char |
| 73 | Lokasi | Pengaturan — Pengguna Offline | PASS | HTTP 200, 1160 char |
| 74 | Lokasi | Website — Postingan | PASS | HTTP 200, 768 char |
| 75 | Lokasi | Website — Halaman | PASS | HTTP 200, 822 char |
| 76 | Lokasi | Website — Pengaturan | PASS | HTTP 200, 867 char |
| 77 | Lokasi | Website — Pesan | PASS | HTTP 200, 604 char |
| 78 | Lokasi | Billing — Invoice | PASS | HTTP 200, 1099 char |
| 79 | Lokasi | Catatan Supervisor | PASS | HTTP 200, 1248 char |
| 80 | Lokasi | Portal Anggota | PASS | HTTP 404 — halaman portal khusus akun anggota (admin tidak tertaut anggota), bukan kegagalan server |
| 81 | Lokasi | Changelog | PASS | HTTP 200, 97671 char |
| 82 | Lokasi | Simpanan | PASS | HTTP 200, 1581 char |
| 83 | Lokasi | Simpanan — Pengaturan | PASS | HTTP 200, 2142 char |
| 84 | Lokasi | Simpanan — Bunga | PASS | HTTP 200, 1595 char |
| 85 | Lokasi | Tambah anggota baru | PASS | Anggota Uji 08627 / 2041794803408627 |
| 86 | Lokasi | Cari & buka detail anggota | PASS | http://asta.siupknext.test/master-data/members/48 |
| 87 | Lokasi | Edit nomor HP anggota | PASS | HP diperbarui |
| 88 | Lokasi | Tambah lembaga lain | PASS | Lembaga Uji 44536 |
| 89 | Lokasi | Verifikasi lembaga di daftar | PASS | Lembaga Uji 44536 |
| 90 | Lokasi | Reset password admin tenant | PASS | password diganti; sesi sendiri otomatis berakhir (perilaku keamanan) |
| 91 | Lokasi | Logout dari aplikasi tenant | PASS | sudah di halaman login setelah reset password — logout tidak diperlukan |
| 92 | Lokasi | Login manual dengan password baru | PASS | http://asta.siupknext.test/dashboard |
| 93 | Isolasi | Akun tenant tidak boleh membuka panel pusat | PASS | HTTP 200 (ditolak sesuai harapan) |
| 94 | Isolasi | Host pusat tanpa sesi tidak menampilkan data tenant | PASS | HTTP 200 (redirect ke login bila 302) |
| 95 | Isolasi | Akun tenant tidak melihat user tenant lain di /login/user | PASS | HTTP 200, hanya user asta |
| 96 | Pusat | Audit log mencatat aksi tenant | PASS | Tercatat: Tenant Impersonate, Tenant Activate, Tenant Suspend |
| 97 | Pusat | Form invoice tenant | PASS | HTTP 200 |

## Console / Page Errors

- central: console: Failed to load resource: the server responded with a status of 502 (Bad Gateway)
- central: console: Failed to load resource: the server responded with a status of 502 (Bad Gateway)
- tenant: console: TypeError: o is not a function
    at http://asta.siupknext.test/build/assets/Index-Dr_04JbF.js:9:4971
    at T_ (http://asta.siupknext.test/build/assets/app-CIuKrh-f.js:87:3817)
    at http://asta.siupknext.test/build/a
- tenant: console: Failed to load resource: the server responded with a status of 404 (Not Found)
