# Cancel Reschedule Pinjaman — SIUPK Next 2026-08-18

> **Pertanyaan user:** "bagaimana jika saya mau membatalkan rescheduling?
> maksudnya saya sudah melakukan rescheduling pinjaman dan pinjaman baru sudah
> terbit. tapi kemudian saya mau membatalkannya"

## Latar Belakang

Operator dapat membatalkan reschedule yang baru saja dilakukan, asalkan belum
ada angsuran dibayar di pinjaman baru. Tujuan: recovery dari kesalahan input
parameter reschedule (mis. suku bunga) tanpa meninggalkan jejak jurnal yang
merusak laporan.

## Mekanisme

`LoanService::reschedule()` membuat **2 jurnal posted** yang saling offset:

| Jurnal | Source Type | Loan | Debit | Credit |
|---|---|---|---|---|
| Angs. Resc. | `loan_reschedule_close` | Loan A (lama) | Kas | Piutang |
| Pencairan Resc. | `loan_reschedule_open` | Loan B (baru) | Piutang | Kas |

Cancel Reschedule mengembalikan efek dua jurnal tersebut dengan cara:

1. **Hard-delete kedua jurnal** via raw query (bypass `JournalEntry` immutability
   hooks untuk posted entry — cancellation adalah operasi reverse yang sah).
2. **Hard-delete Loan B** (cascade: beneficiaries, installments, committees,
   borrower).
3. **Restore Loan A** ke status sebelum reschedule (`active`/`disbursed`) +
   append history entry.

## Perubahan Skema

- `loans.rescheduled_from_loan_row_id` (FK self-reference, nullable)

## Modifikasi Model

- `Loan::rescheduledFrom()` (belongsTo self)
- `JournalEntry`: TIDAK diubah — original `deleting`/`updating` hooks tetap
  menolak mutasi posted entry. Service pakai `DB::table()->delete()` raw query
  untuk bypass di konteks cancel reschedule saja.

## Verifikasi

8 test PASS di `tests/Feature/Lending/LoanRescheduleCancelTest.php`:

1. Happy path: cancel → old loan restored, new loan hard-deleted, 2 jurnal
   hard-deleted, history recorded.
2. Reject ketika `principal_paid > 0`.
3. Validation: reason required (min 5 char).
4. Reject loan yang bukan hasil reschedule.
5. Permission `loans.manage` required.
6. Double cancel cycle (cancel → re-reschedule → cancel lagi).
7. Reject loan status di luar `active`/`disbursed`.
8. History entry `from=rescheduled`, `to=active`, notes contain reason.

Tidak ada regresi: 66 lending + accounting tests tetap PASS.

## API

```
POST /lending/loans/{loan}/cancel-reschedule
Body: { reason: string (5..5000 chars) }
Auth: loans.manage
```

## Edge Cases

- **FK self-reference** ke `loans.row_id` aman di SQLite (test) dan MySQL
  (prod).
- **`loan_payments` restrictOnDelete** aman karena precondition
  `principal_paid == 0` ensures tidak ada payments.
- **`loan_status_histories`** orphan rows acceptable (no FK ke loans).
- **Sequence number** loan baru tidak di-reuse setelah cancel → gap di sequence
  acceptable.

## Trade-off: Hard vs Soft Delete

Keputusan: hard-delete jurnal reschedule. Alasan:

- Jumlah baris kecil (2 jurnal per cancel), tidak ada value untuk audit trail
  via `deleted_at` karena:
  - `loan_status_histories` sudah mencatat notes "Cancel reschedule" sebagai
    audit trail utama.
  - Tidak ada business case untuk restore jurnal yang sudah di-cancel.
- Menghindari overhead kolom `deleted_at` di `journal_entries` (yang dipakai
  banyak tempat) dan kompleksitas trait `SoftDeletes`.
