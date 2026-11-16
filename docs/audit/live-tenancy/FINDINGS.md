# Temuan & Perbaikan — Live Audit Pusat → Lokasi (ASTA)

Tanggal: 2026-11-16
Lingkungan: `http://siupknext.test` (Pusat) dan `http://asta.siupknext.test` (Lokasi, tenant ASTA, row_id 4)
Metode: Playwright **headed** di Chrome, data nyata tenant, ekspor PDF/Excel diunduh dan diperiksa magic bytes-nya.

## Hasil akhir

| Suite | Test | Langkah PASS | FAIL |
|---|---|---|---|
| `tests/e2e/tenant_full_flow.spec.ts` (01–06) | 6 | 140 | 0 |
| `tests/e2e/tenancy_live.spec.ts` (01–12) | 12 | 97 | 0 |

Laporan rinci:
- `docs/audit/live-tenancy/report-crud-reports.md` — CRUD anggota, pinjaman/angsuran, laporan, PDF/Excel.
- `docs/audit/live-tenancy/report.md` — perjalanan Pusat → Lokasi, isolasi, impersonasi, audit trail.
- `docs/audit/live-tenancy/exports/` — berkas PDF/Excel hasil uji.

## Bug aplikasi yang ditemukan & diperbaiki

1. **Tab Aksi blank / TypeError** — `resources/js/Pages/Lending/MemberLoans/Show.vue`
   `verifyForm.errors.verification_notes` diakses saat `verifyForm` bukan `useForm`, memicu TypeError dan merender halaman kosong. Diperbaiki dengan optional chaining `verifyForm.errors?.verification_notes`.

2. **Dropdown pinjaman & akun kas tidak muncul** — `resources/js/Pages/Accounting/JournalEntries/InstallmentIndividual.vue`
   `<SmartSelect>` dipakai di template tetapi tidak pernah di-import, sehingga ter-render sebagai elemen HTML mentah `<smartselect>`. Ditambahkan `import SmartSelect`.

3. **Akun kas form angsuran kosong** — `app/Domain/Accounting/Services/JournalEntryOptionResolver.php`
   `getOptionsFor('angsuran')` selalu mengembalikan array kosong karena `angsuran` punya `LABELS`/`RULES` tetapi tidak ada di `TYPES`. Guard diubah agar tipe yang punya label tetap boleh di-resolve.

4. **Angsuran gagal posting karena baris header #0** — `app/Http/Controllers/Accounting/JournalEntryController.php` dan `InstallmentIndividual.vue`
   Jadwal warisan menyimpan baris `installment_number = 0` sebagai header tanggal. Baris ini tampil sebagai opsi bayar dan bisa terpilih karena `Number('') === 0`, lalu ditolak validasi `min:1`. Baris #0 tidak lagi dimasukkan ke opsi angsuran, dan pemilihan angsuran dijaga saat nomor masih kosong.

5. **Pinjaman tidak bisa dicari berdasarkan nama anggota** — `app/Http/Controllers/Accounting/JournalEntryController.php`
   Opsi pinjaman hanya punya `subject_label`, sedangkan SmartSelect mencari `subtitle`/`description`. Ditambahkan `subtitle` agar nama peminjam tampil dan bisa dicari.

6. **Laporan Jatuh Tempo & Tunggakan error 500** — `DueTodayReportService.php`, `OverdueReportService.php`
   `orderBy('borrower_name')` padahal kolom hasil join bernama `p.full_name`/`g.name`. Diganti `orderByRaw('COALESCE(p.full_name, g.name)')`.

7. **OJK Pinjaman Aktif error 500** — `app/Domain/Regulatory/Services/ActiveLoansService.php`
   Query memilih `l.tenor` yang tidak ada di tabel `loans` (kolomnya `term_months`). Kolom `l.tenor` dihapus dari `selectRaw`.

8. **PDF Near Settlement error 500** — `app/Domain/Lending/Services/Reports/NearSettlementReportService.php`
   Query memilih `p.address`, padahal tabel `people` tidak punya kolom alamat. Ditambahkan join ke `member_addresses` (alamat primer) dan memilih `ma.address`.

9. **Tambah Lembaga Lain error 500** — `app/Http/Requests/MasterData/OtherInstitutionRequest.php`
   Validasi `Rule::exists('organization_units', ...)` dan `Rule::unique('organization_units', ...)` memakai koneksi default/platform, sedangkan tabelnya ada di DB tenant. Diganti memakai model `OrganizationUnit` (koneksi tenant).

10. **Impersonasi Pusat → Lokasi gagal 419** — `resources/views/app.blade.php`
    Tombol “Login Tenant” mengirim `X-CSRF-TOKEN` dari `meta[name="csrf-token"]`, tetapi meta tersebut tidak ada di layout. Ditambahkan `<meta name="csrf-token" content="{{ csrf_token() }}">`. Setelah itu impersonasi membuka dashboard tenant dan banner “Mode Impersonasi Superadmin” tampil.

## Perbaikan pada pengujian (bukan bug aplikasi)

- Lookup NIK anggota bersifat async dan mengosongkan ulang form saat respons “tidak ditemukan” tiba; test kini menunggu respons lookup sebelum mengisi field lain.
- Setelah reset password, login manual dapat mendarat di *intended URL* (`/access/users`), bukan `/dashboard`; assertion disesuaikan.
- Audit log menampilkan label manusiawi (`Tenant Impersonate`), bukan nama aksi mentah (`tenant.impersonate`); assertion disesuaikan.
- Ekspor Buku Besar membutuhkan parameter `account`; matriks ekspor mengirim `account=478` (Kas Tunai).
- Tanggal transaksi angsuran tidak boleh di masa depan. Agar angsuran pertama jatuh tempo pada hari uji, tanggal verifikasi/penetapan/cair dibuat satu bulan ke belakang.

## Catatan / keterbatasan

- **Aset Tetap & Aset Tidak Berwujud tidak punya halaman UI**; hanya tersedia ekspor `/accounting/reports/assets/fixed/{pdf,excel}` dan `.../intangible/{pdf,excel}`. Kedua ekspor sudah diuji dan PASS. Ini dicatat sebagai INFO, bukan FAIL.
- Baris jadwal `#0` adalah warisan pacuan (header tanggal), bukan angsuran riil. Perilaku ini dipertahankan; hanya tidak lagi ditampilkan sebagai opsi pembayaran.
- Tenant ASTA tetap aktif dan data uji (anggota, pinjaman, angsuran, lembaga) tertinggal di database tenant sebagai bukti pengujian.
- Perubahan frontend memerlukan `npm run build`; aset `public/build` sudah di-rebuild saat pengujian.
