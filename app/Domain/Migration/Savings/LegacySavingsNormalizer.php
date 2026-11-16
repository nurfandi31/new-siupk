<?php

declare(strict_types=1);

namespace App\Domain\Migration\Savings;

use App\Domain\Migration\Membership\Support\LegacyRow;
use App\Domain\Migration\Savings\DTO\NormalizedSavingsAccount;
use App\Domain\Migration\Savings\DTO\NormalizedSavingsProduct;
use App\Domain\Migration\Savings\DTO\NormalizedSavingsTransaction;
use App\Domain\Migration\Support\LegacyAmountParser;
use App\Domain\Savings\SavingsModule;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class LegacySavingsNormalizer
{
    /** @var array<string, int> legacy product code => target row_id */
    private array $productRows = [];

    public function __construct(
        private TenantContext $context,
        private LegacyAmountParser $amounts,
    ) {}

    public function warmProducts(): void
    {
        $tenantId = $this->context->id();
        $conn = (string) config('tenancy.tenant_connection', 'tenant');
        $table = SavingsModule::table('products', $tenantId);
        $this->productRows = [];

        if (! Schema::connection($conn)->hasTable($table)) {
            return;
        }

        foreach (DB::connection($conn)->table($table)->where('tenant_id', $tenantId)->get(['row_id', 'code']) as $row) {
            $this->productRows[trim((string) $row->code)] = (int) $row->row_id;
        }
    }

    public function productRowId(int $legacyId): int
    {
        return $this->productRows[(string) $legacyId] ?? 0;
    }

    public function normalizeProduct(object $row): NormalizedSavingsProduct
    {
        $legacyId = (int) ($row->id ?? 0);
        $name = trim((string) (LegacyRow::str($row, ['nama_js']) ?? 'Simpanan '.$legacyId));
        $file = trim((string) (LegacyRow::str($row, ['file']) ?? '1'));
        $lower = strtolower($name);

        $category = 'umum';
        if (str_contains($lower, 'utang')) {
            $category = 'utang';
        } elseif ($file === '2' || str_contains($lower, 'deposito') || str_contains($lower, 'berjangka')) {
            $category = 'deposito';
        } elseif ($file === '3' || str_contains($lower, 'program')) {
            $category = 'program';
        }

        $formType = match ($file) {
            '2' => 2,
            '3' => 3,
            default => 1,
        };

        try {
            $minimum = $this->parseMoney($row->saldo_minimal ?? '0');
        } catch (InvalidArgumentException) {
            $minimum = '0.00';
        }

        return new NormalizedSavingsProduct(
            legacyId: $legacyId,
            code: (string) $legacyId,
            name: $name !== '' ? $name : 'Simpanan '.$legacyId,
            category: $category,
            formType: $formType,
            minimumBalance: $minimum,
            sortOrder: max(0, $legacyId),
            snapshot: LegacyRow::snapshot($row),
        );
    }

    /**
     * @return array{ok: NormalizedSavingsAccount|null, error: string|null, skip: bool}
     */
    public function normalizeAccount(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => 'simpanan_anggota missing id', 'skip' => false];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'savings_account')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $memberId = (int) ($row->nia ?? 0);
        if ($memberId <= 0) {
            return ['ok' => null, 'error' => "simpanan id={$legacyId}: missing nia", 'skip' => false];
        }

        $productLegacyId = (int) ($row->jenis_simpanan ?? 0);
        if ($productLegacyId <= 0) {
            return ['ok' => null, 'error' => "simpanan id={$legacyId}: missing jenis_simpanan", 'skip' => false];
        }

        $openedAt = LegacyRow::date($row, ['tgl_buka', 'tgl_up', 'lu']) ?? now()->toDateString();
        $minimumClosingDate = LegacyRow::date($row, ['tgl_tutup']);
        $statusRaw = strtoupper(trim((string) (LegacyRow::str($row, ['status']) ?? 'A')));
        $status = $statusRaw === '' || $statusRaw === 'A' ? 'active' : 'closed';
        $closedAt = $status === 'closed'
            ? LegacyRow::date($row, ['tgl_st', 'tgl_tutup'])
            : null;

        try {
            $openingAmount = $this->parseMoneyAbs($row->jumlah ?? '0');
            $interestRate = $this->parseMoney($row->bunga ?? '0');
            $taxRate = $this->parseMoney($row->pajak ?? '0');
            $adminFee = $this->parseMoney($row->admin ?? '0');
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "simpanan id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        $number = trim((string) (LegacyRow::str($row, ['nomor_rekening']) ?? ''));
        if ($number === '' || $number === '0') {
            $number = 'S-'.$legacyId;
        }

        $authority = (int) ($row->sp ?? 1);
        if ($authority < 1 || $authority > 3) {
            $authority = 1;
        }

        return [
            'ok' => new NormalizedSavingsAccount(
                legacyId: $legacyId,
                productLegacyId: $productLegacyId,
                productRowId: $this->productRowId($productLegacyId),
                memberLegacyId: $memberId,
                accountNumber: $number,
                openingAmount: $openingAmount,
                interestRate: $interestRate,
                taxRate: $taxRate,
                adminFee: $adminFee,
                openingFee: '0.00',
                openedAt: $openedAt,
                minimumClosingDate: $minimumClosingDate ?? $openedAt,
                closedAt: $closedAt,
                status: $status,
                authorityType: $authority,
                institutionName: $this->nullableString($row, ['lembaga']),
                institutionPosition: $this->nullableString($row, ['jabatan']),
                guardianName: $this->nullableString($row, ['pengampu']),
                relationship: $this->nullableString($row, ['hubungan']),
                notes: $this->nullableString($row, ['catatan_simpanan']),
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{ok: NormalizedSavingsTransaction|null, error: string|null, skip: bool}
     */
    public function normalizeTransaction(object $row, string $sourceTable, callable $isMapped): array
    {
        $legacyId = (int) ($row->id ?? 0);
        if ($legacyId <= 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }
        if ($isMapped($sourceTable, (string) $legacyId, 'savings_transaction')) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $accountLegacyId = (int) ($row->cif ?? 0);
        if ($accountLegacyId <= 0) {
            return ['ok' => null, 'error' => "real_simpanan id={$legacyId}: missing cif", 'skip' => false];
        }

        $date = LegacyRow::date($row, ['tgl_transaksi']);
        if ($date === null) {
            return ['ok' => null, 'error' => "real_simpanan id={$legacyId}: bad tgl_transaksi", 'skip' => false];
        }

        try {
            $credit = $this->parseMoney($row->real_k ?? '0');
            $debit = $this->parseMoney($row->real_d ?? '0');
            $balanceRaw = LegacyRow::str($row, ['sum']);
            $balanceAfter = $balanceRaw !== null ? $this->parseMoney($balanceRaw) : null;
        } catch (InvalidArgumentException $e) {
            return ['ok' => null, 'error' => "real_simpanan id={$legacyId}: {$e->getMessage()}", 'skip' => false];
        }

        $net = bcsub($credit, $debit, 2);
        if (bccomp($net, '0.00', 2) === 0) {
            return ['ok' => null, 'error' => null, 'skip' => true];
        }

        $direction = bccomp($net, '0.00', 2) > 0 ? 'in' : 'out';
        $amount = $direction === 'in' ? $net : bcsub('0.00', $net, 2);
        [$type, $code] = $this->classifyTransaction(
            LegacyRow::str($row, ['keterangan_transaksi']),
            $direction,
        );

        return [
            'ok' => new NormalizedSavingsTransaction(
                legacyId: $legacyId,
                accountLegacyId: $accountLegacyId,
                transactionDate: $date,
                type: $type,
                code: $code,
                direction: $direction,
                amount: $amount,
                balanceAfter: $balanceAfter,
                description: $this->nullableString($row, ['keterangan_transaksi']),
                reference: $this->nullableString($row, ['idt']) ?? (string) $legacyId,
                snapshot: LegacyRow::snapshot($row),
            ),
            'error' => null,
            'skip' => false,
        ];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function classifyTransaction(?string $description, string $direction): array
    {
        $text = strtolower(trim((string) $description));

        return match (true) {
            str_contains($text, 'setoran awal') => ['saldo_awal', 1],
            str_contains($text, 'tutup') => ['penarikan', 14],
            str_contains($text, 'bunga') => ['bunga', 4],
            str_contains($text, 'pajak') => ['pajak', 10],
            str_contains($text, 'admin'), str_contains($text, 'biaya') => ['admin', 5],
            str_contains($text, 'koreksi') => ['koreksi', $direction === 'in' ? 8 : 9],
            $direction === 'in' => ['setoran', 2],
            default => ['penarikan', 3],
        };
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
        if (preg_match('/^-?\d+(\.\d+)?$/', $s) === 1) {
            return number_format(round((float) $s, 2), 2, '.', '');
        }

        $parsed = $this->amounts->parseSigned($raw);
        $amount = $parsed['amount'];

        return $parsed['negative'] ? bcsub('0.00', $amount, 2) : $amount;
    }

    private function parseMoneyAbs(mixed $raw): string
    {
        $value = $this->parseMoney($raw);

        return bccomp($value, '0.00', 2) < 0 ? bcsub('0.00', $value, 2) : $value;
    }

    /**
     * @param  list<string>  $keys
     */
    private function nullableString(object $row, array $keys): ?string
    {
        $value = LegacyRow::str($row, $keys);
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }

        return $value;
    }
}
