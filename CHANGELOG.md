# Changelog

Semua perubahan penting pada proyek **siupk Next** didokumentasikan dalam berkas ini.
Format penulisan mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/).

## [Unreleased] — Paritas Dokumen PDF Pinjaman (1:1 dengan Pacuan)

### Added
- **4 dokumen PDF khusus individu** (paritas `PinjamanIndividuController` pacuan):
  - **Analisa & Keputusan Kredit** (`analisis_keputusan_kredit`) — form analisa kelayakan kredit individu lengkap dengan: data pokok peminjam (NIK, TTL, JK, alamat, penjamin), permohonan pinjaman (plafon, suku jasa, angsuran), perhitungan kredit (flat rate, jangka, plafon maksimum), rekomendasi pemberian kredit (jenis, plafon, suku, jangka, angsuran). Stage `individual_verification`.
  - **Surat Pemberitahuan Kredit (SP2K)** (`surat_pemberitahuan`) — surat persetujuan perjanjian kredit dengan rincian fasilitas (plafon, jangka, jenis, suku jasa, cara penarikan/pembayaran/pengikat, syarat lainnya). Stage `individual_disbursement`.
  - **Surat Pengikat Diri Penjamin** (`pengikat_diri_penjamin`) — pernyataan penjamin (biasanya pasangan) untuk mengikat diri membayar tagihan jika debitur wanprestasi. Stage `individual_disbursement`.
  - **Surat Pernyataan Suami/Istri** (`surat_pernyataan_suami`) — persetujuan pasangan atas pinjaman yang diajukan. Stage `individual_disbursement`.
- **Migration** `2026_09_22_000002_add_officer_identity_to_organization_profiles.php` — tambah kolom identitas pejabat struktural: `manager_nik`, `manager_position`, `manager_address`, `secretary_nik`, `treasurer_nik`, `kepala_desa_name`, `kepala_desa_nip`, `court_of_jurisdiction`, `institution_level_1`/`2`/`3`.
- **Token baru di `LoanDocumentService::tokenReplacer` & `SpkTokenResolver`:**
  - `{kepala_lembaga_nik}`, `{kepala_lembaga_jabatan}`, `{kepala_lembaga_alamat}` — NIK/jabatan/alamat direktur Lembaga.
  - `{sekretaris_lembaga_nik}`, `{bendahara_lembaga_nik}` — NIK sekretaris & bendahara.
  - `{kades}`, `{nip_kades}` — nama & NIP Kepala Desa (override dari `village.leader_name`).
  - `{pengadilan_negeri}` — yurisdiksi hukum untuk klausul arbitrase SPK.
  - `{sebutan_level_1}`, `{sebutan_level_2}`, `{sebutan_level_3}` — sebutan struktural Lembaga (paritas pacuan `$kec->sebutan_level_*`).
  - `{penjamin_nama}`, `{penjamin_nik}`, `{suami_nama}`, `{suami_nik}` — data penjamin/suami untuk dokumen individu.
  - `{peminjam_nama}`, `{peminjam_nik}`, `{peminjam_hp}`, `{peminjam_alamat}` — alias untuk konsistensi dokumen individu.

### Changed
- **Paritas placeholder `{{ '' }}` → token binding** di 9 blade alias kelompok/individu:
  - `spk.blade.php` — PIHAK PERTAMA (nama/NIK/alamat direktur), PIHAK KEDUA detail, Pasal 2 ayat 3 (angsuran pokok + jasa + terbilang), Pengadilan Negeri, tanda tangan Pihak Pertama.
  - `kuitansi_pencairan.blade.php` — Telah Diterima Dari, Uang Sebanyak (terbilang), Dikeluarkan Oleh + Diterima Oleh (3 kolom: direktur/bendahara/ketua), nama direktur & bendahara.
  - `kuitansi_anggota.blade.php` — Telah Diterima Dari, Uang Sebanyak (terbilang per anggota), alamat lengkap + desa per kuitansi.
  - `berita_acara_pencairan.blade.php` — field 1 (sebutan desa), 4 (tgl berdiri), 7 (jenis usaha), 8 (jenis kegiatan), 9 (tingkat kelompok), 10 (fungsi kelompok), 12 (phone kelompok), 14 (no HP ketua), 15 (sistem angsuran), HP per anggota di tabel rincian, direktur di tanda tangan.
  - `kartu_angsuran_anggota.blade.php` — telpon kelompok, suffix "bulan", tanggal angsuran default, catatan lembaga, nama pemanfaat di tanda tangan.
  - `daftar_pemanfaat.blade.php` — sistem angsuran, kolom JK, kolom Usia (calculated dari birth_date).
  - `pernyataan_tanggung_renteng.blade.php` — kolom JK per anggota, hapus placeholder kosong, kepala desa + NIP di blok tanda tangan.
  - `peserta_asuransi.blade.php` — field Desa, Jangka, Sistem Bagi Hasil, TTL per anggota (birth_place + birth_date_label), direktur Lembaga.
- **`LoanDocumentService::payload()` — expose field tambahan**:
  - Block `group`: tambah `phone`, `established_at`, `established_label`, `business_type`, `activity_type`, `level`, `function` (resolved via DB untuk handle global scope TenantScope).
  - Block `beneficiaries`: tambah `phone`, `address`, `village`, `gender`, `birth_date`, `birth_date_label`, `birth_place`, `guarantor_phone`, `guarantor_nik`.
  - Top-level: tambah `profile` (OrganizationProfile) — dipakai oleh blade individu yang sebelumnya gagal render karena undefined variable.
- **`LoanDocumentController::document()`** — tambah validasi stage vs `legacy_source`: pinjaman perorangan tidak dapat membuka dokumen kelompok stage `proposal`/`verification`/`disbursement`, dan sebaliknya. Response 422 dengan label jelas.

### Fixed
- **Duplikat token `{kades}`/`{nip_kades}` di `SpkTokenResolver`** — yang menang dulunya dari `village->leader_name`, sekarang konsisten dari profile `kepala_desa_name` (lebih reliable, override via array_merge).
- **Parse error ternary tanpa else-branch** di `LoanDocumentService::tokenReplacer` (`$individualGuarantor = $isIndividual ? ... : null`).

### Test Coverage (Penambahan)
- 5 test baru di `LoanDocumentTest` khusus 4 dokumen individu (registry, payload, token, endpoint PDF, reject cross-source): PASS.
- 4 test paritas alias blade:
  - `test_alias_documents_have_no_empty_placeholders` — render 8 blade utama dan assert tidak ada `{{ '' }}` tersisa.
  - `test_new_officer_tokens_resolve_in_token_replacer` — verifikasi token baru direktur/kades/pengadilan_negeri.
  - `test_beneficiary_block_exposes_extended_profile_fields` — verifikasi phone, gender, birth_date, birth_place di payload beneficiaries.
  - `test_group_block_exposes_classification_fields` — verifikasi business_type, activity_type, level, function, established_at, established_label.
- 2 test paritas konten HTML (visual rendering assertion per-blade, paritas 1:1 dengan pacuan):
  - `test_individual_documents_html_content_parity_with_pacuan` — render 4 blade individu baru dan cek string kunci (section I-IV, BUDI SANTOSO, SITI AMINAH, ucapkan `Delapan Juta Rupiah`, 8 poin fasilitas kredit, dst).
  - `test_alias_documents_html_content_parity_with_pacuan` — render 9 blade alias dan cek string kunci (PIHAK PERTAMA, "Telah Diterima Dari", "Uang Sebanyak", "Dikeluarkan Oleh", pertanian/simpan pinjam/madya/produktif, "Koperasi Maju Bersama", "15 Mei 1985", dst).
- **Total Lending test suite: 111/112 PASS** (1 fail pre-existing `LoanProposalRegistrationTest::test_index_lists_loans_filtered_by_tabs` — `LoanAlreadyActiveException`, bukan regression).
- **LoanDocumentTest saja: 21/21 PASS (413 assertions)** — semua dokumen 13 (4 baru + 9 alias) terverifikasi render HTML paritas pacuan.

## [Unreleased] — Audit & Pemisahan Laporan Kelompok vs Individu

Mencakup 9 area: dashboard breakdown, permission nav_map, dan filter K/I untuk seluruh laporan Lending (Portfolio, Schedule vs Actual, LPP, Kolektibilitas, CKPN) plus Simulation & UI menu.

### Added
- **Filter scope Kelompok/Individu di semua laporan Lending:**
  - `LoanPortfolioReportService::build(?string $asOf, string $filter, ?string $borrowerScope)` — terima parameter `borrowerScope` (`null`/`'group'`/`'member'`) memfilter `loans.legacy_source`.
  - `LoanScheduleVsActualService::build(int $year, int $month, ?string $borrowerScope)` — filter K/I.
  - `LppReportService::buildDesa(int $year, int $month, ?string $productCode, ?string $borrowerScope)` — filter K/I; tambah join `members.organization_unit_row_id` untuk desa individu.
  - `CollectibilityReportService::buildDesa/buildCadangan(int $year, int $month, ?string $productCode, ?string $borrowerScope)` — filter K/I; tambah join member village.
- **Method baru khusus Individu:**
  - `LppReportService::buildIndividu(int $year, int $month, ?string $productCode)` — LPP rincian per pinjaman individu dengan field `member_name`, `member_number`, `nik`, desa dari `members.organization_unit_row_id`. Selalu filter `legacy_source='member_loan'`.
  - `CollectibilityReportService::buildIndividu(int $year, int $month, ?string $productCode)` — kolektibilitas per anggota individu, hitung kolek 1/2/3 sederhana (lancar/diragukan/macet) per pinjaman.
- **Routes baru (`routes/web.php`):**
  - `GET /lending/reports/lpp-individual` & `/pdf` → `LoanReportController::lppIndividu/...Pdf`.
  - `GET /lending/reports/kolek-individual` & `/pdf` → `LoanReportController::kolekIndividu/...Pdf`.
  - `GET /lending/reports/cadangan-penghapusan-individual` & `/pdf` → `LoanReportController::cadanganPenghapusanIndividu/...Pdf`.
- **Vue pages baru:**
  - `resources/js/Pages/Lending/Reports/LppIndividu.vue` — mirror LppKelompok.vue tapi kolom Peminjam + NIK, tanpa Pmf (perorangan).
  - `resources/js/Pages/Lending/Reports/KolekIndividu.vue` — kolektibilitas per peminjam.
  - `resources/js/Pages/Lending/Reports/CadanganPenghapusanIndividu.vue` — CKPN khusus individu.
  - `resources/js/Pages/Lending/Reports/LppKelompok.vue` (existing) — `filters.scope` di-pass untuk filter K/I.
  - `resources/js/Pages/Lending/Simulation/Index.vue` — tambah radio button **Tipe Peminjam** (Semua/Kelompok/Individu) filter `loan_products.borrower_scope` (member/group/both).
- **PDF template baru:**
  - `resources/views/reports/pdf/lending/lpp_individu.blade.php` — header "DAFTAR PERKEMBANGAN PIUTANG (LPP) RINCIAN INDIVIDU", kolom Peminjam/NIK/Pencairan/Alokasi.
- **Auth menu baru di `AuthenticatedLayout.vue`:**
  - Sub-menu **LPP (Perkembangan Piutang)** → Rekap Desa / Rincian Kelompok / Rincian Individu.
  - Sub-menu **Kolektibilitas** → Rekap Desa / Rincian Individu.
  - Entry langsung: **Cadangan Penghapusan (CKPN)** + **CKPN Pinjaman Individu**.
- **Test coverage baru:**
  - `tests/Feature/Lending/LppReportScopeTest.php` — 2 test (scope filter untuk buildDesa, buildIndividu return hanya member_loan): **PASS (15 assertions)**.
  - `tests/Feature/Lending/LoanPortfolioReportTest.php` — tambah `test_portfolio_scope_member_filter_excludes_group_loans` (validasi via `legacy_source` di rows): **PASS**.
  - `tests/Unit/Access/PermissionConfigTest.php` — tambah `test_installment_individual_route_is_in_nav_map` & `test_dashboard_service_uses_member_loan_enum_not_individual_loan`: **PASS**.

### Changed
- **`LoanReportController::portfolio/scheduleVsActual/lppDesa/lppKelompok/kolekDesa/cadanganPenghapusan`** — terima query param `?scope=all|group|member`, default `all`. Method `borrowerScope()` helper validasi whitelist.
- **`AuthenticatedLayout.vue` menu Pelaporan** — di-restructure untuk nested children (parent LPP & Kolek expand jadi subgroup Desa/Kelompok/Individu).
- **`LppReportService::buildDesa`** — logika fallback desa: individu pakai desa anggota (`member_village_name`), kelompok pakai desa group (`village_name`).
- **`CollectibilityReportService::buildDesa`** — sama, fallback desa by `legacy_source`.

