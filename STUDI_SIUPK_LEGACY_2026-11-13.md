# STUDI SIUPK LEGACY — 13 November 2026

**Target studi:** `C:\laragon\www\siupk`
**Aplikasi pembanding:** `C:\laragon\www\siupknext` (SIUPK Next)
**Sifat studi:** Read-only — tidak ada file legacy yang diubah dan aplikasi legacy tidak dijalankan.
**Metode:** pembacaan kode, pemetaan model/controller/route/view, penelusuran alur bisnis, dan verifikasi kelemahan otorisasi pada kode sumber.

> Dokumen ini melengkapi `AUDIT_2026-11-13.md`. Fokusnya bukan menilai kualitas kode legacy, tetapi **memahami cara kerja, data, dan aturan bisnisnya** agar migrasi ke SIUPK Next tidak kehilangan perilaku yang dipakai pengguna.

---

## 1. Ringkasan

SIUPK legacy adalah aplikasi **Laravel 10 monolit** untuk koperasi simpan pinjam tingkat kecamatan. Satu instalasi melayani banyak kecamatan dengan pola **tabel per kecamatan** (`rekening_{id}`, `saldo_{id}`, `pinjaman_kelompok_{id}`, dst.), bukan multi-tenant berbasis kolom seperti SIUPK Next.

| Metrik | Nilai |
|---|---:|
| File PHP di `app/` | 146 |
| LOC PHP di `app/` | 26.847 |
| Model | 73 |
| Controller | 38 |
| Blade view | 407 |
| Migration | 11 |
| Route declaration | 270 |
| Model dengan tabel dinamis per kecamatan | 19 |
| Referensi `Session::get('lokasi')` di `app/` | 384 |
| Guard autentikasi | 3 (`web`, `master`, `kab`) |
| Config file | 28 |

Karakter utama:

- **Kecamatan adalah unit tenant.** Host (`web_kec` / `web_alternatif`) menentukan `Kecamatan`, lalu `Session::get('lokasi')` menentukan suffix tabel.
- **Otorisasi berbasis menu.** Tidak ada permission per aksi; `hak_akses` hanya menyembunyikan menu, sedangkan route hanya dilindungi `auth`.
- **Password disimpan plaintext** dan dibandingkan dengan `===`.
- **Banyak route pengubah data tanpa autentikasi**, termasuk generator pinjaman dan endpoint unduhan file.
- **Pelaporan digerakkan database** (`jenis_laporan`, `sub_laporan`, `tanda_tangan_laporan`), bukan service hardcoded seperti SIUPK Next.

---

## 2. Stack & Dependensi

| Komponen | Versi / Catatan |
|---|---|
| PHP | `^8.1` |
| Laravel | `^10.10` |
| Sanctum | `^3.2` |
| Yajra DataTables | `10.0` |
| DomPDF | `^2.0` |
| Maatwebsite Excel | `^3.1` |
| Barcode | `milon/barcode ^10`, `picqer/php-barcode-generator ^2.3` |
| Relasi komposit | `awobaz/compoships ^2.2` |
| Rich text | `tinymce/tinymce ^6.7` |
| Debug | `itsgoingd/clockwork`, `spatie/laravel-ignition` |
| Queue | `sync` |
| Session | `file` |
| DB | MySQL `siupk_upk` (host internal pada `.env` legacy) |

`composer.json` juga memuat helper global di `autoload.files`: `Keuangan.php`, `Pinjaman.php`, `Inventaris.php`, `Tanggal.php`. Helper inilah yang menjadi “otak” perhitungan bunga, angsuran, dan tanggal di legacy — banyak logika bisnis tidak berada di service, melainkan di helper dan controller.

Tidak ada package permission/ACL (Spatie Permission dan sejenisnya tidak dipakai). Tidak ada test suite yang terlihat di struktur proyek.

---

## 3. Arsitektur & Struktur

