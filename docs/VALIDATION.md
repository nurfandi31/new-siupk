# Validation Notes

## Pemeriksaan statis

- Seluruh file PHP harus lolos `php -l`.
## Pemeriksaan statis

- Seluruh file PHP harus lolos `php -l`.
- `composer.json` dan `package.json` harus valid.
- Foreign key tenant komposit tidak menggunakan `ON DELETE SET NULL` pada `tenant_id`.
- Nama constraint/index MySQL harus maksimal 64 karakter.
- Migration platform dan shard tetap berada pada path terpisah.
- Backup scheduler tidak diduplikasi di Laravel atau Docker.

## Verifikasi development

```bash
cp .env.example .env
docker compose config
docker compose up --build -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan about
docker compose exec app php artisan migrate --database=platform --path=database/migrations/platform
docker compose exec app php artisan tenancy:migrate-shards --shard=local --force
docker compose exec app php artisan tenancy:sync-registry --shard=local
docker compose exec app php artisan test
docker compose exec node npm run build
```

Daftarkan shard `local` pada platform sebelum menjalankan command shard. Jalankan migration dan integration test hanya pada database disposable atau hasil restore terpisah.

## Verifikasi wajib sebelum produksi

## Mobile offline sync

- `GET /api/v1/mobile/sync/collection` mengirim `loans`, `installments`, `members`, dan `accounts` yang di-scope per petugas desa. Parameter `?since=ISO8601` bersifat opsional; respons menyediakan `generated_at`/`synced_at` untuk delta berikutnya.
- `POST /api/v1/mobile/sync/push` menerima format mutasi yang sama dengan desktop push. Whitelist tabel mobile hanya `loan_payments` (`insert`) dan `loans` (`update` terbatas untuk pipeline verifikasi).
- Pembayaran diteruskan ke `LoanService::recordInstallmentPayment()` agar aturan bisnis, jurnal, dan notifikasi tetap konsisten. `mutation_uuid` memakai mekanisme idempotensi push yang ada, dan konflik status pinjaman dicatat ke `sync_conflicts`.
- Saat mode offline aktif, endpoint `api/v1/mobile/sync/*` di-whitelist `BlockOfflineMutations`, sedangkan endpoint mutasi mobile lainnya tetap ditolak.

- Import data tenant nyata pada lingkungan rehearsal.
- Rekonsiliasi jumlah record, legacy ID, debit, kredit, saldo akun, dan saldo pinjaman.
- Jalankan perbandingan laporan lama dan baru.
- Uji tenant isolation, queue context, cache namespace, dan restore satu tenant dari shared shard.
- Ganti `ConfigShardCredentialProvider` dengan secret manager/credential store terenkripsi.
- Gunakan image production immutable, reverse proxy TLS, worker queue terpisah, serta kredensial unik.

Jangan menjalankan pipeline migrasi langsung terhadap satu-satunya salinan produksi.


---

## Suite Pengujian Otomatis (100% Passed)

### 1. Backend PHPUnit Suite (258 Tests - 1.779 Assertions)
Menguji logika transaksi akuntansi double-entry (termasuk reverse + recreate atomik via `JournalEditService`), lifecycle pinjaman, isolasi multi-tenant sharding, perpanjangan otomatis Tripay billing, scope operator desa (VillageScope), dan konsolidasi laporan supervisi kabupaten/provinsi:
`ash
php artisan test
`

### 2. Playwright E2E Page & Route Suite (47 Tests)
Pengujian kelayakan pemuatan halaman (*smoke test*) untuk 47 rute/halaman aplikasi (Admin platform, Tenant operational suite, Master Data, Lending, Accounting, Budgeting, Settings, Profile, dan 40+ laporan legacy PDF/web):
`ash
npx playwright test tests/e2e/all_features.spec.ts
`

### 3. Playwright E2E Interactive CRUD Suite (25 Tests)
Pengujian otomatis interaktif pada browser nyata (Playwright Chromium) yang mensimulasikan interaksi pengguna secara penuh:
- Membuka halaman login dan submit kredensial valid.
- Mengisi form anggota, memilih dropdown SmartSelect, toggle switch, dan submit data.
- Mengisi form kelompok dan lembaga.
- Menjalankan proposal pinjaman baru.
- Membuka modal aksi, memilih opsi tab, dan melakukan pencarian real-time.
- Memverifikasi error handling, validasi form, dan *redirect after submit*.

`ash
# Mode Headless (Background)
npx playwright test tests/e2e/all_interactive_crud.spec.ts

# Mode Headed (Membuka browser nyata di layar secara otomatis)
npx playwright test tests/e2e/all_interactive_crud.spec.ts --headed --slow-mo=200
`

### 4. Pengujian Supervisi Provinsi & Kabupaten
`ash
npx playwright test tests/e2e/province.spec.ts
`