### Fixed
- **🔴 BUG `DashboardService` enum invalid:** query `activeLoanBreakdown()` line 159 & `memberBreakdown()` line 210 pakai literal `'individual_loan'` yang **TIDAK ADA** di enum DB (CHECK constraint hanya izinkan `'member_loan'` & `'group_loan'`). Hasilnya individu count selalu 0 di dashboard. Fix: ganti ke `'member_loan'`. Validasi via `test_dashboard_service_uses_member_loan_enum_not_individual_loan`.
- **🔴 BUG `nav_map` missing installment-individual:** route `/accounting/journal-entries/installment-individual` tidak ada di `config/permissions.php` nav_map, akibatnya user dengan `installments.record` (kasir) tidak melihat menu ini di sidebar. Fix: tambah entry `'/accounting/journal-entries/installment-individual' => 'installments.record'`.
- **`LoanPortfolioReportService::build()`** — tambah `legacy_source` ke rows output agar frontend bisa membedakan kelompok/individu (berguna untuk filter UI & display label).

### Validation Status
- **Unit tests:** 49/49 PASS (152 assertions).
- **Accounting tests:** 68/68 PASS (421 assertions).
- **Lending tests terkait perubahan:** 80/80 PASS (InstalmentIndividual 7 + InstallmentSystems 18 + LoanProposalDeletion 5 + LoanBeneficiaryWriteOff 12 + MemberLoanLifecycle 12 + LoanAuditHistory 2 + LoanDocument 21 + LoanScheduleVsActual 2 + LppReportScope 2 + LoanPortfolioReport 5 + LoanSimulation 8 — total 94 assertions di subset ini).
- Pre-existing failure tidak terkait: `LoanProposalRegistrationTest::test_index_lists_loans_filtered_by_tabs` — gagal juga di git HEAD dengan `LoanAlreadyActiveException`.

## [Unreleased] — Split Menu Jurnal Angsuran (Kelompok vs Individu)

### Added
- **Split menu "Jurnal Angsuran" menjadi 2 entri terpisah** (paritas legacy SIUPK `TransaksiController::jurnalAngsuran` & `jurnalAngsuranIndividu`):
  - **Backend** — `JournalEntryController::installment(string $type = 'group')` filter `loans.legacy_source`:
    - `type='group'` → `legacy_source != 'member_loan'` atau NULL → route `/accounting/journal-entries/installment` (default).
    - `type='individual'` → `legacy_source = 'member_loan'` → route `/accounting/journal-entries/installment-individual`.
  - Method baru `JournalEntryController::individualInstallment()` (shortcut → `installment('individual')`).
  - Helper privat `resolveIndividualSubject($tenantId, $loanId)` — ambil label nama anggota + NIK untuk dropdown "Pinjaman Perorangan".
  - `loanOptions` sekarang punya field tambahan `subject_label` & `is_individual` agar UI tahu subject (kelompok/perorangan).
- **Vue page** `Accounting/JournalEntries/InstallmentIndividual.vue` — mirror `Installment.vue` tapi untuk pinjaman perorangan: tanpa "Detail Kelompok", tanpa "Catatan Per-Anggota" (1 peminjam saja), auto-deskripsi `Angsuran {PRODUCT} ke-{N} Individu a/n {Nama}`.
- **Menu navbar Keuangan → Transaksi** di `AuthenticatedLayout.vue:338` & `Accounting/Journals/Index.vue:204` dipecah jadi 2 entry:
  - "Jurnal Angsuran Kelompok" → `/accounting/journal-entries/installment`
  - "Jurnal Angsuran Individu" → `/accounting/journal-entries/installment-individual`
- **Test backend** `InstallmentIndividualRouteTest` (4 tests, 61 assertions): verifikasi filter `legacy_source` benar antara route kelompok vs individu, label `subject_label` menampilkan nama+NIK anggota, dan `borrower_member_row_id` di-expose agar frontend bisa auto-fill field Penyetor.

### Fixed
- **Bug #1 (kritis) — POST `/installment-individual` 404 Method Not Allowed**:
  - Tambah `Route::post('/journal-entries/installment-individual', ...)` di `routes/web.php:536` (named `journal-entries.installment-individual.store`).
  - Method baru `JournalEntryController::storeIndividualInstallment()` di-refactor dari `storeInstallment()` lewat helper `processInstallmentSubmission(string $redirectRoute)` agar tidak duplikasi logic WA notification, journal entry flash, dan receipt URL.
- **Bug #3 — Submit jurnal individu me-redirect ke halaman kelompok**:
  - `processInstallmentSubmission()` sekarang menerima parameter `redirectRoute`, sehingga submit dari halaman individu akan redirect ke `accounting.journal-entries.installment-individual` (UX benar, state form kembali ke halaman yg benar).
- **Bug #2/#5 — JOIN `loan_borrowers` tanpa deduplication**:
  - Query di `JournalEntryController::installment()` refactor dari `LEFT JOIN + GROUP BY` (raw MySQL ONLY_FULL_GROUP_BY fragile) menjadi **subquery agregat** `leftJoinSub(MIN(group_row_id), MIN(member_row_id) GROUP BY loan_row_id)`. Aman untuk MySQL strict mode + SQLite, dan tidak menggandakan loan row.
- **Bug #4 — `form.reference` kosong di halaman Individu**:
  - Backend tambah `borrower_member_row_id` di setiap `loanOptions[].borrower_member_row_id` agar frontend bisa auto-fill field Penyetor.
  - Frontend `InstallmentIndividual.vue` watch `form.loan_id` → auto-set `form.reference = borrower_member_row_id`. Hidden input `<input type="hidden" :value="form.reference">` ditambahkan agar field ini ke-serialize.
- **Bug #6 — `description` kosong setelah submit sukses**:
  - `InstallmentIndividual.vue::submit()` setelah `form.reset('description')` manual re-call `form.description = autoGenerateDescription()` karena watch `[selectedLoan, autoInstallment]` tidak trigger saat form di-reset tanpa perubahan loan/tanggal.
- **Bug #7 — `loan_installments.principal_paid` tidak ter-update untuk individu**:
  - `processInstallmentSubmission()` setelah `recordInstallmentPayment`:
    1. Auto-construct `member_allocations` dari `borrower_member_row_id` (frontend tidak kirim karena 1 peminjam).
    2. Re-call `recordInstallmentPayment` agar `loan_installment_tracking` ter-insert (parity dengan kelompok).
    3. Update `loan_installments` dengan `principal_paid += principal_amount`, `interest_paid += interest_amount`, `penalty_paid += penalty_amount` agar Sisa Pokok di dropdown akurat.
- **Bug #9 — Cross-route guard tidak ada**:
  - `LoanInstallmentJournalRequest::expectedLoanScope()` deteksi route via `$request->route()->getName()`. `Rule::exists(Loan::class, ...)` di-scope sesuai route: route `/installment-individual` butuh `legacy_source='member_loan'`; route `/installment` butuh `legacy_source != 'member_loan'` (atau NULL). User tak bisa submit loan kelompok ke endpoint individu dan sebaliknya — FormRequest redirect dengan `errors.loan_id`.
- **Build error pre-existing di `MemberLoans/Show.vue:228`** — extra `});` dari indentasi tidak konsisten, build gagal sebelum fix ini.

## [Unreleased] — Paritas Penuh Pinjaman Individu (1:1 dengan Pacuan)

### Added
- **Paritas Penuh Pinjaman Individu (perorangan) — logika 1:1 dengan pacuan `PinjamanIndividuController::generate()`:**
  - `MemberLoanScheduleCalculator` direwrite sebagai salinan persis rumus pacuan line 2317-2607: `tempo_pokok`/`tempo_jasa` (sistem 11/12/14/15/20 + default), `wajib_jasa = alokasi_jasa/tempo_jasa`, `wajib_pokok = pembulatan(alokasi/tempo, mode_kec)`, magic formula `jangka==24` (pembulatan `-500`/`5000`, adjustment ±5000 untuk alokasi 6/8/12/14/18 juta), tanggal `+x*7 days` untuk sistem 12 (mingguan), override `jadwal_angsuran_desa`, `batas_angsuran` kecamatan, end-of-month handling, header row ke-0 + baris angsuran 1..N (paritas `RencanaAngsuranI` pacuan).
  - Kolom `running_principal` & `running_interest` ditambahkan ke `loan_installments` (paritas `target_pokok`/`target_jasa` pacuan) untuk audit kumulatif per baris angsuran.
  - `LoanService::createMemberProposal()` sekarang memakai `MemberLoanScheduleCalculator` sejak create — jadwal angsuran pacuan-style terbentuk bahkan sebelum verifikasi.
  - `LoanService::approve()` jalur individu me-regenerate jadwal via kalkulator pacuan-style (override `generatePrincipalSchedule`/`generateInterestSchedule` generic).
  - `LoanService::reschedule()` jalur individu me-regenerate jadwal pinjaman baru via kalkulator pacuan-style.
  - Mapping string frekuensi Next → id sistem angsuran pacuan (`mapFrequencyToSystemId()`): `'weekly'→12, 'biweekly'→14, 'monthly'→1, 'bimonthly'→2, 'quarterly'→3, 'every_6_months'→6, 'every_12_months'→15, 'every_24_months'→20, dst.`.
- **Akun COA piutang perorangan + CKPN:**
  - `DefaultChartOfAccountsProvisioner` menambah akun `1.1.03.09` (Piutang Perorangan Pokok), `1.1.03.10` (Piutang Jasa Perorangan), `1.1.04.08`/`1.1.04.09` (CKPN Pokok/Jasa Perorangan), dan `4.1.01.07` (Pendapatan Jasa Piutang Perorangan) + `4.1.01.08` (Pendapatan Denda Piutang Perorangan).
- **Produk pinjaman `pi` (Pinjaman Individu):**
  - `TenantLoanProductProvisioner` menambah produk `pi` default dengan `borrower_scope='member'` (peminjam perorangan) — plafon 500rb–50jt, jasa 1.5%/bulan, tenor 12 bulan.
- **Field-field pacuan pada pinjaman individu:**
  - `loans.collateral` (JSON: `type`, `description`, `value`, `reference`, `recorded_at`) — diset saat registrasi proposal (paritas `data_jaminan` pacuan yang disimpan sebagai JSON).
  - `loans.verification_remarks` — catatan verifikasi awal (paritas `catatan_verifikasi` pacuan).
  - `loans.spk_no`, `loans.disbursement_slot`, `loans.funding_source` — sudah ter-handle di method `disburse()`.
- **Validasi form individu:**
  - `MemberLoanRequest` menambah rules untuk `collateral.*` (jenis, deskripsi, nilai, nomor dokumen) dan `verification_remarks`. Method `normalized()` mengembalikan payload siap-pakai untuk service.
  - `LoanDisburseRequest` menambah validasi `verification_remarks` & `funding_source`.
- **Route web individu:** seluruh endpoint `lending/member-loans/*` (verify, approve, disburse, revert, reject, complete, write-off, reschedule, cancel-reschedule, card, settlement-letter, documents) sudah lengkap dan ter-guard `legacy_source='member_loan'`.

### Fixed
- **`MemberLoanCardService` import salah namespace** (`App\Models\Tenant\OrganizationProfile` → `App\Domain\Membership\Models\OrganizationProfile`) — kartu angsuran individu sebelumnya gagal dengan `Class not found`.
- **`createMemberProposal` schema mapping:** cast `loans.collateral` ke `array`, cast `loans.funding_source` ke `integer` agar persistensi JSON/INT konsisten.
- **Pembulatan konsistensi pacuan:** helper `pembulatan()` di `MemberLoanScheduleCalculator` rewrite mengikuti `Keuangan::pembulatan` line 34-75 (auto/keatas/kebawah dengan `$ratusan` substring quirk pacuan).
- **Eligibility checker:** dokumentasi disempurnakan — anggota dengan pinjaman berstatus terminal (`completed`/`written_off`/`rescheduled`/`rejected`) boleh mengajukan proposal baru (sesuai SOP BUMDesma).