```
app/
├── Console/
├── Exceptions/
├── Helpers/                 # helper global (Keuangan, Pinjaman, Tanggal, Inventaris)
├── Http/
│   ├── Controllers/         # 38 controller
│   ├── Middleware/          # 13 middleware
│   └── Kernel.php           # 3 guard + alias basic/master/kab/holding.license
├── Models/                  # 73 model
├── Providers/
└── Utils/                   # Keuangan, Pinjaman, Inventaris, Tanggal
resources/views/             # 407 blade
routes/web.php               # 270 deklarasi route
```

Pola yang dominan:

- **Controller gemuk.** Validasi, query, perhitungan, dan render view sering berada dalam satu method.
- **Query builder + Eloquent campur.** Banyak laporan memakai `DB::table(...)` dengan nama tabel yang dirakit dari `Session::get('lokasi')`.
- **View-centric.** DataTables, modal, dan form sebagian besar dirender server-side; JavaScript mengandalkan endpoint JSON ad-hoc.
- **Tidak ada domain layer.** Berbeda dengan Next yang memisahkan `app/Domain/*`, legacy menaruh aturan bisnis di controller/helper/model.

---

## 4. Model Tenancy: Tabel per Kecamatan

### 4.1 Alur penentuan tenant

1. Request masuk dengan `Host` (mis. `kecamatan-a.siupk.test`).
2. `AuthController@index/login` mencari `Kecamatan` berdasarkan `web_kec` atau `web_alternatif`.
3. `$lokasi = $kec->id` disimpan di session.
4. Model dengan tabel dinamis membentuk nama tabel: `'rekening_' . Session::get('lokasi')`.
5. `HoldingLicense` (API holding) melakukan hal yang sama dari header `X-Holding-Tenant`, lalu `config(['tenant.suffix' => '_'.$kec->id])` dan `Session::put('lokasi', $kec->id)`.

### 4.2 Model dengan tabel dinamis (19)

`Anggota`, `Anggotas`, `Ebudgeting`, `Inventaris`, `Kelompok`, `PinjamanAnggota`, `PinjamanIndividu`, `PinjamanKelompok`, `RealAngsuran`, `RealAngsuranI`, `RealSimpanan`, `Rekening`, `RekeningOjk`, `RencanaAngsuran`, `RencanaAngsuranI`, `Saldo`, `Simpanan`, `SimpananAnggota`, `Transaksi`.

Contoh konstruktor:

```php
public function __construct()
{
    $this->table = 'rekening_' . Session::get('lokasi');
}
```

Pola nama tabel:

| Entitas | Tabel |
|---|---|
| Buku besar / COA per kecamatan | `rekening_{id}` |
| Saldo akun | `saldo_{id}` |
| Jurnal/transaksi | `transaksi_{id}` |
| Simpanan anggota | `simpanan_anggota_{id}` |
| Pinjaman kelompok | `pinjaman_kelompok_{id}` |
| Pinjaman individu | `pinjaman_individu_{id}` |
| Pinjaman anggota | `pinjaman_anggota_{id}` |
| Rencana angsuran | `rencana_angsuran_{id}`, `rencana_angsuran_i_{id}` |
| Realisasi angsuran | `real_angsuran_{id}`, `real_angsuran_i_{id}` |
| Saham | `saham_{id}` |

### 4.3 Konsekuensi desain

**Kelebihan**

- Isolasi data antar kecamatan sangat kuat di level database: query yang lupa memfilter tenant tetap hanya menyentuh tabel kecamatan yang aktif.
- Backup/restore per kecamatan relatif mudah.
- Tidak ada risiko “kolom tenant salah” seperti pada model ber-scope.

**Kelemahan**

- **Session adalah sumber kebenaran tenant.** Jika `lokasi` kosong, model membentuk tabel `rekening_` dan query gagal; jika session diubah, tabel berpindah.
- **Tidak ada registry tenant terpusat** di database aplikasi; daftar tenant ada di tabel `kecamatan`.
- **Ledakan tabel.** Setiap kecamatan menambah puluhan tabel; migrasi harus dijalankan untuk semua suffix.
- **Sulit di-scale horizontal** dan sulit membuat laporan konsolidasi lintas kecamatan.
- **Bug lintas kecamatan mungkin terjadi** pada endpoint yang menerima `{kec}` eksplisit (lihat §8).

---

## 5. Autentikasi & Otorisasi

