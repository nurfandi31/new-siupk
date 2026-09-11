# RBAC matrix — siupk tenant user

**Rules:** superadmin = all · zero roles = full access (legacy) · has role(s) = union packs.

Platform `/admin/*` = **superadmin only** (bukan role tenant).

## A. Nav tenant → permission

| Section | Label | Path | Permission |
|---|---|---|---|
| — | Dashboard | `/dashboard` | *(auth)* |
| Master | Data Desa | `/master-data/villages` | `villages.view` |
| Master | Daftar / Tambah Anggota | `/master-data/members…` | `members.view` / `members.manage` |
| Master | Daftar / Tambah Kelompok | `/master-data/groups…` | `groups.view` / `groups.manage` |
| Master | Daftar / Tambah Lembaga | `/master-data/institutions…` | `institutions.view` / `institutions.manage` |
| siupk | Register Proposal | `/lending/loans/create` | `loans.propose` |
| siupk | Tahapan Perguliran | `/lending/loans` | `loans.view` |
| Transaksi | Daftar Jurnal | `/accounting/journals` | `journals.view` |
| Transaksi | Daftar Inventaris | `/accounting/assets` | `assets.view` |
| Transaksi | Jurnal Umum | `/accounting/journal-entries/create` | `journals.create` |
| Transaksi | Jurnal Angsuran | `/accounting/journal-entries/installment` | `installments.record` |
| Keuangan | Bagan Akun | `/accounting/chart-of-accounts` | `journals.view` |
| Periodik | E-Budgeting | `/budgeting` | `budgeting.view` |
| Periodik | Tutup Buku | `/accounting/period-close` | `period_close.view` |
| Periodik | Taksiran Pajak | `/accounting/tax-estimate` | `tax.view` |
| Periodik | Notifikasi Tagihan | `/notifications/billing` | `messages.send` |
| Pelaporan | Accounting reports + PDF | `/accounting/reports…` | `reports.view` |
| Pelaporan | Portofolio / Rencana vs Realisasi | `/lending/reports…` | `loans.view` |
| Tagihan | Daftar Tagihan SaaS | `/billing/invoices` | `billing.view` |
| Pengaturan | Pengaturan | `/settings` | `settings.manage` |
| Website | Berita | `/website/posts` | `website.view` / `website.manage` |
| Website | Halaman | `/website/pages` | `website.view` / `website.manage` |
| Website | Pengaturan Situs | `/website/settings` | `website.view` / `website.manage` |
| Website | Pesan Masuk | `/website/messages` | `website.view` / `website.manage` |
| Widget | Ariel | session + tools | `assistant.use` |
| Header | Profil | `/profile` | *(self)* |
| Header | Search | `/search` | any `*.view` held |
| Platform | Panel Admin | `/admin` | superadmin |
| Platform | WhatsApp | `/admin/whatsapp` | superadmin |

## B. Tombol aksi → permission

| Modul | Aksi UI | Permission |
|---|---|---|
| Anggota | Tambah/Edit/Import/Export/Hapus | `members.manage` (list = `members.view`) |
| Kelompok | Tambah/Edit/Import/Export/Hapus/quick member | `groups.manage` |
| Lembaga | CRUD/Import/Export | `institutions.manage` |
| Desa | Edit | `villages.manage` |
| Pinjaman | Proposal | `loans.propose` |
| Pinjaman | Verify | `loans.verify` |
| Pinjaman | Approve / alokasi | `loans.approve` |
| Pinjaman | Disburse | `loans.disburse` |
| Pinjaman | Edit/reschedule/write-off/revert/committee/hapus beneficiary | `loans.manage` |
| Jurnal | Post / reverse | `journals.create` |
| Angsuran | Catat | `installments.record` |
| Inventaris | Edit/Hapus | `assets.manage` (lihat = `assets.view`) |
| Tutup buku | Tutup/buka bulan/tahun/alokasi | `period_close.manage` |
| Budget | Simpan/copy/approve/reopen | `budgeting.manage` |
| Laporan | Lihat/PDF | `reports.view` |
| CALK | Simpan catatan | `reports.manage` |
| Notifikasi WA | Kirim | `messages.send` |
| Tagihan tenant | Bayar | `billing.pay` |
| Settings | Semua simpan | `settings.manage` |
| Website | Tambah/Edit/Hapus/pulihkan berita & halaman, hapus cover | `website.manage` (lihat = `website.view`) |
| Website | Simpan pengaturan situs (hero, kontak, sosmed, footer), tandai baca/hapus pesan masuk | `website.manage` (lihat = `website.view`) |
| Assistant tools | per `tool_map` | lihat config |

## C. Role packs (default)

| Role | Isi |
|---|---|
| `admin` | `*` |
| `kasir` | view master+loan+jurnal+assets+reports; `journals.create`; `installments.record`; `messages.send`; `billing.view`+`pay`; `assistant.use` |
| `verifikator` | view + `loans.verify` + `assistant.use` |
| `viewer` | view-only (+ tax, reports, budgeting.view, billing.view, assistant.use) |

## D. Admin platform (bukan role tenant)

Dashboard · Tenant CRUD · Users/role · Repair · Plans · Invoices/void/pay · Regional API · WhatsApp platform → **superadmin**.

## E. Enforcement status

| Layer | Status |
|---|---|
| FormRequest `request_map` | write kritis (loan/jurnal/member/group/budget save/settings/website save) |
| Controller `denyUnless` | journals/assets/period/reports/loan reports/assistant session/website CRUD |
| Nav hide by permission | via `auth.permissions` + `nav_map` |
| GET index members/groups/loans | still mostly auth-only (legacy); nav hides entry |
| Institutions/villages/billing GET | catalog + nav; controller wire incremental |

Update packs / keys: `config/permissions.php`.


## F. Peran Pengawasan Wilayah & Operator Desa (System Roles)

| Peran System | Flag User | Prefix Path | Permission Key | Deskripsi & Scope |
|---|---|---|---|---|
| **Superadmin Platform** | is_superadmin = true | /admin/* | * | Manajemen SaaS platform, tenant sharding, subscriptions, billing, pengguna. |
| **Supervisor Provinsi** | is_province_user = true | /province/* | province.view_reports | Pengawasan keuangan konsolidasi tingkat provinsi (lintas kabupaten & kecamatan). |
| **Supervisor Kabupaten** | is_regency_user = true | /regency/* | 
egency.view_reports | Pengawasan keuangan konsolidasi tingkat kabupaten (lintas kecamatan). |
| **Operator Desa Scoped** | is_village_user = true | Tenant paths | illage_user.access, members.*, groups.*, loans.propose | Pengoperasian tingkat desa, dibatasi secara otomatis oleh global scope VillageScope pada illage_row_id. |