### Changed
- **`MemberLoanLifecycleTest`** (14 test, 111 assertions) menggantikan test kosong sebagai paritas pinjaman individu:
  - `test_create_member_proposal_persists_collateral_and_individual_schedule` — collateral JSON + jadwal pacuan-style.
  - `test_member_proposal_schedule_handles_jangka_24_with_magic_formula` — magic formula `jangka==24` (alokasi 8jt, adjustment -5000) menghasilkan total pokok tutup pas 8jt.
  - `test_verify_to_waiting_to_disburse_to_complete_lifecycle_for_individual` — lifecycle P→V→W→A→L paritas pacuan, simpan SPK/disbursement_slot/verification_remarks.
  - `test_reject_member_loan_sets_rejected_status_only_from_pre_active_status` — pacuan `tidakLayak()` (status='T'/'rejected').
  - `test_collateral_helper_normalizes_various_input_shapes` — 5 bentuk input (array lengkap, JSON string, null/string kosong, tipe invalid, semua field kosong).
  - `test_member_loan_card_service_renders_pdf_for_individual` — kartu angsuran individu (Blade `loan_card_member`).
  - `test_member_loan_index_lists_only_member_loans` — Index filter `legacy_source='member_loan'`.
  - `test_member_loan_endpoints_404_for_group_loans` — guard route individu untuk loan kelompok.
  - `test_write_off_for_individual_creates_pacuan_style_journal` — pacuan `hapus()` (status='H'/'written_off') + jurnal allowance↔receivable.
  - `test_reschedule_for_individual_uses_pacuan_schedule_calculator` — pacuan `rescedule()` (status='R'/'rescheduled' + pinjaman baru dengan `tgl_tunggu`/`tgl_cair` lengkap).
  - `test_revert_member_loan_returns_to_draft_status` — pacuan `kembaliProposal()` (status='P'/'draft').
  - `test_record_installment_payment_for_individual_posts_correct_journal` — pacuan `TransaksiController::angsuran` (debit kas, kredit piutang+jasa+denda).
  - `test_individual_schedule_parity_with_single_member_group_loan` — paritas aggregate total pokok+jasa antara pinjaman individu dan kelompok dengan parameter identik (paritas nominal per-bulan masih berbeda karena kelompok Next belum migrasi ke kalkulator pacuan-style — lihat catatan di test).
  - `test_individual_schedule_uses_weekly_dates_for_sistem_12` — sistem angsuran 12 (mingguan): tanggal jatuh tempo `+x*7 days` dari override jadwal desa, paritas pacuan baris 2515-2518.

- **Test infrastructure:** `BuildsTenantTestDatabase` retry `migrate:fresh` hingga 3× dan membersihkan file DB SQLite (-journal/-shm/-wal) untuk cegah "database disk image is malformed" intermiten.

### Known Gap
- **Pinjaman kelompok belum migrasi ke `MemberLoanScheduleCalculator`** (masih pakai `generatePrincipalSchedule`/`generateInterestSchedule` generik). Aggregate total pokok+jasa paritas dengan individu (test `test_individual_schedule_parity_*`), tapi nominal per-bulan bisa berbeda karena kelompok tidak ter-apply magic formula `jangka==24` dan pembulatan per kecamatan.
- **Dokumen PDF 30+ (`coverProposal`, `BA musyawarah`, `verifikasi`, `SPK`, `kuitansi`, dll)** untuk individu belum dimigrasi — kartu angsuran (`card`) & keterangan lunas (`settlement-letter`) sudah, sisanya di luar scope sesi ini.

## [2026-09-10]

### Added
- **Download Bundle Laporan (ZIP) — Laporan Keuangan + Buku Besar per Akun + Dokumen LPJ:**
  - Route `GET /accounting/reports/bundle/pdf` (`ReportController::bundlePdf`, kawal permission laporan, default bulan Desember) beserta kartu "Download Bundle Laporan (ZIP)" pada halaman `AnnualPack.vue`.
  - `ReportBundleService` menggabungkan satu ZIP `bundle-laporan-{tenant}-{Y}-{m}.pdf.zip`: 7 laporan keuangan (Neraca, Laba Rugi, Arus Kas, Perubahan Ekuitas, CALK, Neraca Saldo, Jurnal Transaksi) + Buku Besar per akun + 4 dokumen LPJ (Cover, Surat Pengantar, Berita Acara, MoU).
  - Buku Besar dalam bundle dibuat **satu PDF per akun postable aktif** (`buku-besar-{kode}-{Y}-{m}.pdf`, label manifest `Buku Besar {kode} · {nama}`) — memperbaiki implementasi awal yang memanggil `GeneralLedgerService::build()` tanpa argumen akun wajib (fatal saat runtime).
  - Akun tanpa mutasi (saldo awal tahun nol, debit/kredit periode nol, tanpa baris jurnal) dilewati agar ZIP tidak berisi puluhan PDF kosong; jumlahnya dilaporkan pada `README-bundle.txt` ("Akun tanpa mutasi dilewati: N").
  - Manifest dinamis, `set_time_limit(0)` untuk bundle besar, ZIP otomatis dihapus setelah terkirim (`deleteFileAfterSend`); test `ReportBundleTest` (2 test, 13 asersi) memverifikasi isi ZIP, skip akun tanpa mutasi, dan penamaan file buku besar.

### Changed
- **Pemilihan Tenant Legacy Disederhanakan (Migrasi):**
  - Admin hanya memilih tenant legacy (dari `kecamatan`: `nama_kec` + `kd_kec`) tanpa pairing manual: suffix server-side dari `legacy_id`, tenant Next dicocokkan otomatis via `district_code == kd_kec`, tenant otomatis di-provision bila belum ada; expert mode mempertahankan alur pairing lama.
  - Endpoint `GET /admin/migration/legacy-tenants` (cache 5 menit) + test `MigrationFlowTest`.

### Fixed
- **Perbaikan UX Batch Halaman Website:**
  - `app.js`: menghapus override `route()` naive (pengganti titik→slash) yang menimpa helper Ziggy — akar error "route password/request could not be found" dan "GET not supported for website/messages/index".
  - Sidebar: posisi scroll kini dipulihkan saat navigasi antar halaman Inertia.
  - Form Posts/Pages: raw input diganti komponen `AppTextarea`, `AppFileUpload`, `AppDatePicker` (component-first, `new_siupk` tidak mengimpor `@its-enpii/ui`).
  - `Messages/Index.vue` & `Settings/Form.vue`: paritas layout dengan halaman dashboard standar.

## [2026-09-08]

### Added
- **Form Verifikasi & Form Penetapan Alokasi Mendukung Parameter Lengkap:**
  - Form Verifikasi pada `Show.vue` kini menyediakan input penyesuaian: rekomendasi jangka waktu, pros jasa total, sistem angsuran pokok & jasa, serta grace period.
  - Form Penetapan Alokasi mengambil snapshot verifikasi terakhir sebagai default dan dapat disesuaikan sebelum disetujui.
  - `LoanService::approve()` menyimpan parameter final yang disetujui ke record pinjaman dan langsung meregenerasi jadwal angsuran sesuai jangka, suku bunga, frekuensi, dan grace period yang disetujui.
- **Riwayat Audit Parameter Pinjaman (Tahap P, V, W, Pencairan) & Modal Dialog:**
  - Migrasi shard `loan_status_histories`: menambahkan kolom snapshot parameter lengkap (`service_rate_total`, `principal_frequency`, `interest_frequency`, `principal_grace_months`, `interest_grace_months`).
  - `LoanService` otomatis merekam snapshot parameter pada setiap transisi tahap siklus pinjaman (Proposal / P, Verifikasi / V, Penetapan Alokasi / W, dan Pencairan / Aktif).
  - `LegacyLoanLoader` membentuk riwayat audit bertahap untuk data impor legacy (P, V, W, pencairan) lengkap dengan snapshot parameter.
  - UI `Show.vue`: menghapus card riwayat inline yang panjang, menggantinya dengan tombol ringkas "Riwayat & Audit Parameter" yang membuka `AppModal` (tabel perbandingan parameter side-by-side antar-tahap dan timeline tambahan).

## [2026-09-07]

### Added
- **Sistem Angsuran Legacy (23 Sistem) & Grace Period:**
  - Master data tabel shard `installment_systems` berisi 23 sistem angsuran legacy SI UPK (seeder `InstallmentSystemSeeder`, provisioning otomatis per-tenant via `TenantInstallmentSystemProvisioner`).
  - Enum frekuensi baru pada mesin pinjaman & simulasi: `every_4` s/d `every_12`, `every_24`, dan `every_36` bulan; `weekly`, `bimonthly`, `quarterly`, dan `at_maturity` tetap didukung.
  - Kolom `principal_grace_months` & `interest_grace_months` pada tabel `loans`: sistem M1/M2/M3/M6/M12/M24/Musiman kini menghasilkan jadwal yang benar (pokok dan/atau jasa ditunda N bulan setelah cair; total pokok tetap, pembulatan diserap angsuran terakhir; M1 menunda pokok+jasa, sisanya pokok saja).
  - `LegacyLendingNormalizer` & `LegacyLoanLoader` tidak lagi memaksa `flat`: `sistem_angsuran`/`sa_jasa` legacy dipetakan ke frekuensi + grace yang sesuai.
  - UI Form/Show/Simulasi pinjaman menggunakan SmartSelect bergrup (Frekuensi / M-system / Lainnya) + rule validasi `ValidLoanSchedule` (interval melebihi jangka ditolak).

## [2026-09-04]

### Added
- **Pengerasan Lokasi SQLite Desktop & Migrasi Otomatis userData:**
  - `electron/main.cjs` kini secara default mengarahkan database SQLite lokal ke `app.getPath('userData')/database/database.sqlite` (bukan direktori instalasi) agar file database tidak terhapus saat uninstall/reinstall aplikasi.
  - Startup handler di Electron secara otomatis mendeteksi dan menyalin database lama dari path legacy (`<appDir>/database/database.sqlite`) ke lokasi baru di `userData` jika file target belum ada.
  - Template `.env.desktop.example` diperbarui dengan panduan pengosongan `DESKTOP_SQLITE_PATH` agar lokasi dikelola otomatis oleh runtime Electron.
- **Cadangan Otomatis SQLite Pre-Update di Electron:**
  - `electron/main.cjs` membuat file backup SQLite (`<nama>.bak-YYYYMMDD-HHmmss`) di direktori `backups/` sebelum `autoUpdater.quitAndInstall()` dieksekusi.
  - Rotasi cadangan otomatis mempertahankan maksimal 3 file backup terbaru dan membersihkan file backup yang lebih lama.

### Changed
- **Pengerasan Keamanan Token Desktop Sync API (`VerifyDesktopApiToken`):**
  - Menghapus penerimaan token autentikasi melalui query parameter (`?api_key=` dan `?desktop_key=`) guna mencegah pencatatan kredensial pada server access logs maupun proxy. Token kini wajib dikirim melalui header `Authorization: Bearer` atau `X-Desktop-Key` / `X-API-Key`.
  - Mengubah fallback middleware: jika `services.desktop.api_key` kosong di environment `production`, request otomatis ditolak (HTTP 401) dengan pesan bahwa `DESKTOP_SYNC_API_KEY` wajib dikonfigurasi.
  - Memperbarui automated feature test `tests/Feature/Api/DesktopSyncApiTest.php` untuk mencakup penolakan query parameter dan kewajiban konfigurasi API key di environment produksi.

## [2026-09-03]

### Added
- **Panel WhatsApp Platform untuk Superadmin & OTP Lupa Password:**
  - Tabel platform `whatsapp_platform_instances` dan model `WhatsappPlatformInstance` untuk nomor WhatsApp system-level yang tidak bergantung pada instance tenant.
  - Service `PlatformWhatsappGatewayService` dengan kontrak HTTP gateway yang sama (`create-instance`, `instance-state`, `delete-instance`, `send-message`) serta resolusi instance default/aktif untuk OTP platform.
  - Panel `/admin/whatsapp`: daftar instance, tambah/edit, QR + polling status, hapus session, uji kirim, dan set default; item navigasi "WhatsApp" ditambahkan pada layout superadmin.
  - OTP lupa password kini mencoba instance platform terlebih dahulu, lalu fallback ke instance tenant; akun tanpa `tenant_id` (superadmin) hanya menggunakan instance platform agar tidak ada NPE pada resolver tenant.
  - Automated feature test `tests/Feature/Admin/PlatformWhatsappTest.php` serta skenario fallback OTP pada `tests/Feature/Auth/ForgotPasswordTest.php`.
- **Situs Publik Ber-branding Tenant di Domain Kustom (Fase 1):**
  - Resolusi host → tenant untuk halaman publik via `PublicSiteResolver` (`app/Tenancy/Services/`) dengan cache berversi (TTL 300 detik, flush O(1) lewat kenaikan versi) — `localhost`/host platform/`SITE_PLATFORM_HOSTS` selalu merender halaman vendor siupk.
  - Middleware `ResolvePublicSite` (alias `public.site`): menghubungkan shard + inisialisasi `TenantContext` untuk host tenant, **tanpa fail keras** — host tak dikenal jatuh lembut ke halaman vendor, bukan 403; context & koneksi dilepas di blok `finally` agar tidak bocor antar-request worker.
  - Route `/` kini dipegang `PublicSiteController` (`app/Http/Controllers/PublicSite/`): host tenant aktif merender halaman landing `PublicSite/TenantHome` ber-branding `OrganizationProfile` (logo, nama legal/singkat, alamat, kontak, tahun berdiri, CTA "Masuk Sistem" ke `/login`), tenant `suspended` dan host tak dikenal tetap ke halaman vendor, short-circuit desktop (`X-Desktop-Client` / `DESKTOP_MODE`) ke `/login` tetap terjaga.
  - `Tenant::matchesHost()` di `app/Models/Platform/Tenant.php` — logika pencocokan domain (exact + wildcard `*.domain`) dipindah dari `TenantResolver::candidateMatchesHost()` (dihapus) agar dipakai bersama resolver tenancy & resolver situs publik.
  - Flush cache host otomatis saat admin mengubah domain/status tenant (`TenantController::update/suspend/activate`).
  - Konfigurasi baru `config/site.php` (`SITE_PLATFORM_HOSTS`) untuk host platform tambahan di balik load balancer.
  - Automated feature test `tests/Feature/PublicSite/PublicTenantSiteTest.php` (7 test, 67 asersi): fallback vendor, landing tenant, prioritas nama profil organisasi, tenant suspended, redirect desktop, dan perilaku cache-until-flush.