### 5.1 Tiga guard

| Guard | Provider | Model | Penggunaan |
|---|---|---|---|
| `web` | `users` | `App\Models\User` | user koperasi/kecamatan |
| `master` | `admins` | `App\Models\AdminUser` | admin platform/master |
| `kab` | `kabupaten` | `App\Models\Kabupaten` | supervisor kabupaten |

Middleware `isAuth` hanya memeriksa:

```php
if (Auth::check() || auth()->guard('master')->check() || auth()->guard('kab')->check()) {
    return $next($request);
}
```

Tidak ada pemeriksaan tenant, role, atau permission di middleware ini. Middleware `master`/`kab` membatasi area, tetapi tidak membatasi aksi di dalam area tersebut.

### 5.2 Login plaintext dan bypass `force`

`AuthController@login`:

```php
$password = $request->password;
if ($password == 'force') {
    $username = 'superadmin';
    $password = 'superadmin';
}
...
$user = User::where([['uname', $username], ['lokasi', $lokasi]])->first();
if ($user) {
    if ($password === $user->pass) {
        Auth::loginUsingId($user->id);
```

Temuan:

- Password dibandingkan **plaintext** (`$user->pass`), bukan `Hash::check`.
- Siapa pun yang mengirim password `force` dipaksa login sebagai `superadmin`/`superadmin`.
- `superadmin` bahkan dipindahkan `lokasi`-nya ke kecamatan request sebelum login.
- Tidak ada rate limit, lockout, atau audit log login.

### 5.3 `/uname`: dump user + password + one-click login

Route `GET /uname` **tanpa middleware**:

```php
$users = User::where('lokasi', $kec->id)->with('l', 'j')->get();
session(['login' => true]);
return view('auth.uname', compact('users', ...));
```

View `auth/uname.blade.php` merender:

```blade
<div class="user-card" data-uname="{{ $user->uname }}" data-pass="{{ $user->pass }}" ondblclick="quickLogin(this)">
```

Artinya:

- Nama, username, jabatan, dan **password plaintext** seluruh user kecamatan dapat dibaca tanpa login.
- Double-click pada kartu mengirim form ke `/login` dan langsung masuk sebagai user tersebut.
- `session(['login' => true])` adalah flag tambahan yang tidak menggantikan autentikasi, tetapi menunjukkan bahwa route ini memang dirancang sebagai “developer quick login” dan lupa dimatikan.

### 5.4 Otorisasi berbasis menu

- `hak_akses` pada tabel `users` berisi daftar ID menu yang **disembunyikan**.
- `AuthController@login` membangun menu dengan `whereNotIn('id', $hak_akses)`.
- Tidak ada pengecekan `hak_akses` di controller/route. Jika URL diketahui, user tetap dapat mengakses aksi yang tidak ada di menunya.
- Tombol/menu adalah satu-satunya kontrol UI; tidak ada otorisasi server-side per aksi.

---

## 6. Modul & Pemetaan Route

### 6.1 Dashboard

- `/dashboard` — ringkasan simpanan, pinjaman, tunggakan.
- `/dashboard/pinjaman`, `/dashboard/pinjamanI`, `/dashboard/pemanfaat`.
- `/dashboard/jatuh_tempo`, `/dashboard/nunggak`, `/dashboard/tagihan` (POST, DataTables).
- `/piutang_jasa`, `/unpaid`, `/simpan_saldo`.

### 6.2 Master data

- `/database/penduduk` — anggota/penduduk (`AnggotaController`), termasuk `cari_nik`, `blokir`, `edits`.
- `/database/kelompok` — kelompok (`KelompokController`), termasuk `register`, `generateKode`, `detailKelompok`.
- `/database/desa` — desa.
- Supplier, agent, unit usaha, jenis usaha/kegiatan, tingkat kelompok, jabatan, pendidikan.

### 6.3 Simpanan

