<?php

declare(strict_types=1);

namespace App\Domain\Membership\Services;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat pinjaman untuk detail anggota / kelompok.
 * Link ke /lending/loans/{row_id}.
 */
final class EntityLoanHistoryService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forMember(int $memberRowId): array
    {
        $tenantId = $this->context->id();

        // As group-loan beneficiary
        $asBeneficiary = DB::connection('tenant')
            ->table('loan_beneficiaries as lb')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'lb.tenant_id')
                    ->on('l.row_id', '=', 'lb.loan_row_id');
            })
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('loan_products as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'l.tenant_id')
                    ->on('p.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('lb.tenant_id', $tenantId)
            ->where('lb.member_row_id', $memberRowId)
            ->select([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.status',
                'l.principal_amount',
                'l.disbursed_at',
                'l.completed_at',
                'l.proposed_at',
                'p.code as product_code',
                'p.name as product_name',
                'g.name as group_name',
                'lb.allocated_amount',
            ])
            ->get();

        // As direct borrower (member_loan)
        $asBorrower = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'b.tenant_id')
                    ->on('l.row_id', '=', 'b.loan_row_id');
            })
            ->leftJoin('loan_products as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'l.tenant_id')
                    ->on('p.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('b.tenant_id', $tenantId)
            ->where('b.member_row_id', $memberRowId)
            ->select([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.status',
                'l.principal_amount',
                'l.disbursed_at',
                'l.completed_at',
                'l.proposed_at',
                'p.code as product_code',
                'p.name as product_name',
            ])
            ->get();

        $byRowId = [];
        foreach ($asBorrower as $r) {
            $byRowId[(int) $r->row_id] = $this->presentLoan($r, role: 'borrower', allocated: null, groupName: null);
        }
        foreach ($asBeneficiary as $r) {
            $id = (int) $r->row_id;
            if (isset($byRowId[$id])) {
                $byRowId[$id]['role'] = 'borrower+beneficiary';
                $byRowId[$id]['allocated_amount'] = $r->allocated_amount !== null
                    ? round((float) $r->allocated_amount, 2)
                    : $byRowId[$id]['allocated_amount'];
                if (empty($byRowId[$id]['group_name']) && $r->group_name) {
                    $byRowId[$id]['group_name'] = (string) $r->group_name;
                }
            } else {
                $byRowId[$id] = $this->presentLoan(
                    $r,
                    role: 'beneficiary',
                    allocated: $r->allocated_amount !== null ? (float) $r->allocated_amount : null,
                    groupName: $r->group_name ? (string) $r->group_name : null,
                );
            }
        }

        $this->attachRemaining($byRowId);

        return $this->sortHistory(array_values($byRowId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forGroup(int $groupRowId): array
    {
        $tenantId = $this->context->id();

        $rows = DB::connection('tenant')
            ->table('loan_borrowers as b')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'b.tenant_id')
                    ->on('l.row_id', '=', 'b.loan_row_id');
            })
            ->leftJoin('loan_products as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'l.tenant_id')
                    ->on('p.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('b.tenant_id', $tenantId)
            ->where('b.group_row_id', $groupRowId)
            ->select([
                'l.row_id',
                'l.id',
                'l.loan_number',
                'l.status',
                'l.principal_amount',
                'l.disbursed_at',
                'l.completed_at',
                'l.proposed_at',
                'p.code as product_code',
                'p.name as product_name',
            ])
            ->get();

        $byRowId = [];
        foreach ($rows as $r) {
            $byRowId[(int) $r->row_id] = $this->presentLoan($r, role: 'group', allocated: null, groupName: null);
        }

        $this->attachRemaining($byRowId);

        return $this->sortHistory(array_values($byRowId));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLoan(object $r, string $role, ?float $allocated, ?string $groupName): array
    {
        return [
            'row_id' => (int) $r->row_id,
            'id' => (int) $r->id,
            'loan_number' => $r->loan_number ? (string) $r->loan_number : null,
            'status' => (string) ($r->status ?? ''),
            'product_code' => $r->product_code ? (string) $r->product_code : null,
            'product_name' => $r->product_name ? (string) $r->product_name : null,
            'principal_amount' => round((float) ($r->principal_amount ?? 0), 2),
            'allocated_amount' => $allocated !== null ? round($allocated, 2) : null,
            'principal_remaining' => null,
            'disbursed_at' => $r->disbursed_at ? substr((string) $r->disbursed_at, 0, 10) : null,
            'completed_at' => $r->completed_at ? substr((string) $r->completed_at, 0, 10) : null,
            'proposed_at' => $r->proposed_at ? substr((string) $r->proposed_at, 0, 10) : null,
            'group_name' => $groupName,
            'role' => $role,
            'href' => '/lending/loans/'.(int) $r->row_id,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $byRowId
     */
    private function attachRemaining(array &$byRowId): void
    {
        if ($byRowId === []) {
            return;
        }

        $ids = array_keys($byRowId);
        $tenantId = $this->context->id();
        $sums = DB::connection('tenant')
            ->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->whereIn('loan_row_id', $ids)
            ->groupBy('loan_row_id')
            ->selectRaw('loan_row_id')
            ->selectRaw('CAST(COALESCE(SUM(principal_due - principal_paid), 0) AS CHAR) AS remaining')
            ->pluck('remaining', 'loan_row_id');

        foreach ($byRowId as $id => &$row) {
            $row['principal_remaining'] = round(max(0, (float) ($sums[$id] ?? 0)), 2);
        }
        unset($row);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortHistory(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $da = $a['disbursed_at'] ?? $a['proposed_at'] ?? '';
            $db = $b['disbursed_at'] ?? $b['proposed_at'] ?? '';

            return $db <=> $da;
        });

        return $rows;
    }
}