- **Blog & Halaman Statis Tenant (Fase 2):**
  - Tabel shard `site_posts` dan `site_pages` (migrasi `2026_09_03_000002_create_site_content_tables.php`) dengan slug unik per tenant, status `draft|published`, soft delete, serta indeks `(tenant_id, status)`; model `SitePost`/`SitePage` (`app/Domain/Website/Models/`) menandai kontrak `ExcludedFromDesktopSync` agar konten situs publik tidak ikut terkirim ke outbox sinkronisasi desktop.
  - Admin CRUD berita (`/website/posts`) dan halaman (`/website/pages`) dengan editor rich text `AppRichEditor`, upload/hapus gambar sampul (JPG/PNG/WebP maks 2 MB), filter status, pencarian, sort, per-page, soft delete + pulihkan; nama penulis distempel otomatis dari pengguna yang membuat berita.
  - Slug stabil untuk URL publik: otomatis dari judul (transliterasi ASCII, akhiran `-2`, `-3`, … bila bentrok) saat baris baru, dipertahankan saat edit kecuali penulis mengosongkannya; `published_at` dicap saat pertama tayang dan tidak berubah pada edit berikutnya.
  - Render publik di domain tenant: `/berita` (indeks paginated 9 per halaman + pencarian judul/ringkasan), `/berita/{slug}` (detail berita), `/p/{slug}` (halaman statis); hanya konten `published` dengan `published_at <= now()` yang tampil, slug tak dikenal jatuh lembut ke landing tenant — bukan 404 — agar branding tetap milik desa.
  - Halaman `PublicSite/BlogIndex`, `PublicSite/BlogPost`, `PublicSite/StaticPage` dengan styling artikel `.prose-siupk` (`resources/css/app.css`) dan tombol "Berita" pada landing tenant yang mengarah ke `/berita`.
  - Permission baru `website.view` / `website.manage` (`config/permissions.php`): nav sidebar "Website" disembunyikan tanpa izin, index/crud dikawal `denyUnless`, dan — perbaikan keamanan — `request_map` kini memetakan kelas FormRequest **konkret** (`SitePostRequest`/`SitePageRequest`), bukan kelas abstrak `SiteContentRequest` yang tidak pernah cocok dengan lookup `static::class` sehingga store/update sebelumnya lolos tanpa cek `website.manage`.
  - Update `docs/RBAC_MATRIX.md`: baris nav Website, aksi konten, dan status enforcement.
  - Automated feature test `tests/Feature/Website/WebsiteContentTest.php` (15 test): slug unik & stabil, stamping publish, upload/hapus cover, soft delete + restore, guard permission (kasir 403 termasuk pada store), render publik index/search/detail/fallback, dan eksklusi outbox desktop dengan kontrol positif; ditambah asersi `website.*` pada `tests/Unit/Access/PermissionConfigTest.php`.
- **Pengaturan Situs, Form Kontak & SEO (Fase 3):**
  - Tabel shard `site_settings` (satu baris per tenant, unique `tenant_id`) dan `site_messages` (indeks `(tenant_id, read_at)`, migrasi `2026_09_03_000003_create_site_settings_and_messages.php`); model `SiteSetting`/`SiteMessage` (`app/Domain/Website/Models/`) menandai kontrak `ExcludedFromDesktopSync`.
  - Halaman admin **Pengaturan Situs** (`/website/settings`): tagline & deskripsi hero, upload/ganti/hapus gambar hero (JPG/PNG/WebP maks 2 MB, file lama otomatis dihapus), deskripsi "tentang", tautan sosial media, kontak (telepon/email/alamat), dan catatan footer; write dikawal `request_map` → `website.manage` (`SiteSettingRequest`), lihat `website.view`.
  - Form kontak publik **/kontak** di domain tenant: rate limit `throttle:10,1` di level route, honeypot field `website` tersembunyi CSS (bot dikaburi sukses palsu tanpa menyimpan apa pun), validasi nama/pesan wajib; pesan tersimpan per tenant dan admin membacanya di **Pesan Masuk** (`/website/messages`) dengan pencarian, badge jumlah belum dibaca, tandai sudah dibaca, dan hapus (`website.manage`).
  - Payload `settings` kini menyebar ke seluruh halaman publik via `PublicSiteController::resolveTenantSite()` — landing tenant menampilkan tagline/hero/about/footer dari pengaturan, bukan nilai kosong.
  - SEO halaman publik: meta Open Graph + Twitter Card (title/description/type/url, `og:image` + `article:published_time` untuk berita) di semua 5 halaman `PublicSite/*`, plus `GET /sitemap.xml` (home, /berita, daftar berita & halaman published) dan `GET /robots.txt` (blokir /login, /dashboard, /website, /master-data, dll; tautan sitemap) — keduanya tetap melayani host platform tanpa konteks tenant.
  - Perbaikan bug sitemap: penambahan URL memakai `->each(fn () => $urls[] = …)` yang menangkap `$urls` by-value sehingga daftar berita/halaman tidak pernah masuk XML; diganti `foreach` eksplisit.
  - Nav sidebar Website bertambah menu **Pengaturan Situs** dan **Pesan Masuk** (otomatis disembunyikan tanpa `website.view` via `nav_map`); `docs/RBAC_MATRIX.md` diperbarui (baris nav + aksi).
  - Automated feature test `tests/Feature/Website/WebsiteSettingsAndMessagesTest.php` (20 test, 151 asersi): render & persist pengaturan termasuk siklus hidup gambar hero (upload/ganti/hapus), guard permission kasir 403 di seluruh endpoint, render kontak di domain tenant dan fallback vendor Home di host platform, persist/validasi/honeypot/rate-limit form kontak, inbox pencarian/tandai-baca/hapus, eksklusi outbox desktop, propagasi settings ke landing tenant, sitemap, dan robots.
- **Runbook Domain Kustom Tenant (Fase 4):**
  - `docs/CUSTOM_DOMAIN_RUNBOOK.md` — prosedur operasional DNS & TLS untuk domain kustom tenant: model resolusi host (`ResolvePublicSite` → `PublicSiteResolver` → `TenantContext`), prasyarat, opsi DNS (subdomain platform wildcard vs apex kustom penuh + aturan A/CNAME apex), verifikasi propagasi (`dig` + smoke `curl` per halaman publik), setup lokal Laragon (hosts file), TLS Caddy on-demand (endpoint `ask`  anti-abuse) vs nginx + certbot per domain, checklist verifikasi TLS, prosedur operator 7 langkah, rollback, dan tabel troubleshooting.
- **Lupa Password via OTP WhatsApp:**
  - Alur lupa password untuk tamu: minta OTP (`/forgot-password`), verifikasi OTP (`/forgot-password/otp`), dan setel ulang password (`/forgot-password/reset`), ditautkan dari halaman login ("Lupa password?").
  - OTP 6 digit dikirim melalui WhatsApp gateway per-tenant (instance hasil scan QR di WhatsApp Hub) via `WhatsAppPasswordOtpService`; karena OTP dikirim via WhatsApp, kolom `users.phone` kini **wajib dan unik** (migrasi platform fail-loud bila masih ada user tanpa phone atau phone duplikat).
  - Rate limiting berlapis: `throttle:5,1` per IP di endpoint POST, maksimal 3 OTP per jam per nomor, jeda kirim ulang 60 detik, dan batas percobaan verifikasi OTP.
  - Anti-enumeration: identifier yang tidak dikenal atau gateway yang tidak tersedia tetap mendapatkan respons sukses generik yang identik.
  - Token reset tersimpan ter-hash di tabel platform `password_reset_tokens` (auto-increment, indeks phone/created_at, housekeeping token >1 jam); halaman reset memakai `grant_token` sesi dan OTP terkunci per user.
  - Normalisasi nomor WhatsApp (`PhoneNormalizer`, format `62xxx`) dipakai konsisten di seluruh alur termasuk validasi profil.
  - Halaman baru memakai komponen App* (Material Design 3, aman dark mode, responsif) dan `flash` global.
  - Automated feature test `tests/Feature/Auth/ForgotPasswordTest.php` (7 test, 42 assertion).

## [2026-09-02]

### Added
- **Mekanisme Update Aplikasi Desktop (Electron Auto-Update):**
  - Endpoint `GET /desktop/update/check` (`UpdateManifestService`) yang mengembalikan versi terbaru, versi minimum yang didukung, URL unduhan, dan status langganan tenant — cek update dan status langganan selesai dalam satu permintaan.
  - Automatic updater Electron menggunakan feed GitHub Releases (`electron-updater`): pemeriksaan non-blokir saat aplikasi start dan berulang tiap 6 jam, notifikasi progress via IPC, serta dialog "restart untuk memperbarui" yang tidak dapat ditolak saat paksa update.
  - Version gate `X-App-Version` pada push sync desktop dan mobile: client di bawah versi minimum ditolak dengan HTTP 426 `CLIENT_OUTDATED` tanpa memproses mutasi.
  - Workflow GitHub Actions `desktop-release.yml`: push tag `desktop-vX.Y.Z` memicu build installer Windows NSIS dan pembuatan GitHub Release otomatis beserta manifest `latest.yml`.
  - Runbook rilis `docs/DESKTOP_UPDATE_RUNBOOK.md` dan konfigurasi `config/desktop-update.php` (`DESKTOP_LATEST_VERSION`, `DESKTOP_MIN_VERSION`).
  - Automated feature test `tests/Feature/Desktop/UpdateManifestTest.php` (8 test): manifest & normalisasi versi, force update, blokir langganan, dan 426 pada push client lama.
- **Sinkronisasi Dua Arah Desktop (Outbox & Push):**
  - Outbox lokal SQLite yang mencatat seluruh mutasi offline, indikator jumlah mutasi tertunda pada halaman Pengaturan, dan log konflik.
  - Endpoint push API dengan resolusi konflik hybrid (last-write-wins berbasis `client_updated_at` dengan proteksi status server), sehingga data yang dibuat offline tersinkron aman saat koneksi kembali.
  - Automated feature test `tests/Feature/Sync/DesktopPushTest.php` (6 test).
- **Sinkronisasi Offline Mobile (Pull & Push Terkontrol):**
  - Endpoint pull scoped per petugas desa (`/api/v1/mobile/sync/collection`) dan push whitelist ketat (`/api/v1/mobile/sync/push`) dengan idempotensi `mutation_uuid`, audit log, dan penolakan tabel di luar whitelist.
  - Pembayaran angsuran mobile melalui jalur `LoanService` yang sama dengan web agar jurnal dan notifikasi WhatsApp tetap terpicu; pengecualian push sync saat mode offline (middleware `BlockOfflineMutations`).
  - Automated feature test `tests/Feature/Api/MobileSyncApiTest.php` (8 test).
- **Pengecekan Langganan Pertama Saat Reconnect (`SubscriptionGateService`):**
  - Satu sumber status blokir langganan untuk desktop, mobile, dan web: begitu perangkat tersambung internet, masa langganan dicek lebih dulu sebelum proses push/pull apapun.
  - Push desktop ditangguhkan dengan HTTP 402 saat langganan terblokir tagihan (`blocks_access`), respons blok `subscription` pada `/sync/status`, dan data outbox lokal tetap aman hingga langganan aktif kembali.
  - Automated feature test `tests/Feature/Billing/SubscriptionGateSyncTest.php` (7 test).

## [2026-09-01]

### Added
- **Upload & Render Foto KTP Anggota:**
  - Upload foto KTP di halaman detail Master Data Anggota (section Dokumen) dengan validasi JPG/PNG/WebP maksimal 4 MB, preview, penggantian/penghapusan aman, dan tampilan NIK.
  - Kolom `identity_photo_path` pada tabel shard `people` (migrasi `2026_09_03_000001_add_identity_photo_to_people.php`).
  - Dokumen pinjaman "FC KTP Pemanfaat dan Penjamin" kini menampilkan foto KTP (ter-embed base64, satu pemanfaat/penjamin per halaman) dengan caption nama & NIK, serta placeholder jelas "FOTO KTP BELUM DIUNGGAH" bila foto belum tersedia — sebelumnya dokumen ini selalu kosong.
  - Automated feature test `tests/Feature/MasterData/MemberIdentityPhotoTest.php` (7 test, 24 assertion): upload, validasi, replace, hapus, permission, dan render PDF dengan/tanpa foto.