- `/simpanan` (resource), `/form_simp/{nia}`, `/simpanan/get-transaksi`, `/simpanan/simpan-transaksi`.
- `/simpanan/generate`, `/simpanan/generate-bunga`, `/simpanan/generate-bunga2/{offset?}`.
- `/bunga_simpanan`, `/simpanan/simpan_bunga`.
- Jenis simpanan: pokok, wajib, sukarela, saham; parameter di `jenis_simpanan` (saldo minimal, setoran, penarikan).
- Bunga memakai tiga metode: `1 = saldo terakhir`, `2 = saldo terendah`, `3 = saldo rata-rata` (dipakai di generator bunga dan laporan).

### 6.4 Pinjaman

Tiga jalur:

| Jalur | Controller | Tabel utama | Ciri |
|---|---|---|---|
| Kelompok | `PinjamanKelompokController` | `pinjaman_kelompok_{id}` | proposal kelompok, pemanfaat anggota, kartu angsuran kelompok |
| Individu | `PinjamanIndividuController` | `pinjaman_individu_{id}` | jaminan, penjamin, anggunan |
| Anggota | `PinjamanAnggotaController` | `pinjaman_anggota_{id}` | pinjaman anggota dalam kelompok |

Route penting:

- Proposal: `/register_proposal`, `/register_proposal/{id_kel}`, `/register_proposal_i`, `/register_proposal_i/{nia}`.
- Pipeline: `/perguliran/proposal`, `/verified`, `/waiting`, `/aktif`, `/lunas`.
- Aksi: `simpan`, `rescedule`, `hapus`, `kembaliProposal`, `tidakLayak`, `lunas/{perguliran}`, `generate/{id_pinj}`.
- Dokumen: `kartu_angsuran`, `kartu_angsuran_anggota`, `cetakPadaKartu`, SPK.
- Pemanfaat: `data_pemanfaat`, alokasi per anggota.
- Penghapusan: `Penghapusan`, `write-off` (piutang dihapus).

Alur status yang terbaca dari route dan model:

```
proposal/draft → verified → waiting/approved → aktif (cair) → lunas
                                             ↘ macet/tidak layak
                                             ↘ dihapus (write-off)
```

### 6.5 Transaksi & angsuran

- `/transaksi` — jurnal transaksi.
- `/transaksi/generate_real/{id_pinkel}`, `/regenerate_real/{id_pinkel}`, `/regenerate_real_i/{id_pinj_i}` — membuat realisasi angsuran dari rencana.
- `/pindah-buku` — posting manual antar akun.
- `RealAngsuran`/`RealAngsuranI` menyimpan realisasi; `RencanaAngsuran`/`RencanaAngsuranI` menyimpan jadwal.

### 6.6 Akuntansi

- COA tiga level: `akun_level_1`, `akun_level_2`, `akun_level_3`; saldo per akun di `saldo_{id}`; mutasi di `transaksi_{id}`.
- `RekeningOjk` memetakan akun COA ke kode OJK.
- `ArusKas`, `Calk`, `Catatanbp` untuk laporan arus kas dan CALK.
- `Pindah buku` memindahkan saldo antar akun tanpa jurnal double-entry formal; model `Transaksi` mencatat debit/kredit per baris.

### 6.7 Pelaporan

- `/pelaporan` — daftar laporan.
- `/pelaporan/sub_laporan/{file}`, `/pelaporan/preview`, `/pelaporan/preview/{lokasi?}`.
- Konfigurasi laporan ada di tabel: `jenis_laporan`, `sub_laporan`, `tanda_tangan_laporan`, `jenis_laporan_pinjaman`.
- Laporan dapat memakai `tgl_kondisi` dari session (`Session::get('tgl_kondisi_laporan')`) untuk “laporan per tanggal”.
- OJK: `RekeningOjk`, `Calk`, laporan POJK 19/41 (migrasi `split_laporan_ojk_pojk19_41`).
- Tanda tangan laporan disimpan di `tanda_tangan_laporan` dan ditempel ke PDF.

### 6.8 SOP & pengaturan

