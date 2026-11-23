<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\MemberLoanScheduleCalculator;
use App\Domain\Migration\Lending\LegacyLendingNormalizer;
use App\Domain\Migration\Support\LegacyConnection;
use App\Models\Platform\Tenant;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Audit angka jadwal angsuran legacy vs Next.
 *
 * Dua lapis audit:
 *  A. Fidelitas impor  — setiap baris `rencana_angsuran(_i)_<suffix>` legacy
 *     dibandingkan dengan `loan_installments` yang tersimpan (sumber angka
 *     dokumen & laporan).
 *  B. Reproduksi rumus — jadwal dihitung ulang lewat MemberLoanScheduleCalculator
 *     memakai parameter legacy (plafon, jangka, jasa, sistem angsuran) dan
 *     dibandingkan dengan baris legacy. Ini menguji simulasi, dokumen
 *     pra-cair, dan pinjaman baru.
 *
 * Pinjaman yang jadwal legacy-nya sendiri tidak konsisten (jumlah pokok baris
 * != plafon `alokasi`) dipisahkan dari kesimpulan rumus, karena angka
 * tersimpannya tetap menjadi kebenaran dokumen (Audit A).
 */
final class AuditLegacySchedules extends Command
{
    protected $signature = 'legacy:audit-schedules
        {tenant : Tenant row ID or code (e.g. 5)}
        {suffix : Legacy lokasi id (e.g. 1)}
        {--skip-recalc : Hanya audit jadwal tersimpan}
        {--show=8 : Jumlah contoh selisih yang ditampilkan}';

    protected $description = 'Audit baris jadwal angsuran legacy vs Next dan reproduksi kalkulator.';

    public function handle(
        TenantContext $context,
        ShardConnectionManager $connections,
        LegacyConnection $legacy,
        MemberLoanScheduleCalculator $calculator,
    ): int {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        $placement = $tenant->placement;
        $shard = $placement?->shard;
        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);

        try {
            $suffix = (string) $this->argument('suffix');
            if (! preg_match('/^\d+$/', $suffix)) {
                throw new RuntimeException('Suffix harus berupa id lokasi legacy (angka).');
            }

            $store = $this->auditStoredSchedules($legacy, $context->id(), $suffix);
            $recalc = $this->option('skip-recalc')
                ? null
                : $this->auditRecalculation($calculator, $context->id(), $store);
        } finally {
            $context->clear();
            $connections->disconnect();
        }