- Fix import `useToast` pada halaman Admin Payment Gateways yang menyebabkan halaman blank total (E2E gagal karena `#app` kosong).
- Halaman mandiri **WhatsApp Gateway** (`/settings/whatsapp/manage`) untuk mengelola beberapa nomor/instance WhatsApp per tenant.
- **WhatsApp Hub** satu halaman bertab pada `/settings/whatsapp` untuk Status & Instance, Template Pesan, dan Kirim Tagihan. Halaman ini memakai payload per izin (`settings.manage` atau `messages.send`) tanpa menambah endpoint baru.
- Tabel tenant `whatsapp_instances` untuk menyimpan nama, status koneksi, nomor, batas harian, status default, dan status aktif setiap instance WhatsApp.
- Strategi rotasi pesan Round Robin atau Nomor Utama pada `WhatsappGatewayService` agar beban notifikasi dapat didistribusikan antar nomor dan membantu mengurangi risiko blokir.
- Kolom koordinat tenant (`map_latitude`, `map_longitude`, `map_zoom`) pada tabel platform `tenants` dan registry shard `tenant_registry`, dengan sinkronisasi registry dan tampilan koordinat di detail tenant.
- Pemilih lokasi Leaflet (`LocationMapPicker.vue`) pada form create/edit tenant dengan input latitude/longitude, klik-to-set, drag pin, dan zoom opsional.
- Endpoint `GET /admin/regional/regency-center/{regency}` untuk menentukan pusat peta berdasarkan kode kabupaten/kota.
- Papan status pinjaman (`LoanKanbanBoard`) untuk mempercepat verifikasi, persetujuan, alokasi pemanfaat, dan pencatatan pencairan dari satu tampilan.
- Upload dan penghapusan gambar tanda tangan (`SignaturePad`) pada Pengaturan, dengan validasi tipe file, ukuran maksimal 2 MB, dan penyimpanan aman untuk laporan pinjaman.
- Antrean sinkronisasi offline mobile (`OfflineQueueService` dan `MobileOfflineSyncService`) yang menyimpan pengajuan pembayaran saat koneksi terputus lalu mengirim ulang otomatis dengan batas percobaan.

### Changed
- Tab "WhatsApp Gateway" pada halaman Pengaturan dihapus agar template dan status WhatsApp hanya dikelola melalui WhatsApp Hub. Endpoint `/settings/whatsapp/manage` tetap tersedia sebagai alias.
- Halaman Notifikasi Tagihan kini dialihkan ke tab "Kirim Tagihan" pada WhatsApp Hub, item menu Keuangan→Periodik dihapus, dan navigasi WhatsApp diarahkan ke `/settings/whatsapp`.
- Resolver peta konsolidasi kabupaten kini membaca koordinat tersimpan tenant terlebih dahulu, lalu fallback ke dataset `RegencyGeoService`.
- Validasi koordinat tenant dibatasi ke rentang global dan batas radius kabupaten/kota terpilih pada `StoreTenantRequest` serta `Admin\\UpdateTenantRequest`.
- Kalkulator simulasi pinjaman mendukung satuan bunga bulanan/tahunan, preskripsi pembulatan tanpa batas minimum, ringkasan pemanfaat, dan tampilan hasil yang diperbarui.
- Dashboard kabupaten menambahkan metrik perputaran, total aset, NPL, dan tunggakan pokok beserta peta sebaran kecamatan berbasis Leaflet.
- Middleware `BlockOfflineMutations` memberikan pengecualian mutasi hanya untuk pengguna tenant yang ditetapkan sebagai pengguna offline, sedangkan autentikasi mobile tetap di-whitelist.

### Fixed
- Kontrak parameter URL `download_report` AI Assistant agar sesuai dengan controller laporan: jurnal menggunakan `year`/`month`, buku besar menggunakan `account`, dan portofolio pinjaman menggunakan `as_of`.
- Keamanan halaman multi-instance WhatsApp: operasi `update` kini melakukan otorisasi izin `settings.manage` sebelum perubahan data.
- Kesalahan parameter entity pemanfaat pinjaman pada test mobile agar analisis Flutter kembali berjalan tanpa error pada kode test.
- Deklarasi `ext-bcmath` pada `composer.json` karena `JournalPostingService` memakai `bccomp()` dan Dockerfile sudah menginstall ekstensi tersebut (environment tanpa bcmath gagal 111 test).
- Instalasi ekstensi `gd` dan `zip` pada Dockerfile serta deklarasi `ext-gd` dan `ext-zip` pada `composer.json` agar fitur foto/profile dan export Excel tidak gagal saat runtime.
- Konsistensi token MD3 pada `SignaturePad.vue` (ganti `text-slate-400` → `text-on-surface-variant`) dan format pembulatan simulasi pinjaman memakai `useMoney()` pada `Lending/Simulation/Index.vue`.
- Pembersihan artefak karakter asing pada `docs/audit/2026-08-15/loan-deletion-audit.md`.

---

## [2026-08-31]

### Added
- **Jenis Transaksi Pembelian Aset Tak Berwujud (Lisensi/Sewa/Asuransi):**
  - Penghubungan jenis transaksi `pembelian_aset_tak_berwujud` ke alur pembelian aset pada `JournalEntryOptionResolver` (COA debit `1.2.03`, umur ekonomis default 60 bulan), sehingga kini menampilkan field nama barang, jumlah unit, harga satuan, dan umur ekonomis pada form jurnal (`Create.vue` & `Edit.vue`) dan otomatis mendaftarkan aset baru ke modul Inventaris dengan kategori `ATB` melalui `JournalEntryController`, `JournalEditService`, dan `AssistantToolService`.
  - Dukungan `AssetService::create` untuk parameter `category_code` guna pengisian kategori aset otomatis saat registrasi dari jurnal.
  - Penambahan heuristik asisten AI (`AssistantToolService`) untuk mengenali lisensi/sewa/asuransi sebagai pembelian aset tak berwujud dan memetakan akun debit ke sub-akun `1.2.03.xx` yang sesuai (Biaya Pendirian Organisasi, Lisensi, Sewa Dibayar Dimuka, Asuransi Dibayar Dimuka).

## [2026-08-27]

### Added
- **Halaman Catatan Rilis (Changelog) & Ikon Navbar:**
  - Controller `ChangelogController` (`app/Http/Controllers/ChangelogController.php`) yang mem-parsing `CHANGELOG.md` secara otomatis menjadi daftar rilis terstruktur dengan metadata versi, tanggal terformat, pengelompokan kategori perubahan (`Added`, `Changed`, `Fixed`, `Security`, dsb.), dan konversi markdown ke HTML via `Str::markdown()`.
  - Halaman antarmuka interaktif `resources/js/Pages/Changelog/Index.vue` dengan linimasa rilis visual berstandar Material Design 3, pencarian kata kunci/fitur instan, filter segment per kategori perubahan, kartu rilis berbadge, dan penyesuaian layout otomatis sesuai peran pengguna (`AuthenticatedLayout`, `AdminLayout`, `ProvinceLayout`, `RegencyLayout`).
  - Penambahan ikon tombol cepat Changelog (`history_edu`) pada navbar di seluruh layout aplikasi (`AuthenticatedLayout.vue`, `AdminLayout.vue`, `ProvinceLayout.vue`, `RegencyLayout.vue`) di samping menu tema & notifikasi.
  - Automated feature test `tests/Feature/ChangelogTest.php` (3 test, 28 assertion).
- **Peningkatan Ekosistem Asisten AI (Integrasi `enpii/assistant` v0.2.1):**
  - Pembaruan package `enpii/assistant` ke versi `0.2.1` (commit `e329d41`) dengan preservasi lampiran media pada auto-compaction percakapan.
  - Tool baru `simulate_loan` (`SimulateLoanHandler.php` & `AssistantToolService::simulateLoan`) yang dapat menghitung simulasi pinjaman interaktif dan memberikan tombol direct download PDF jadwal simulasi lengkap.
  - Dukungan multi-modal lampiran dokumen (PDF/gambar) pada widget chat `AssistantWidget.vue` untuk parsing dokumen/kuitansi dengan OCR.
  - Implementasi interface `CompensatableToolHandler` pada `CreateJournalEntryHandler` untuk mendukung aksi rollback/kompensasi transaksi otomatis.
  - Penanganan real-time SSE event `tool_progress` pada widget chat untuk menampilkan kemajuan eksekusi tool secara visual.
  - Penanda status pesan terbaca (*read receipts*) dan badge jumlah pesan belum dibaca (*unread count*) pada tombol bubble chat.
  - Automated feature test `tests/Feature/Assistant/SimulateLoanToolTest.php` (4 test, 25 assertion).
- **Fitur Simulasi Pinjaman (`/lending/simulation`):**
  - Mesin kalkulasi `LoanSimulationService` (`app/Domain/Lending/Services/LoanSimulationService.php`) yang mendukung metode perhitungan bunga Flat (Tetap), Efektif Menurun (Declining), dan Anuitas (Annuity).
  - Mendukung kustomisasi frekuensi pembayaran pokok dan jasa (Bulanan, Triwulanan, Semesteran, Tahunan, Jatuh Tempo di Akhir) serta aturan pembulatan nilai angsuran minimal Rp 500 (Rp 500, Rp 1.000, Rp 5.000, Rp 10.000, Rp 50.000).
  - Controller `LoanSimulationController` (`app/Http/Controllers/Lending/LoanSimulationController.php`) dengan 3 endpoint:
    - `GET /lending/simulation` (index): Halaman kalkulator interaktif dengan preset produk pinjaman yang aktif.
    - `POST /lending/simulation/calculate` (calculate): API kalkulasi JSON tervalidasi.
    - `GET /lending/simulation/pdf` (pdf): Cetak dokumen resmi estimasi jadwal angsuran berformat PDF.
  - Template cetak laporan PDF `resources/views/reports/pdf/loan_simulation.blade.php` lengkap dengan kop identitas lembaga, ringkasan peminjam & plafon, serta tabel jadwal amortisasi angsuran.
  - Antarmuka interaktif `resources/js/Pages/Lending/Simulation/Index.vue` dengan pemilihan cepat template produk kredit (SPP, UEP, PL), kalkulasi real-time di browser, kartu KPI (Total Pinjaman, Total Bunga, Total Pembayaran, Estimasi Bulanan), filter tanggal mulai, dan ekspor cetak PDF.
  - Navigasi sidebar `resources/js/Layouts/AuthenticatedLayout.vue` pada menu Perguliran dan pemetaan permission `loans.view` di `config/permissions.php`.
  - Automated feature test `tests/Feature/Lending/LoanSimulationTest.php` (8 test, 70 assertion).
- **Nomor / Kode Transaksi Otomatis (`journal_number`):**
  - Auto-generate kode transaksi dengan format `YYMMNNN` (contoh: `2608001`, `2608002`, dst.) saat jurnal diposting pada `JournalPostingService::post()`.
  - Menggunakan `TenantSequenceService` dengan sequence berlingkup per-bulan (`journal_number:YYMM`) sehingga nomor urut reset otomatis menjadi `001` setiap pergantian bulan dan aman dari race condition.
  - Berlaku merata untuk semua jenis transaksi sistem: jurnal manual/umum, pencairan pinjaman, angsuran pinjaman, penghapusan buku (write-off), penjadwalan ulang (reschedule), pembalikan jurnal (reversal), alokasi laba, asisten AI, dan pencatatan saldo awal pada `TenantOnboardingService`. Khusus transaksi hasil migrasi dari legacy (`LegacyJournalLoader` & `BackfillJournalNumbers`), kolom `journal_number` diisi dengan ID transaksi legacy asli untuk mempertahankan konsistensi nomor cetak dokumen historis.
  - Artisan command `php artisan accounting:backfill-journal-numbers {tenant}` (`BackfillJournalNumbers.php`) untuk mengisi `journal_number` pada transaksi historis/terposting yang masih kosong, dilengkapi opsi `--dry-run`.
- **Masa Jabatan & Status Pengguna:**
  - Migrasi `2026_08_27_092016_add_term_end_at_to_users_table.php` menambahkan kolom `term_end_at` (tanggal selesai menjabat, nullable) pada tabel `users` (koneksi platform).
  - Cast `term_end_at` sebagai tipe `date` pada model `User`.
  - Kolom tanggal mulai menjabat (`appointed_at`) dan selesai menjabat (`term_end_at`) terintegrasi pada form profil pengguna (`/profile`, tab Data Pribadi) dan manajemen akses pengguna tenant (`/access/users/create`, `/access/users/{id}/edit`).
  - Kolom "Masa Jabatan" ditampilkan pada tabel daftar pengguna (`/access/users`) dengan rentang tanggal `appointed_at s.d. term_end_at`.
  - Validasi request pada `UpdateProfileRequest`, `StoreTenantUserRequest`, dan `UpdateTenantUserRequest` (`term_end_at` harus berformat tanggal dan `after_or_equal:appointed_at`).