- `/pengaturan/sop`, `/pengaturan/coa`, `/pengaturan/ttd_pelaporan`, `/pengaturan/ttd_spk`, `/pengaturan/ttd_spk_i`.
- Update per kecamatan: `lembaga/{kec}`, `pengelola/{kec}`, `pinjaman/{kec}`, `kolek/{kec}`, `simpanan/{kec}`, `asuransi/{kec}`, `spk/{kec}`, `logo/{kec}`, `calk/{kec}`, `pesan_whatsapp/{kec}`.
- WhatsApp: `init`, `status/{kec}`, `send_bulk`, `webhook`, `delete_session`.
- Invoice/langganan: `/pengaturan/invoice`, `/pengaturan/{inv}/invoice`.

### 6.9 API & integrasi

- Holding API dilindungi `holding.license` dan header `X-Holding-Token` + `X-Holding-Tenant`.
- `License` menyimpan `api_secret`, `is_active`, dan masa berlaku; middleware **fail-closed** (401 jika token/slug kosong atau lisensi tidak valid/kedaluwarsa).
- Namun `api_secret` disimpan plaintext dan `Session::put('lokasi')` di middleware berarti konteks tenant bergantung pada session server.

### 6.10 Tool generator (legacy)

- `/generate`, `/generate/individu`, `/generate/kelompok`, `/generate/save/{offset?}`.
- `/sinkronisasi/kelompok/{id}`, `/sinkronisasi/individu/{id}`.
- Ini adalah tool operasional untuk membuat data pinjaman/angsuran massal; sebagian **tanpa middleware**.

---

## 7. Alur Bisnis Rinci

### 7.1 Simpanan

1. Anggota didaftarkan (`Anggota`), punya `nia`/`cif`.
2. Jenis simpanan menentukan setoran, penarikan, saldo minimal, dan metode bunga.
3. Setoran/penarikan dicatat di `simpanan_anggota_{id}` dan `transaksi_{id}`.
4. Bunga dihitung periodik:
   - **1 — saldo terakhir:** bunga = saldo akhir periode × rate.
   - **2 — saldo terendah:** bunga = saldo terendah dalam periode × rate.
   - **3 — saldo rata-rata:** bunga = rata-rata saldo harian × rate.
5. Saldo hasil posting masuk ke `saldo_{id}` akun simpanan.
6. Cetak buku simpanan/anggota.

### 7.2 Pinjaman kelompok

1. Pendaftaran proposal kelompok + daftar pemanfaat.
2. Verifikasi (catatan verifikasi), lalu waiting list/alokasi.
3. Pencairan: membuat `real_angsuran` dan jadwal `rencana_angsuran`.
4. Angsuran dibayar per anggota/pemanfaat; `real_angsuran` mencatat tanggal, pokok, jasa, denda.
5. Pelunasan atau status macet; write-off jika dihapus.
6. Dokumen: SPK, kartu angsuran, keterangan lunas.

### 7.3 Pinjaman individu

1. Proposal individu + jaminan/penjamin.
2. Verifikasi dan approve.
3. Pencairan + jadwal angsuran (`rencana_angsuran_i`, `real_angsuran_i`).
4. Angsuran, pelunasan, write-off.
5. Dokumen: SPK individu, kartu angsuran, form jaminan.

### 7.4 Akuntansi

1. COA level 1–3; tiap akun punya `kode_akun`.
2. Transaksi mencatat debit/kredit per akun; saldo di-update di `saldo_{id}`.
3. Laporan: neraca, laba rugi, arus kas, perubahan ekuitas, CALK, trial balance, buku besar.
4. `pindah buku` untuk koreksi antar akun.
5. OJK: mapping akun ke kode laporan; CALK memakai catatan per tahun/bulan.

### 7.5 Pelaporan

- Laporan tidak di-hardcode; baris laporan berasal dari `jenis_laporan`/`sub_laporan`.
- Parameter laporan (tanggal kondisi, kelompok, desa, jenis pinjaman) dikirim ke `PelaporanController@preview`.
- Tanda tangan pejabat diambil dari `tanda_tangan_laporan`.
- PDF dihasilkan DomPDF; Excel via Maatwebsite.

---

## 8. Kelemahan Keamanan Legacy (Terverifikasi di Kode)

