# GAP ANALYSIS: Pinjaman Individu — siupk (pacuan) vs siupknext

Tanggal analisis: 2026-09-18
Tanggal update status: 2026-09-22 — **PARITAS LOGIKA PENCAPAIAN: jadwal angsuran 1:1 dengan pacuan; alur penuh (P/V/W/A/L/R/H/T/revert) selesai; 14 test paritas pass (111 assertions)**.

Lihat juga:
- `tests/Feature/Lending/MemberLoanLifecycleTest.php` — 14 test paritas
- `app/Domain/Lending/Services/MemberLoanScheduleCalculator.php` — salinan rumus pacuan line 2317-2607
- `CHANGELOG.md` [Unreleased] — detail teknis seluruh perubahan

Spesifikasi sumber: `C:\laragon\www\siupk\app\Http\Controllers\PinjamanIndividuController.php` (2845 baris) + `PinjamanAnggotaController.php`
Spesifikasi target: `C:\laragon\www\siupknext\app\Http\Controllers\Lending\LoanController.php` + `app\Domain\Lending\Services\LoanService.php`

---

## A. KONVENSI STATUS

| Kode | Arti (pacuan) | Status Next | Catatan |
|---|---|---|---|
| `P` | Proposal (pengajuan) | `draft` atau `proposed` | Pacuan: awal. Next: `draft` setelah create |
| `V` | Verifikasi (sudah diverifikasi, menunggu pencairan) | `verified` | |
| `W` | Waiting (dana sudah disiapkan, menunggu tgl cair) | `approved` | |
| `A` | Aktif (sudah cair, angsuran berjalan) | `active` / `disbursed` | |
| `L` | Lunas | `completed` | |
| `R` | Reschedule (di-reshedule ke pinjaman baru) | `rescheduled` | |
| `H` | Hapus / Write-off | `written_off` | |
| `T` | Tidak Layak (verifikasi menolak) | `rejected` | **Tidak ada di Next** |
| `0` | Edit proposal (sebelum verifikasi) | `edit_proposal` (view state) | view-only, bukan status |

Pacuan punya **9 status**, Next punya **8 status** (tidak ada `T`/rejected).

---

## B. ALUR BISNIS (END-TO-END)

| Step | Pacuan | Next (saat ini) | Status |
|---|---|---|---|
| 1. Pilih anggota + form proposal | `register($id_angg)` + view `pinjaman_i.register` | `individualCreate` | OK (parsial) |
| 2. Simpan proposal (status `P`) | `store()` → insert baris + insert `DataPemanfaat` | `individualStore` → `createMemberProposal` | OK (tanpa DataPemanfaat, tanpa jaminan) |
| 3. Generate Rencana Angsuran (RA) | `generate($id, true)` dengan rumus **PACUAN SPESIAL** | `generatePrincipalSchedule` + `generateInterestSchedule` | **TIDAK SAMA** |
| 4. Verifikasi proposal | form `verifikasi` → `update(status=V)` | `verify()` kelompok only | **BELUM ADA untuk individu** |
| 5. Persetujuan pencairan (waiting) | `update(status=W)` → generate RA | `approve()` kelompok only | **BELUM ADA untuk individu** |
| 6. Pencairan (status A) | `simpan()` set tgl_cair & spk_no → `generate()` | `disburse()` kelompok only | **BELUM ADA untuk individu** |
| 7. Bayar angsuran | lewat `TransaksiController::angsuran` dengan link ke `id_pinj_i` | `LoanPayment` + `LoanInstallmentJournal` | **BELUM ADA logic angsuran individu** |
| 8. Pelunasan | `update(status=L)` + auto-generate transaksi | `complete()` kelompok only | **BELUM ADA untuk individu** |
| 9. Kembali ke Proposal (revert) | `kembaliProposal()` → status `P` | tidak ada | **BELUM** |
| 10. Tolak (Tidak Layak) | `tidakLayak()` → status `T` | tidak ada | **BELUM** |
| 11. Reschedule | `rescedule()` → buat pinjaman baru dari sisa saldo | `reschedule()` + `cancelReschedule()` kelompok only | **BELUM untuk individu** |
| 12. Hapus / Write-off | `hapus()` → buat transaksi hapus | `writeOff()` kelompok only | **BELUM untuk individu** |
| 13. Cetak Kartu Angsuran | `kartuAngsuran()` + `cetakKartuAngsuranAnggota()` | `individualCard()` (stub) | **STUB** |
| 14. Cetak Keterangan Lunas | `keterangan()` | tidak ada | **BELUM** |
| 15. Generate semua dokumen PDF (cover proposal, BA musyawarah, verifikasi, SPK, kuitansi, dll) | 30+ method `coverProposal/baMusyawarahDesa/suratPengajuanPinjaman/...` | tidak ada | **BELUM** |