- **Tool AI Asisten: Download Laporan Langsung (`download_report`):**
  - Tool handler `App\Assistant\Handlers\DownloadReportHandler` (`download_report`) yang terdaftar di `ToolRegistry` dan dipetakan ke permission `reports.view`.
  - Method `AssistantToolService::downloadReport()` yang menyusun URL endpoint ekspor PDF/Excel beserta komponen visual tombol interaktif (`::button{"label":"...","url":"...","icon":"download"}::`) yang otomatis di-render oleh widget chat `AssistantWidget.vue`.
  - Mendukung seluruh laporan akuntansi/keuangan (Neraca, Laba Rugi, Arus Kas, Neraca Saldo, Perubahan Ekuitas, CALK, Buku Besar, Jurnal Transaksi, Kesehatan Keuangan, Daftar Aset Tetap) dan laporan pinjaman (Portofolio, Rencana vs Realisasi, LPP Desa, LPP Kelompok, Kolektibilitas, PPAP Cadangan Penghapusan, Ekspor Anggota/Kelompok).
  - Dilengkapi test otomatis `tests/Feature/Assistant/DownloadReportToolTest.php` (6 tests).

## [2026-08-24]

### Added
- **Log Audit Platform Global (`/admin/audit-logs`):**
  - Tabel `audit_logs` pada koneksi platform (migration `2026_08_24_100000_create_audit_logs_table.php`) dengan kolom aktor, tenant, aksi, subjek polimorfik ringan, deskripsi, properti JSON, IP address, dan user agent.
  - Service `AuditLogger` (`app/Services/Admin/AuditLogger.php`) sebagai satu pintu pencatatan: tidak pernah melempar exception (try/catch + report) sehingga aksi utama admin selalu berhasil, plus helper `AuditLogger::diff()` untuk menghitung perubahan field before/after.
  - Instrumentasi seluruh aksi sensitif superadmin: pembuatan/perubahan/suspensi/aktivasi tenant, penetapan langganan, impersonasi tenant, manajemen user tenant (create/update/reset password), invoice (void/toggle blocking), dan siklus Data Purifier (start/end training, purge, reset).
  - Halaman viewer `/admin/audit-logs` dengan filter pencarian, dropdown aksi (distinct dari database), filter tenant, paginasi, badge tone per kategori aksi, dan detail properti JSON yang collapsible; terhubung ke navigasi sidebar AdminLayout.
  - Automated feature test `tests/Feature/Admin/AuditLogTest.php` (5 test): akses halaman superadmin, penolakan non-superadmin, audit suspend tenant, audit void invoice, dan jaminan logger tidak melempar exception saat tabel tidak tersedia.
- **Halaman Shard & Cutover (`/admin/shards`):**
  - Halaman overview infrastruktur sharding hanya-baca: KPI ringkasan (total shard aktif, tenant ter-place, cutover selesai/gagal, shard dengan versi skema tertinggal), daftar shard dengan status, endpoint, jumlah tenant aktif, progress bar beban (weight), dan versi skema saat ini vs target.
  - Riwayat cutover run terpaginasi via `SmartDataTable`: tenant, suffix, mode dry-run/produksi, progres step (X/Y ok), status run beserta pesan error yang ter-truncate.
  - Controller `Admin\ShardController` dengan route `admin.shards.index`, entri navigasi sidebar AdminLayout, dan automated feature test `tests/Feature/Admin/ShardPageTest.php` (2 test): akses superadmin dan penolakan non-superadmin.
- **Manajemen Pengguna Platform Global (`/admin/users`):**
  - Pencarian pengguna lintas-tenant (nama/username/email), filter status akun dan tenant, KPI ringkasan (total user, aktif, nonaktif, tanpa tenant), serta login terakhir tiap user.
  - Aksi disable/enable akun (`toggle-status`): menonaktifkan user langsung memblokir login di seluruh aplikasi (AuthController hanya menerima status `active`), menyinkronkan status membership tenant terkait, dan terekam ke log audit (`user.disable` / `user.enable`).
  - Proteksi: akun superadmin dan akun sendiri tidak dapat dinonaktifkan; dialog konfirmasi sebelum eksekusi.
  - Route `admin.users.index` + `admin.users.toggle-status`, entri navigasi sidebar AdminLayout, dan automated feature test `tests/Feature/Admin/UserManagementTest.php` (4 test): akses halaman, penolakan non-superadmin, siklus disable→login gagal→enable dengan audit, dan larangan menonaktifkan superadmin.
- **Halaman Platform Settings (`/admin/settings`):**
  - Manajemen key-value store tingkat instalasi (`platform_settings`) yang berlaku lintas tenant: kredensial payment gateway, template WhatsApp, dan konfigurasi integrasi lainnya. Tampilan terpaginasi dengan pencarian key, KPI (total setting & jumlah setting sensitif), tipe nilai (string/int/float/bool/json), dan waktu perubahan terakhir.
  - Deteksi otomatis key sensitif (mengandung `secret`/`api_key`/`private_key`/`token`/`password`): nilainya disimpan terenkripsi via `PlatformSettingService::setEncrypted`, ditampilkan sebagai masker di UI, tidak pernah dikirim ke browser, dan dimasker juga pada properti log audit.
  - Validasi sebelum simpan: JSON harus valid untuk tipe `json`, konversi tipe otomatis (bool dari `1/true/yes/on`, float menerima koma desimal); penghapusan setting melalui dialog konfirmasi.
  - Setiap perubahan/hapus terekam ke log audit (`platform_setting.update` / `platform_setting.delete`).
  - Controller `Admin\PlatformSettingController` dengan route `admin.settings.index/update/destroy`, entri navigasi sidebar AdminLayout, dan automated feature test `tests/Feature/Admin/PlatformSettingsTest.php` (5 test): akses halaman, penolakan non-superadmin, siklus update→hapus dengan audit, enkripsi & masking key sensitif, serta penolakan JSON tidak valid.

## [2026-08-21]

### Added
- **Arsitektur & RESTful API Aplikasi Lapangan Mobile Flutter (`docs/FLUTTER_MOBILE_ROADMAP.md` & `mobile/`):**
  - Penyusunan roadmap arsitektur, clean architecture BLoC, dan spesifikasi integrasi multi-tenant Flutter Mobile App untuk operasional lapangan: Petugas Penagihan/Kolektor, Surveyor Lapangan, dan Eksekutif/Direktur.
  - Implementasi Laravel Sanctum multi-tenant token guard (`app/Models/Platform/PersonalAccessToken.php`, `config/sanctum.php`, `database/migrations/platform/2026_08_21_162000_create_personal_access_tokens_table.php`).
  - RESTful Mobile API Endpoints (`routes/api.php`, `app/Http/Controllers/Api/Mobile/`):
    - **Autentikasi & Profil**: `/api/v1/mobile/auth/login`, `/api/v1/mobile/auth/me`, `/api/v1/mobile/auth/logout`.
    - **Kolektor Lapangan**: `/api/v1/mobile/collection/loans`, `/api/v1/mobile/collection/loans/{id}`, `/api/v1/mobile/collection/pay` (dengan payload cetak struk Bluetooth Thermal & notifikasi WhatsApp otomatis).
    - **Surveyor Verifikasi Pinjaman**: `/api/v1/mobile/verification/proposals`, `/api/v1/mobile/verification/proposals/{id}`, `/api/v1/mobile/verification/submit` (dengan analisis 5C, geo-tagging koordinat GPS, foto agunan/usaha, dan tanda tangan digital).
    - **Eksekutif & Persetujuan Pinjaman**: `/api/v1/mobile/executive/summary`, `/api/v1/mobile/executive/approvals`, `/api/v1/mobile/executive/approvals/{id}`, `/api/v1/mobile/executive/approve`, `/api/v1/mobile/executive/reject`.
  - Struktur starter project Flutter dengan Clean Architecture & BLoC state management (`mobile/`).
  - Automated Feature Test Suite: `MobileAuthApiTest.php`, `MobileCollectionApiTest.php`, `MobileVerificationApiTest.php`, `MobileExecutiveApiTest.php` (19 test cases, 145 assertions).
- **Sistem Pemblokiran Akses Tenant Berbasis Invoice Tertunggak (*Invoice Access Blocking*):**
  - Penambahan kolom `blocks_access` (boolean) pada tabel `invoices` platform dan dukungan opsi toggle pemblokiran akses operasional tenant saat pembuatan maupun pengelolaan invoice oleh Superadmin (`Admin/Invoices/Create.vue`, `Admin/Invoices/Show.vue`).
  - Middleware `EnsureSubscriptionActive.php` memblokir akses ke rute operasional jika tenant memiliki invoice terbuka dengan status `blocks_access = true` dan mengarahkan otomatis ke halaman penagihan (`/billing/invoices/{id}`), sementara tetap mengizinkan akses ke rute pembayaran dan autentikasi logout.
  - Penambahan automated feature test `InvoiceBlockingTest.php` untuk memvalidasi alur pembuatan, pengalihan rute operasional, proteksi API (HTTP 402 Payment Required), dan pemulihan akses instan setelah pelunasan invoice.
- **Komponen Accordion Reusable & Animasi Ketinggian Grid Dinamis (`AppAccordion.vue`):**
  - Pembuatan komponen `AppAccordion.vue` yang mendukung mode kartu collapsible tunggal maupun daftar multi-item dengan animasi ekspansi ketinggian halus berbasis CSS Grid (`grid-template-rows: 0fr -> 1fr`), rotasi ikon chevron 180°, dan aksesibilitas keyboard WAI-ARIA.
  - Integrasi animasi accordion pada bagian Tanya Jawab (FAQ) di landing page (`Home.vue`).
- **Pintasan Keyboard Global & Modal Panduan Shortcut (`useKeyboardShortcuts.js` & `KeyboardShortcutsModal.vue`):**
  - Pembuatan composable `useKeyboardShortcuts.js` dan modal dialog interaktif `KeyboardShortcutsModal.vue` dengan pemetaan pintasan keyboard produktivitas: `Ctrl+/` (atau `Cmd+/`) untuk panduan pintasan, `Ctrl+K` untuk Global Search / Command Palette, `Ctrl+Shift+A` untuk memicu widget Asisten AI, `Ctrl+Shift+N` untuk membuka pusat notifikasi, dan `Ctrl+Shift+S` untuk sinkronisasi data Desktop ke Cloud.
  - Integrasi event listener global pada layout utama (`AuthenticatedLayout.vue`), pusat notifikasi (`NotificationDropdown.vue`), titlebar desktop (`DesktopTitleBar.vue`), dan widget chatbot (`AssistantWidget.vue`).
  - Peningkatan komponen tombol aksi asisten (`ActionButton.vue`) dan parser markdown (`useMarkdown.js`) dengan dukungan tautan URL eksternal/internal otomatis.
- **Sistem Notifikasi Toast Global & Reaktif (`useToast.js` & `AppToast.vue`):**
  - Pembuatan composable `useToast.js` berbasis event-bus reaktif dengan queue notifikasi mengambang (*stacked floating toasts*), timer *auto-dismiss*, *progress bar* durasi, dan method praktis: `toast.success()`, `toast.error()`, `toast.warning()`, dan `toast.info()`.
  - Refaktor komponen `AppToast.vue` dengan animasi transisi masuk/keluar yang mulus, tema visual berbasis palet Tailwind UI siupk, dan tombol tutup instan.
  - Migrasi seluruh alert banner statis ke sistem `useToast` terpusat pada halaman Pembuatan Jurnal (`JournalEntries/Create.vue`), AI Assistant (`AiAssistant/Index.vue`), Payment Gateways (`PaymentGateways/Index.vue`), dan Pengaturan Lembaga (`Settings/Index.vue`).
- **Layar Pembuka (*Desktop Splash Screen*) & Kontrol Window IPC (`DesktopSplashScreen.vue` & `DesktopTitleBar.vue`):**
  - Komponen `DesktopSplashScreen.vue` untuk transisi startup aplikasi Desktop Electron dengan animasi *pulsing logo* siupk Next, simulasi status inisialisasi koneksi database SQLite lokal, dan efek *fade-out* otomatis saat halaman utama siap.
  - Penambahan sinkronisasi status maximize/unmaximize window melalui listener event IPC Electron pada `DesktopTitleBar.vue`.
- **Arsitektur Aplikasi Desktop & Infrastruktur Sinkronisasi Offline (Hybrid Cloud-Desktop / Electron):**
  - Framework Desktop Hybrid siupk Next berbasis Electron + SQLite lokal + Cloud Sync Engine (`docs/DESKTOP_ROADMAP.md`).
  - Service Provider `DesktopAppServiceProvider.php` dan konfigurasi `config/desktop.php` dengan deteksi otomatis runtime desktop/offline.
  - Snapshot & Ingestion Engine: `TenantSnapshotService.php`, `DesktopSnapshotIngestionService.php`, dan `DesktopSyncClientService.php` untuk ekspor/impor snapshot database tenant (full & delta) yang aman dengan verifikasi checksum SHA-256.
  - Endpoint RESTful API Sync Desktop (`/api/v1/desktop/sync/*` dan `/desktop/sync/*`) dengan proteksi middleware `VerifyDesktopApiToken.php`.
  - Guard Keamanan Offline: Middleware `BlockOfflineMutations.php` untuk melindungi integritas data lokal saat beroperasi dalam mode offline (read-only) dengan whitelist autentikasi lokal.
  - Perintah CLI Manajemen Desktop: `php artisan desktop:init`, `php artisan desktop:status`, dan `php artisan desktop:sync`.
  - Shell Electron: Konfigurasi build `electron/electron-builder.json`, proses utama `electron/main.cjs`, dan bridge IPC `electron/preload.cjs` (`window.desktopAPI`).
  - Antarmuka Desktop: Komponen `DesktopTitleBar.vue`, composable `useAppMode.js`, push notification native OS, dan banner status konektivitas offline (`AppOfflineBanner.vue`).
  - Test Suite Komprehensif: `DesktopFoundationTest.php`, `DesktopReadOnlyGuardTest.php`, `DesktopSyncEngineTest.php`, dan `DesktopSyncApiTest.php`.