| # | Temuan | Lokasi | Dampak |
|---|---|---|---|
| L-1 | Password plaintext | `AuthController@login` (`$password === $user->pass`) | Seluruh kredensial bocor bila DB/session/backup diakses |
| L-2 | Bypass `force` | `AuthController@login` baris 72–74 | Siapa pun login sebagai `superadmin` |
| L-3 | `/uname` tanpa auth | `routes/web.php:365-380`, `auth/uname.blade.php` | Dump user + password + one-click login |
| L-4 | `/download/{file}` tanpa auth | `routes/web.php:382-384` | Path traversal baca file di `storage/app/public/docs/..` |
| L-5 | Generator tanpa auth | `routes/web.php:394-400`, `433-438` | Membuat/mengubah pinjaman & bunga massal tanpa login |
| L-6 | `POST /simpanan/simpan-transaksi` tanpa auth | `routes/web.php:431` | Setoran/penarikan tanpa login |
| L-7 | `POST /update-catatan-verifikasi/{id}` tanpa auth | `routes/web.php:195`, `PinjamanAnggotaController` | Mengubah catatan verifikasi pinjaman mana pun |
| L-8 | `POST /anggota/edits/{nia}` tanpa auth | `routes/web.php:197` | Endpoint mati (`dd()`), tetapi tetap terekspos |
| L-9 | Update setting lintas kecamatan | `PUT /pengaturan/{...}/{kec}` | User login kecamatan A dapat mengubah setting kecamatan B dengan mengganti `{kec}` |
| L-10 | Otorisasi hanya menu | `hak_akses`, `Menu` | URL langsung melewati pembatasan menu |
| L-11 | `api_secret` lisensi plaintext | `License` | Bocor bila DB diakses; tidak bisa di-rotate tanpa update manual |
| L-12 | `APP_DEBUG=true` di `.env` | `.env` legacy | Stack trace, query, path bocor bila terbawa ke server |
| L-13 | Tidak ada rate limit login | `AuthController` | Brute force; diperparah password plaintext |
| L-14 | Tidak ada audit log | seluruh aplikasi | Perubahan finansial tidak dapat dilacak |
| L-15 | Session `lokasi` menentukan tabel | 19 model | Session rusak/berubah → query ke tabel salah; sulit diaudit |

Catatan: L-4 perlu diuji di server legacy untuk memastikan normalisasi path; secara kode, `storage_path('app/public/docs/' . $file)` menerima `../` tanpa validasi. L-5/L-6/L-7 dapat dipastikan dari route tanpa middleware.

---

## 9. Desain Database yang Perlu Dipahami Saat Migrasi

### 9.1 Entitas inti

| Entitas | Tabel/Model | Kunci | Catatan |
|---|---|---|---|
| Kecamatan/tenant | `kecamatan` | `id` | `web_kec`, `web_alternatif`, logo, setting |
| User | `users` | `id` | `uname`, `pass` plaintext, `lokasi`, `hak_akses` |
| Admin platform | `admin_users` | `id` | guard `master` |
| Kabupaten | `kabupaten` | `id` | guard `kab` |
| Anggota | `anggotas`/`anggota` | `nia`/`nik` | relasi desa, kelompok |
| Kelompok | `kelompok` | `id` | relasi desa, tingkat, fungsi |
| Pinjaman kelompok | `pinjaman_kelompok_{id}` | `id_pinkel` | pemanfaat, jadwal, realisasi |
| Pinjaman individu | `pinjaman_individu_{id}` | `id_pinj_i` | jaminan/penjamin |
| Pinjaman anggota | `pinjaman_anggota_{id}` | `nia`/`id` | pinjaman dalam kelompok |
| Simpanan | `simpanan_anggota_{id}` | `cif`/`nia` | jenis simpanan, saldo |
| COA | `akun_level_1/2/3` | `kode_akun` | mapping OJK |
| Saldo | `saldo_{id}` | `kode_akun` | saldo per akun |
| Transaksi | `transaksi_{id}` | `id` | debit/kredit, tanggal, referensi |
| Rencana angsuran | `rencana_angsuran_{id}`, `_i` | `id_pinkel`/`id_pinj_i` | jadwal |
| Realisasi angsuran | `real_angsuran_{id}`, `_i` | `id` | pembayaran aktual |
| Laporan | `jenis_laporan`, `sub_laporan` | `id` | konfigurasi laporan |
| Tanda tangan | `tanda_tangan_laporan` | `id` | pejabat penanda tangan |
| Lisensi/holding | `license` | `id` | `api_secret`, expiry |