Total method pacuan: **77 method** (66 method utama + beberapa private/internal).
Total method individu di Next: **5 method** (`individualIndex/Create/Store/Show/Card` + 5 private helper).

---

## C. LOGIKA HITUNG ANGSURAN — INI KRUSIAL

### Pacuan (`generate()` line 2317-2607):

**Input:**
- `alokasi`, `tgl`, `jangka`, `pros_jasa`, `jenis_jasa`, `sistem_angsuran_pokok`, `sistem_angsuran_jasa`
- Override tanggal jatuh tempo dari `anggota->d->jadwal_angsuran_desa`
- `Kecamatan::batas_angsuran` → kalau tanggal cair >= batas, mundur 1 bulan
- `Kecamatan::pembulatan` → mode pembulatan (5000 default, atau +5000/-5000)

**Tahap 1 — Hitung JASA:**
1. Tentukan `tempo_jasa`:
   - jika `sa_jasa == 11` → `jangka - 24 / sistem_jasa`
   - jika `sa_jasa == 14` → `jangka - 3 / sistem_jasa`
   - jika `sa_jasa == 15` → `jangka - 2 / sistem_jasa`
   - jika `sa_jasa == 20` → `jangka - 12 / sistem_jasa`
   - else → `floor(jangka / sistem_jasa)`
2. `alokasi_jasa = alokasi_pokok * (pros_jasa / 100)` — TOTAL jasa, bukan per periode
3. `wajib_jasa = alokasi_jasa / tempo_jasa`
4. Bulatkan per `Keuangan::pembulatan($kec->pembulatan)`
5. Loop `j=1..jangka`: `sisa = j % sistem_jasa`, `ke = j / sistem_jasa`
   - Kalau `jenis_jasa == 2` (sliding/effective): `alokasi_pokok -= ra[j]['pokok']` dulu, baru hitung `angsuran_jasa = wajib_jasa`
   - Kalau flat: `angsuran_jasa = wajib_jasa` (konstan tiap periode angsuran jasa)
   - Angsuran terakhir = selisih agar total pas

**Tahap 2 — Hitung POKOK:**
1. Tentukan `tempo_pokok` (sama pola)
2. **SPESIAL: kalau `jangka == 24`** — pakai rumus magic:
   - `wajib_pokok = pembulatan((alokasi/10 - jasa) / 2, -500)` untuk alokasi <= 1.000.000
   - `wajib_pokok = pembulatan((alokasi/10 - jasa) / 2, 5000)` untuk alokasi > 1.000.000
   - Plus adjustment ±5000 berdasarkan nominal alokasi (8 juta, 12 juta, 14 juta, 18 juta, 6 juta)
3. Else: `wajib_pokok = pembulatan(alokasi / tempo_pokok, pembulatan)`
4. Sama pola flat: angsuran pokok konstan, angsuran terakhir = selisih

**Tahap 3 — TANGGAL JATUH TEMPO:**
- `sa_pokok == 12` → mingguan (`+$x * 7 days`)
- else → bulanan (`+$x month`)
- End-of-month handling: kalau tanggal cair > hari terakhir bulan target, pakai tanggal terakhir bulan

**Tahap 4 — SIMPAN:**
- Insert `RencanaAngsuranI` baris ke-0 (header tanggal) + 1..jangka
- Setiap baris punya `wajib_pokok`, `wajib_jasa`, `target_pokok`, `target_jasa` (running total)

### Next (`generatePrincipalSchedule` + `generateInterestSchedule`):

**Logika flat/sliding generik:**
- Pakai `service_rate_total / periods` → `principalRatePerPeriod`
- Generate baris dengan `principal_due`, `interest_due`, `due_date`
- **TIDAK ada**: logika khusus `jangka==24`, pembulatan per kecamatan, override jadwal desa, `batas_angsuran`, mode mingguan (`sa_pokok == 12`), adjustment magic 5000-an.

**Kesimpulan: logika hitung TIDAK 1:1**. Untuk individu, harus ada servis terpisah `generateRencanaAngsuranIndividu()` yang **persis copy** rumus pacuan di atas.

---

## D. KOLOM DATA YANG BELUM ADA DI NEXT

