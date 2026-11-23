<?php

declare(strict_types=1);

namespace App\Domain\Migration\Lending;

use App\Domain\Migration\Lending\DTO\NormalizedBeneficiary;
use App\Domain\Migration\Lending\DTO\NormalizedGroupLoan;
use App\Domain\Migration\Lending\DTO\NormalizedInstallment;
use App\Domain\Migration\Lending\DTO\NormalizedMemberInstallment;
use App\Domain\Migration\Lending\DTO\NormalizedMemberLoan;
use App\Domain\Migration\Lending\DTO\NormalizedMemberPayment;
use App\Domain\Migration\Lending\DTO\NormalizedPayment;
use App\Domain\Migration\Membership\Support\LegacyRow;
use App\Domain\Migration\Support\LegacyAmountParser;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyLendingNormalizer
{
    /** @var array<int, int> jenis_pp => product row_id */
    private array $productsByJenis = [];

    /** @var array<int, string> */
    private array $productCodes = [];

    private int $individualProductRowId = 0;

    public function __construct(
        private TenantContext $context,
        private LegacyAmountParser $amounts,
    ) {}

    public function warmCaches(): void
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $rows = DB::connection($conn)->table('loan_products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['row_id', 'code']);

        $byCode = [];
        foreach ($rows as $r) {
            $byCode[strtolower((string) $r->code)] = (int) $r->row_id;
            $this->productCodes[(int) $r->row_id] = strtolower((string) $r->code);
        }

        // legacy jenis_pp: 1=SPP, 2=UEP, 3=PL (observed)
        $this->productsByJenis = [
            1 => $byCode['spp'] ?? (array_values($byCode)[0] ?? 0),
            2 => $byCode['uep'] ?? ($byCode['spp'] ?? 0),
            3 => $byCode['pl'] ?? ($byCode['spp'] ?? 0),
        ];
        $this->individualProductRowId = $byCode['pi'] ?? 0;
    }

    /**
     * @return array{ok: NormalizedGroupLoan|null, error: string|null, skip: bool}
     */
    public function normalizeGroupLoan(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => 'pinjaman_kelompok missing id', 'skip' => false];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'loan')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $groupLegacyId = (int) ($row->id_kel ?? 0);
        if ($groupLegacyId <= 0) {
            return ['ok' => null, 'error' => "pk id={$legacyId}: missing id_kel", 'skip' => false];
        }

        $jenisPp = (int) ($row->jenis_pp ?? 1);
        $productRowId = $this->productsByJenis[$jenisPp] ?? ($this->productsByJenis[1] ?? 0);
        if ($productRowId <= 0) {
            return ['ok' => null, 'error' => "pk id={$legacyId}: no loan product for jenis_pp={$jenisPp}", 'skip' => false];
        }

        try {
            $principal = $this->parseMoney($row->alokasi ?? $row->verifikasi ?? $row->proposal ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "pk id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        $rateRaw = LegacyRow::str($row, ['pros_jasa']) ?? '0';
        try {
            $rate = $this->parseMoney($rateRaw);
        } catch (InvalidArgumentException) {
            $rate = '0.00';
        }

        $term = max(0, (int) (LegacyRow::str($row, ['jangka']) ?? '0'));
        $seq = max(1, (int) ($row->pinjaman_ke ?? 1));
        $status = $this->mapStatus(LegacyRow::str($row, ['status']));
        $method = self::mapInstallmentMethod(LegacyRow::str($row, ['jenis_jasa']));
        $system = self::mapInstallmentSystem(
            (int) (LegacyRow::str($row, ['sistem_angsuran']) ?? '0'),
            (int) (LegacyRow::str($row, ['sa_jasa']) ?? '0'),
        );

        $spk = LegacyRow::str($row, ['spk_no']);
        $loanNumber = $spk !== null && $spk !== '' ? $spk : ('PK-'.$legacyId);

        return [
            'ok' => new NormalizedGroupLoan(
                legacyId: $legacyId,
                sequenceNumber: $seq,
                groupLegacyId: $groupLegacyId,
                productRowId: $productRowId,
                productCode: $this->productCodes[$productRowId] ?? 'spp',
                principal: $principal,
                interestRate: $rate,
                termMonths: $term,
                installmentMethod: $method,
                principalFrequency: $system['principal_frequency'],
                interestFrequency: $system['interest_frequency'],
                principalGraceMonths: $system['principal_grace_months'],
                interestGraceMonths: $system['interest_grace_months'],
                status: $status,
                proposedAt: LegacyRow::date($row, ['tgl_proposal']),
                verifiedAt: LegacyRow::date($row, ['tgl_verifikasi']),
                approvedAt: LegacyRow::date($row, ['tgl_dana']),
                fundedAt: LegacyRow::date($row, ['tgl_tunggu']),
                disbursedAt: LegacyRow::date($row, ['tgl_cair']),
                completedAt: LegacyRow::date($row, ['tgl_lunas']),
                loanNumber: $loanNumber,
                verificationNotes: LegacyRow::str($row, ['catatan_verifikasi']),
                guidanceNotes: LegacyRow::str($row, ['catatan_bimbingan']),
                verificationTime: $this->normalizeTime(LegacyRow::str($row, ['waktu_verifikasi'])),
                disbursementScheduleText: LegacyRow::str($row, ['wt_cair']),
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * Normalisasi pinjaman individu. SIUPK menyimpan header pinjaman individu
     * di `pinjaman_anggota_{suffix}` dengan jenis_pinjaman='I' dan id_pinkel=0;
     * jadwal/realisasi berada di tabel `*_i_{suffix}`.
     *
     * @return array{ok: NormalizedMemberLoan|null, error: string|null, skip: bool}
     */
    public function normalizeMemberLoan(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => 'pinjaman_anggota (individu) missing id', 'skip' => false];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'member_loan')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $memberId = (int) ($row->nia ?? 0);
        if ($memberId <= 0) {
            return ['ok' => null, 'error' => "pi id={$legacyId}: missing nia", 'skip' => false];
        }

        $jenisPp = (int) ($row->jenis_pp ?? 1);
        $productRowId = $this->individualProductRowId;
        if ($productRowId <= 0) {
            $productRowId = $this->productsByJenis[$jenisPp] ?? ($this->productsByJenis[1] ?? 0);
        }
        if ($productRowId <= 0) {
            return ['ok' => null, 'error' => "pi id={$legacyId}: no individual loan product", 'skip' => false];
        }

        try {
            $principal = $this->parseMoney($row->alokasi ?? $row->verifikasi ?? $row->proposal ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "pi id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        $rateRaw = LegacyRow::str($row, ['pros_jasa']) ?? '0';
        try {
            $rate = $this->parseMoney($rateRaw);
        } catch (InvalidArgumentException) {
            $rate = '0.00';
        }

        $term = max(0, (int) (LegacyRow::str($row, ['jangka']) ?? '0'));
        $seq = max(1, (int) ($row->pinjaman_ke ?? 1));
        $status = $this->mapStatus(LegacyRow::str($row, ['status']));
        $method = self::mapInstallmentMethod(LegacyRow::str($row, ['jenis_jasa']));
        $system = self::mapInstallmentSystem(
            (int) (LegacyRow::str($row, ['sistem_angsuran']) ?? '0'),
            (int) (LegacyRow::str($row, ['sa_jasa']) ?? '0'),
        );

        $spk = LegacyRow::str($row, ['spk_no']);
        $loanNumber = $spk !== null && $spk !== '' && $spk !== '0' ? $spk : ('PI-'.$legacyId);

        return [
            'ok' => new NormalizedMemberLoan(
                legacyId: $legacyId,
                sequenceNumber: $seq,
                memberLegacyId: $memberId,
                productRowId: $productRowId,
                productCode: $this->productCodes[$productRowId] ?? 'spp',
                principal: $principal,
                interestRate: $rate,
                termMonths: $term,
                installmentMethod: $method,
                principalFrequency: $system['principal_frequency'],
                interestFrequency: $system['interest_frequency'],
                principalGraceMonths: $system['principal_grace_months'],
                interestGraceMonths: $system['interest_grace_months'],
                status: $status,
                proposedAt: LegacyRow::date($row, ['tgl_proposal']),
                verifiedAt: LegacyRow::date($row, ['tgl_verifikasi']),
                approvedAt: LegacyRow::date($row, ['tgl_dana']),
                fundedAt: LegacyRow::date($row, ['tgl_tunggu']),
                disbursedAt: LegacyRow::date($row, ['tgl_cair']),
                completedAt: LegacyRow::date($row, ['tgl_lunas']),
                loanNumber: $loanNumber,
                verificationNotes: LegacyRow::str($row, ['catatan_verifikasi']),
                guidanceNotes: LegacyRow::str($row, ['catatan_bimbingan']),
                verificationTime: $this->normalizeTime(LegacyRow::str($row, ['waktu_verifikasi'])),
                disbursementScheduleText: LegacyRow::str($row, ['wt_cair']),
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedMemberInstallment|null, error: string|null, skip: bool}
     */
    public function normalizeMemberInstallment(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => 'rencana_angsuran_i missing id', 'skip' => false];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'member_installment')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $loanId = (int) ($row->loan_id ?? 0);
        $num = (int) ($row->angsuran_ke ?? 0);
        if ($loanId <= 0 || $num <= 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $due = LegacyRow::date($row, ['jatuh_tempo']);
        if ($due === null) {
            return ['ok' => null, 'error' => "rencana_i id={$legacyId}: bad jatuh_tempo", 'skip' => false];
        }

        try {
            $pokok = $this->parseMoneyAbs($row->wajib_pokok ?? $row->target_pokok ?? '0');
            $jasa = $this->parseMoneyAbs($row->wajib_jasa ?? $row->target_jasa ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "rencana_i id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        return [
            'ok' => new NormalizedMemberInstallment(
                legacyId: $legacyId,
                memberLoanLegacyId: $loanId,
                installmentNumber: $num,
                dueDate: $due,
                principalDue: $pokok,
                interestDue: $jasa,
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedMemberPayment|null, error: string|null, skip: bool}
     */
    public function normalizeMemberPayment(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'member_payment')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $loanId = (int) ($row->loan_id ?? 0);
        if ($loanId <= 0) {
            return ['ok' => null, 'error' => "real_i id={$legacyId}: missing loan_id", 'skip' => false];
        }

        $paidAt = LegacyRow::date($row, ['tgl_transaksi']);
        if ($paidAt === null) {
            return ['ok' => null, 'error' => "real_i id={$legacyId}: bad tgl_transaksi", 'skip' => false];
        }

        try {
            $pokok = $this->parseMoney($row->realisasi_pokok ?? '0');
            $jasa = $this->parseMoney($row->realisasi_jasa ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "real_i id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        if (bccomp($pokok, '0.00', 2) === 0 && bccomp($jasa, '0.00', 2) === 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        return [
            'ok' => new NormalizedMemberPayment(
                legacyId: $legacyId,
                memberLoanLegacyId: $loanId,
                paidAt: $paidAt.' 00:00:00',
                principal: $pokok,
                interest: $jasa,
                amount: bcadd($pokok, $jasa, 2),
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedBeneficiary|null, error: string|null, skip: bool}
     */
    public function normalizeBeneficiary(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => 'pinjaman_anggota missing id', 'skip' => false];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'beneficiary')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $groupLoanId = (int) ($row->id_pinkel ?? 0);
        $memberId = (int) ($row->nia ?? 0);
        if ($groupLoanId <= 0) {
            return ['ok' => null, 'error' => "pa id={$legacyId}: missing id_pinkel", 'skip' => false];
        }
        if ($memberId <= 0) {
            return ['ok' => null, 'error' => "pa id={$legacyId}: missing nia", 'skip' => false];
        }

        try {
            $allocated = $this->parseMoney($row->alokasi ?? $row->verifikasi ?? $row->proposal ?? '0');
            $proposed = $this->parseMoney($row->proposal ?? '0');
            $verified = $this->parseMoney($row->verifikasi ?? $row->proposal ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "pa id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        return [
            'ok' => new NormalizedBeneficiary(
                legacyId: $legacyId,
                groupLoanLegacyId: $groupLoanId,
                memberLegacyId: $memberId,
                allocated: $allocated,
                proposed: $proposed,
                verified: $verified,
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedInstallment|null, error: string|null, skip: bool}
     */
    public function normalizeInstallment(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => 'rencana missing id', 'skip' => false];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'installment')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $loanId = (int) ($row->loan_id ?? 0);
        $num = (int) ($row->angsuran_ke ?? 0);
        if ($loanId <= 0 || $num <= 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $due = LegacyRow::date($row, ['jatuh_tempo']);
        if ($due === null) {
            return ['ok' => null, 'error' => "rencana id={$legacyId}: bad jatuh_tempo", 'skip' => false];
        }

        try {
            // Schedule dues use magnitude; legacy sometimes stores negative jasa adjustments.
            $pokok = $this->parseMoneyAbs($row->wajib_pokok ?? $row->target_pokok ?? '0');
            $jasa = $this->parseMoneyAbs($row->wajib_jasa ?? $row->target_jasa ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "rencana id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        return [
            'ok' => new NormalizedInstallment(
                legacyId: $legacyId,
                groupLoanLegacyId: $loanId,
                installmentNumber: $num,
                dueDate: $due,
                principalDue: $pokok,
                interestDue: $jasa,
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedPayment|null, error: string|null, skip: bool}
     */
    public function normalizePayment(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'payment')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $loanId = (int) ($row->loan_id ?? 0);
        if ($loanId <= 0) {
            return ['ok' => null, 'error' => "real id={$legacyId}: missing loan_id", 'skip' => false];
        }

        $paidAt = LegacyRow::date($row, ['tgl_transaksi']);
        if ($paidAt === null) {
            return ['ok' => null, 'error' => "real id={$legacyId}: bad tgl_transaksi", 'skip' => false];
        }

        try {
            $pokok = $this->parseMoney($row->realisasi_pokok ?? '0');
            $jasa = $this->parseMoney($row->realisasi_jasa ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "real id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        // Skip pure zero placeholder rows; keep negative rows (legacy reversals).
        if (bccomp($pokok, '0.00', 2) === 0 && bccomp($jasa, '0.00', 2) === 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $amount = bcadd($pokok, $jasa, 2);

        return [
            'ok' => new NormalizedPayment(
                legacyId: $legacyId,
                groupLoanLegacyId: $loanId,
                paidAt: $paidAt.' 00:00:00',
                principal: $pokok,
                interest: $jasa,
                amount: $amount,
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    private function parseMoney(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '0.00';
        }
        if (is_int($raw) || is_float($raw)) {
            return number_format(round((float) $raw, 2), 2, '.', '');
        }
        $s = trim((string) $raw);
        if ($s === '' || $s === '0') {
            return '0.00';
        }
        // plain digits or IEEE float spill from legacy varchar (89999.890000001)
        if (preg_match('/^-?\d+(\.\d+)?$/', $s) === 1) {
            return number_format(round((float) $s, 2), 2, '.', '');
        }

        $parsed = $this->amounts->parseSigned($raw);
        $amt = $parsed['amount'];
        if ($parsed['negative']) {
            $amt = bcsub('0.00', $amt, 2);
        }

        return $amt;
    }

    private function parseMoneyAbs(mixed $raw): string
    {
        $v = $this->parseMoney($raw);
        if (bccomp($v, '0.00', 2) < 0) {
            return bcsub('0.00', $v, 2);
        }

        return $v;
    }

    private function mapStatus(?string $raw): string
    {
        $v = strtoupper(trim((string) $raw));

        return match ($v) {
            'L' => 'completed',
            'A' => 'active',
            'W' => 'waiting',
            'V' => 'verified',
            'P' => 'proposed',
            'R' => 'rescheduled',
            'H' => 'written_off',
            'T' => 'draft',
            default => $v === '' ? 'active' : 'active',
        };
    }

    /**
     * Petakan sistem angsuran legacy (pokok & jasa) ke frekuensi Next.
     *
     * `sistem_angsuran` dan `sa_jasa` adalah id pada tabel legacy
     * `sistem_angsuran`. Kolom `sistem` pada tabel itu adalah interval bulan;
     * id 11/14/15/20 menambah masa tenggang, id 12 mingguan, dan id 25
     * ("Dua Mingguan") pada praktik legacy memakai interval 2 bulan (bukan 2
     * minggu) karena hanya id 12 yang memakai tanggal +7 hari.
     *
     * @return array{principal_frequency: string, interest_frequency: string, principal_grace_months: int, interest_grace_months: int}
     */
    public static function mapInstallmentSystem(int $principalId, int $interestId = 0): array
    {
        [$principalFrequency, $principalGrace] = self::legacySystem($principalId);
        [$interestFrequency, $interestGrace] = self::legacySystem($interestId);

        return [
            'principal_frequency' => $principalFrequency,
            'interest_frequency' => $interestFrequency,
            'principal_grace_months' => $principalGrace,
            'interest_grace_months' => $interestGrace,
        ];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function legacySystem(int $legacyId): array
    {
        return match ($legacyId) {
            2 => ['quarterly', 0],          // 3 bulan
            3 => ['every_4_months', 0],     // 4 bulan
            4 => ['every_6_months', 0],     // 6 bulan
            6 => ['every_5_months', 0],     // 5 bulan
            7 => ['every_7_months', 0],     // 7 bulan
            8 => ['every_8_months', 0],     // 8 bulan
            9 => ['bimonthly', 0],          // 2 bulan
            10 => ['every_12_months', 0],   // 12 bulan
            11 => ['monthly', 24],          // M24 (jangka + 24 pada pacuan)
            12 => ['weekly', 0],            // mingguan (+7 hari)
            13 => ['every_9_months', 0],    // 9 bulan
            14 => ['monthly', 3],           // M3
            15 => ['monthly', 2],           // M2
            16 => ['every_24_months', 0],   // 24 bulan
            20 => ['monthly', 12],          // M12
            21 => ['every_10_months', 0],   // 10 bulan
            22 => ['every_24_months', 0],   // 24 bulan
            23 => ['every_36_months', 0],   // 36 bulan
            24 => ['every_11_months', 0],   // 11 bulan
            25 => ['bimonthly', 0],         // "Dua Mingguan" legacy = interval 2 bulan
            26 => ['monthly', 6],           // M6 (konvensi Next, tidak ada di tabel legacy)
            default => ['monthly', 0],
        };
    }

    public static function mapInstallmentMethod(?string $raw): string
    {
        // Pacuan hanya membedakan flat (1) dan efektif (2); anuitas (3) pada
        // data legacy tetap menghasilkan baris flat.
        return trim((string) $raw) === '2' ? 'effective' : 'flat';
    }

    private function normalizeTime(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
            return strlen($raw) === 5 ? $raw.':00' : $raw;
        }

        return null;
    }
}
