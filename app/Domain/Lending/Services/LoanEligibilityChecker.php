<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Lending\Exceptions\LoanAlreadyActiveException;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\GroupMembership;
use Illuminate\Support\Facades\DB;

/**
 * Cegah pinjaman ganda untuk anggota / kelompok.
 *
 * Pattern pacuan (PinjamanIndividuController::store):
 * - Cek anggota belum punya pinjaman aktif di status P/V/W (di scope kecamatan).
 * - Cek data_pemanfaat.lokasi != current (kalau lintas kecamatan).
 *
 * siupknext equivalent:
 * - Query Loan via LoanBorrower.member_row_id / LoanBorrower.group_row_id.
 * - Status yang dianggap "active" (memblokir): draft, verified, waiting, approved, active, disbursed.
 * - Status terminal (tidak memblokir): completed, written_off, rescheduled, rejected.
 */
final class LoanEligibilityChecker
{
    /** Status yang men-block pengajuan baru. */
    private const ACTIVE_STATUSES = [
        'draft',
        'verified',
        'waiting',
        'approved',
        'active',
        'disbursed',
    ];

    public function __construct(
        private readonly string $connectionName = 'tenant',
    ) {}

    /**
     * Pastikan anggota (perorangan) belum punya pinjaman aktif.
     *
     * @throws LoanAlreadyActiveException
     */
    public function ensureMemberEligible(int $memberRowId, ?int $excludeLoanRowId = null): void
    {
        $loan = $this->findActiveLoanByMember($memberRowId, $excludeLoanRowId);
        if ($loan === null) {
            return;
        }

        $member = Member::query()->with('person:row_id,full_name')->find($memberRowId);
        $identifier = $member?->person?->full_name ?? "ID #{$memberRowId}";

        throw new LoanAlreadyActiveException(
            memberIdentifier: $identifier,
            loanNumber: (string) ($loan->loan_number ?? "(row_id {$loan->row_id})"),
            currentStatus: (string) $loan->status,
        );
    }

    /**
     * Pastikan semua anggota yang diajukan belum punya pinjaman aktif.
     *
     * @param  array<int, int>  $memberRowIds
     *
     * @throws LoanAlreadyActiveException
     */
    public function ensureMembersEligible(array $memberRowIds, ?int $excludeLoanRowId = null): void
    {
        foreach (array_unique($memberRowIds) as $memberRowId) {
            $this->ensureMemberEligible((int) $memberRowId, $excludeLoanRowId);
        }
    }

    /**
     * Pastikan kelompok belum punya pinjaman aktif.
     *
     * @throws LoanAlreadyActiveException
     */
    public function ensureGroupEligible(int $groupRowId, ?int $excludeLoanRowId = null): void
    {
        $loan = $this->findActiveLoanByGroup($groupRowId, $excludeLoanRowId);
        if ($loan === null) {
            return;
        }

        $group = DB::connection($this->connectionName)
            ->table('groups')
            ->where('row_id', $groupRowId)
            ->first(['name', 'code']);

        $identifier = $group?->name ?? "Kelompok #{$groupRowId}";

        throw new LoanAlreadyActiveException(
            memberIdentifier: $identifier,
            loanNumber: (string) ($loan->loan_number ?? "(row_id {$loan->row_id})"),
            currentStatus: (string) $loan->status,
        );
    }

    /**
     * List anggota existing di kelompok yang akan pinjam (untuk hindari deteksi false-positive
     * dari anggota yang memang sedang dalam satu kelompok).
     *
     * @return array<int, int>
     */
    public function existingActiveMemberRowIdsInGroup(int $groupRowId, ?int $excludeLoanRowId = null): array
    {
        $loanIds = $this->activeLoanRowIdsByGroup($groupRowId, $excludeLoanRowId);
        if ($loanIds === []) {
            return [];
        }

        return DB::connection($this->connectionName)
            ->table('loan_borrowers as lb')
            ->whereIn('lb.loan_row_id', $loanIds)
            ->whereNotNull('lb.member_row_id')
            ->pluck('lb.member_row_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();
    }

    /**
     * Cek apakah anggota boleh ditambahkan sebagai pemanfaat di loan tertentu.
     * Mengembalikan list loan aktif yang akan konflik (untuk UI warning).
     *
     * @return list<array{loan_row_id: int, loan_number: ?string, status: string}>
     */
    public function conflictsForMember(int $memberRowId, ?int $excludeLoanRowId = null): array
    {
        $loans = $this->queryActiveLoansByMember($memberRowId, $excludeLoanRowId)->get();

        return $loans->map(fn ($loan) => [
            'loan_row_id' => (int) $loan->row_id,
            'loan_number' => $loan->loan_number,
            'status' => (string) $loan->status,
        ])->all();
    }

    private function findActiveLoanByMember(int $memberRowId, ?int $excludeLoanRowId = null): ?object
    {
        return $this->queryActiveLoansByMember($memberRowId, $excludeLoanRowId)->first();
    }

    private function queryActiveLoansByMember(int $memberRowId, ?int $excludeLoanRowId = null)
    {
        $query = DB::connection($this->connectionName)
            ->table('loans as l')
            ->join('loan_borrowers as lb', function ($j): void {
                $j->on('lb.tenant_id', '=', 'l.tenant_id')
                    ->on('lb.loan_row_id', '=', 'l.row_id');
            })
            ->where('lb.member_row_id', $memberRowId)
            ->whereIn('l.status', self::ACTIVE_STATUSES)
            ->orderByDesc('l.disbursed_at')
            ->orderByDesc('l.row_id')
            ->select(['l.row_id', 'l.loan_number', 'l.status', 'l.disbursed_at']);

        if ($excludeLoanRowId !== null) {
            $query->where('l.row_id', '!=', $excludeLoanRowId);
        }

        return $query;
    }

    private function findActiveLoanByGroup(int $groupRowId, ?int $excludeLoanRowId = null): ?object
    {
        $loanIds = $this->activeLoanRowIdsByGroup($groupRowId, $excludeLoanRowId);
        if ($loanIds === []) {
            return null;
        }

        return DB::connection($this->connectionName)
            ->table('loans')
            ->whereIn('row_id', $loanIds)
            ->orderByDesc('disbursed_at')
            ->orderByDesc('row_id')
            ->select(['row_id', 'loan_number', 'status', 'disbursed_at'])
            ->first();
    }

    /**
     * @return array<int, int>
     */
    private function activeLoanRowIdsByGroup(int $groupRowId, ?int $excludeLoanRowId = null): array
    {
        $query = DB::connection($this->connectionName)
            ->table('loans as l')
            ->join('loan_borrowers as lb', function ($j): void {
                $j->on('lb.tenant_id', '=', 'l.tenant_id')
                    ->on('lb.loan_row_id', '=', 'l.row_id');
            })
            ->where('lb.group_row_id', $groupRowId)
            ->whereIn('l.status', self::ACTIVE_STATUSES)
            ->select('l.row_id');

        if ($excludeLoanRowId !== null) {
            $query->where('l.row_id', '!=', $excludeLoanRowId);
        }

        return $query->pluck('l.row_id')->map(fn ($v) => (int) $v)->unique()->all();
    }
}