- **Atribusi Aktor & Notifikasi Live Multi-Pengguna (Notification Center):**
  - Pelacakan aktivitas pengguna real-time dengan atribusi nama (*actor*): pencatatan jurnal umum, penerimaan pembayaran angsuran oleh kasir, pengajuan pinjaman baru, dan pengingat jatuh tempo/tunggakan peminjam.
  - Integrasi push notification native OS pada aplikasi desktop saat terdeteksi notifikasi operasional baru.
  - Mekanisme background polling adaptif (interval 45 detik saat tab/jendela aktif) pada `NotificationDropdown.vue`.
  - Dukungan batch `markAsRead` untuk penandaan banyak ID notifikasi sekaligus.
- **Sinkronisasi Otomatis Aturan Pembulatan Pinjaman (Lending System Settings):**
  - Penambahan method `syncRoundingFromProducts()` pada `LoanService.php` untuk menyinkronkan metode pembulatan dari master produk ke seluruh proposal pinjaman draft/verified.
  - Aksi interaktif satu klik pada halaman pengaturan sistem pinjaman (`resources/js/Pages/Settings/Index.vue`) beserta endpoint API `POST /settings/lending-system/sync-rounding`.
  - Pengujian otomatis pada `LoanRoundingAndBeneficiarySplitTest.php`.

### Changed
- **Penyempurnaan Pesan dan Tata Letak Pusat Notifikasi (`NotificationCenterController.php` & `NotificationDropdown.vue`):**
  - Penyederhanaan struktur payload notifikasi, ringkasan pesan nominal tagihan dan tunggakan yang lebih padat, serta penguatan deep link aksi ke detail transaksi.
  - Penyempurnaan indikator visual status belum dibaca (*unread dot*) dan ikon aksi pada daftar popover notifikasi.
- **Audit & Pemolesan Animasi Mulus Seluruh Komponen Interaktif (*Component Animation & Transition Audit*):**
  - **SmartSelect (`SmartSelect.vue`):** Penambahan transisi `<Transition>` enter/leave dengan dynamic origin (`origin-top` / `origin-bottom`), animasi rotasi chevron dropdown 180°, transisi pencarian, dan *tactile active scale* (`active:scale-[0.99]`).
  - **AppDatePicker (`AppDatePicker.vue`):** Peningkatan transisi popover kalender dengan origin dinamis, animasi transisi antar-mode tampilan tanggal/bulan/tahun (`<Transition name="calendar-view" mode="out-in">`), serta feedback sentuhan mikro pada tombol navigasi dan pemilihan tanggal/bulan (`active:scale-90`).
  - **Tombol & Tombol Ikon (`AppButton.vue` & `AppIconButton.vue`):** Penambahan feedback tekan mikro (`active:scale-[0.98]` dan `active:scale-90`) dengan transisi `duration-150`, varian `outline`/`tertiary`, dan efek focus ring yang konsisten.
  - **Switch & Checkbox (`AppSwitch.vue` & `AppCheckbox.vue`):** Penambahan animasi thumb geser elastis bergaya Material Design 3 (`cubic-bezier(0.4, 0, 0.2, 1)`), transisi warna track switch, dan scaling klik checkbox.
  - **Modal & Dialog Konfirmasi (`AppModal.vue` & `AppConfirmDialog.vue`):** Penyesuaian kurva easing spring MD3 (`cubic-bezier(0.16, 1, 0.3, 1)`) pada backdrop blur dan dialog pop-in.
  - **Dropdown Notifikasi & Menu Tema (`NotificationDropdown.vue` & `ThemeMenu.vue`):** Penambahan transisi origin popover `origin-top-right`, efek transisi tab notifikasi, dan animasi pemilihan swatch tema tampilan.
  - **Tab, Filter Pill, & Tooltip (`AppTabs.vue`, `AppFilterPill.vue`, `AppTooltip.vue`):** Penambahan animasi interaktif pada segment control, filter status, perpindahan tab, dan tooltip scale-in.
  - **Drawer Navigasi Seluler & Submenu Sidebar (`AuthenticatedLayout.vue`, `AdminLayout.vue`, `ProvinceLayout.vue`, `RegencyLayout.vue`):** Penambahan efek fade backdrop dengan `backdrop-blur` dan transisi slide drawer `duration-300` yang mulus.

### Fixed
- **Audit & Normalisasi Encoding & Mojibake Menyeluruh (*Repository-Wide Character Cleanup*):**
  - Mengaudit seluruh 949 berkas repositori dan membersihkan artefak karakter encoding/mojibake (em-dash `—`, middle dot `·`, centang `✓`, silang `✗`, relasi `→`, dan simbol matematika `≤`/`≥`) pada komponen antarmuka, routing, dan dokumentasi (`AGENT.md`, `Create.vue`, `AiAssistant/Index.vue`, `Budgeting/Index.vue`, `Settings/Index.vue`, `routes/web.php`, `DESKTOP_ROADMAP.md`).
  - Menormalisasi berkas `JournalEntryRequest.php` dari UTF-16LE ke UTF-8 murni tanpa BOM serta memulihkan sintaks validasi form request pembuatan jurnal SOP.
  - Memperbaiki byte CP1252 tidak valid menjadi simbol UTF-8 semantik pada `AuthController.php`, `HOLDING_API_INTEGRATION_GUIDE.md`, `README.md`, dan `Lending/Loans/Index.vue`.
  - Menghapus Byte Order Mark (UTF-8 BOM) pada 41+ berkas Vue, Blade, TypeScript, dan Markdown untuk menjamin konsistensi encoding UTF-8 tanpa BOM di seluruh repositori.
- **Perbaikan Unggah & Penayangan Foto Profil serta Storage Serving (`config/filesystems.php`, `ProfileController.php`, `StorageServeController.php`):**
  - Mengoreksi konfigurasi disk `local` (`'serve' => false`) dan disk `public` (`'serve' => true`, `'visibility' => 'public'`) pada `config/filesystems.php` yang sebelumnya menyebabkan disk privat membajak rute `/storage/...` dan menolak seluruh akses berkas publik dengan status HTTP 403 Forbidden.
  - Menambahkan controller fallback `StorageServeController.php` dan rute `/storage/{path}` untuk melayani berkas publik (foto profil, logo tenant, dll.) secara langsung dan aman jika symlink webserver belum tersedia atau dibatasi oleh lingkungan hosting.
  - Menambahkan *cache-busting query parameter* (`?v={timestamp}`) pada `photoUrl` di `ProfileController.php` dan `HandleInertiaRequests.php` agar pembaruan foto profil langsung muncul seketika tanpa tertahan cache browser.
  - Memperbarui komponen `Profile/Edit.vue` dengan deteksi reaktif `watch` pada perubahan `photoUrl` dan *graceful error fallback* (`@error`) pada gambar avatar.
  - Mengintegrasikan penayangan foto profil pengguna pada kartu profil sidebar seluruh layout aplikasi (`AuthenticatedLayout.vue`, `AdminLayout.vue`, `ProvinceLayout.vue`, `RegencyLayout.vue`).
  - Menambahkan langkah otomatis `docker compose exec -T app php artisan storage:link || true` pada pipeline CI/CD GitHub Actions (`.github/workflows/deploy.yml`).
  - Menambahkan automated feature test `ProfilePhotoTest.php` untuk memvalidasi alur unggah, simpan, serving via `/storage/{path}`, proteksi direktori traversal, dan hapus foto profil.

### Removed
- **Pembersihan Berkas Template Blade Legacy Tidak Terpakai (164 Berkas):**
  - Menghapus berkas view legacy yang telah 100% dimigrasi ke Vue 3 / Inertia dan Service PDF baru: `resources/views/pelaporan/` (54 berkas), `resources/views/reports/legacy/` (54 berkas), `resources/views/reports/legacy_pinjaman/` (37 berkas), `resources/views/reports/legacy_sop/` (18 berkas), dan `resources/views/reports/pdf/loan_documents/cetak_kartu_angsuran_anggota.blade.php` (1 berkas duplikat usang).
  - Mempertahankan 85 berkas template aktif (`resources/views/app.blade.php`, 12 berkas error pages, dan 72 berkas template DomPDF aktif di `resources/views/reports/pdf/`) yang telah diverifikasi bersih via `php artisan view:cache`.

---
## [2026-08-20]

### Added
- **Animasi Transisi Halaman Mulus (Smooth Page Transitions):**
  - Implementasi komponen `<Transition name="page" mode="out-in" appear>` pada 4 layout utama (`AuthenticatedLayout.vue`, `AdminLayout.vue`, `ProvinceLayout.vue`, `RegencyLayout.vue`) berbasis `:key="$page.url"`.
  - Styling transisi kurva cubic-bezier (`resources/css/app.css`) dengan efek *fade*, *subtle lift* (8px), dan *scale easing* (0.22s) yang cepat dan responsif tanpa lag.
  - Efek visual *glowing accent line* dengan *drop-shadow* lembut pada bilah progres Inertia (NProgress) saat navigasi data berlangsung.
  - Kepatuhan aksesibilitas penuh terhadap pengaturan sistem operasi (`@media (prefers-reduced-motion: reduce)`).
- **Penyempurnaan Animasi Interaktif Beranda & Portal Login (`Home.vue` & `Login.vue`):**
  - **Beranda (`Home.vue`)**: Interaksi 3D tilt parallax pada kartu mockup hero dengan respon pergerakan kursor mouse, floating pills multi-layer, animasi ambient glowing orbs berkala, dan micro-interaction spring pada kartu fitur.
  - **Portal Login (`Login.vue`)**: Interaksi 3D parallax pada panel informasi kiri, animasi live breathing bar chart keuangan, micro-interaction scale bounce pada toggle sandi, dan spring shake form saat validasi gagal.
- **Dokumentasi Lengkap Panduan Pengguna (User Manual) (`docs/USER_GUIDE.md`):**
  - Penyusunan dokumen panduan operasional komprehensif (35,7 KB, 452 baris) dalam Bahasa Indonesia mencakup seluruh 86 halaman dan alur kerja aplikasi siupk Next.
  - Dokumentasi lengkap untuk 24 bab: Mulai dari Autentikasi, Dashboard Drilldown, Master Data, Siklus Perguliran Pinjaman (6 tahapan), Akuntansi Double-Entry & Immutable Ledger, Inventaris Aset, E-Budgeting, 16 Laporan Keuangan & Piutang, Billing SaaS Multi-Gateway, Notifikasi WhatsApp, RBAC 37 permissions, Wizard Onboarding, Portal Supervisi Kabupaten/Provinsi, Superadmin SaaS, AI Assistant (Ariel), hingga Katalog 36 Dokumen Cetak PDF.
- **Restrukturisasi Indeks Dokumentasi (`docs/README.md` & `README.md`):**
  - Pengelompokan seluruh 15 dokumen teknis ke dalam 4 kategori terstruktur: Panduan Pengguna & Operasional, Arsitektur & Spesifikasi Sistem, Analisis Komparatif & Migrasi Legacy, serta Roadmap & Riwayat Pengujian.
- **Fitur Impersonasi Tenant Superadmin & Holding Sync API:**
  - Implementasi login impersonasi satu klik dari panel Superadmin ke akun tenant/pengguna dengan token temporer berbatas waktu (`TenantImpersonationService`).
  - Penambahan banner peringatan impersonasi aktif di `AuthenticatedLayout.vue` dengan tombol kembali ke akun superadmin.
  - Pembuatan endpoint API sinkronisasi tenant holding (`HoldingTenantSyncController`) dan panduan integrasi `docs/HOLDING_API_INTEGRATION_GUIDE.md`.
  - Penambahan tabel migrasi `tenant_impersonation_tokens` dan test suite `TenantImpersonationTest.php`.