        $this->renderStored($store);
        if ($recalc !== null) {
            $this->renderRecalc($recalc);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{
     *   loans: array<string, array{total: int, matched: int, missing: int}>,
     *   legacy_rows: int,
     *   next_rows: int,
     *   matched_rows: int,
     *   mismatches: list<array<string, mixed>>,
     *   missing_rows: list<array<string, mixed>>,
     *   extra_rows: list<array<string, mixed>>,
     *   legacy: array<string, array<int, array{pokok: float, jasa: float, jatuh_tempo: string}>>,
     *   next: array<string, array<int, array{pokok: float, jasa: float, jatuh_tempo: string}>>,
     *   legacy_params: array<string, object|null>
     * }
     */
    private function auditStoredSchedules(LegacyConnection $legacy, int $tenantId, string $suffix): array
    {
        $sources = $this->sources($legacy, $suffix);

        $legacyRows = [];
        $nextRows = [];
        $legacyParams = [];
        $loans = [];
        $mismatches = [];
        $missingRows = [];
        $extraRows = [];
        $legacyRowCount = 0;
        $nextRowCount = 0;
        $matchedRows = 0;

        foreach ($sources as $source) {
            $legacyByLoan = $this->loadLegacySchedules($legacy, $source['schedule_table']);
            $paramsByLoan = $this->loadLegacyParams($legacy, $source['loan_table'], $source['schedule_table']);

            $mapping = DB::connection($this->tenantConnection())
                ->table('legacy_record_mappings')
                ->where('tenant_id', $tenantId)
                ->where('source_table', $source['loan_table'])
                ->where('target_table', 'loans')
                ->get(['source_id', 'target_row_id']);

            $targetToLegacy = [];
            foreach ($mapping as $row) {
                $targetToLegacy[(int) $row->target_row_id] = (int) $row->source_id;
            }

            $nextByLoan = $this->loadNextSchedules($tenantId, array_keys($targetToLegacy));

            $loans[$source['legacy_source']] = [
                'total' => count($legacyByLoan),
                'matched' => 0,
                'missing' => 0,
            ];

            foreach ($legacyByLoan as $legacyLoanId => $rows) {
                $key = $source['legacy_source'].':'.$legacyLoanId;
                $nextForLoan = $nextByLoan[$legacyLoanId] ?? [];
                if ($nextForLoan === []) {
                    $loans[$source['legacy_source']]['missing']++;
                } else {
                    $loans[$source['legacy_source']]['matched']++;
                }

                $legacyRows[$key] = $rows;
                $nextRows[$key] = $nextForLoan;
                $legacyParams[$key] = $paramsByLoan[$legacyLoanId] ?? null;

                foreach ($rows as $ke => $legacyRow) {
                    $legacyRowCount++;
                    $nextRow = $nextForLoan[$ke] ?? null;

                    if ($nextRow === null) {
                        $missingRows[] = [
                            'type' => $source['legacy_source'],
                            'loan' => $legacyLoanId,
                            'ke' => $ke,
                        ];

                        continue;
                    }

                    $nextRowCount++;

                    $deltaPrincipal = round($nextRow['pokok'] - $legacyRow['pokok'], 2);
                    $deltaInterest = round($nextRow['jasa'] - $legacyRow['jasa'], 2);
                    $dateDiff = $nextRow['jatuh_tempo'] !== $legacyRow['jatuh_tempo'];
                    $hasDiff = abs($deltaPrincipal) > 0.005 || abs($deltaInterest) > 0.005 || $dateDiff;

                    if (! $hasDiff) {
                        $matchedRows++;

                        continue;
                    }

                    $mismatches[] = [
                        'type' => $source['legacy_source'],
                        'loan' => $legacyLoanId,
                        'ke' => $ke,
                        'legacy_pokok' => $legacyRow['pokok'],
                        'next_pokok' => $nextRow['pokok'],
                        'legacy_jasa' => $legacyRow['jasa'],
                        'next_jasa' => $nextRow['jasa'],
                        'legacy_tempo' => $legacyRow['jatuh_tempo'],
                        'next_tempo' => $nextRow['jatuh_tempo'],
                    ];
                }

                foreach (array_diff_key($nextForLoan, $rows) as $ke => $nextRow) {
                    $extraRows[] = [
                        'type' => $source['legacy_source'],
                        'loan' => $legacyLoanId,
                        'ke' => $ke,
                        'pokok' => $nextRow['pokok'],
                        'jasa' => $nextRow['jasa'],
                    ];
                }
            }
        }

        return [
            'loans' => $loans,
            'legacy_rows' => $legacyRowCount,
            'next_rows' => $nextRowCount,
            'matched_rows' => $matchedRows,
            'mismatches' => $mismatches,
            'missing_rows' => $missingRows,
            'extra_rows' => $extraRows,
            'legacy' => $legacyRows,
            'next' => $nextRows,
            'legacy_params' => $legacyParams,
        ];
    }

    /**
     * @param  array<string, mixed>  $store
     * @return array{
     *   checked: int,
     *   matched: int,
     *   mismatches: list<array<string, mixed>>,
     *   inconsistent: list<array<string, mixed>>,
     *   inconsistent_rows: int,
     *   metadata: list<array<string, mixed>>,
     *   skipped: list<string>
     * }
     */
    private function auditRecalculation(
        MemberLoanScheduleCalculator $calculator,
        int $tenantId,
        array $store,
    ): array {
        $keys = array_keys($store['legacy']);
        $loanIds = array_map(
            static fn (string $key): int => (int) substr($key, strpos($key, ':') + 1),
            $keys,
        );

        $loans = Loan::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $loanIds)
            ->with(['borrower.member.village', 'borrower.group.village'])
            ->get()
            ->keyBy(static fn (Loan $loan): string => $loan->legacy_source.':'.$loan->id);

        $mismatches = [];
        $inconsistent = [];
        $inconsistentRows = 0;
        $metadata = [];
        $checked = 0;
        $matched = 0;
        $skipped = [];

        foreach ($keys as $key) {
            $loan = $loans->get($key);
            $params = $store['legacy_params'][$key] ?? null;
            $legacyRows = $store['legacy'][$key] ?? [];

            if (! $loan instanceof Loan || $params === null || $legacyRows === []) {
                $skipped[] = $key;

                continue;
            }

            $principal = (float) ($params->alokasi ?? 0);
            $term = (int) ($params->jangka ?? 0);
            $rate = (float) ($params->pros_jasa ?? 0);
            $scheduleSum = (float) array_sum(array_column($legacyRows, 'pokok'));

            $expected = LegacyLendingNormalizer::mapInstallmentSystem(
                (int) ($params->sistem_angsuran ?? 0),
                (int) ($params->sa_jasa ?? 0),
            );
            $expectedMethod = LegacyLendingNormalizer::mapInstallmentMethod((string) ($params->jenis_jasa ?? '1'));

            $this->compareMetadata($metadata, $loan, $expected, $expectedMethod);

            // Jadwal legacy yang jumlah pokoknya tidak sama dengan plafon adalah
            // data stale (plafon diubah setelah jadwal dibuat). Angka tersimpan
            // tetap dipakai dokumen, tetapi tidak bisa dipakai menilai rumus.
            if (abs($scheduleSum - $principal) > 0.005) {
                $inconsistent[] = [
                    'type' => (string) $loan->legacy_source,
                    'loan' => (int) $loan->id,
                    'principal' => $principal,
                    'schedule_sum' => $scheduleSum,
                    'delta' => $scheduleSum - $principal,
                    'rows' => count($legacyRows),
                    'status' => (string) $loan->status,
                ];
                $inconsistentRows += count($legacyRows);

                continue;
            }

            if ($principal <= 0 || $term <= 0) {
                $skipped[] = $key;

                continue;
            }

            $auditLoan = clone $loan;
            $auditLoan->principal_frequency = $expected['principal_frequency'];
            $auditLoan->interest_frequency = $expected['interest_frequency'];
            $auditLoan->principal_grace_months = $expected['principal_grace_months'];
            $auditLoan->interest_grace_months = $expected['interest_grace_months'];
            $auditLoan->installment_method = $expectedMethod;

            $date = $loan->disbursed_at
                ?? $loan->approved_at
                ?? $loan->verified_at
                ?? $loan->proposed_at
                ?? now();

            $calculated = $calculator->preview(
                loan: $auditLoan,
                term: $term,
                principal: $principal,
                principalSystem: $expected['principal_frequency'],
                interestSystem: $expected['interest_frequency'],
                serviceRateTotal: $rate,
                interestMethod: $expectedMethod,
                disbursementDate: CarbonImmutable::parse($date),
                principalGraceMonths: $expected['principal_grace_months'],
                interestGraceMonths: $expected['interest_grace_months'],
            );

            $calcByKe = [];
            foreach ($calculated as $row) {
                $calcByKe[(int) $row['angsuran_ke']] = [
                    'pokok' => (float) $row['wajib_pokok'],
                    'jasa' => (float) $row['wajib_jasa'],
                ];
            }

            foreach ($legacyRows as $ke => $legacyRow) {
                $checked++;
                $calcRow = $calcByKe[$ke] ?? null;

                if ($calcRow === null) {
                    $mismatches[] = [
                        'type' => (string) $loan->legacy_source,
                        'loan' => (int) $loan->id,
                        'ke' => $ke,
                        'field' => 'baris hilang',
                        'legacy' => $legacyRow['pokok'],
                        'next' => null,
                        'delta' => null,
                    ];

                    continue;
                }

                $deltaPrincipal = round($calcRow['pokok'] - $legacyRow['pokok'], 2);
                $deltaInterest = round($calcRow['jasa'] - $legacyRow['jasa'], 2);

                if (abs($deltaPrincipal) <= 0.005 && abs($deltaInterest) <= 0.005) {
                    $matched++;

                    continue;
                }

                $principalDiff = abs($deltaPrincipal) > 0.005;

                $mismatches[] = [
                    'type' => (string) $loan->legacy_source,
                    'loan' => (int) $loan->id,
                    'ke' => $ke,
                    'field' => $principalDiff ? 'pokok' : 'jasa',
                    'legacy' => $principalDiff ? $legacyRow['pokok'] : $legacyRow['jasa'],
                    'next' => $principalDiff ? $calcRow['pokok'] : $calcRow['jasa'],
                    'delta' => $principalDiff ? $deltaPrincipal : $deltaInterest,
                ];
            }
        }

        return [
            'checked' => $checked,
            'matched' => $matched,
            'mismatches' => $mismatches,
            'inconsistent' => $inconsistent,
            'inconsistent_rows' => $inconsistentRows,
            'metadata' => $metadata,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $metadata
     * @param  array{principal_frequency: string, interest_frequency: string, principal_grace_months: int, interest_grace_months: int}  $expected
     */
    private function compareMetadata(array &$metadata, Loan $loan, array $expected, string $expectedMethod): void
    {
        $fields = [
            'principal_frequency' => $expected['principal_frequency'],
            'interest_frequency' => $expected['interest_frequency'],
            'principal_grace_months' => $expected['principal_grace_months'],
            'interest_grace_months' => $expected['interest_grace_months'],
            'installment_method' => $expectedMethod,
        ];

        foreach ($fields as $field => $expectedValue) {
            $actual = $loan->getAttribute($field);
            if ((string) $actual === (string) $expectedValue) {
                continue;
            }

            $metadata[] = [
                'type' => (string) $loan->legacy_source,
                'loan' => (int) $loan->id,
                'field' => $field,
                'next' => (string) $actual,
                'legacy' => (string) $expectedValue,
            ];
        }
    }

    /**
     * @return array<int, array<int, array{pokok: float, jasa: float, jatuh_tempo: string}>>
     */
    private function loadLegacySchedules(LegacyConnection $legacy, string $table): array
    {
        if (! $legacy->tableExists($table)) {
            return [];
        }

        $sql = "SELECT CAST(loan_id AS UNSIGNED) AS loan_id,
                       CAST(angsuran_ke AS UNSIGNED) AS ke,
                       jatuh_tempo,
                       COALESCE(wajib_pokok, 0) AS pokok,
                       COALESCE(wajib_jasa, 0) AS jasa
                FROM `{$table}`
                WHERE CAST(angsuran_ke AS UNSIGNED) > 0
                ORDER BY loan_id, ke";

        $rows = [];
        foreach ($legacy->select($sql) as $row) {
            $loanId = (int) $row->loan_id;
            $ke = (int) $row->ke;
            $rows[$loanId][$ke] = [
                'pokok' => (float) $row->pokok,
                'jasa' => (float) $row->jasa,
                'jatuh_tempo' => (string) ($row->jatuh_tempo ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, object>
     */
    private function loadLegacyParams(LegacyConnection $legacy, string $loanTable, string $scheduleTable): array
    {
        if (! $legacy->tableExists($loanTable) || ! $legacy->tableExists($scheduleTable)) {
            return [];
        }

        $sql = "SELECT p.id, p.alokasi, p.jangka, p.pros_jasa,
                       p.sistem_angsuran, p.sa_jasa, p.jenis_jasa
                FROM `{$loanTable}` p
                WHERE EXISTS (SELECT 1 FROM `{$scheduleTable}` r WHERE r.loan_id = p.id)";

        $rows = [];
        foreach ($legacy->select($sql) as $row) {
            $rows[(int) $row->id] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<int>  $targetRowIds
     * @return array<int, array<int, array{pokok: float, jasa: float, jatuh_tempo: string}>>
     */
    private function loadNextSchedules(int $tenantId, array $targetRowIds): array
    {
        if ($targetRowIds === []) {
            return [];
        }

        $mapping = DB::connection($this->tenantConnection())
            ->table('legacy_record_mappings')
            ->where('tenant_id', $tenantId)
            ->where('target_table', 'loans')
            ->whereIn('target_row_id', $targetRowIds)
            ->get(['target_row_id', 'target_local_id']);

        $rowToLoan = [];
        foreach ($mapping as $row) {
            $rowToLoan[(int) $row->target_row_id] = (int) $row->target_local_id;
        }

        $next = [];
        foreach (array_chunk(array_keys($rowToLoan), 500) as $chunk) {
            $rows = DB::connection($this->tenantConnection())
                ->table('loan_installments')
                ->whereIn('loan_row_id', $chunk)
                ->groupBy('loan_row_id', 'installment_number')
                ->selectRaw('loan_row_id, installment_number AS ke, SUM(principal_due) AS pokok, SUM(interest_due) AS jasa, MIN(due_date) AS jatuh_tempo')
                ->get();

            foreach ($rows as $row) {
                $loanId = $rowToLoan[(int) $row->loan_row_id] ?? null;
                if ($loanId === null) {
                    continue;
                }

                $next[$loanId][(int) $row->ke] = [
                    'pokok' => (float) $row->pokok,
                    'jasa' => (float) $row->jasa,
                    'jatuh_tempo' => (string) ($row->jatuh_tempo ?? ''),
                ];
            }
        }

        return $next;
    }

    /**
     * @return list<array{legacy_source: string, loan_table: string, schedule_table: string}>
     */
    private function sources(LegacyConnection $legacy, string $suffix): array
    {
        return [
            [
                'legacy_source' => 'group_loan',
                'loan_table' => $legacy->pinjamanKelompokTable($suffix),
                'schedule_table' => $legacy->rencanaAngsuranTable($suffix),
            ],
            [
                'legacy_source' => 'member_loan',
                'loan_table' => $legacy->pinjamanAnggotaTable($suffix),
                'schedule_table' => $legacy->rencanaAngsuranIndividuTable($suffix),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $store
     */
    private function renderStored(array $store): void
    {
        $this->newLine();
        $this->info('=== AUDIT A — JADWAL TERSIMPAN (sumber angka dokumen & laporan) ===');

        foreach ($store['loans'] as $type => $counts) {
            $this->line(sprintf(
                '%-12s pinjaman legacy: %d | ada di Next: %d | tanpa jadwal Next: %d',
                $type,
                $counts['total'],
                $counts['matched'],
                $counts['missing'],
            ));
        }

        $this->line(sprintf(
            'Baris legacy: %d | baris Next: %d | cocok: %d | selisih nominal/tanggal: %d | hilang di Next: %d | ekstra di Next: %d',
            $store['legacy_rows'],
            $store['next_rows'],
            $store['matched_rows'],
            count($store['mismatches']),
            count($store['missing_rows']),
            count($store['extra_rows']),
        ));

        if ($store['mismatches'] !== []) {
            $this->newLine();
            $this->warn('Contoh selisih jadwal tersimpan:');
            $this->renderStoredRows($store['mismatches']);
        }

        if ($store['missing_rows'] !== []) {
            $this->newLine();
            $this->warn('Contoh baris legacy yang hilang di Next:');
            $this->renderStoredRows($store['missing_rows']);
        }

        if ($store['extra_rows'] !== []) {
            $this->newLine();
            $this->warn('Contoh baris ekstra di Next:');
            $this->renderStoredRows($store['extra_rows']);
        }

        $this->newLine();
        $ok = $store['mismatches'] === [] && $store['missing_rows'] === [] && $store['extra_rows'] === [];
        $this->line($ok
            ? '<fg=green;options=bold>A: 100% COCOK — angka dokumen/laporan identik dengan legacy.</>'
            : '<fg=red;options=bold>A: ADA SELISIH — jangan dipakai untuk dokumen/laporan sebelum diperbaiki.</>');
    }

    /**
     * @param  array<string, mixed>  $recalc
     */
    private function renderRecalc(array $recalc): void
    {
        $this->newLine();
        $this->info('=== AUDIT B — REPRODUKSI KALKULATOR (simulasi, dokumen pra-cair, pinjaman baru) ===');

        $this->line(sprintf(
            'Baris diuji (pinjaman konsisten): %d | cocok: %d | selisih rumus: %d',
            $recalc['checked'],
            $recalc['matched'],
            count($recalc['mismatches']),
        ));
        $this->line(sprintf(
            'Pinjaman legacy tidak konsisten (jumlah pokok jadwal != plafon): %d | baris dikecualikan: %d',
            count($recalc['inconsistent']),
            $recalc['inconsistent_rows'],
        ));
        $this->line(sprintf(
            'Metadata frekuensi/metode Next berbeda dari legacy: %d kolom',
            count($recalc['metadata']),
        ));

        if ($recalc['mismatches'] !== []) {
            $this->newLine();
            $this->warn('Contoh selisih hasil hitung ulang:');
            $this->renderRecalcRows($recalc['mismatches']);
        }

        if ($recalc['inconsistent'] !== []) {
            $this->newLine();
            $this->warn('Pinjaman legacy yang jadwalnya tidak konsisten dengan plafon:');
            $this->renderInconsistentRows($recalc['inconsistent']);
        }

        if ($recalc['metadata'] !== []) {
            $this->newLine();
            $this->warn('Metadata Next yang perlu disegarkan agar sama dengan legacy:');
            $this->renderMetadataRows($recalc['metadata']);
        }

        if ($recalc['skipped'] !== []) {
            $this->line('Dilewati: '.implode(', ', array_slice($recalc['skipped'], 0, 20)));
        }

        $this->newLine();
        $formulaOk = $recalc['mismatches'] === [];
        $this->line($formulaOk
            ? '<fg=green;options=bold>B: 100% COCOK untuk pinjaman yang datanya konsisten — kalkulator mereproduksi jadwal legacy.</>'
            : '<fg=red;options=bold>B: ADA SELISIH RUMUS — cek pembulatan/frekuensi sebelum dipakai.</>');

        if ($recalc['inconsistent'] !== []) {
            $this->line(sprintf(
                '<fg=yellow>Catatan: %d pinjaman legacy punya jadwal lama yang tidak sama dengan plafon. Angka tersimpan (Audit A) tetap benar untuk dokumen, tetapi jangan dijadikan patokan rumus.</>',
                count($recalc['inconsistent']),
            ));
        }

        if ($recalc['metadata'] !== []) {
            $this->line('<fg=yellow>Catatan: normalizer baru sudah memetakan frekuensi legacy dengan benar; data hasil migrasi lama perlu disegarkan (mis. migrasi ulang/backfill).</>');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderStoredRows(array $rows): void
    {
        $limit = max(1, (int) $this->option('show'));
        $table = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $table[] = [
                $row['type'],
                $row['loan'],
                $row['ke'],
                $this->money($row['legacy_pokok'] ?? $row['pokok'] ?? 0),
                $this->money($row['next_pokok'] ?? 0),
                $this->money($row['legacy_jasa'] ?? $row['jasa'] ?? 0),
                $this->money($row['next_jasa'] ?? 0),
                ($row['legacy_tempo'] ?? '-').' → '.($row['next_tempo'] ?? '-'),
            ];
        }

        $this->table(
            ['Tipe', 'Pinjaman', 'Ke', 'Pokok legacy', 'Pokok Next', 'Jasa legacy', 'Jasa Next', 'Jatuh tempo'],
            $table,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderRecalcRows(array $rows): void
    {
        $limit = max(1, (int) $this->option('show'));
        $table = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $table[] = [
                $row['type'],
                $row['loan'],
                $row['ke'],
                $row['field'],
                $this->money($row['legacy'] ?? 0),
                $row['next'] === null ? '-' : $this->money($row['next']),
                $row['delta'] === null ? '-' : $this->money($row['delta']),
            ];
        }

        $this->table(['Tipe', 'Pinjaman', 'Ke', 'Field', 'Legacy', 'Hitung ulang', 'Delta'], $table);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderInconsistentRows(array $rows): void
    {
        $limit = max(1, (int) $this->option('show'));
        $table = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $table[] = [
                $row['type'],
                $row['loan'],
                $this->money($row['principal']),
                $this->money($row['schedule_sum']),
                $this->money($row['delta']),
                $row['rows'],
                $row['status'],
            ];
        }

        $this->table(['Tipe', 'Pinjaman', 'Plafon legacy', 'Jumlah pokok jadwal', 'Delta', 'Baris', 'Status'], $table);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderMetadataRows(array $rows): void
    {
        $limit = max(1, (int) $this->option('show'));
        $table = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $table[] = [
                $row['type'],
                $row['loan'],
                $row['field'],
                $row['next'],
                $row['legacy'],
            ];
        }

        $this->table(['Tipe', 'Pinjaman', 'Field', 'Next', 'Legacy (benar)'], $table);
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function tenantConnection(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    private function resolveTenant(string $value): Tenant
    {
        return Tenant::query()
            ->with('placement.shard')
            ->when(
                ctype_digit($value),
                fn ($query) => $query->whereKey((int) $value),
                fn ($query) => $query->where('code', $value),
            )
            ->firstOrFail();
    }
}