Pacuan `PinjamanIndividu` (dynamic table `pinjaman_anggota_{lokasi}`):
- `jenis_pinjaman` (=`'I'`) → di Next: `legacy_source='member_loan'`
- `id_kel` (=`'0'`) → di Next: `loan_borrowers.group_row_id` nullable
- `id_pinkel` (=`'0'`) → tidak ada di Next (loan ini bukan bagian dari pinkel kelompok)
- `jenis_pp` → di Next: `loan_product_row_id`
- `nia` → di Next: `loan_borrowers.member_row_id`
- `tgl_proposal`, `tgl_verifikasi`, `tgl_dana`, `tgl_tunggu`, `tgl_cair`, `tgl_lunas` → Next punya `proposed_at`, `disbursed_at`, `completed_at` tapi **TIDAK ada** `tgl_verifikasi` dan `tgl_tunggu` terpisah
- `proposal`, `verifikasi`, `alokasi` (3 nominal terpisah) → Next: hanya `principal_amount`
- `kom_pokok`, `kom_jasa` → tidak ada di Next
- `spk_no` → tidak ada di Next
- `sumber` → tidak ada
- `pros_jasa` → di Next: `service_rate_total`
- `jenis_jasa` (=`1` flat, `=2` sliding) → di Next: `installment_method` enum beda
- `jangka` → di Next: `term_months`
- `sistem_angsuran` (=11, 12, 14, 15, 20) → di Next: `principal_frequency` enum beda
- `sa_jasa` → di Next: `interest_frequency` enum beda
- `status` → di Next: `status` enum beda (lihat tabel A)
- `jaminan` (JSON) → **TIDAK ADA di Next** — pacuan simpan jaminan sebagai JSON string
- `catatan_verifikasi` → tidak ada
- `wt_cair` (waktu+tempat pencairan) → tidak ada

**Kekurangan utama:**
1. **Jaminan (collateral)** — JSON field di pacuan, tidak ada di schema Next → butuh tabel `loan_collaterals` atau kolom JSON di `loans`
2. **3 nominal terpisah** (proposal/verifikasi/alokasi) — di Next hanya 1 nominal final
3. **Tanggal terpisah** — verifikasi, waiting tidak punya kolom tanggal
4. **`sumber`** (sumber dana/rekening) — tidak ada
5. **SPK No.** — tidak ada
6. **`wt_cair`** (waktu & tempat pencairan) — tidak ada

---

## E. INTEGRASI ACCOUNTING / TRANSAKSI

Pacuan: setiap perubahan status buat **Transaksi** dengan kode rekening terformat:
- `1.1.01.{jpp_kode+1}` (kas)
- `1.1.03.{jpp_kode}` (piutang)
- `1.1.04.{jpp_kode}` (pinjaman dihapus)

Pacuan langsung tulis ke tabel `transaksi` dengan field `id_pinj_i` (FK ke pinjaman individu) atau `id_pinj` (FK ke kelompok).

Next: ada `LoanInstallmentJournalRequest`, `LoanPayment`, `LoanPaymentAllocation`. **Belum diverifikasi** apakah model-model ini handle `id_pinj_i` (individu) atau hanya kelompok.

---

## F. CASH TRANCHE / DROP TENOR

Pacuan membedakan **PinjamanBertahap** (cash bertahap) — field `tgl_dana` vs `tgl_cair`. Next punya `installment_method='cash_tranche'` (perlu cek). Belum diverifikasi apakah behavior-nya match.

---

## G. YANG SUDAH BENAR DI NEXT UNTUK INDIVIDU

| Aspek | Status |
|---|---|
| Tabel unified `loans` dengan `legacy_source` discriminator | OK |
| `loan_borrowers.member_row_id` nullable (untuk individu) | OK |
| `loan_product.borrower_scope` enum | OK |
| CHECK constraint `chk_loan_borrower_one_owner` | OK |
| Sequence `loans:member_loan` | OK |
| Halaman Index (5 tab) dengan SmartDataTable | OK |
| Form registrasi proposal (tanpa pengurus/pemanfaat) | OK |
| Halaman Show dengan ringkasan + jadwal + history | OK |
| Route terpisah `/lending/member-loans/*` | OK |
| Sidebar menu Pinjaman/Perguliran dengan sub-menu gabung | OK |

---

## H. PRIORITAS IMPLEMENTASI UNTUK 1:1 DENGAN SIUPK

### PRIORITAS TINGGI (blokir alur kerja nyata):
1. **Servis `MemberLoanService::generateSchedule()`** — copy rumus `generate()` pacuan (pembulatan, sistem angsuran, jangka==24 magic, jadwal desa, batas angsuran, mode mingguan).
2. **Method controller** (view + update) untuk status V (verifikasi), W (waiting/approved), A (active/disbursed).
3. **Method `complete` (lunas)** + **method `tidakLayak` (rejected)** + **method `kembaliProposal` (revert)**.
4. **Tabel/kolom collateral (jaminan)** — JSON di `loans` atau tabel baru.
5. **Tanggal verifikasi & waiting** — tambah kolom `verified_at`, `approved_at`.
6. **Field `spk_no`, `wt_cair`, `sumber`** — tambah kolom.