### 9.2 Relasi komposit

`awobaz/compoships` dipakai karena banyak relasi memakai lebih dari satu kolom (mis. `nia` + `lokasi`, atau `id_pinkel` + `lokasi`). Saat migrasi ke Next, relasi ini harus dipetakan ke foreign key eksplisit di shard, bukan mengandalkan suffix tabel.

### 9.3 Perbedaan fundamental dengan Next

| Aspek | Legacy | Next |
|---|---|---|
| Tenant | Tabel per kecamatan, session `lokasi` | Shard + kolom `tenant_id`, `TenantContext` |
| COA | `akun_level_1/2/3`, `rekening_{id}` | `accounts` hierarkis |
| Jurnal | `transaksi_{id}` + update `saldo_{id}` | `journal_entries` + `journal_lines` (double-entry) |
| Pinjaman | Tiga tabel terpisah per jenis | `loans` + `loan_borrowers`/`loan_beneficiaries` |
| Simpanan | `simpanan_anggota_{id}` + `real_simpanan` | Modul Savings baru (belum terhubung jurnal) |
| Laporan | DB-driven `jenis_laporan` | Service hardcoded per laporan |
| Otorisasi | Menu (`hak_akses`) | Role + permission (`config/permissions.php`) |
| Password | Plaintext | Hash bcrypt |

---

## 10. Matriks Paritas Legacy → Next

| Modul legacy | Status di Next | Catatan migrasi |
|---|---|---|
| Login & user | **Ada, lebih aman** | Next memakai hash, Sanctum, quick login terkontrol; legacy plaintext harus diimpor dengan reset password |
| Master anggota/penduduk | **Ada** | Pastikan NIK, KK, alamat, desa, status blokir ikut |
| Kelompok & pemanfaat | **Ada** | Cek komposisi pemanfaat dan jabatan kelompok |
| Desa/wilayah | **Ada** | Mapping kode wilayah |
| Simpanan pokok/wajib/sukarela | **UI ada, integrasi jurnal belum** | Migrasi saldo awal per anggota; hubungkan jurnal |
| Bunga simpanan 3 metode | **Ada (service)** | Pastikan parameter rate & metode per produk terbawa |
| Pinjaman kelompok | **Ada** | Termasuk pemanfaat, jadwal, realisasi |
| Pinjaman individu | **Ada** | Termasuk jaminan/penjamin |
| Pinjaman anggota | **Sebagian** | Cek `legacy_source=member_loan` dan alur status |
| Angsuran & kartu | **Ada** | Next belum menulis `loan_payments` dari web (gap audit) |
| Write-off | **Ada** | Pastikan jurnal allowance vs piutang |
| COA & jurnal | **Ada** | Legacy `saldo/transaksi` harus dikonversi ke double-entry |
| Pindah buku | **Ada (jurnal)** | Validasi periode & akun |
| Laporan keuangan | **Ada** | 9 export Excel masih mati (audit Next) |
| OJK & CALK | **Ada, sebagian error** | CALK save 500; DRPY `$legalName` 500 |
| Laporan DB-driven | **Tidak sama** | Next hardcoded; daftar laporan legacy perlu dicek satu per satu |
| SOP & tanda tangan | **Ada** | Template tanda tangan & logo per tenant |
| WhatsApp | **Ada** | Gateway baru; template legacy perlu migrasi |
| Invoice/langganan | **Ada (billing)** | Mapping paket & license |
| Holding API | **Ada, fail-open** | Legacy fail-closed; Next harus diperbaiki |
| Generator pinjaman/bunga | **Tidak diperlukan** | Tool operasional legacy; jangan dimigrasikan |
| Form pengawas | **Ada (supervisor)** | Cek field & alur catatan |
| Mobile/desktop | **Baru di Next** | Tidak ada padanan legacy |

---

## 11. Checklist Migrasi Data

### 11.1 Sebelum migrasi

