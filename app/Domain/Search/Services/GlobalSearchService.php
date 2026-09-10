<?php

declare(strict_types=1);

namespace App\Domain\Search\Services;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Assets\Models\Asset;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Header omnibox — jump to entity, not a full-text engine.
 */
final class GlobalSearchService
{
    private const LIMIT_PER = 5;

    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionChecker $permissions,
    ) {}

    /**
     * @return array{
     *   q: string,
     *   groups: list<array{key:string,label:string,items:list<array{title:string,subtitle:?string,href:string,icon:string}>}>
     * }
     */
    public function search(string $q, User $user): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['q' => $q, 'groups' => []];
        }

        $groups = [];

        if ($this->permissions->allows($user, 'members.view')) {
            $items = $this->members($q);
            if ($items !== []) {
                $groups[] = ['key' => 'members', 'label' => 'Anggota', 'items' => $items];
            }
        }

        if ($this->permissions->allows($user, 'groups.view')) {
            $items = $this->groups($q);
            if ($items !== []) {
                $groups[] = ['key' => 'groups', 'label' => 'Kelompok', 'items' => $items];
            }
        }

        if ($this->permissions->allows($user, 'loans.view')) {
            $items = $this->loans($q);
            if ($items !== []) {
                $groups[] = ['key' => 'loans', 'label' => 'Pinjaman', 'items' => $items];
            }
        }

        if ($this->permissions->allows($user, 'journals.view')) {
            $items = $this->journals($q);
            if ($items !== []) {
                $groups[] = ['key' => 'journals', 'label' => 'Jurnal', 'items' => $items];
            }
            $items = $this->assets($q);
            if ($items !== []) {
                $groups[] = ['key' => 'assets', 'label' => 'Inventaris', 'items' => $items];
            }
        }

        return ['q' => $q, 'groups' => $groups];
    }

    /**
     * @return list<array{title:string,subtitle:?string,href:string,icon:string}>
     */
    private function members(string $q): array
    {
        $term = '%'.$q.'%';

        return Member::query()
            ->with(['person:row_id,full_name,national_identity_number,phone'])
            ->where(function ($w) use ($term, $q): void {
                $w->where('member_number', 'like', $term)
                    ->orWhereHas('person', function ($p) use ($term, $q): void {
                        $p->where('full_name', 'like', $term)
                            ->orWhere('national_identity_number', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                        if (ctype_digit($q)) {
                            $p->orWhere('national_identity_number', $q);
                        }
                    });
            })
            ->orderBy('row_id', 'desc')
            ->limit(self::LIMIT_PER)
            ->get()
            ->map(fn (Member $m): array => [
                'title' => (string) ($m->person?->full_name ?: $m->member_number ?: 'Anggota #'.$m->id),
                'subtitle' => trim(implode(' · ', array_filter([
                    $m->member_number,
                    $m->person?->national_identity_number,
                ]))) ?: null,
                'href' => '/master-data/members/'.$m->row_id,
                'icon' => 'person',
            ])
            ->all();
    }

    /**
     * @return list<array{title:string,subtitle:?string,href:string,icon:string}>
     */
    private function groups(string $q): array
    {
        $term = '%'.$q.'%';

        return Group::query()
            ->with(['village:row_id,name'])
            ->where(function ($w) use ($term): void {
                $w->where('name', 'like', $term)->orWhere('code', 'like', $term);
            })
            ->orderBy('name')
            ->limit(self::LIMIT_PER)
            ->get()
            ->map(fn (Group $g): array => [
                'title' => (string) $g->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $g->code,
                    $g->village?->name,
                    $g->status,
                ]))) ?: null,
                'href' => '/master-data/groups/'.$g->row_id,
                'icon' => 'groups',
            ])
            ->all();
    }

    /**
     * @return list<array{title:string,subtitle:?string,href:string,icon:string}>
     */
    private function loans(string $q): array
    {
        $term = '%'.$q.'%';
        $query = Loan::query()
            ->where(function ($w) use ($term, $q): void {
                $w->where('loan_number', 'like', $term);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q)->orWhere('row_id', (int) $q);
                }
            })
            ->orderByDesc('row_id')
            ->limit(self::LIMIT_PER)
            ->get();

        return $query->map(function (Loan $loan): array {
            $borrower = DB::connection('tenant')
                ->table('loan_borrowers as b')
                ->leftJoin('groups as g', function ($j): void {
                    $j->on('g.row_id', '=', 'b.group_row_id')
                        ->on('g.tenant_id', '=', 'b.tenant_id');
                })
                ->leftJoin('members as m', function ($j): void {
                    $j->on('m.row_id', '=', 'b.member_row_id')
                        ->on('m.tenant_id', '=', 'b.tenant_id');
                })
                ->leftJoin('people as p', function ($j): void {
                    $j->on('p.row_id', '=', 'm.person_row_id')
                        ->on('p.tenant_id', '=', 'm.tenant_id');
                })
                ->where('b.loan_row_id', $loan->row_id)
                ->where('b.tenant_id', $this->context->id())
                ->select(['g.name as group_name', 'p.full_name as member_name'])
                ->first();

            $who = $borrower->group_name ?? $borrower->member_name ?? null;

            return [
                'title' => (string) ($loan->loan_number ?: 'Pinjaman #'.$loan->id),
                'subtitle' => trim(implode(' · ', array_filter([
                    $who,
                    $loan->status,
                    $loan->principal_amount !== null
                        ? 'Rp '.number_format((float) $loan->principal_amount, 0, ',', '.')
                        : null,
                ]))) ?: null,
                'href' => '/lending/loans/'.$loan->row_id,
                'icon' => 'account_balance',
            ];
        })->all();
    }

    /**
     * @return list<array{title:string,subtitle:?string,href:string,icon:string}>
     */
    private function journals(string $q): array
    {
        $term = '%'.$q.'%';
        $query = JournalEntry::query()
            ->where('status', 'posted')
            ->where(function ($w) use ($term, $q): void {
                $w->where('journal_number', 'like', $term)
                    ->orWhere('description', 'like', $term);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q)->orWhere('row_id', (int) $q);
                }
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('row_id')
            ->limit(self::LIMIT_PER)
            ->get();

        return $query->map(fn (JournalEntry $e): array => [
            'title' => (string) ($e->journal_number ?: 'Jurnal #'.$e->id),
            'subtitle' => trim(implode(' · ', array_filter([
                $e->transaction_date?->format('Y-m-d'),
                $e->source_type,
                $e->description ? mb_substr((string) $e->description, 0, 60) : null,
            ]))) ?: null,
            // Daftar jurnal filter by q (no dedicated show yet).
            'href' => '/accounting/journals?q='.urlencode((string) ($e->journal_number ?: $e->id)),
            'icon' => 'receipt_long',
        ])->all();
    }

    /**
     * @return list<array{title:string,subtitle:?string,href:string,icon:string}>
     */
    private function assets(string $q): array
    {
        $term = '%'.$q.'%';

        return Asset::query()
            ->where(function ($w) use ($term): void {
                $w->where('name', 'like', $term)->orWhere('asset_code', 'like', $term);
            })
            ->orderBy('name')
            ->limit(self::LIMIT_PER)
            ->get()
            ->map(fn (Asset $a): array => [
                'title' => (string) $a->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $a->asset_code,
                    $a->status,
                ]))) ?: null,
                'href' => '/accounting/assets/'.$a->row_id,
                'icon' => 'inventory_2',
            ])
            ->all();
    }
}
