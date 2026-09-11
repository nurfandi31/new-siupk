# Runbook cutover 1 tenant (Phase 5 rehearsal)

Rehearsal per tenant: **legacy suffix** → Next tenant code.  
Pilot referensi: `suffix=1` → `tenant=local` (Phase 3–4 hijau).

## Prinsip (PROJECT_OVERVIEW §12.2)

1. Tenant maintenance / read-only di legacy (produksi).  
2. Backup terverifikasi.  
3. Migrate + recon.  
4. Smoke UI + laporan.  
5. Switch placement (jika belum).  
6. Legacy read-only.  

Rehearsal dev **tanpa** maintenance/backup live — tetap jalankan chain Artisan yang sama.

## Prasyarat

| Item | Cek |
|---|---|
| `LEGACY_DB_*` di `.env` (password tidak di git) | `legacy:discover-accounting --suffix=N` |
| Platform tenant + placement + shard | `tenants.code`, `tenant_placements` |
| Shard schema migrasi | `tenancy:migrate-shards` |
| COA postable di tenant | `tenancy:import-legacy-chart-of-accounts {tenant}` |
| Docker (Laragon) | host DB = `mysql` → **wajib** `docker exec new_siupk-app-1 …` |

Prefix perintah di bawah:  
`docker exec new_siupk-app-1 php artisan`

## Urutan load (wajib)

```text
fiscal periods
  → COA (jika belum)
  → accounting (transaksi + saldo0)
  → villages (legacy:sync-villages — termasuk desa custom)
  → membership (anggota + kelompok; pipeline juga auto-sync villages)
  → lending (pinjaman_kelompok + pinjaman_anggota + rencana + real)
  → apply payment → installment progress
  → reconcile lending (§65 + exceptions)
  → sequences (pipeline biasanya sudah bump)
```

Desa: `kelompok.desa` / `anggota.desa` = `desa.kd_desa` (bisa custom, bukan hanya BPS API).
Command: `legacy:sync-villages {tenant} {suffix}` — seed `organization_units` + backfill FK.

**Idempotent:** re-run skip baris yang sudah di `legacy_record_mappings`.

## Command orchestrator

```bash
# Dry-run full chain (validasi saja di tiap step migrate)
docker exec new_siupk-app-1 php artisan legacy:cutover-tenant local 1 --dry-run

# Full load rehearsal
docker exec new_siupk-app-1 php artisan legacy:cutover-tenant local 1 \
  --from-year=2018 --to-year=2026 --chunk=500 --no-fail-fast

# Skip step yang sudah hijau
docker exec new_siupk-app-1 php artisan legacy:cutover-tenant local 1 \
  --skip-fiscal --skip-coa --skip-accounting
```

## Manual step-by-step (jika orchestrator tidak dipakai)

Ganti `TENANT` / `SUFFIX`.

```bash
# 0. Discover (read-only)
php artisan legacy:discover-accounting --suffix=SUFFIX
php artisan legacy:discover-membership --suffix=SUFFIX

# 1. Fiscal
php artisan legacy:ensure-fiscal-periods TENANT --from=2018 --to=2026

# 2. COA (sekali per tenant)
php artisan tenancy:import-legacy-chart-of-accounts TENANT

# 3. Accounting
php artisan legacy:migrate-accounting TENANT SUFFIX --dry-run --chunk=500
php artisan legacy:migrate-accounting TENANT SUFFIX --chunk=500 --no-fail-fast

# 4. Membership
php artisan legacy:migrate-membership TENANT SUFFIX --dry-run --chunk=500
php artisan legacy:migrate-membership TENANT SUFFIX --chunk=500 --no-fail-fast

# 5. Lending
php artisan legacy:migrate-lending TENANT SUFFIX --dry-run --chunk=500
php artisan legacy:migrate-lending TENANT SUFFIX --chunk=500 --no-fail-fast

# 6. Progress angsuran (jika lending sudah pernah load tanpa apply)
php artisan legacy:apply-loan-payment-progress TENANT

# 7. Recon pinjaman
php artisan legacy:reconcile-lending TENANT SUFFIX

# 8. Sequences (opsional; pipeline biasanya sudah)
php artisan tenancy:initialize-sequences TENANT
```

## Acceptance checklist (per tenant)

### Counts

- [ ] `anggota_N` active ≈ `members`  
- [ ] `kelompok_N` ≈ `groups`  
- [ ] `pinjaman_kelompok_N` = `loans` where `legacy_source=group_loan`  
- [ ] `transaksi_N` migratable ≈ `journal_entries` source legacy (± exception disetujui)  

### Accounting

- [ ] Openings bulan0 match (±0.01)  
- [ ] Neraca / Laba Rugi / Neraca Saldo seimbang vs legacy spot  
- [ ] Buku Besar 3 footer totals  

### Lending

- [ ] `legacy:reconcile-lending` → `group_loans` **matched**  
- [ ] `loan_balance` matched **atau** partial + exception `pending_approval` ditinjau  
- [ ] Spot 1 pinjaman aktif: sisa pokok = last `saldo_pokok` legacy  
- [ ] UI detail pinjaman menampilkan **legacy `id`**, bukan hanya `row_id`  

### Ops / data kotor (bukan silent fix)

- [ ] Exception missing `nia` (beneficiary)  
- [ ] Exception orphan `rencana`  
- [ ] Pengurus kosong → isi via form “Simpan Pengurus” (sekali, irrevocable)  
- [ ] Gap jurnal (jika ada) dicatat  

### Smoke UI

- [ ] `/master-data/members`, `/master-data/groups`  
- [ ] `/lending/loans?tab=aktif`  
- [ ] `/accounting/reports/*`  
- [ ] Dashboard KPI load  

## Known gaps pilot `local` / suffix `1` (signed disposition)

| Item | Status | Disposition |
|---|---|---|
| Journals | **matched** 19075 | gap 2 rows loaded on re-cutover |
| Payments | **matched** 7542 | zero-placeholder skip; negative reversals loaded |
| loan_balance §65 | **matched** 921/921 | outstanding = `disbursed − Σ realisasi` (ignore dirty last saldo) |
| Beneficiaries 182 nia orphan | **approve_skip** | `nia` not in `anggota_1` (id range below min anggota) — no invent people |
| Installments 24 orphan | **approve_skip** | `loan_id` not in `pinjaman_kelompok_1` — deleted parent |
| Committee 0/921 | ops | free-text legacy; UI “Simpan Pengurus” one-shot |
| `group_members` tipis | known | only resolvable officers at membership load |

## Out of scope runbook ini

- Multi-suffix production batch (Phase 6)  
- UI admin migrasi  
- Hapus / putus legacy DB  
- Holding server terpisah  

## Referensi

- `PROJECT_OVERVIEW.md` §11 Phase 5–6, §12 cutover, §14 DoD  
- `DATABASE_STRUCTURE.md` §59–65  
- `VALIDATION.md`  