- [ ] Inventarisasi semua kecamatan aktif + tabel suffix-nya.
- [ ] Bekukan penulisan (maintenance mode) atau catat delta transaksi.
- [ ] Backup penuh per kecamatan + uji restore.
- [ ] Normalisasi password: paksa reset via OTP/email saat login pertama.
- [ ] Bersihkan user `force`/developer dan password plaintext.
- [ ] Matikan `/uname`, `/download`, `/generate*`, dan endpoint tanpa auth.
- [ ] Petakan `kode_akun` legacy → `accounts.code` Next.
- [ ] Petakan `jenis_simpanan` → `savings_products`.
- [ ] Petakan `jenis_laporan`/`sub_laporan` → service/laporan Next.

### 11.2 Urutan impor yang disarankan

1. Tenant & organization profile (dari `kecamatan`, `lembaga`).
2. COA (`akun_level_1/2/3` → `accounts`).
3. Desa/organization units.
4. Anggota + alamat + identitas.
5. Kelompok + anggota kelompok + jabatan.
6. Produk simpanan & pinjaman.
7. Saldo awal simpanan per anggota.
8. Saldo awal COA (opening balance).
9. Pinjaman aktif + pemanfaat + jadwal.
10. Realisasi angsuran historis (`loan_payments`).
11. Jurnal historis (opsional; untuk audit trail).
12. User, role, dan mapping anggota–user.
13. Setting, template WhatsApp, tanda tangan, logo.

### 11.3 Rekonsiliasi wajib

- Total saldo simpanan per anggota = saldo awal + setoran − penarikan + bunga.
- Total pinjaman aktif = pokok dicairkan − pokok dibayar − write-off.
- Neraca: aset = kewajiban + ekuitas.
- Jumlah anggota/kelompok per desa.
- Daftar tunggakan per tanggal kondisi.
- Total kolektibilitas per bucket.

---

## 12. Pelajaran untuk SIUPK Next

1. **Jangan meniru table-per-kecamatan.** Next sudah benar memakai shard + `tenant_id`; pertahankan.
2. **Jangan meniru otorisasi berbasis menu.** Next sudah punya permission; lengkapi route yang belum memakainya.
3. **Legacy fail-closed pada Holding API.** Next justru fail-open — regresi yang harus diperbaiki.
4. **Legacy menyimpan password plaintext.** Migrasi harus memaksa reset, bukan mengimpor hash palsu.
5. **Legacy punya laporan DB-driven.** Next perlu memastikan daftar laporan legacy benar-benar tercakup, bukan hanya laporan yang sudah di-hardcode.
6. **Legacy menulis realisasi angsuran.** Next belum menulis `loan_payments` dari web — ini gap integritas paling berbahaya.
7. **Legacy menghitung bunga simpanan 3 metode.** Next sudah mengimplementasikan; pastikan test dan parameter produk terbawa.
8. **Legacy menyimpan tanda tangan & logo per kecamatan.** Next sudah punya template; migrasikan asetnya.
9. **Legacy punya tool generator.** Jangan migrasikan; ganti dengan seeding/import yang tervalidasi.
10. **Legacy tidak punya audit log.** Next punya `AuditLogger`; pastikan semua aksi finansial memakainya.

---

## 13. Kesimpulan

Legacy SIUPK adalah aplikasi yang **fungsional dan kaya aturan bisnis**, tetapi dibangun dengan asumsi keamanan jaringan internal: password plaintext, login bypass, route tanpa auth, dan otorisasi hanya di menu. Kekuatan utamanya ada pada **kelengkapan alur simpan pinjam, laporan DB-driven, dan perhitungan bunga/angsuran** — bukan pada arsitektur.

SIUPK Next sudah mengambil arah yang benar: tenancy berbasis shard, double-entry journal, permission, dan pemisahan domain. Risiko migrasi terbesar bukan pada fitur yang belum ada, melainkan pada **detail perilaku legacy yang tidak terlihat di UI**: metode bunga, urutan status pinjaman, komposisi pemanfaat, format laporan, dan makna tanggal kondisi. Detail-detail inilah yang harus diuji dengan data legacy nyata sebelum cutover.