- **API Laporan Keuangan untuk Integrasi Aplikasi Holding (`routes/api.php`):**
  - Implementasi rute RESTful API lengkap untuk integrasi aplikasi Holding / BUMDesma Induk yang mencakup 5 laporan keuangan utama:
    - **Neraca / Balance Sheet** (`/api/v1/holding/reports/balance-sheet` & `/api/v1/holding/tenants/{tenant}/reports/balance-sheet`).
    - **Laba Rugi / Income Statement** (`/api/v1/holding/reports/income-statement` & `/api/v1/holding/tenants/{tenant}/reports/income-statement`).
    - **Arus Kas / Cash Flow** (`/api/v1/holding/reports/cash-flow` & `/api/v1/holding/tenants/{tenant}/reports/cash-flow`).
    - **Catatan Atas Laporan Keuangan / CALK** (`/api/v1/holding/reports/calk` & `/api/v1/holding/tenants/{tenant}/reports/calk`).
    - **Perubahan Ekuitas / Modal** (`/api/v1/holding/reports/equity-changes` & `/api/v1/holding/tenants/{tenant}/reports/equity-changes`).
    - **Paket Lengkap / Financial Report Pack** (`/api/v1/holding/reports/pack` & `/api/v1/holding/tenants/{tenant}/reports/pack`) — mengembalikan 5 laporan keuangan sekaligus dalam 1 request roundtrip.
    - **Laporan Keuangan Konsolidasi** (`/api/v1/holding/reports/consolidated/*`) — laporan konsolidasi seluruh anak perusahaan / unit usaha holding.
    - **Direktori Anak Usaha / Tenants Discovery** (`/api/v1/holding/tenants` & `/api/v1/holding/tenants/{tenant}`).
  - Controller `App\Http\Controllers\Api\Holding\HoldingReportController` dan `App\Http\Controllers\Api\Holding\HoldingTenantController`.
  - Middleware otentikasi API Key `App\Http\Middleware\VerifyHoldingApiToken` (`holding.auth`) dengan dukungan Bearer token, header `X-Holding-Key`, `X-API-Key`, query parameter `api_key`, dan platform supervisor bypass.
  - Automated feature test suite `tests/Feature/Api/HoldingReportApiTest.php` dengan 12 test cases yang lolos 100%.
- **Animasi Interaktif GSAP pada Landing Page & Login Portal:**
  - Integrasi library `gsap` pada `package.json` untuk micro-interaction dan transisi visual modern berstandar Material Design 3.
  - **Landing Page (`resources/js/Pages/Home.vue`)**:
    - Staggered timeline entrance untuk navbar, hero badge, headline gradient, deskripsi, tombol CTA, dan trust badges.
    - Continuous floating levitation & ambient glow blob pada kartu mockup portofolio dan floating feature badges.
    - Animated number counter yang menghitung naik secara dinamis saat section statistik masuk ke viewport (`IntersectionObserver`).
    - Staggered scroll-reveal animation untuk grid kartu fitur utama, alur kerja tahapan sistem, dan FAQ accordion.
  - **Login Portal (`resources/js/Pages/Auth/Login.vue`)**:
    - Ambient glowing floating circles dan animasi pertumbuhan grafik batang (*bar chart growth with back easing*) pada panel branding kiri.
    - Staggered entrance untuk header, input fields, checkbox, tombol submit, dan info bantuan pada panel form login.
    - Animasi horizontal shake interaktif pada container form ketika validasi submit gagal.
- **Export Laporan Keuangan Format Excel Native:**
  - Implementasi engine writer OpenXML/ZIP standalone tanpa dependensi luar (`App\Support\Excel\XlsxWriter` & `App\Support\Excel\ReportExcel`) untuk export 8 laporan akuntansi (Neraca, Laba Rugi, Arus Kas, Perubahan Ekuitas, CALK, Buku Besar, Neraca Saldo, dan Jurnal).
  - Format angka nominal otomatis dengan format mata uang Rupiah (`#,##0`).
  - Penambahan endpoint download Excel pada `ReportController.php` dan tombol aksi download pada `ReportPeriodFilter.vue`.
  - Penambahan unit/feature test di `tests/Feature/Accounting/Reports/ExcelExportTest.php`.
- **Opsi Pembulatan Angsuran Tambahan:**
  - Penambahan opsi pembulatan angsuran pinjaman ke Rp 500, Rp 1.000, Rp 5.000, Rp 10.000, dan Rp 50.000 pada pengaturan sistem (`resources/js/Pages/Settings/Index.vue`) dan kalkulasi jadwal angsuran pinjaman.
- **Pengujian E2E Live Chat Assistant:**
  - Penambahan skenario Playwright test `tests/e2e/live_chat_test.spec.ts` untuk memverifikasi streaming respon Server-Sent Events (SSE) AI assistant secara end-to-end.
- **Autentikasi CI/CD Composer untuk Private Package:**
  - Penambahan environment variable `COMPOSER_AUTH` pada workflow deployment (`.github/workflows/deploy.yml`) untuk otorisasi download private package `enpii/assistant`.

### Changed
- **Pembaruan Package `enpii/assistant`:**
  - Pembaruan dependensi `enpii/assistant` ke commit terbaru (`2c676f0`) pada `composer.json` & `composer.lock`.
  - Penyesuaian `App\Http\Controllers\Admin\AiAssistantController::chatStream` dan rute `/assistant/*` agar sesuai dengan signature `AgentLoop::run(...)` dan `SseEmitter` terbaru dari package.
  - Penyesuaian urutan navigasi sidebar RBAC pada `resources/js/Layouts/AuthenticatedLayout.vue` ("Manajemen Role" sebelum "Manajemen User").
  - Form entri jurnal umum (`resources/js/Pages/Accounting/JournalEntries/Create.vue`) disesuaikan menjadi lebar penuh (*full-width*) dan penyesuaian tombol "Cek riwayat/saldo".

### Fixed
- **Persistensi Status Notifikasi Terbaca & Handler Klik Notifikasi (Notification Center):**
  - Menambahkan kolom `notifications_read` (JSON) pada tabel `users` di database platform (`2026_08_20_130000_add_notifications_read_to_users_table.php`) dan casting `array` pada model `User.php`.
  - Memperbarui `NotificationCenterController.php` agar status dibaca (`readIds`) dibaca dan disimpan langsung ke database akun pengguna (`$user->notifications_read`) serta disinkronkan ke sesi aktif, sehingga status terbaca tetap bertahan permanen meskipun pengguna telah logout dan login kembali.
  - Memperbaiki `NotificationDropdown.vue` dengan membuat `handleItemClick` bersifat asynchronous (`await markAsRead(item.id)`) sebelum memicu `router.visit(item.target_url)`, menambahkan opsi `keepalive: true` pada API fetch mark-read, serta pembaruan status visual secara instan (*optimistic UI update*).
  - Memperbaiki teks rendering status memuat notifikasi.
  - Menambahkan test suite `NotificationCenterTest.php` untuk memvalidasi alur pembacaan, persistensi database lintas sesi/login, dan fitur tandai semua notifikasi terbaca.
- **Optimasi Key Transisi Halaman (Mencegah Kedipan / Blink pada Modal & Filter Query):**
  - Mengubah binding `:key` pada wrapper `<Transition name="page">` di seluruh layout dari `$page.url` menjadi `currentPath` (path tanpa query string).
  - Mencegah unmount/re-render seluruh halaman saat membuka/menutup modal pipeline dashboard (`?pipeline=...`) atau saat memfilter data dengan parameter URL sehingga modal terbuka/tertutup instan dan mulus tanpa kedipan layar putih.
- **Penyelarasan Kolom Percakapan AI (`ai_conversations`):**
  - Perbaikan pembuatan conversation pada `AiAssistantController` menggunakan `external_user_id` untuk mengatasi error SQLSTATE[23502] Not Null Violation pada database Postgres/RAG.
- **Migrasi Database RAG & Default:**
  - Eksekusi migrasi `2026_08_20_000001_add_message_attachments` untuk penambahan kolom `attachments_json` pada tabel `ai_messages` di database default dan `rag` (PostgreSQL).
- **Resolusi Domain Tenant:**
  - Optimalisasi pencocokan domain tenant pada `app/Tenancy/TenantResolver.php` dengan query yang database-agnostic.

### Removed
- **Pembersihan Folder Legacy `packages/assistant`:**
  - Menghapus folder `packages/assistant/` dan direktori `packages/` karena package telah dikelola via Composer (`vendor/enpii/assistant`).
  - Memperbarui seluruh referensi path migrasi dan rute di `app/Services/TenantRegistrationService.php`, `routes/web.php`, dan `.github/workflows/deploy.yml` ke `vendor/enpii/assistant/`.

## [2026-08-19]

### Added
- **Redesain Dashboard Admin & Monitor Pendapatan Tenant:**
  - Pemindahan chart tren pendapatan/penagihan tahunan ke halaman utama **Dashboard Admin Platform** (`resources/js/Pages/Admin/Dashboard.vue` & `app/Http/Controllers/Admin/DashboardController.php`) dengan metrik KPI bisnis komprehensif (Pendapatan Bulan Ini, Pertumbuhan MoM, Pendapatan YTD, Total Piutang Belum Bayar, dan Tagihan Terbuka).
  - Redesain halaman **Pendapatan** (`resources/js/Pages/Admin/Revenue/Index.vue` & `app/Http/Controllers/Admin/RevenueController.php`) menjadi **Billing Overview** dengan analitik invoice, perbandingan kuartal, dan tabel rincian transaksi berpaginasi.
- **Global Search Keyboard Shortcut Modal:**
  - Komponen `GlobalSearchModal.vue` dengan pintasan keyboard `Ctrl+K` / `Cmd+K` untuk navigasi cepat antar menu, aksi, data anggota, kelompok, dan invoice.
- **Tool AI Assistant WhatsApp Gateway:**
  - Tool baru `send_whatsapp_message` dan `check_whatsapp_status` pada AI Assistant untuk kirim pesan/notifikasi langsung via obrolan AI.

---

## [2026-08-18]

### Added
- **Fitur Koreksi Jurnal Akuntansi (Journal Edit & Reverse-and-Replace):**
  - Halaman edit jurnal akuntansi (`Accounting/JournalEntries/Edit.vue`) dengan riwayat alasan koreksi.
  - Service `JournalEditService` untuk pembatalan jurnal lama (reverse) dan pembuatan entri jurnal revisi secara atomik.
- **Fitur Saldo Awal Manual & Jurnal Agregat Mid-Year:**
  - Modul input saldo awal manual per tahun fiskal pada Import Wizard.
  - Form posting jurnal agregat mid-year untuk migrasi pembukuan berjalan.
- **Fitur Pembatalan Reschedule Pinjaman:**
  - Endpoint & request validation `LoanRescheduleCancelRequest` untuk membatalkan status restrukturisasi pinjaman yang belum diproses.
- **Manajemen Revenue Platform Admin:**
  - Halaman dan controller `RevenueController` (`Admin/Revenue/Index.vue`) untuk monitoring omzet langganan platform per tenant.

### Changed
- **Pembersihan Hardcoded Color ke Token MD3:**
  - Penggantian total kelas warna hardcoded Tailwind (`gray-*`, `slate-*`, `emerald-*`, `amber-*`, `red-*`, `rose-*`, `blue-*`) ke token semantik Material Design 3 (`surface-*`, `primary-*`, `secondary-*`, `tertiary-*`, `error-*`).

---

## [2026-08-15]

### Added
- **Penghapusan Pemanfaat Pinjaman (Write-Off Individual):**
  - Dukungan penghapusan pemanfaat macet tingkat anggota individu dalam kelompok pinjaman SPP/UEP tanpa menghapus seluruh kelompok.
- **Dukungan Desimal & Format Koma Rupiah:**
  - Komponen `AppCurrencyInput` mendukung input angka desimal bertanda koma sesuai format standar Indonesia.

### Fixed
- **AI Assistant Tooling & Payload:**
  - Perbaikan pemanggilan skema tool handler `jsonSchema()` pada sinkronisasi tool AI Assistant.
  - Penyesuaian fallback conversation dan detail dokumen regulasi di `AiAssistantController`.
- **Kalkulasi Portofolio & Pinjaman Aktif:**
  - Sinkronisasi perhitungan jumlah pinjaman aktif dan sisa pokok piutang pada dashboard tenant.

---

## [2026-08-14]

### Added
- **Sistem Notifikasi WhatsApp Gateway:**
  - Pengiriman pesan otomatis untuk jadwal angsuran, konfirmasi pembayaran, dan tagihan invoice.
- **Penyempurnaan Modul Onboarding & Migrasi Shard:**
  - Runner cutover data eksisting (siupk Access / Excel) dengan validasi akun debit-kredit otomatis.
  - Peningkatan idempotensi loader master data anggota dan kelompok.
- **Komponen UI Baru:**
  - `AppFilterPill.vue` untuk filter status interaktif.
  - `AppTabs.vue` untuk navigasi tab modular.

---

## [2026-07-26]

### Added
- **Konfigurasi Pembulatan Angsuran (Rounding Methods):**
  - Opsi pembulatan pinjaman: `decimal_2`, `rupiah_bersih`, `ceil_100`, `floor_100`, serta nominal kelipatan ratusan hingga puluhan ribu.
- **Isolasi Sharding Multi-Tenant:**
  - Arsitektur basis data terpisah per tenant BUMDesma dengan koneksi dinamis.
