<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Support\Csv;
use App\Tenancy\Services\TenantSequenceService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantOnboardingService
{
    /**
     * Post journal saldo awal / opening balances.
     *
     * @param  array<int, array{account_row_id: int, debit: float, credit: float}>  $lines
     */
    public function saveOpeningBalances(array $lines, string $asOfDate, int $userId): JournalEntry
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $validLines = [];

        foreach ($lines as $line) {
            $accountRowId = (int) ($line['account_row_id'] ?? 0);
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($accountRowId <= 0 || ($debit <= 0 && $credit <= 0)) {
                continue;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
            $validLines[] = [
                'account_row_id' => $accountRowId,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (empty($validLines)) {
            throw new InvalidArgumentException('Minimal 1 baris saldo awal akun harus diisi.');
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new InvalidArgumentException(sprintf(
                'Saldo awal tidak imbang (Unbalanced)! Total Debit: Rp %s vs Total Kredit: Rp %s (Selisih: Rp %s).',
                number_format($totalDebit, 2, ',', '.'),
                number_format($totalCredit, 2, ',', '.'),
                number_format(abs($totalDebit - $totalCredit), 2, ',', '.'),
            ));
        }

        return DB::connection('tenant')->transaction(function () use ($validLines, $asOfDate, $userId): JournalEntry {
            $prefix = date('ym', strtotime($asOfDate));
            $seq = app(TenantSequenceService::class)->next('journal_number:'.$prefix);
            $journalNumber = $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

            $entry = JournalEntry::query()->create([
                'journal_number' => $journalNumber,
                'transaction_date' => $asOfDate,
                'transaction_type' => 'pemindahan_saldo',
                'description' => 'Posting Saldo Awal Keuangan Tenant Baru (Opening Balances - '.$asOfDate.')',
                'status' => 'posted',
                'posted_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $lineNumber = 1;
            foreach ($validLines as $l) {
                JournalLine::query()->create([
                    'journal_entry_row_id' => $entry->row_id,
                    'line_number' => $lineNumber++,
                    'account_row_id' => $l['account_row_id'],
                    'debit' => $l['debit'],
                    'credit' => $l['credit'],
                    'description' => 'Saldo Awal',
                ]);
            }

            return $entry;
        });
    }

    /**
     * Import active loans with cumulative paid amounts allocated FIFO across monthly schedule.
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function importActiveLoans(UploadedFile $file, int $userId): array
    {
        [, $rows] = Csv::read($file);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $defaultProduct = LoanProduct::query()->where('is_active', true)->orderBy('row_id')->first();
        if ($defaultProduct === null) {
            throw new InvalidArgumentException('Produk pinjaman belum disetup di sistem.');
        }

        foreach ($rows as $row) {
            $line = (int) ($row['_line'] ?? 0);
            $spkNumber = trim((string) ($row['nomor_spk'] ?? $row['spk_number'] ?? ''));
            $nik = preg_replace('/\D+/', '', $row['nik_anggota'] ?? '') ?? '';
            $groupName = trim((string) ($row['nama_kelompok'] ?? ''));
            $disbursedAt = trim((string) ($row['tanggal_pencairan'] ?? ''));
            $principal = (float) ($row['plafon_pinjaman'] ?? 0);
            $interestRate = (float) ($row['bunga_persen'] ?? 10);
            $months = max(1, (int) ($row['jangka_bulan'] ?? 12));
            $principalPaid = (float) ($row['akumulasi_pokok_dibayar'] ?? $row['pokok_dibayar'] ?? 0);
            $interestPaid = (float) ($row['akumulasi_bunga_dibayar'] ?? $row['bunga_dibayar'] ?? 0);

            if ($spkNumber === '' && $principal <= 0) {
                $skipped++;

                continue;
            }

            if ($principal <= 0) {
                $errors[] = "Baris {$line}: Plafon pinjaman harus lebih dari 0.";

                continue;
            }

            if ($disbursedAt === '') {
                $disbursedAt = now()->toDateString();
            }

            // Find member or group
            $memberRowId = null;
            $groupRowId = null;

            if ($nik !== '') {
                $memberRowId = Person::query()
                    ->where('national_identity_number', $nik)
                    ->join('members', 'people.row_id', '=', 'members.person_row_id')
                    ->value('members.row_id');
            }

            if ($groupName !== '') {
                $groupRowId = Group::query()->where('name', $groupName)->value('row_id');
            }

            if ($memberRowId === null && $groupRowId === null) {
                $errors[] = "Baris {$line}: Anggota (NIK {$nik}) atau Kelompok \"{$groupName}\" tidak ditemukan.";

                continue;
            }

            try {
                DB::connection('tenant')->transaction(function () use (
                    $spkNumber, $defaultProduct, $memberRowId, $groupRowId,
                    $disbursedAt, $principal, $interestRate, $months,
                    $principalPaid, $interestPaid
                ): void {
                    $loan = Loan::query()->create([
                        'legacy_source' => $memberRowId !== null ? 'member_loan' : 'group_loan',
                        'loan_product_row_id' => $defaultProduct->row_id,
                        'loan_number' => $spkNumber !== '' ? $spkNumber : 'ONB-'.random_int(100000, 999999),
                        'principal_amount' => $principal,
                        'interest_rate' => $interestRate,
                        'term_months' => $months,
                        'disbursed_at' => $disbursedAt,
                        'status' => 'disbursed',
                    ]);

                    LoanBorrower::query()->create([
                        'loan_row_id' => $loan->row_id,
                        'member_row_id' => $memberRowId,
                        'group_row_id' => $groupRowId,
                    ]);

                    $monthlyPrincipal = round($principal / $months, 2);
                    $totalInterest = round($principal * ($interestRate / 100), 2);
                    $monthlyInterest = round($totalInterest / $months, 2);

                    $remPrincipalPaid = $principalPaid;
                    $remInterestPaid = $interestPaid;
                    $startDate = Carbon::parse($disbursedAt);

                    for ($i = 1; $i <= $months; $i++) {
                        $dueDate = $startDate->copy()->addMonths($i)->toDateString();
                        $pDue = ($i === $months) ? round($principal - ($monthlyPrincipal * ($months - 1)), 2) : $monthlyPrincipal;
                        $iDue = ($i === $months) ? round($totalInterest - ($monthlyInterest * ($months - 1)), 2) : $monthlyInterest;

                        $pAlloc = min($pDue, $remPrincipalPaid);
                        $remPrincipalPaid = max(0.0, $remPrincipalPaid - $pAlloc);

                        $iAlloc = min($iDue, $remInterestPaid);
                        $remInterestPaid = max(0.0, $remInterestPaid - $iAlloc);

                        $isFullyPaid = ($pAlloc >= $pDue) && ($iAlloc >= $iDue);
                        $isPartiallyPaid = ($pAlloc > 0 || $iAlloc > 0) && ! $isFullyPaid;

                        LoanInstallment::query()->create([
                            'loan_row_id' => $loan->row_id,
                            'installment_number' => $i,
                            'due_date' => $dueDate,
                            'principal_due' => $pDue,
                            'interest_due' => $iDue,
                            'principal_paid' => $pAlloc,
                            'interest_paid' => $iAlloc,
                            'penalty_due' => 0,
                            'penalty_paid' => 0,
                            'status' => $isFullyPaid ? 'paid' : ($isPartiallyPaid ? 'partially_paid' : 'pending'),
                            'paid_at' => $isFullyPaid ? now() : null,
                        ]);
                    }
                });

                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = "Baris {$line}: ".$exception->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * Download CSV template file for onboarding data.
     */
    public function downloadCsvTemplate(string $type): StreamedResponse
    {
        // Canonicalize English variants to the Indonesian type names so that
        // /onboarding/templates/{type} accepts both `members` and `anggota`,
        // `groups` / `kelompok`, `active-loans` / `pinjaman-aktif`, dst.
        $type = match ($type) {
            'members', 'member' => 'anggota',
            'groups', 'group', 'kelompoks' => 'kelompok',
            'active-loans', 'active-loan', 'loans-active' => 'pinjaman-aktif',
            'opening-balances', 'opening-balance' => 'saldo-awal',
            'assets', 'asset', 'fixed-assets' => 'aset-tetap',
            default => $type,
        };

        return match ($type) {
            'saldo-awal' => Csv::download('template_saldo_awal.csv', [
                'kode_akun', 'nama_akun', 'debit', 'kredit',
            ], [
                ['1.1.01.01', 'Kas Kantor', '10000000', '0'],
                ['1.1.02.01', 'Bank BRI', '25000000', '0'],
                ['3.1.01.01', 'Modal Diterima', '0', '35000000'],
            ]),

            'anggota' => Csv::download('template_anggota.csv', [
                'nik', 'nama', 'jenis_kelamin', 'alamat', 'desa', 'no_hp', 'status',
            ], [
                ['3515011203900001', 'Siti Aminah', 'P', 'Jl. Mawar No. 12', 'Desa Maju', '081234567890', 'active'],
                ['3515011203900002', 'Budi Santoso', 'L', 'RT 02 RW 01', 'Desa Maju', '081987654321', 'active'],
            ]),

            'kelompok' => Csv::download('template_kelompok.csv', [
                'nama', 'desa', 'alamat', 'no_hp',
            ], [
                ['Kelompok Melati 01', 'Desa Maju', 'RT 01 RW 01', '081234567800'],
                ['Kelompok Seroja 02', 'Desa Makmur', 'RT 03 RW 02', '081234567801'],
            ]),

            'pinjaman-aktif' => Csv::download('template_pinjaman_aktif.csv', [
                'nomor_spk', 'nik_anggota', 'nama_kelompok', 'tanggal_pencairan', 'plafon_pinjaman', 'bunga_persen', 'jangka_bulan', 'akumulasi_pokok_dibayar', 'akumulasi_bunga_dibayar',
            ], [
                ['SPK-2025-001', '3515011203900001', 'Kelompok Melati 01', '2025-06-15', '5000000', '10', '10', '2000000', '200000'],
            ]),

            'aset-tetap' => Csv::download('template_aset_tetap.csv', [
                'nama_barang', 'tanggal_perolehan', 'harga_perolehan', 'akumulasi_penyusutan_awal', 'umur_ekonomis_bulan',
            ], [
                ['Laptop HP ProBook', '2024-01-10', '8500000', '1700000', '48'],
                ['Sepeda Motor Honda Beat', '2023-05-20', '18000000', '7200000', '60'],
            ]),

            default => throw new InvalidArgumentException("Tipe template '{$type}' tidak dikenal."),
        };
    }
}