### PRIORITAS SEDANG:
7. **Kartu Angsuran individu** — Blade view samakan dengan `cetak_kartu_angsuran.blade.php` pacuan (per kategori: P, V, W, A, L).
8. **Keterangan Lunas** — Blade view samakan `cetak_keterangan.blade.php`.
9. **Bayar angsuran individu** — link ke LoanPayment existing + test id_pinj_i flow.
10. **Reschedule individu** — logika `rescedule()` pacuan.
11. **Write-off individu** — logika `hapus()` pacuan.

### PRIORITAS RENDAH (dokumen PDF):
12. 30+ dokumen PDF (cover proposal, BA musyawarah, verifikasi, SPK, kuitansi, dll) — bisa di-skip dulu atau di-batch terakhir.

---

## I. RENCANA EKSEKUSI

Tahap 1 — Schema migration: ✅ SELESAI
- ✅ Kolom `verified_at`, `approved_at` sudah ada (sebelumnya).
- ✅ Kolom `spk_no`, `disbursement_slot`, `funding_source` sudah ada di migration `2026_09_18_000001_add_individual_loan_fields.php`.
- ✅ Kolom `collateral` (JSON), `verification_remarks` sudah ada.
- ✅ Status `rejected` di-handle oleh `LoanService::rejectMemberLoan()` — schema `loans.status` adalah string tanpa enum constraint, jadi `rejected` sudah bisa ditulis.
- ✅ Kolom `running_principal` & `running_interest` di `loan_installments` ditambah di migration `2026_09_22_000001_add_running_totals_to_loan_installments.php` (paritas `target_pokok`/`target_jasa` pacuan).

Tahap 2 — Servis `MemberLoanScheduleCalculator`: ✅ SELESAI
- ✅ Rumus pacuan line 2317-2607 disalin 1:1 — `tempo_pokok`/`tempo_jasa`, magic `jangka==24`, mode mingguan (sistem 12 → +x*7 days), override jadwal desa, `batas_angsuran`, end-of-month handling.
- ✅ Test 12/24 bulan dengan flat & sliding — `test_create_member_proposal_persists_collateral_and_individual_schedule`, `test_member_proposal_schedule_handles_jangka_24_with_magic_formula`, `test_individual_schedule_uses_weekly_dates_for_sistem_12`.

Tahap 3 — Controller methods V/W/A/L/R/H/T: ✅ SELESAI
- ✅ `individualVerify`, `individualApprove`, `individualDisburse`, `individualRevert`, `individualReject`, `individualComplete`, `individualWriteOff`, `individualReschedule`, `individualCancelReschedule`.
- ✅ Guard `legacy_source='member_loan'` di tiap method controller.
- ✅ Test `test_verify_to_waiting_to_disburse_to_complete_lifecycle_for_individual`, `test_reject_member_loan_sets_rejected_status_only_from_pre_active_status`, `test_write_off_for_individual_creates_pacuan_style_journal`, `test_reschedule_for_individual_uses_pacuan_schedule_calculator`, `test_revert_member_loan_returns_to_draft_status`.

Tahap 4 — Bayar angsuran individu: ✅ SELESAI
- ✅ `recordInstallmentPayment()` di `LoanService` sudah generik — handle individu & kelompok via `loan_row_id` (menggantikan `id_pinj_i` pacuan).
- ✅ Test `test_record_installment_payment_for_individual_posts_correct_journal` — jurnal debit kas, kredit piutang+jasa+denda (paritas `TransaksiController::angsuran` pacuan).

Tahap 5 — Kartu angsuran PDF: ✅ SELESAI
- ✅ `individualCard` route + `MemberLoanCardService` + Blade `reports.pdf.loan_card_member`.
- ✅ Test `test_member_loan_card_service_renders_pdf_for_individual`.

Tahap 6 — Keterangan Lunas PDF: ✅ SELESAI
- ✅ `individualSettlementLetter` route + Blade `reports.pdf.loan_settlement_member`.

Tahap 7 — Dokumen PDF batch (30+): ⏳ BELUM
- ⏳ `coverProposal`, `BA musyawarah`, `verifikasi`, `SPK`, `kuitansi`, dll. — di luar scope sesi ini. Dapat di-batch di iterasi berikutnya jika diperlukan untuk paritas penuh.
