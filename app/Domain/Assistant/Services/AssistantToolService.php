<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\JournalEntryOptionResolver;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\JournalReversalService;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanProduct;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\LoanSimulationService;
use App\Domain\Membership\Models\Member;
use App\Domain\Notifications\Services\WhatsappNotificationService;
use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * JSON tool handlers invoked by the assistant orchestrator.
 * Always run as the resolved assistant actor + current tenant.
 */
final class AssistantToolService
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly TenantContext $context,
        private readonly JournalPostingService $journalPosting,
        private readonly JournalReversalService $journalReversal,
        private readonly LoanService $loans,
        private readonly LoanSimulationService $loanSimulator,
        private readonly WhatsappNotificationService $notices,
    ) {}

    /**
     * Run a tool by name. Used by ToolHandler classes (one per tool) and
     * by the legacy AssistantToolController — kept as the single entry point.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function dispatch(string $tool, array $params, User $actor): array
    {
        $required = config('permissions.tool_map.'.$tool);
        if (is_string($required) && $required !== '') {
            $this->permissions->denyUnless($actor, $required);
        }

        return match ($tool) {
            'search_members' => $this->searchMembers($params),
            'search_groups' => $this->searchGroups($params),
            'groups_with_loans' => $this->groupsWithLoans($params),
            'search_loans' => $this->searchLoans($params),
            'get_loan' => $this->getLoan($params),
            'list_accounts' => $this->listAccounts($params),
            'search_journals' => $this->searchJournals($params),
            'search_assets' => $this->searchAssets($params),
            'get_asset' => $this->getAsset($params),
            'list_due_billing' => $this->listDueBilling($params),
            'create_journal_entry' => $this->createJournalEntry($params, $actor),
            'reverse_journal' => $this->reverseJournal($params, $actor),
            'record_installment' => $this->recordInstallment($params, $actor),
            'send_billing_notices' => $this->sendBillingNotices($params),
            'download_report' => $this->downloadReport($params),
            'simulate_loan' => $this->simulateLoan($params),
            default => throw new RuntimeException("Unknown tool: {$tool}"),
        };
    }

    /**
     * Same as dispatch() but without permission check — used by Sidbm
     * handlers where permissions are already enforced upstream.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(string $tool, array $params, User $actor): array
    {
        return match ($tool) {
            'search_members' => $this->searchMembers($params),
            'search_groups' => $this->searchGroups($params),
            'groups_with_loans' => $this->groupsWithLoans($params),
            'search_loans' => $this->searchLoans($params),
            'get_loan' => $this->getLoan($params),
            'list_accounts' => $this->listAccounts($params),
            'search_journals' => $this->searchJournals($params),
            'search_assets' => $this->searchAssets($params),
            'get_asset' => $this->getAsset($params),
            'list_due_billing' => $this->listDueBilling($params),
            'create_journal_entry' => $this->createJournalEntry($params, $actor),
            'reverse_journal' => $this->reverseJournal($params, $actor),
            'record_installment' => $this->recordInstallment($params, $actor),
            'send_billing_notices' => $this->sendBillingNotices($params),
            'download_report' => $this->downloadReport($params),
            'simulate_loan' => $this->simulateLoan($params),
            default => throw new RuntimeException("Unknown tool: {$tool}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchAssets(array $params): array
    {
        $q = trim((string) ($params['query'] ?? $params['name'] ?? $params['asset_name'] ?? ''));
        if (mb_strlen($q) < 2) {
            throw ValidationException::withMessages(['query' => 'query min 2 characters']);
        }

        $term = '%'.$q.'%';
        $status = trim((string) ($params['status'] ?? ''));
        $asOf = trim((string) ($params['as_of'] ?? ''));
        $asOfDate = $asOf !== ''
            ? CarbonImmutable::parse($asOf)->startOfDay()
            : CarbonImmutable::today();

        $query = Asset::query()
            ->with(['category:row_id,code,name'])
            ->where(function ($w) use ($term): void {
                $w->where('name', 'like', $term)->orWhere('asset_code', 'like', $term);
            });

        if ($status !== '' && isset(Asset::STATUSES[$status])) {
            $query->where('status', $status);
        }

        $rows = $query->orderBy('name')->limit(15)->get();
        $service = app(AssetService::class);

        $items = $rows->map(function (Asset $a) use ($service, $asOfDate): array {
            $calc = $service->bookValue($a, $asOfDate);

            return [
                'row_id' => (int) $a->row_id,
                'id' => (int) $a->id,
                'asset_code' => $a->asset_code,
                'name' => $a->name,
                'status' => (string) $a->status,
                'status_label' => Asset::STATUSES[(string) $a->status] ?? (string) $a->status,
                'category' => $a->category?->name,
                'purchased_at' => $a->purchased_at?->format('Y-m-d'),
                'quantity' => (int) $a->quantity,
                'unit_cost' => (float) $a->unit_cost,
                'useful_life_months' => $a->useful_life_months !== null ? (int) $a->useful_life_months : null,
                'acquisition' => $calc['acquisition'],
                'book_value' => $calc['book_value'],
                'accumulated_depreciation' => $calc['accumulated_depreciation'],
                'href' => '/accounting/assets/'.$a->row_id,
            ];
        })->all();

        $count = count($items);

        return [
            'items' => $items,
            'match_count' => $count,
            'needs_clarification' => $count !== 1,
            'as_of' => $asOfDate->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getAsset(array $params): array
    {
        $rowId = (int) ($params['asset_row_id'] ?? $params['row_id'] ?? 0);
        if ($rowId <= 0) {
            throw ValidationException::withMessages(['asset_row_id' => 'asset_row_id required']);
        }

        $asset = Asset::query()->with(['category', 'unit'])->whereKey($rowId)->first();
        if ($asset === null) {
            throw ValidationException::withMessages(['asset_row_id' => 'Inventaris tidak ditemukan']);
        }

        $asOf = trim((string) ($params['as_of'] ?? ''));
        $detail = app(AssetService::class)->detail($asset, $asOf !== '' ? $asOf : null);

        return [
            ...$detail,
            'href' => '/accounting/assets/'.$asset->row_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchMembers(array $params): array
    {
        $q = trim((string) ($params['query'] ?? ''));
        if (mb_strlen($q) < 2) {
            throw ValidationException::withMessages(['query' => 'query min 2 characters']);
        }

        $tenantId = $this->context->id();
        $groupQ = trim((string) ($params['group_query'] ?? $params['group_name'] ?? ''));

        $query = DB::connection('tenant')
            ->table('members as m')
            ->join('people as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'm.person_row_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('m.tenant_id', $tenantId)
            ->whereNull('m.deleted_at')
            ->where(function ($w) use ($q): void {
                $w->where('p.full_name', 'like', '%'.$q.'%')
                    ->orWhere('p.national_identity_number', 'like', '%'.$q.'%')
                    ->orWhere('p.phone', 'like', '%'.$q.'%');
            });

        if ($groupQ !== '') {
            $query->join('group_members as gm', function ($join) use ($tenantId): void {
                $join->on('gm.member_row_id', '=', 'm.row_id')
                    ->where('gm.tenant_id', '=', $tenantId)
                    ->whereNull('gm.left_at');
            })->join('groups as g', function ($join) use ($tenantId): void {
                $join->on('g.row_id', '=', 'gm.group_row_id')
                    ->where('g.tenant_id', '=', $tenantId)
                    ->whereNull('g.deleted_at');
            })->where(function ($w) use ($groupQ): void {
                $w->where('g.name', 'like', '%'.$groupQ.'%')
                    ->orWhere('g.code', 'like', '%'.$groupQ.'%');
            });
        }

        $rows = $query
            ->orderBy('p.full_name')
            ->limit(20)
            ->get([
                'm.row_id as member_row_id',
                'm.id as member_id',
                'm.status',
                'p.full_name',
                'p.national_identity_number as nik',
                'p.phone',
            ]);

        $items = $rows->map(fn ($r): array => [
            'member_row_id' => (int) $r->member_row_id,
            'member_id' => (int) $r->member_id,
            'status' => (string) $r->status,
            'name' => (string) $r->full_name,
            'nik' => (string) ($r->nik ?? ''),
            'phone' => $r->phone ? (string) $r->phone : null,
        ])->all();

        return $this->withMatchMeta($items);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchGroups(array $params): array
    {
        $q = trim((string) ($params['query'] ?? ''));
        if (mb_strlen($q) < 2) {
            throw ValidationException::withMessages(['query' => 'query min 2 characters']);
        }

        $tenantId = $this->context->id();
        $rows = DB::connection('tenant')
            ->table('groups as g')
            ->where('g.tenant_id', $tenantId)
            ->whereNull('g.deleted_at')
            ->where(function ($w) use ($q): void {
                $w->where('g.name', 'like', '%'.$q.'%')
                    ->orWhere('g.code', 'like', '%'.$q.'%');
            })
            ->orderBy('g.name')
            ->limit(20)
            ->get(['g.row_id', 'g.id', 'g.code', 'g.name', 'g.status', 'g.phone']);

        $items = $rows->map(fn ($r): array => [
            'group_row_id' => (int) $r->row_id,
            'group_id' => (int) $r->id,
            'code' => (string) ($r->code ?? ''),
            'name' => (string) $r->name,
            'status' => (string) $r->status,
            'phone' => $r->phone ? (string) $r->phone : null,
        ])->all();

        return $this->withMatchMeta($items);
    }

    /**
     * List groups with workable-loan summary in one shot.
     * Loan-status filter follows searchLoans() workable set (active/disbursed/ongoing/approved/funded).
     * Group status untouched unless include_inactive_groups=false.
     *
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function groupsWithLoans(array $params): array
    {
        $q = trim((string) ($params['query'] ?? $params['name'] ?? ''));
        $includeInactive = filter_var($params['include_inactive_groups'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $workable = ['active', 'disbursed', 'ongoing', 'approved', 'funded'];
        $tenantId = $this->context->id();

        $base = DB::connection('tenant')
            ->table('groups as g')
            ->where('g.tenant_id', $tenantId)
            ->whereNull('g.deleted_at');

        if (! $includeInactive) {
            $base->where('g.status', 'active');
        }

        if (mb_strlen($q) >= 2) {
            $base->where(function ($w) use ($q): void {
                $w->where('g.name', 'like', '%'.$q.'%')->orWhere('g.code', 'like', '%'.$q.'%');
            });
        }

        // Aggregate workable loans per group + member count with any loan.
        $rows = $base
            ->leftJoin('loan_borrowers as lb', function ($join) use ($tenantId): void {
                $join->on('lb.group_row_id', '=', 'g.row_id')->where('lb.tenant_id', '=', $tenantId);
            })
            ->leftJoin('loans as l', function ($join) use ($tenantId, $workable): void {
                $join->on('l.row_id', '=', 'lb.loan_row_id')
                    ->where('l.tenant_id', '=', $tenantId)
                    ->whereIn('l.status', $workable);
            })
            ->leftJoin('loan_installments as li', function ($join) use ($tenantId): void {
                $join->on('li.loan_row_id', '=', 'l.row_id')
                    ->where('li.tenant_id', '=', $tenantId)
                    ->where('li.component', 'principal');
            })
            ->selectRaw('
                g.row_id as group_row_id, g.code, g.name, g.status, g.phone,
                COUNT(DISTINCT l.row_id) as active_loan_count,
                COALESCE(SUM(l.principal_amount), 0) as principal_total,
                COALESCE(SUM(li.principal_paid), 0) as principal_paid_total
            ')
            ->groupBy('g.row_id', 'g.code', 'g.name', 'g.status', 'g.phone')
            ->havingRaw('COUNT(DISTINCT l.row_id) > 0')
            ->orderByDesc('active_loan_count')
            ->orderBy('g.name')
            ->limit(50)
            ->get();

        // Members with loan (distinct per group) — one extra cheap query, not per-group.
        $memberLoanCounts = [];
        if ($rows->isNotEmpty()) {
            $groupIds = $rows->pluck('group_row_id')->all();
            $memberLoanCounts = DB::connection('tenant')
                ->table('loan_borrowers as lb')
                ->join('loans as l', function ($join) use ($tenantId, $workable): void {
                    $join->on('l.row_id', '=', 'lb.loan_row_id')
                        ->where('l.tenant_id', '=', $tenantId)
                        ->whereIn('l.status', $workable);
                })
                ->where('lb.tenant_id', $tenantId)
                ->whereIn('lb.group_row_id', $groupIds)
                ->selectRaw('lb.group_row_id, COUNT(DISTINCT lb.member_row_id) as members_with_loan')
                ->groupBy('lb.group_row_id')
                ->pluck('members_with_loan', 'lb.group_row_id')
                ->all();
        }

        $items = $rows->map(function ($r) use ($memberLoanCounts): array {
            $paid = (float) $r->principal_paid_total;
            $total = (float) $r->principal_total;

            return [
                'group_row_id' => (int) $r->group_row_id,
                'code' => (string) ($r->code ?? ''),
                'name' => (string) $r->name,
                'status' => (string) $r->status,
                'phone' => $r->phone ? (string) $r->phone : null,
                'active_loan_count' => (int) $r->active_loan_count,
                'principal_total' => round($total, 2),
                'principal_outstanding' => round($total - $paid, 2),
                'members_with_loan' => (int) ($memberLoanCounts[(int) $r->group_row_id] ?? 0),
                'href' => '/master-data/groups/'.((string) ($r->code ?? '')),
            ];
        })->all();

        return $this->withMatchMeta($items);
    }

    /**
     * Find loans by group/member/loan number. Prefer active/disbursed.
     *
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchLoans(array $params): array
    {
        $groupQ = trim((string) ($params['group_query'] ?? $params['group_name'] ?? ''));
        $memberQ = trim((string) ($params['member_query'] ?? $params['member_name'] ?? ''));
        $loanNumber = trim((string) ($params['loan_number'] ?? ''));
        $loanId = (int) ($params['loan_row_id'] ?? $params['loan_id'] ?? 0);
        $status = trim((string) ($params['status'] ?? ''));

        if ($loanId <= 0 && $groupQ === '' && $memberQ === '' && $loanNumber === '') {
            throw ValidationException::withMessages([
                'query' => 'Berikan group_query, member_query, loan_number, atau loan_row_id',
            ]);
        }

        $tenantId = $this->context->id();
        $q = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as lb', function ($join) use ($tenantId): void {
                $join->on('lb.loan_row_id', '=', 'l.row_id')
                    ->where('lb.tenant_id', '=', $tenantId);
            })
            ->leftJoin('groups as g', function ($join) use ($tenantId): void {
                $join->on('g.row_id', '=', 'lb.group_row_id')
                    ->where('g.tenant_id', '=', $tenantId);
            })
            ->leftJoin('loan_products as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'l.loan_product_row_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('l.tenant_id', $tenantId);

        if ($loanId > 0) {
            $q->where('l.row_id', $loanId);
        }
        if ($loanNumber !== '') {
            $q->where('l.loan_number', 'like', '%'.$loanNumber.'%');
        }
        if ($groupQ !== '') {
            $q->where(function ($w) use ($groupQ): void {
                $w->where('g.name', 'like', '%'.$groupQ.'%')
                    ->orWhere('g.code', 'like', '%'.$groupQ.'%');
            });
        }
        if ($memberQ !== '') {
            $q->leftJoin('members as m', function ($join) use ($tenantId): void {
                $join->on('m.row_id', '=', 'lb.member_row_id')
                    ->where('m.tenant_id', '=', $tenantId);
            })->leftJoin('people as pe', function ($join) use ($tenantId): void {
                $join->on('pe.row_id', '=', 'm.person_row_id')
                    ->where('pe.tenant_id', '=', $tenantId);
            })->where('pe.full_name', 'like', '%'.$memberQ.'%');
        }
        if ($status !== '') {
            $q->where('l.status', $status);
        } else {
            // Prefer workable loans; still return drafts if nothing else matches later.
            $q->whereIn('l.status', ['active', 'disbursed', 'ongoing', 'approved', 'funded']);
        }

        $rows = $q->orderByRaw("FIELD(l.status,'active','disbursed','ongoing','funded','approved','verified','waiting','draft')")
            ->orderByDesc('l.row_id')
            ->limit(20)
            ->get([
                'l.row_id as loan_row_id',
                'l.id as loan_id',
                'l.loan_number',
                'l.status',
                'l.principal_amount',
                'l.disbursed_at',
                'p.code as product_code',
                'p.name as product_name',
                'lb.group_row_id',
                'lb.member_row_id',
                'g.name as group_name',
                'g.code as group_code',
            ]);

        // Fallback: if status filter empty and no hits, retry without status filter.
        if ($rows->isEmpty() && $status === '') {
            $params['status'] = '*';
            // Re-run loose: any status
            $paramsLoose = $params;
            unset($paramsLoose['status']);
            $paramsLoose['status'] = '__any__';

            return $this->searchLoansAnyStatus($paramsLoose);
        }

        $items = $rows->map(function ($r): array {
            $loanRowId = (int) $r->loan_row_id;
            $next = $this->nextOpenInstallment($loanRowId);

            return [
                'loan_row_id' => $loanRowId,
                'loan_id' => (int) $r->loan_id,
                'loan_number' => $r->loan_number,
                'status' => (string) $r->status,
                'principal_amount' => (float) $r->principal_amount,
                'product_code' => $r->product_code ? (string) $r->product_code : null,
                'product_name' => $r->product_name ? (string) $r->product_name : null,
                'group_row_id' => $r->group_row_id ? (int) $r->group_row_id : null,
                'group_name' => $r->group_name ? (string) $r->group_name : null,
                'group_code' => $r->group_code ? (string) $r->group_code : null,
                'member_row_id' => $r->member_row_id ? (int) $r->member_row_id : null,
                'disbursed_at' => $r->disbursed_at ? (string) $r->disbursed_at : null,
                'next_installment' => $next,
            ];
        })->all();

        return $this->withMatchMeta($items);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    private function searchLoansAnyStatus(array $params): array
    {
        $params['status'] = ''; // will be treated specially
        // Direct rebuild without preferred status filter — set a sentinel.
        $groupQ = trim((string) ($params['group_query'] ?? $params['group_name'] ?? ''));
        $memberQ = trim((string) ($params['member_query'] ?? $params['member_name'] ?? ''));
        $loanNumber = trim((string) ($params['loan_number'] ?? ''));
        $loanId = (int) ($params['loan_row_id'] ?? $params['loan_id'] ?? 0);
        $tenantId = $this->context->id();

        $q = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as lb', function ($join) use ($tenantId): void {
                $join->on('lb.loan_row_id', '=', 'l.row_id')->where('lb.tenant_id', '=', $tenantId);
            })
            ->leftJoin('groups as g', function ($join) use ($tenantId): void {
                $join->on('g.row_id', '=', 'lb.group_row_id')->where('g.tenant_id', '=', $tenantId);
            })
            ->leftJoin('loan_products as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'l.loan_product_row_id')->where('p.tenant_id', '=', $tenantId);
            })
            ->where('l.tenant_id', $tenantId);

        if ($loanId > 0) {
            $q->where('l.row_id', $loanId);
        }
        if ($loanNumber !== '') {
            $q->where('l.loan_number', 'like', '%'.$loanNumber.'%');
        }
        if ($groupQ !== '') {
            $q->where(function ($w) use ($groupQ): void {
                $w->where('g.name', 'like', '%'.$groupQ.'%')->orWhere('g.code', 'like', '%'.$groupQ.'%');
            });
        }
        if ($memberQ !== '') {
            $q->leftJoin('members as m', function ($join) use ($tenantId): void {
                $join->on('m.row_id', '=', 'lb.member_row_id')->where('m.tenant_id', '=', $tenantId);
            })->leftJoin('people as pe', function ($join) use ($tenantId): void {
                $join->on('pe.row_id', '=', 'm.person_row_id')->where('pe.tenant_id', '=', $tenantId);
            })->where('pe.full_name', 'like', '%'.$memberQ.'%');
        }

        $rows = $q->orderByDesc('l.row_id')->limit(20)->get([
            'l.row_id as loan_row_id',
            'l.id as loan_id',
            'l.loan_number',
            'l.status',
            'l.principal_amount',
            'l.disbursed_at',
            'p.code as product_code',
            'p.name as product_name',
            'lb.group_row_id',
            'lb.member_row_id',
            'g.name as group_name',
            'g.code as group_code',
        ]);

        $items = $rows->map(function ($r): array {
            $loanRowId = (int) $r->loan_row_id;

            return [
                'loan_row_id' => $loanRowId,
                'loan_id' => (int) $r->loan_id,
                'loan_number' => $r->loan_number,
                'status' => (string) $r->status,
                'principal_amount' => (float) $r->principal_amount,
                'product_code' => $r->product_code ? (string) $r->product_code : null,
                'product_name' => $r->product_name ? (string) $r->product_name : null,
                'group_row_id' => $r->group_row_id ? (int) $r->group_row_id : null,
                'group_name' => $r->group_name ? (string) $r->group_name : null,
                'group_code' => $r->group_code ? (string) $r->group_code : null,
                'member_row_id' => $r->member_row_id ? (int) $r->member_row_id : null,
                'disbursed_at' => $r->disbursed_at ? (string) $r->disbursed_at : null,
                'next_installment' => $this->nextOpenInstallment($loanRowId),
            ];
        })->all();

        return $this->withMatchMeta($items);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getLoan(array $params): array
    {
        $loanId = (int) ($params['loan_row_id'] ?? $params['loan_id'] ?? 0);
        if ($loanId <= 0) {
            throw ValidationException::withMessages(['loan_row_id' => 'required']);
        }

        $loan = Loan::query()
            ->with(['product:row_id,code,name', 'borrower.group:row_id,name,phone,code'])
            ->where('row_id', $loanId)
            ->firstOrFail();

        $tenantId = $this->context->id();
        $paid = (float) DB::connection('tenant')
            ->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->where('loan_row_id', $loanId)
            ->where('component', 'principal')
            ->sum('principal_paid');

        $next = $this->nextOpenInstallment($loanId);
        $members = [];
        if ($loan->borrower?->group_row_id) {
            $members = $this->groupMemberItems((int) $loan->borrower->group_row_id);
        }

        $memberName = null;
        $memberRowId = $loan->borrower?->member_row_id ? (int) $loan->borrower->member_row_id : null;
        if ($memberRowId) {
            $memberName = DB::connection('tenant')
                ->table('members as m')
                ->join('people as p', function ($join) use ($tenantId): void {
                    $join->on('p.row_id', '=', 'm.person_row_id')->where('p.tenant_id', '=', $tenantId);
                })
                ->where('m.tenant_id', $tenantId)
                ->where('m.row_id', $memberRowId)
                ->value('p.full_name');
        }

        return [
            'loan_row_id' => (int) $loan->row_id,
            'loan_id' => (int) $loan->id,
            'loan_number' => $loan->loan_number,
            'status' => $loan->status,
            'principal_amount' => (float) $loan->principal_amount,
            'principal_paid' => $paid,
            'principal_remaining' => round((float) $loan->principal_amount - $paid, 2),
            'product_code' => $loan->product?->code,
            'group_row_id' => $loan->borrower?->group_row_id ? (int) $loan->borrower->group_row_id : null,
            'group_name' => $loan->borrower?->group?->name,
            'group_code' => $loan->borrower?->group?->code,
            'member_row_id' => $memberRowId,
            'member_name' => $memberName ? (string) $memberName : null,
            'disbursed_at' => $loan->disbursed_at?->toDateString(),
            'next_installment' => $next,
            'group_members' => $members,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function listAccounts(array $params): array
    {
        $prefix = isset($params['code_prefix']) ? trim((string) $params['code_prefix']) : '';
        $nameQ = trim((string) ($params['query'] ?? $params['name'] ?? ''));
        $cashOnly = filter_var($params['cash_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->limit(50);

        if ($prefix !== '') {
            $query->where('code', 'like', $prefix.'%');
        }
        if ($cashOnly) {
            $query->where('code', 'like', '1.1.01.%');
        }
        if ($nameQ !== '') {
            $tokens = preg_split('/\s+/', mb_strtolower($nameQ)) ?: [];
            $query->where(function ($w) use ($nameQ, $tokens): void {
                $w->where('name', 'like', '%'.$nameQ.'%')
                    ->orWhere('code', 'like', '%'.$nameQ.'%');
                foreach ($tokens as $tok) {
                    if (mb_strlen($tok) >= 2) {
                        $w->orWhere('name', 'like', '%'.$tok.'%');
                    }
                }
            });
        }

        $items = $query->get(['row_id', 'code', 'name', 'account_type', 'normal_balance'])
            ->map(fn (Account $a): array => [
                'row_id' => (int) $a->row_id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
                'account_type' => (string) $a->account_type,
                'normal_balance' => (string) $a->normal_balance,
            ])->all();

        // Rank bank name hits higher when query looks like a bank.
        if ($nameQ !== '' && $items !== []) {
            $needle = mb_strtolower($nameQ);
            usort($items, function (array $a, array $b) use ($needle): int {
                $sa = $this->accountNameScore($a['name'], $a['code'], $needle);
                $sb = $this->accountNameScore($b['name'], $b['code'], $needle);

                return $sb <=> $sa;
            });
        }

        return $this->withMatchMeta($items);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{due_date:string,items:list<array<string,mixed>>}
     */
    public function listDueBilling(array $params): array
    {
        $due = isset($params['due_date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $params['due_date'])->startOfDay()
            : CarbonImmutable::today();

        return [
            'due_date' => $due->toDateString(),
            'items' => $this->notices->dueOn($due),
        ];
    }

    /**
     * Find posted journals for correction/duplicate handling.
     * Prefer recent + filters (date, amount, type, account name, description).
     *
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchJournals(array $params): array
    {
        $tenantId = $this->context->id();
        $dateFrom = trim((string) ($params['date_from'] ?? $params['transaction_date'] ?? ''));
        $dateTo = trim((string) ($params['date_to'] ?? $params['transaction_date'] ?? ''));
        $type = trim((string) ($params['transaction_type'] ?? ''));
        $sourceType = trim((string) ($params['source_type'] ?? ''));
        $desc = trim((string) ($params['query'] ?? $params['description'] ?? ''));
        $amount = isset($params['amount']) ? (float) $params['amount'] : null;
        $accountQ = trim((string) ($params['account_query'] ?? ''));
        $journalId = (int) ($params['journal_row_id'] ?? $params['journal_id'] ?? 0);
        $recent = filter_var($params['recent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $createdBy = isset($params['created_by_user_id']) ? (int) $params['created_by_user_id'] : null;
        $excludeReversed = filter_var($params['exclude_reversed'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $limit = min(30, max(1, (int) ($params['limit'] ?? 15)));

        // "barusan" / recent without date → last 48h of actor-ish activity
        if ($recent && $dateFrom === '' && $dateTo === '') {
            $dateFrom = CarbonImmutable::now()->subDays(2)->toDateString();
            $dateTo = CarbonImmutable::today()->toDateString();
        }

        $q = DB::connection('tenant')
            ->table('journal_entries as e')
            ->where('e.tenant_id', $tenantId)
            ->where('e.status', 'posted');

        if ($journalId > 0) {
            $q->where(function ($w) use ($journalId): void {
                $w->where('e.row_id', $journalId)->orWhere('e.id', $journalId);
            });
        }
        if ($dateFrom !== '') {
            $q->whereDate('e.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $q->whereDate('e.transaction_date', '<=', $dateTo);
        }
        if ($type !== '') {
            $q->where('e.transaction_type', $type);
        }
        if ($sourceType !== '') {
            $q->where('e.source_type', $sourceType);
        }
        if ($desc !== '') {
            $q->where('e.description', 'like', '%'.$desc.'%');
        }
        if ($createdBy !== null && $createdBy > 0) {
            $q->where('e.created_by_user_id', $createdBy);
        }
        if ($excludeReversed) {
            // hide originals already reversed + hide reversal entries themselves by default
            $q->whereNotExists(function ($sub) use ($tenantId): void {
                $sub->selectRaw('1')
                    ->from('journal_entries as rev')
                    ->whereColumn('rev.reversed_entry_row_id', 'e.row_id')
                    ->where('rev.tenant_id', $tenantId);
            })->whereNull('e.reversed_entry_row_id');
        }

        if ($accountQ !== '' || $amount !== null) {
            $q->whereExists(function ($sub) use ($tenantId, $accountQ, $amount): void {
                $sub->selectRaw('1')
                    ->from('journal_lines as jl')
                    ->whereColumn('jl.journal_entry_row_id', 'e.row_id')
                    ->where('jl.tenant_id', $tenantId);
                if ($accountQ !== '') {
                    $sub->join('accounts as a', function ($join) use ($tenantId): void {
                        $join->on('a.row_id', '=', 'jl.account_row_id')
                            ->where('a.tenant_id', '=', $tenantId);
                    })->where(function ($w) use ($accountQ): void {
                        $w->where('a.name', 'like', '%'.$accountQ.'%')
                            ->orWhere('a.code', 'like', '%'.$accountQ.'%');
                    });
                }
                if ($amount !== null) {
                    $sub->where(function ($w) use ($amount): void {
                        $w->where('jl.debit', $amount)->orWhere('jl.credit', $amount);
                    });
                }
            });
        }

        // Default window if nothing specified: last 7 days (avoid dumping entire ledger)
        if ($journalId <= 0 && $dateFrom === '' && $dateTo === '' && $desc === '' && $amount === null && $accountQ === '' && $type === '') {
            $q->whereDate('e.transaction_date', '>=', CarbonImmutable::today()->subDays(7)->toDateString());
        }

        // Prefer installment journals when user hints angsuran/kelompok
        $installmentHint = trim((string) ($params['group_query'] ?? $params['wrong_group_query'] ?? $params['correct_group_query'] ?? ''));
        if ($type === '' && $sourceType === '' && ($installmentHint !== '' || filter_var($params['installments_only'] ?? false, FILTER_VALIDATE_BOOLEAN))) {
            $q->where(function ($w): void {
                $w->where('e.source_type', 'loan_installment')
                    ->orWhere('e.transaction_type', 'angsuran');
            });
        }

        $rows = $q->orderByDesc('e.transaction_date')
            ->orderByDesc('e.row_id')
            ->limit($limit)
            ->get([
                'e.row_id',
                'e.id',
                'e.journal_number',
                'e.transaction_date',
                'e.transaction_type',
                'e.source_type',
                'e.source_row_id',
                'e.description',
                'e.status',
                'e.created_by_user_id',
                'e.reversed_entry_row_id',
                'e.posted_at',
            ]);

        $items = [];
        foreach ($rows as $r) {
            $lines = DB::connection('tenant')
                ->table('journal_lines as jl')
                ->join('accounts as a', function ($join) use ($tenantId): void {
                    $join->on('a.row_id', '=', 'jl.account_row_id')->where('a.tenant_id', '=', $tenantId);
                })
                ->where('jl.tenant_id', $tenantId)
                ->where('jl.journal_entry_row_id', $r->row_id)
                ->orderBy('jl.line_number')
                ->get(['a.code', 'a.name', 'a.row_id as account_row_id', 'jl.debit', 'jl.credit']);

            $totalDebit = round((float) $lines->sum('debit'), 2);
            $loanCtx = null;
            $sourceRowId = isset($r->source_row_id) ? (int) $r->source_row_id : 0;
            // re-fetch source_row_id if not in select — add below in query
            $item = [
                'journal_row_id' => (int) $r->row_id,
                'journal_id' => (int) $r->id,
                'journal_number' => $r->journal_number,
                'transaction_date' => $r->transaction_date ? (string) $r->transaction_date : null,
                'transaction_type' => $r->transaction_type,
                'source_type' => $r->source_type,
                'source_row_id' => $sourceRowId > 0 ? $sourceRowId : null,
                'description' => (string) ($r->description ?? ''),
                'status' => (string) $r->status,
                'amount' => $totalDebit,
                'created_by_user_id' => $r->created_by_user_id ? (int) $r->created_by_user_id : null,
                'posted_at' => $r->posted_at ? (string) $r->posted_at : null,
                'already_reversed' => $r->reversed_entry_row_id !== null,
                'lines' => $lines->map(fn ($l): array => [
                    'account_code' => (string) $l->code,
                    'account_name' => (string) $l->name,
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                ])->all(),
                'split' => $this->extractInstallmentSplitFromLines($lines),
            ];

            if ((string) $r->source_type === 'loan_installment' && $sourceRowId > 0) {
                $loanCtx = $this->loanContextForJournal($sourceRowId);
                $item['loan'] = $loanCtx;
            }

            // optional filter: wrong/group name in description or linked loan group
            $groupQ = trim((string) ($params['group_query'] ?? $params['wrong_group_query'] ?? ''));
            if ($groupQ !== '') {
                $hay = mb_strtolower($item['description'].' '.($loanCtx['group_name'] ?? ''));
                if (! str_contains($hay, mb_strtolower($groupQ))) {
                    continue;
                }
            }

            $items[] = $item;
        }

        // Detect possible duplicates: same date+amount+type among results
        if (count($items) >= 2) {
            $finger = [];
            foreach ($items as $i => $it) {
                $key = ($it['transaction_date'] ?? '').'|'.$it['amount'].'|'.($it['transaction_type'] ?? '').'|'.($it['source_type'] ?? '');
                $finger[$key][] = $i;
            }
            foreach ($finger as $idxs) {
                if (count($idxs) < 2) {
                    continue;
                }
                foreach ($idxs as $i) {
                    $items[$i]['possible_duplicate_of'] = array_values(array_map(
                        static fn (int $j): int => $items[$j]['journal_row_id'],
                        array_filter($idxs, static fn (int $j): bool => $j !== $i),
                    ));
                }
            }
        }

        return $this->withMatchMeta($items);
    }

    /**
     * Reverse a posted journal (immutable ledger). Optionally re-post a corrected entry.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function reverseJournal(array $params, User $actor): array
    {
        $resolved = $this->resolveJournalForReverse($params);
        if (($resolved['needs_clarification'] ?? false) === true) {
            return $resolved;
        }

        /** @var JournalEntry $original */
        $original = $resolved['entry'];
        $reversalDate = (string) ($params['reversal_date'] ?? $params['transaction_date'] ?? CarbonImmutable::today()->toDateString());
        $reason = trim((string) ($params['reason'] ?? $params['description'] ?? ''));
        if ($reason === '') {
            $reason = sprintf('Pembatalan/koreksi jurnal #%s', $original->id ?? $original->row_id);
        }

        $correctGroup = trim((string) ($params['correct_group_query'] ?? $params['correct_group_name'] ?? ''));
        $correctLoanId = (int) ($params['correct_loan_id'] ?? $params['correct_loan_row_id'] ?? 0);
        $isInstallment = (string) $original->source_type === 'loan_installment'
            || (string) $original->transaction_type === 'angsuran';
        $willRepostInstallment = $isInstallment && (
            filter_var($params['repost'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($params['repost_installment'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || $correctGroup !== ''
            || $correctLoanId > 0
        );
        $willRepostGeneral = filter_var($params['repost'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || isset($params['correct_bank_account_query'])
            || isset($params['correct_debit_account_row_id']);

        if (! $this->isConfirmed($params)) {
            $original->loadMissing('lines.account');
            $amount = round((float) $original->lines->sum('debit'), 2);
            $loanMeta = $isInstallment && $original->source_row_id
                ? $this->loanContextForJournal((int) $original->source_row_id)
                : null;
            $warnings = [];
            if ($willRepostInstallment && $correctGroup === '' && $correctLoanId <= 0) {
                $warnings[] = 'Repost angsuran diminta tapi kelompok/pinjaman tujuan belum jelas.';
            }
            if ($willRepostGeneral && empty($params['correct_bank_account_query']) && empty($params['correct_debit_account_row_id']) && ! $willRepostInstallment) {
                $warnings[] = 'Repost diminta; pastikan akun koreksi sudah dipilih.';
            }

            return $this->previewResponse(
                action: 'reverse_journal',
                summary: sprintf(
                    'Batalkan jurnal #%s (%s) Rp %s tgl %s%s%s',
                    $original->id ?? $original->row_id,
                    $original->transaction_type ?? $original->source_type ?? 'jurnal',
                    number_format($amount, 0, ',', '.'),
                    $original->transaction_date?->toDateString() ?? '?',
                    $loanMeta['group_name'] ?? null ? ' — '.$loanMeta['group_name'] : '',
                    $willRepostInstallment
                        ? ' lalu post ulang angsuran ke '.($correctGroup !== '' ? $correctGroup : ($correctLoanId > 0 ? 'loan #'.$correctLoanId : '?'))
                        : ($willRepostGeneral ? ' lalu post jurnal pengganti' : ' (tanpa post ulang)'),
                ),
                plan: [
                    'journal_row_id' => (int) $original->row_id,
                    'reversal_date' => $reversalDate,
                    'reason' => $reason,
                    'amount' => $amount,
                    'was_installment' => $isInstallment,
                    'original_loan' => $loanMeta,
                    'repost_installment' => $willRepostInstallment,
                    'correct_group_query' => $correctGroup !== '' ? $correctGroup : null,
                    'correct_loan_id' => $correctLoanId > 0 ? $correctLoanId : null,
                    'repost_general' => $willRepostGeneral && ! $willRepostInstallment,
                    'correct_bank_account_query' => $params['correct_bank_account_query'] ?? null,
                    'lines' => $original->lines->map(fn ($l): array => [
                        'account_code' => $l->account?->code,
                        'account_name' => $l->account?->name,
                        'debit' => (float) $l->debit,
                        'credit' => (float) $l->credit,
                    ])->all(),
                ],
                warnings: $warnings,
                proposedParams: array_merge($params, [
                    'journal_row_id' => (int) $original->row_id,
                    'confirm' => true,
                ]),
            );
        }

        try {
            $reversal = $this->journalReversal->reverse(
                $original,
                $reversalDate,
                (int) $actor->row_id,
                $reason,
            );
        } catch (DomainException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        // Side-effect cleanup for installment journals (tracking rows only —
        // schedule principal_paid is not updated by recordInstallmentPayment today).
        if ((string) $original->source_type === 'loan_installment') {
            $this->cleanupInstallmentSideEffects((int) $original->row_id);
            $outNoteLoan = (int) ($original->source_row_id ?? 0);
        } else {
            $outNoteLoan = 0;
        }

        $out = [
            'reversed' => true,
            'original' => $this->serializeJournal($original->fresh(['lines.account'])),
            'reversal' => $this->serializeJournal($reversal->load('lines.account')),
            'reason' => $reason,
            'was_installment' => (string) $original->source_type === 'loan_installment',
            'original_loan_row_id' => $outNoteLoan > 0 ? $outNoteLoan : null,
        ];

        $correctGroup = trim((string) ($params['correct_group_query'] ?? $params['correct_group_name'] ?? ''));
        $correctLoanId = (int) ($params['correct_loan_id'] ?? $params['correct_loan_row_id'] ?? 0);
        $isInstallmentCorrection = (string) $original->source_type === 'loan_installment'
            || (string) $original->transaction_type === 'angsuran'
            || $correctGroup !== ''
            || $correctLoanId > 0
            || filter_var($params['repost_installment'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Optional: immediately post the correct replacement
        $repost = filter_var($params['repost'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasGeneralRepost = isset($params['correct_bank_account_query'])
            || isset($params['correct_transaction_type'])
            || isset($params['correct_debit_account_row_id']);

        if ($isInstallmentCorrection && ($repost || $correctGroup !== '' || $correctLoanId > 0 || filter_var($params['repost_installment'] ?? false, FILTER_VALIDATE_BOOLEAN))) {
            $inst = $this->buildCorrectedInstallmentParams($original, $params, $reversalDate, $reason);
            if (($inst['needs_clarification'] ?? false) === true) {
                $out['correction'] = $inst;
                $out['message'] = 'Angsuran salah sudah di-reversal. Pilih pinjaman/kelompok yang benar untuk post ulang.';

                return $out;
            }
            try {
                // Parent reverse already confirmed — post child without second preview gate.
                $out['correction'] = $this->recordInstallment(array_merge($inst, ['confirm' => true]), $actor);
                $out['message'] = 'Angsuran salah dibatalkan dan dicatat ulang ke pinjaman/kelompok yang benar.';
            } catch (ValidationException $e) {
                $out['correction'] = [
                    'needs_clarification' => true,
                    'reason' => 'correction_validation_failed',
                    'message' => 'Reversal OK; angsuran koreksi gagal validasi.',
                    'messages' => $e->errors(),
                ];
            }

            return $out;
        }

        if ($repost || $hasGeneralRepost) {
            $correct = $this->buildCorrectedJournalParams($original, $params, $reversalDate, $reason);
            if (($correct['needs_clarification'] ?? false) === true) {
                $out['correction'] = $correct;
                $out['message'] = 'Jurnal asli sudah dibatalkan (reversal). Koreksi baru perlu klarifikasi akun.';

                return $out;
            }
            try {
                $out['correction'] = $this->createJournalEntry(array_merge($correct, ['confirm' => true]), $actor);
                $out['message'] = 'Jurnal salah dibatalkan dan diganti entri koreksi.';
            } catch (ValidationException $e) {
                $out['correction'] = [
                    'needs_clarification' => true,
                    'reason' => 'correction_validation_failed',
                    'message' => 'Reversal OK; entri koreksi gagal validasi.',
                    'messages' => $e->errors(),
                ];
            }
        } else {
            $out['message'] = $out['was_installment']
                ? 'Angsuran dibatalkan via reversal GL. Tidak ada post ulang (beri correct_group_query / correct_loan_id + repost).'
                : 'Jurnal dibatalkan via reversal. Tidak ada entri pengganti (set repost=true untuk koreksi).';
        }

        return $out;
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array{principal_amount: float, interest_amount: float, penalty_amount: float, cash_account_row_id: ?int, total: float}
     */
    private function extractInstallmentSplitFromLines($lines): array
    {
        $principal = 0.0;
        $interest = 0.0;
        $penalty = 0.0;
        $cashRowId = null;
        foreach ($lines as $l) {
            $code = (string) ($l->code ?? $l->account_code ?? '');
            $debit = (float) ($l->debit ?? 0);
            $credit = (float) ($l->credit ?? 0);
            $name = mb_strtolower((string) ($l->name ?? $l->account_name ?? ''));
            if ($debit > 0 && str_starts_with($code, '1.1.01.')) {
                $cashRowId = isset($l->account_row_id) ? (int) $l->account_row_id : $cashRowId;
            }
            if ($credit <= 0) {
                continue;
            }
            // Order matters: "Pendapatan Jasa Piutang …" contains both jasa+piutang.
            if (str_contains($name, 'denda')) {
                $penalty += $credit;
            } elseif (str_starts_with($code, '4.') || str_contains($name, 'pendapatan') || str_contains($name, 'jasa')) {
                $interest += $credit;
            } elseif (str_starts_with($code, '1.1.03') || str_starts_with($code, '1.1.02') || str_contains($name, 'piutang')) {
                $principal += $credit;
            } else {
                $principal += $credit;
            }
        }

        return [
            'principal_amount' => round($principal, 2),
            'interest_amount' => round($interest, 2),
            'penalty_amount' => round($penalty, 2),
            'cash_account_row_id' => $cashRowId,
            'total' => round($principal + $interest + $penalty, 2),
        ];
    }

    /**
     * @return array{loan_row_id: int, group_row_id: ?int, group_name: ?string, product_code: ?string, status: ?string}|null
     */
    private function loanContextForJournal(int $loanRowId): ?array
    {
        if ($loanRowId <= 0) {
            return null;
        }
        $tenantId = $this->context->id();
        $row = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as lb', function ($join) use ($tenantId): void {
                $join->on('lb.loan_row_id', '=', 'l.row_id')->where('lb.tenant_id', '=', $tenantId);
            })
            ->leftJoin('groups as g', function ($join) use ($tenantId): void {
                $join->on('g.row_id', '=', 'lb.group_row_id')->where('g.tenant_id', '=', $tenantId);
            })
            ->leftJoin('loan_products as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'l.loan_product_row_id')->where('p.tenant_id', '=', $tenantId);
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.row_id', $loanRowId)
            ->first([
                'l.row_id',
                'l.status',
                'lb.group_row_id',
                'g.name as group_name',
                'p.code as product_code',
            ]);
        if ($row === null) {
            return null;
        }

        return [
            'loan_row_id' => (int) $row->row_id,
            'group_row_id' => $row->group_row_id ? (int) $row->group_row_id : null,
            'group_name' => $row->group_name ? (string) $row->group_name : null,
            'product_code' => $row->product_code ? (string) $row->product_code : null,
            'status' => $row->status ? (string) $row->status : null,
        ];
    }

    private function cleanupInstallmentSideEffects(int $journalRowId): void
    {
        $tenantId = $this->context->id();
        // tracking table may not exist on older shards
        try {
            DB::connection('tenant')
                ->table('loan_installment_tracking')
                ->where('tenant_id', $tenantId)
                ->where('journal_entry_row_id', $journalRowId)
                ->delete();
        } catch (\Throwable) {
            // ignore missing table
        }
    }

    /**
     * After reversing a wrong-group installment, rebuild record_installment params
     * for the correct group/loan, reusing principal/interest/cash from original lines.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildCorrectedInstallmentParams(JournalEntry $original, array $params, string $date, string $reason): array
    {
        $original->loadMissing('lines.account');
        $lines = $original->lines->map(fn ($l) => (object) [
            'code' => $l->account?->code,
            'name' => $l->account?->name,
            'debit' => $l->debit,
            'credit' => $l->credit,
            'account_row_id' => $l->account_row_id,
        ]);
        $split = $this->extractInstallmentSplitFromLines($lines);

        $cashRowId = null;
        foreach ($original->lines as $l) {
            if ((float) $l->debit > 0 && $l->account && str_starts_with((string) $l->account->code, '1.1.01.')) {
                $cashRowId = (int) $l->account_row_id;
                break;
            }
        }
        if ($cashRowId === null) {
            $cash = $this->resolveCashAccount((string) ($params['cash_account_query'] ?? 'Kas Tunai'));
            if (isset($cash['needs_clarification'])) {
                return $cash;
            }
            $cashRowId = $cash['row_id'];
        }

        $correctLoanId = (int) ($params['correct_loan_id'] ?? $params['correct_loan_row_id'] ?? 0);
        $correctGroup = trim((string) ($params['correct_group_query'] ?? $params['correct_group_name'] ?? ''));
        $memberQuery = trim((string) ($params['member_query'] ?? $params['correct_member_query'] ?? ''));
        // Try parse member from original description "a/n X" / "dari X"
        if ($memberQuery === '' && is_string($original->description)) {
            if (preg_match('/a\/n\s+([^.,;]+)/iu', $original->description, $m)
                || preg_match('/(?:dari|titipan(?:\s+angsuran)?)\s+([^.,;]+?)(?:\s+kelompok|\s+group|$)/iu', $original->description, $m)) {
                $memberQuery = trim($m[1]);
            }
        }

        if ($correctLoanId <= 0) {
            if ($correctGroup === '') {
                return [
                    'needs_clarification' => true,
                    'reason' => 'correct_loan_required',
                    'message' => 'Sebutkan correct_group_query atau correct_loan_id untuk angsuran pengganti.',
                    'candidates' => [],
                    'split' => $split,
                ];
            }
            $loans = $this->searchLoans(['group_query' => $correctGroup]);
            if ($loans['match_count'] === 0) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'correct_loan_not_found',
                    'message' => "Tidak ada pinjaman aktif untuk kelompok \"{$correctGroup}\".",
                    'candidates' => [],
                ];
            }
            if ($loans['match_count'] > 1) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'ambiguous_correct_loan',
                    'message' => 'Beberapa pinjaman di kelompok tujuan. Pilih correct_loan_id.',
                    'candidates' => $loans['items'],
                    'match_count' => $loans['match_count'],
                    'split' => $split,
                ];
            }
            $correctLoanId = (int) $loans['items'][0]['loan_row_id'];
        }

        // Don't repost onto the same loan that was just reversed
        $origLoan = (int) ($original->source_row_id ?? 0);
        if ($origLoan > 0 && $correctLoanId === $origLoan && $correctGroup === '') {
            return [
                'needs_clarification' => true,
                'reason' => 'same_loan',
                'message' => 'Pinjaman tujuan sama dengan yang dibatalkan. Sebutkan kelompok/pinjaman yang benar.',
                'candidates' => [],
            ];
        }

        $out = [
            'transaction_date' => $date,
            'loan_id' => $correctLoanId,
            'principal_amount' => $split['principal_amount'],
            'interest_amount' => $split['interest_amount'],
            'penalty_amount' => $split['penalty_amount'],
            'cash_account_row_id' => $cashRowId,
            'description' => mb_substr(
                trim((string) ($params['correct_description'] ?? '')) !== ''
                    ? (string) $params['correct_description']
                    : sprintf('Koreksi angsuran: %s (dari jurnal #%s)', $reason, $original->id ?? $original->row_id),
                0,
                500,
            ),
        ];

        if ($memberQuery !== '') {
            $out['member_query'] = $memberQuery;
            if ($correctGroup !== '') {
                $out['group_query'] = $correctGroup;
            }
        } elseif (! empty($params['reference']) || ! empty($params['member_row_id'])) {
            $out['reference'] = (int) ($params['reference'] ?? $params['member_row_id']);
        } else {
            // let normalizeInstallmentParams resolve from group members / clarify
            if ($correctGroup !== '') {
                $out['group_query'] = $correctGroup;
            }
        }

        // optional override amounts
        if (isset($params['correct_amount']) || isset($params['total_amount'])) {
            $out['total_amount'] = (float) ($params['correct_amount'] ?? $params['total_amount']);
            unset($out['principal_amount'], $out['interest_amount']);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{entry: JournalEntry}|array{needs_clarification: bool, reason: string, message: string, candidates?: list<array<string,mixed>>}
     */
    private function resolveJournalForReverse(array $params): array
    {
        $id = (int) ($params['journal_row_id'] ?? $params['journal_id'] ?? 0);
        if ($id > 0) {
            $entry = JournalEntry::query()->with('lines.account')->where('row_id', $id)->first()
                ?? JournalEntry::query()->with('lines.account')->where('id', $id)->first();
            if ($entry === null) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'journal_not_found',
                    'message' => "Jurnal {$id} tidak ditemukan.",
                    'candidates' => [],
                ];
            }
            if ($entry->status !== 'posted') {
                return [
                    'needs_clarification' => true,
                    'reason' => 'not_posted',
                    'message' => 'Hanya jurnal posted yang bisa di-reversal.',
                    'candidates' => [],
                ];
            }

            return ['entry' => $entry];
        }

        $searchParams = array_merge($params, [
            'exclude_reversed' => true,
            'recent' => filter_var($params['recent'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
        // map wrong-account hint → account_query
        if (empty($searchParams['account_query']) && ! empty($params['wrong_account_query'])) {
            $searchParams['account_query'] = $params['wrong_account_query'];
        }
        if (empty($searchParams['group_query']) && ! empty($params['wrong_group_query'])) {
            $searchParams['group_query'] = $params['wrong_group_query'];
        }
        // angsuran salah kelompok → batasi ke loan_installment
        if (! empty($params['wrong_group_query']) || ! empty($params['correct_group_query']) || ! empty($params['correct_loan_id'])) {
            $searchParams['installments_only'] = true;
            if (empty($searchParams['transaction_type']) && empty($searchParams['source_type'])) {
                $searchParams['source_type'] = 'loan_installment';
            }
        }

        $found = $this->searchJournals($searchParams);
        if ($found['match_count'] === 0) {
            return [
                'needs_clarification' => true,
                'reason' => 'journal_not_found',
                'message' => 'Jurnal tidak ditemukan. Sebutkan tanggal/nominal/akun atau journal_row_id.',
                'candidates' => [],
            ];
        }
        if ($found['match_count'] > 1) {
            // If possible_duplicate clusters of size 2 with identical fingerprint and user said duplicate, still clarify which to keep
            return [
                'needs_clarification' => true,
                'reason' => 'ambiguous_journal',
                'message' => 'Beberapa jurnal cocok. Pilih journal_row_id yang akan di-reversal (untuk duplikat: batalkan yang salah, biarkan yang benar).',
                'candidates' => $found['items'],
                'match_count' => $found['match_count'],
            ];
        }

        $entry = JournalEntry::query()
            ->with('lines.account')
            ->where('row_id', $found['items'][0]['journal_row_id'])
            ->firstOrFail();

        return ['entry' => $entry];
    }

    /**
     * Build create_journal_entry params for a correction after reverse.
     * Typical: wrong bank Ops → correct bank SPP (same amount, swap debit account).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildCorrectedJournalParams(JournalEntry $original, array $params, string $date, string $reason): array
    {
        $original->loadMissing('lines.account');
        $amount = isset($params['correct_amount'])
            ? (float) $params['correct_amount']
            : (isset($params['amount']) ? (float) $params['amount'] : (float) $original->lines->sum('debit'));

        $type = (string) ($params['correct_transaction_type']
            ?? $params['transaction_type']
            ?? $original->transaction_type
            ?? 'pemindahan_saldo');

        $desc = trim((string) ($params['correct_description'] ?? ''));
        if ($desc === '') {
            $desc = 'Koreksi: '.$reason;
            if ($original->description) {
                $desc .= ' (asli: '.$original->description.')';
            }
        }

        $out = [
            'transaction_date' => $date,
            'transaction_type' => $type,
            'amount' => $amount,
            'description' => mb_substr($desc, 0, 500),
            'reference' => 'koreksi-of-'.$original->row_id,
        ];

        if (! empty($params['correct_debit_account_row_id'])) {
            $out['debit_account_row_id'] = (int) $params['correct_debit_account_row_id'];
        } elseif (! empty($params['correct_bank_account_query']) || ! empty($params['correct_account_query'])) {
            $hint = (string) ($params['correct_bank_account_query'] ?? $params['correct_account_query']);
            if ($type === 'pemindahan_saldo' || str_contains(mb_strtolower($hint), 'bank')) {
                $bank = $this->resolveBankAccount($hint);
                if (isset($bank['needs_clarification'])) {
                    return $bank;
                }
                $out['debit_account_row_id'] = $bank['row_id'];
            } else {
                $list = $this->listAccounts(['query' => $hint]);
                if ($list['match_count'] !== 1) {
                    return [
                        'needs_clarification' => true,
                        'reason' => 'ambiguous_correct_account',
                        'message' => 'Akun koreksi ambigu. Pilih correct_debit_account_row_id.',
                        'candidates' => $list['items'],
                    ];
                }
                $out['debit_account_row_id'] = $list['items'][0]['row_id'];
            }
        }

        if (! empty($params['correct_credit_account_row_id'])) {
            $out['credit_account_row_id'] = (int) $params['correct_credit_account_row_id'];
        } elseif (! empty($params['correct_cash_account_query'])) {
            $cash = $this->resolveCashAccount((string) $params['correct_cash_account_query']);
            if (isset($cash['needs_clarification'])) {
                return $cash;
            }
            $out['credit_account_row_id'] = $cash['row_id'];
        } else {
            // default: reuse original credit line (usually Kas Tunai for setor)
            $creditLine = $original->lines->first(static fn ($l) => (float) $l->credit > 0);
            if ($creditLine) {
                $out['credit_account_row_id'] = (int) $creditLine->account_row_id;
            }
        }

        // If debit still missing, try copy non-matching from original debit
        if (empty($out['debit_account_row_id'])) {
            $debitLine = $original->lines->first(static fn ($l) => (float) $l->debit > 0);
            if ($debitLine) {
                $out['debit_account_row_id'] = (int) $debitLine->account_row_id;
            }
        }

        return $out;
    }

    /**
     * General journal → posted.
     *
     * Supports pembelian_aset_* (creates Asset) — same rules as UI:
     * debit = 1.2.01.0x per kind, credit = kas (1.1.01.*).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createJournalEntry(array $params, User $actor): array
    {
        $tenantId = $this->context->id();
        $types = JournalEntryRequest::TRANSACTION_TYPES;
        $accountExists = Rule::exists(Account::class, 'row_id')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true)->where('is_postable', true));

        // Alias UI field names → debit/credit.
        if (! isset($params['debit_account_row_id']) && isset($params['disimpan_ke_row_id'])) {
            $params['debit_account_row_id'] = $params['disimpan_ke_row_id'];
        }
        if (! isset($params['credit_account_row_id']) && isset($params['sumber_dana_row_id'])) {
            $params['credit_account_row_id'] = $params['sumber_dana_row_id'];
        }

        $hintName = trim((string) ($params['asset_name'] ?? $params['description'] ?? ''));
        $type = (string) ($params['transaction_type'] ?? '');
        if ($type === '' && $hintName !== '' && $this->looksLikeInventoryPurchase($hintName, $params)) {
            $code = $this->suggestInventoryAccountCode($hintName !== '' ? $hintName : (string) ($params['asset_name'] ?? ''));
            $type = match ($code) {
                '1.2.01.01' => 'pembelian_aset_tanah',
                '1.2.01.02' => 'pembelian_aset_gedung',
                '1.2.01.03' => 'pembelian_aset_kendaraan',
                '1.2.03.01' => 'pembelian_biaya_pendirian',
                '1.2.03.02' => 'pembelian_lisensi',
                '1.2.03.03' => 'pembelian_sewa_dibayar_dimuka',
                '1.2.03.04' => 'pembelian_asuransi_dibayar_dimuka',
                default => 'pembelian_aset_peralatan',
            };
            $params['transaction_type'] = $type;
        }
        if ($type === '' && $this->looksLikeCashTransfer($hintName, $params)) {
            $type = 'pemindahan_saldo';
            $params['transaction_type'] = $type;
        }
        if ($type === '' && ! empty($params['debit_account_row_id']) && ! empty($params['credit_account_row_id'])) {
            $type = 'pemindahan_saldo';
            $params['transaction_type'] = $type;
        }
        if (empty($params['description'])) {
            $debitName = ! empty($params['debit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['debit_account_row_id'])->value('name')
                : null;
            $creditName = ! empty($params['credit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['credit_account_row_id'])->value('name')
                : null;
            if ($debitName && $creditName) {
                $params['description'] = sprintf('Pemindahan saldo dari %s ke %s', $creditName, $debitName);
            } else {
                $params['description'] = 'Pencatatan jurnal transaksi';
            }
        }
        $isInventory = JournalEntryOptionResolver::isAssetPurchase($type);
        $isTransfer = $type === 'pemindahan_saldo';

        if ($isInventory) {
            $params = $this->normalizeInventoryParams($params, $hintName);
        } elseif ($isTransfer) {
            $params = $this->normalizeTransferParams($params, $hintName);
            if (($params['needs_clarification'] ?? false) === true) {
                return $params;
            }
        }

        // Soft-validate for preview without posting.
        if (! $this->isConfirmed($params)) {
            $previewErrors = [];
            foreach (['transaction_date', 'amount'] as $req) {
                if (empty($params[$req])) {
                    $previewErrors[$req] = ["{$req} wajib untuk preview"];
                }
            }
            if ($type === '') {
                $previewErrors['transaction_type'] = ['Jenis transaksi tidak jelas (setor bank / beli inventaris / …)'];
            }
            if ($previewErrors !== []) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'incomplete_journal',
                    'message' => 'Data jurnal belum lengkap. Lengkapi atau pilih opsi.',
                    'messages' => $previewErrors,
                ];
            }
            $debit = ! empty($params['debit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['debit_account_row_id'])->first(['row_id', 'code', 'name'])
                : null;
            $credit = ! empty($params['credit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['credit_account_row_id'])->first(['row_id', 'code', 'name'])
                : null;

            return $this->previewResponse(
                action: 'create_journal_entry',
                summary: sprintf(
                    '%s Rp %s tgl %s — Dr %s / Cr %s',
                    $type !== '' ? $type : 'jurnal',
                    number_format((float) $params['amount'], 0, ',', '.'),
                    (string) $params['transaction_date'],
                    $debit ? "{$debit->code} {$debit->name}" : '?',
                    $credit ? "{$credit->code} {$credit->name}" : '?',
                ),
                plan: [
                    'transaction_date' => $params['transaction_date'] ?? null,
                    'transaction_type' => $type,
                    'amount' => isset($params['amount']) ? (float) $params['amount'] : null,
                    'description' => $params['description'] ?? null,
                    'debit' => $debit ? ['row_id' => (int) $debit->row_id, 'code' => $debit->code, 'name' => $debit->name] : null,
                    'credit' => $credit ? ['row_id' => (int) $credit->row_id, 'code' => $credit->code, 'name' => $credit->name] : null,
                    'inventory' => $isInventory ? [
                        'asset_name' => $params['asset_name'] ?? null,
                        'qty' => $params['asset_quantity'] ?? null,
                        'unit_cost' => $params['asset_unit_cost'] ?? null,
                    ] : null,
                ],
                warnings: array_values(array_filter([
                    ($debit === null) ? 'Akun debit belum ter-resolve' : null,
                    ($credit === null) ? 'Akun kredit belum ter-resolve' : null,
                    empty($params['description']) && ! $isInventory ? 'Deskripsi kosong' : null,
                ])),
                proposedParams: array_merge($params, ['confirm' => true]),
            );
        }

        $data = Validator::make($params, [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_type' => ['required', Rule::in($types)],
            'description' => [$isInventory ? 'nullable' : 'required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'debit_account_row_id' => ['required', 'integer', $accountExists],
            'credit_account_row_id' => ['required', 'integer', 'different:debit_account_row_id', $accountExists],
            'asset_name' => [$isInventory ? 'required' : 'nullable', 'string', 'max:180'],
            'asset_quantity' => [$isInventory ? 'required' : 'nullable', 'integer', 'min:1', 'max:999999'],
            'asset_unit_cost' => [$isInventory ? 'required' : 'nullable', 'numeric', 'min:1'],
            'asset_useful_life_months' => [
                $isInventory ? 'required' : 'nullable',
                'integer',
                ($type === 'pembelian_aset_tanah') ? 'min:0' : 'min:0',
                'max:1200',
            ],
        ], [], [
            'debit_account_row_id' => 'akun debit (disimpan ke / inventaris)',
            'credit_account_row_id' => 'akun kredit (sumber dana / kas)',
            'asset_name' => 'nama barang',
            'asset_quantity' => 'jumlah unit',
            'asset_unit_cost' => 'harga satuan',
            'asset_useful_life_months' => 'umur ekonomis (bulan)',
        ])->validate();

        if ($isInventory) {
            $qty = (int) $data['asset_quantity'];
            $unit = (float) $data['asset_unit_cost'];
            $expected = round($qty * $unit, 2);
            $amount = round((float) $data['amount'], 2);
            if ($amount !== $expected) {
                // Prefer qty×unit as source of truth for inventory.
                $data['amount'] = $expected;
            }
            $name = trim((string) $data['asset_name']);
            $data['description'] = trim((string) ($data['description'] ?? '')) !== ''
                ? (string) $data['description']
                : sprintf('Pembelian inventaris: %s (%d unit)', $name, $qty);
        }

        $userId = (int) $actor->row_id;

        $entry = DB::connection('tenant')->transaction(function () use ($data, $userId, $isInventory): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $data['transaction_date'],
                'sequence_number' => 0,
                'source_type' => $isInventory ? 'asset_purchase' : 'manual',
                'transaction_type' => $data['transaction_type'],
                'source_row_id' => null,
                'description' => $data['description'],
                'legacy_relation' => $data['reference'] ?? null,
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $entry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $data['debit_account_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => (float) $data['amount'],
                'credit' => 0,
            ]);

            $entry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $data['credit_account_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => 0,
                'credit' => (float) $data['amount'],
            ]);

            if ($isInventory) {
                $asset = app(AssetService::class)->create([
                    'name' => (string) $data['asset_name'],
                    'purchased_at' => $data['transaction_date'],
                    'quantity' => (int) $data['asset_quantity'],
                    'unit_cost' => (float) $data['asset_unit_cost'],
                    'useful_life_months' => (int) ($data['asset_useful_life_months'] ?? 0),
                    'status' => 'good',
                    'category_code' => JournalEntryOptionResolver::ATB_PURCHASE_TYPES[$data['transaction_type']] ?? null,
                ], $userId);
                $entry->update(['source_row_id' => (int) $asset->row_id]);
            }

            return $entry->fresh(['lines.account']);
        });

        $posted = $this->journalPosting->post($entry, $userId);
        $result = $this->serializeJournal($posted);
        if ($isInventory) {
            $result['inventory'] = [
                'asset_row_id' => (int) $posted->source_row_id,
                'asset_name' => (string) $data['asset_name'],
                'quantity' => (int) $data['asset_quantity'],
                'unit_cost' => (float) $data['asset_unit_cost'],
                'useful_life_months' => (int) $data['asset_useful_life_months'],
                'inferred' => [
                    'debit_account' => Account::query()->where('row_id', (int) $data['debit_account_row_id'])->value('code'),
                    'credit_account' => Account::query()->where('row_id', (int) $data['credit_account_row_id'])->value('code'),
                ],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function looksLikeInventoryPurchase(string $text, array $params): bool
    {
        if (isset($params['asset_name']) && trim((string) $params['asset_name']) !== '') {
            return true;
        }
        $t = mb_strtolower($text);
        foreach (['beli', 'membeli', 'pembelian', 'inventaris', 'aset tetap', 'aset tak berwujud', 'lisensi', 'sewa', 'asuransi', 'motor', 'mobil', 'kendaraan', 'laptop', 'komputer', 'meja', 'kursi', 'printer', 'mesin'] as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fill inventory defaults + resolve accounts when LLM omits row ids.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function normalizeInventoryParams(array $params, string $hintName): array
    {
        $name = trim((string) ($params['asset_name'] ?? ''));
        if ($name === '') {
            // "beli sepeda motor kemarin" → strip leading beli/membeli
            $name = trim((string) preg_replace(
                '/^(saya\s+)?(kemarin\s+)?(telah\s+)?(sudah\s+)?(membeli|beli|pembelian)\s+/iu',
                '',
                $hintName,
            ));
            $name = trim((string) preg_replace('/\s+(kemarin|hari\s+ini|tadi).*$/iu', '', $name));
            if ($name === '') {
                $name = $hintName !== '' ? $hintName : 'Inventaris';
            }
            $params['asset_name'] = mb_substr($name, 0, 180);
        }

        $qty = max(1, (int) ($params['asset_quantity'] ?? 1));
        $params['asset_quantity'] = $qty;

        $unit = isset($params['asset_unit_cost']) ? (float) $params['asset_unit_cost'] : 0.0;
        $amount = isset($params['amount']) ? (float) $params['amount'] : 0.0;
        if ($unit <= 0 && $amount > 0) {
            $unit = round($amount / $qty, 2);
        }
        if ($amount <= 0 && $unit > 0) {
            $amount = round($unit * $qty, 2);
        }
        if ($unit > 0) {
            $params['asset_unit_cost'] = $unit;
        }
        if ($amount > 0) {
            $params['amount'] = $amount;
        }

        if (! isset($params['asset_useful_life_months']) || $params['asset_useful_life_months'] === '') {
            $params['asset_useful_life_months'] = $this->defaultUsefulLifeMonths((string) $params['asset_name']);
        }

        // transaction_date: required Y-m-d from the LLM (orchestrator resolves
        // "kemarin" / "minggu lalu" using today's date in the embed system prompt).

        // Resolve debit (inventaris) / credit (kas) if missing.
        if (empty($params['debit_account_row_id'])) {
            $code = $this->suggestInventoryAccountCode((string) $params['asset_name']);
            $isIntangible = str_starts_with($code, '1.2.03.');
            $params['debit_account_row_id'] = $this->findPostableAccountRowId($code, $isIntangible ? '1.2.03.' : '1.2.01.');
        }
        if (empty($params['credit_account_row_id'])) {
            $cashCode = (string) ($params['cash_account_code'] ?? '1.1.01.01');
            $params['credit_account_row_id'] = $this->findPostableAccountRowId($cashCode, '1.1.01.');
        }

        if (empty($params['description'])) {
            $params['description'] = sprintf(
                'Pembelian inventaris: %s (%d unit)',
                $params['asset_name'],
                $params['asset_quantity'],
            );
        }

        return $params;
    }

    private function defaultUsefulLifeMonths(string $assetName): int
    {
        $t = mb_strtolower($assetName);
        // Kendaraan & mesin — COA 1.2.01.03, umum 4–5 tahun.
        foreach (['motor', 'mobil', 'kendaraan', 'truk', 'pick up', 'pickup', 'sepeda motor', 'mesin'] as $kw) {
            if (str_contains($t, $kw)) {
                return 48;
            }
        }
        if (str_contains($t, 'gedung') || str_contains($t, 'bangunan')) {
            return 240;
        }
        if (str_contains($t, 'tanah')) {
            return 0; // non-depreciating
        }
        foreach (['lisensi', 'sewa', 'asuransi', 'hak pakai', 'aset tak berwujud'] as $kw) {
            if (str_contains($t, $kw)) {
                return 60;
            }
        }
        // Inventaris/peralatan & elektronik
        foreach (['laptop', 'komputer', 'printer', 'monitor', 'hp', 'handphone'] as $kw) {
            if (str_contains($t, $kw)) {
                return 36;
            }
        }

        return 60; // default inventaris/peralatan
    }

    private function suggestInventoryAccountCode(string $assetName): string
    {
        $t = mb_strtolower($assetName);
        if (str_contains($t, 'pendirian')) {
            return '1.2.03.01';
        }
        if (str_contains($t, 'lisensi')) {
            return '1.2.03.02';
        }
        if (str_contains($t, 'sewa')) {
            return '1.2.03.03';
        }
        if (str_contains($t, 'asuransi')) {
            return '1.2.03.04';
        }
        if (str_contains($t, 'tanah')) {
            return '1.2.01.01';
        }
        if (str_contains($t, 'gedung') || str_contains($t, 'bangunan')) {
            return '1.2.01.02';
        }
        foreach (['motor', 'mobil', 'kendaraan', 'truk', 'mesin', 'sepeda motor'] as $kw) {
            if (str_contains($t, $kw)) {
                return '1.2.01.03'; // Kendaraan dan Mesin
            }
        }

        return '1.2.01.04'; // Inventaris/Peralatan
    }

    private function findPostableAccountRowId(string $preferredCode, string $fallbackPrefix): int
    {
        $exact = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', $preferredCode)
            ->value('row_id');
        if ($exact !== null) {
            return (int) $exact;
        }

        $fallback = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', $fallbackPrefix.'%')
            ->orderBy('code')
            ->value('row_id');
        if ($fallback === null) {
            throw ValidationException::withMessages([
                'debit_account_row_id' => "Akun postable dengan prefix {$fallbackPrefix} tidak ditemukan.",
            ]);
        }

        return (int) $fallback;
    }

    /**
     * Accept either flat principal/interest OR total_amount + optional
     * member/group names. Resolve loan, cash account, and split schedule.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function recordInstallment(array $params, User $actor): array
    {
        $resolved = $this->normalizeInstallmentParams($params);
        if (($resolved['needs_clarification'] ?? false) === true) {
            return $resolved;
        }

        $loanId = (int) $resolved['loan_id'];
        $principal = (float) $resolved['principal_amount'];
        $interest = (float) $resolved['interest_amount'];
        $penalty = (float) ($resolved['penalty_amount'] ?? 0);
        $total = round($principal + $interest + $penalty, 2);
        if ($total <= 0) {
            throw ValidationException::withMessages(['amount' => 'Total angsuran harus > 0']);
        }

        $next = $this->nextOpenInstallment($loanId);
        $dueTotal = $next ? (float) $next['total_remaining'] : null;
        $excess = $dueTotal !== null ? round($total - $dueTotal, 2) : 0.0;
        $short = $dueTotal !== null ? round($dueTotal - $total, 2) : 0.0;
        $warnings = [];
        $options = [];
        if ($excess > 0) {
            $warnings[] = sprintf(
                'Kelebihan bayar Rp %s di atas sisa angsuran ke-%s (Rp %s). Default: sisa ke pokok.',
                number_format($excess, 0, ',', '.'),
                $next['installment_number'] ?? '?',
                number_format($dueTotal, 0, ',', '.'),
            );
            $options = [
                ['id' => 'apply_excess_to_principal', 'label' => 'Terima full; kelebihan ke pokok'],
                ['id' => 'cap_to_due', 'label' => 'Batasi ke tagihan saja (Rp '.number_format($dueTotal, 0, ',', '.').')'],
                ['id' => 'cancel', 'label' => 'Batalkan, jangan catat'],
            ];
        } elseif ($short > 0 && $dueTotal !== null) {
            $warnings[] = sprintf(
                'Kurang bayar Rp %s dari tagihan angsuran ke-%s (Rp %s).',
                number_format($short, 0, ',', '.'),
                $next['installment_number'] ?? '?',
                number_format($dueTotal, 0, ',', '.'),
            );
        }

        // User chose cap_to_due on a previous preview turn
        $choice = (string) ($params['allocation_choice'] ?? $resolved['allocation_choice'] ?? '');
        if ($choice === 'cap_to_due' && $next !== null && $dueTotal !== null && $total > $dueTotal) {
            $capped = $this->splitInstallmentAmount($loanId, $dueTotal, isset($resolved['installment_number']) ? (int) $resolved['installment_number'] : null);
            $resolved['principal_amount'] = $capped['principal_amount'];
            $resolved['interest_amount'] = $capped['interest_amount'];
            $resolved['penalty_amount'] = $capped['penalty_amount'];
            $principal = $capped['principal_amount'];
            $interest = $capped['interest_amount'];
            $penalty = $capped['penalty_amount'];
            $total = $dueTotal;
            $excess = 0.0;
            $warnings[] = 'Nominal dibatasi ke sisa tagihan (cap_to_due).';
        }
        if ($choice === 'cancel') {
            return [
                'cancelled' => true,
                'message' => 'Pencatatan angsuran dibatalkan atas permintaan user.',
            ];
        }

        if (! $this->isConfirmed($params)) {
            $cash = Account::query()->where('row_id', (int) $resolved['cash_account_row_id'])->first(['row_id', 'code', 'name']);
            $memberName = DB::connection('tenant')
                ->table('members as m')
                ->join('people as p', function ($join): void {
                    $join->on('p.row_id', '=', 'm.person_row_id')->on('p.tenant_id', '=', 'm.tenant_id');
                })
                ->where('m.row_id', (int) $resolved['reference'])
                ->value('p.full_name');

            $loanMeta = $this->loanContextForJournal($loanId);

            return $this->previewResponse(
                action: 'record_installment',
                summary: sprintf(
                    'Angsuran Rp %s (pokok %s + jasa %s%s) pinjaman #%s%s tgl %s — penyetor %s, kas %s',
                    number_format($total, 0, ',', '.'),
                    number_format($principal, 0, ',', '.'),
                    number_format($interest, 0, ',', '.'),
                    $penalty > 0 ? ' + denda '.number_format($penalty, 0, ',', '.') : '',
                    $loanId,
                    $loanMeta['group_name'] ?? null ? ' ('.$loanMeta['group_name'].')' : '',
                    (string) $resolved['transaction_date'],
                    $memberName ? (string) $memberName : ('#'.$resolved['reference']),
                    $cash ? "{$cash->code} {$cash->name}" : '#'.$resolved['cash_account_row_id'],
                ),
                plan: [
                    'transaction_date' => $resolved['transaction_date'],
                    'loan_id' => $loanId,
                    'loan' => $loanMeta,
                    'installment_number' => $resolved['installment_number'] ?? $next['installment_number'] ?? null,
                    'principal_amount' => $principal,
                    'interest_amount' => $interest,
                    'penalty_amount' => $penalty,
                    'total' => $total,
                    'next_installment' => $next,
                    'due_total' => $dueTotal,
                    'excess' => $excess > 0 ? $excess : 0.0,
                    'shortfall' => $short > 0 ? $short : 0.0,
                    'cash_account' => $cash ? ['row_id' => (int) $cash->row_id, 'code' => $cash->code, 'name' => $cash->name] : null,
                    'reference_member_row_id' => (int) $resolved['reference'],
                    'reference_member_name' => $memberName ? (string) $memberName : null,
                    'description' => $resolved['description'] ?? null,
                ],
                warnings: $warnings,
                proposedParams: array_merge($resolved, ['confirm' => true]),
                options: $options,
            );
        }

        // Overpayment without explicit choice → still ask (don't silent-post excess)
        if ($excess > 0 && $choice === '') {
            return [
                'needs_clarification' => true,
                'reason' => 'overpayment',
                'message' => $warnings[0] ?? 'Kelebihan bayar. Pilih alokasi.',
                'options' => $options,
                'plan' => [
                    'total' => $total,
                    'due_total' => $dueTotal,
                    'excess' => $excess,
                    'next_installment' => $next,
                ],
                'proposed_params' => array_merge($resolved, ['confirm' => true]),
            ];
        }

        $tenantId = $this->context->id();
        $cashExists = Rule::exists(Account::class, 'row_id')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true)->where('is_postable', true)->where('code', 'like', '1.1.01.%'));
        $memberExists = Rule::exists(Member::class, 'row_id')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('status', 'active'));

        $data = Validator::make($resolved, [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'loan_id' => ['required', 'integer', Rule::exists(Loan::class, 'row_id')],
            'installment_number' => ['nullable', 'integer', 'min:1'],
            'principal_amount' => ['required', 'numeric', 'min:0'],
            'interest_amount' => ['required', 'numeric', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_account_row_id' => ['required', 'integer', $cashExists],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['required', 'integer', $memberExists],
            'member_allocations' => ['nullable', 'array'],
        ])->validate();

        $posted = $this->loans->recordInstallmentPayment($data, (int) $actor->row_id);
        $out = $this->serializeJournal($posted->load('lines.account'));
        $out['resolved'] = [
            'loan_id' => (int) $data['loan_id'],
            'installment_number' => $data['installment_number'] ?? null,
            'principal_amount' => (float) $data['principal_amount'],
            'interest_amount' => (float) $data['interest_amount'],
            'penalty_amount' => (float) ($data['penalty_amount'] ?? 0),
            'cash_account_row_id' => (int) $data['cash_account_row_id'],
            'reference' => (int) $data['reference'],
            'excess_applied_to_principal' => $excess > 0 && $choice !== 'cap_to_due',
        ];

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function normalizeInstallmentParams(array $params): array
    {
        // loan_id alias
        if (empty($params['loan_id']) && ! empty($params['loan_row_id'])) {
            $params['loan_id'] = $params['loan_row_id'];
        }

        $memberQuery = trim((string) ($params['member_query'] ?? $params['member_name'] ?? ''));
        $groupQuery = trim((string) ($params['group_query'] ?? $params['group_name'] ?? ''));
        $candidates = [];

        if (empty($params['loan_id'])) {
            // Group loans often have borrower.group_row_id only (member_row_id null).
            // Resolve loan by group/loan_number first; member_query is for penyetor, not borrower filter.
            $search = $this->searchLoans([
                'group_query' => $groupQuery,
                'member_query' => $groupQuery === '' ? $memberQuery : '',
                'loan_number' => (string) ($params['loan_number'] ?? ''),
            ]);
            $candidates = $search['items'];
            if (count($candidates) === 0 && $memberQuery !== '' && $groupQuery !== '') {
                // last resort: member-owned loans only
                $search = $this->searchLoans([
                    'member_query' => $memberQuery,
                    'loan_number' => (string) ($params['loan_number'] ?? ''),
                ]);
                $candidates = $search['items'];
            }
            if (count($candidates) === 0) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'loan_not_found',
                    'message' => 'Pinjaman tidak ditemukan. Coba sebutkan kelompok/nomor pinjaman.',
                    'candidates' => [],
                ];
            }
            if (count($candidates) > 1) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'ambiguous_loan',
                    'message' => 'Beberapa pinjaman cocok. Pilih loan_row_id.',
                    'candidates' => $candidates,
                    'match_count' => count($candidates),
                ];
            }
            $params['loan_id'] = $candidates[0]['loan_row_id'];
        }

        $loanId = (int) $params['loan_id'];
        $loan = Loan::query()->with(['borrower.group'])->where('row_id', $loanId)->first();
        if ($loan === null) {
            return [
                'needs_clarification' => true,
                'reason' => 'loan_not_found',
                'message' => "loan_id {$loanId} tidak ada",
                'candidates' => [],
            ];
        }

        // reference (penyetor)
        if (empty($params['reference'])) {
            if (! empty($params['member_row_id'])) {
                $params['reference'] = (int) $params['member_row_id'];
            } elseif ($memberQuery !== '') {
                $members = $this->searchMembers([
                    'query' => $memberQuery,
                    'group_query' => $groupQuery !== '' ? $groupQuery : (string) ($loan->borrower?->group?->name ?? ''),
                ]);
                if ($members['match_count'] === 1) {
                    $params['reference'] = $members['items'][0]['member_row_id'];
                } elseif ($members['match_count'] > 1) {
                    return [
                        'needs_clarification' => true,
                        'reason' => 'ambiguous_member',
                        'message' => 'Beberapa anggota cocok sebagai penyetor. Pilih member_row_id (reference).',
                        'candidates' => $members['items'],
                        'loan_id' => $loanId,
                    ];
                } elseif ($loan->borrower?->member_row_id) {
                    $params['reference'] = (int) $loan->borrower->member_row_id;
                } else {
                    return [
                        'needs_clarification' => true,
                        'reason' => 'member_not_found',
                        'message' => 'Anggota penyetor tidak ditemukan.',
                        'candidates' => $this->groupMemberItems((int) ($loan->borrower?->group_row_id ?? 0)),
                        'loan_id' => $loanId,
                    ];
                }
            } elseif ($loan->borrower?->member_row_id) {
                $params['reference'] = (int) $loan->borrower->member_row_id;
            } else {
                $groupMembers = $this->groupMemberItems((int) ($loan->borrower?->group_row_id ?? 0));
                if (count($groupMembers) === 1) {
                    $params['reference'] = $groupMembers[0]['member_row_id'];
                } else {
                    return [
                        'needs_clarification' => true,
                        'reason' => 'member_required',
                        'message' => 'Sebutkan nama anggota penyetor (reference).',
                        'candidates' => $groupMembers,
                        'loan_id' => $loanId,
                    ];
                }
            }
        }

        // cash account
        if (empty($params['cash_account_row_id'])) {
            $cashHint = trim((string) ($params['cash_account_query'] ?? $params['cash_account_name'] ?? 'Kas Tunai'));
            $cash = $this->resolveCashAccount($cashHint);
            if (($cash['needs_clarification'] ?? false) === true) {
                return $cash + ['loan_id' => $loanId];
            }
            $params['cash_account_row_id'] = $cash['row_id'];
        }

        // split principal/interest from total or schedule
        $hasPrincipal = isset($params['principal_amount']) && $params['principal_amount'] !== '' && $params['principal_amount'] !== null;
        $hasInterest = isset($params['interest_amount']) && $params['interest_amount'] !== '' && $params['interest_amount'] !== null;
        $totalAmount = isset($params['total_amount']) ? (float) $params['total_amount'] : (isset($params['amount']) ? (float) $params['amount'] : 0.0);

        $next = $this->nextOpenInstallment($loanId);
        if (empty($params['installment_number']) && $next !== null) {
            $params['installment_number'] = $next['installment_number'];
        }

        if (! $hasPrincipal || ! $hasInterest) {
            if ($totalAmount <= 0 && $next !== null) {
                // full next installment
                $params['principal_amount'] = $next['principal_remaining'];
                $params['interest_amount'] = $next['interest_remaining'];
            } elseif ($totalAmount > 0) {
                $split = $this->splitInstallmentAmount($loanId, $totalAmount, isset($params['installment_number']) ? (int) $params['installment_number'] : null);
                $params['principal_amount'] = $split['principal_amount'];
                $params['interest_amount'] = $split['interest_amount'];
                $params['penalty_amount'] = $params['penalty_amount'] ?? $split['penalty_amount'];
                if (empty($params['installment_number']) && $split['installment_number'] !== null) {
                    $params['installment_number'] = $split['installment_number'];
                }
            } else {
                return [
                    'needs_clarification' => true,
                    'reason' => 'amount_required',
                    'message' => 'Berikan total_amount atau principal_amount+interest_amount.',
                    'loan_id' => $loanId,
                    'next_installment' => $next,
                ];
            }
        }

        if (empty($params['description'])) {
            $who = $memberQuery !== '' ? $memberQuery : 'anggota';
            $grp = $groupQuery !== '' ? $groupQuery : (string) ($loan->borrower?->group?->name ?? '');
            $params['description'] = trim(sprintf(
                'Titipan angsuran %s%s',
                $who,
                $grp !== '' ? ' kelompok '.$grp : '',
            ));
        }

        $params['resolved_from'] = [
            'loan_status' => $loan->status,
            'group_name' => $loan->borrower?->group?->name,
            'next_installment' => $next,
        ];

        return $params;
    }

    /**
     * Allocate total payment: interest first, then principal, against open schedule.
     *
     * @return array{principal_amount: float, interest_amount: float, penalty_amount: float, installment_number: ?int}
     */
    private function splitInstallmentAmount(int $loanId, float $total, ?int $installmentNumber): array
    {
        $tenantId = $this->context->id();
        $q = DB::connection('tenant')
            ->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->where('loan_row_id', $loanId)
            ->whereIn('status', ['pending', 'partial', 'overdue']);

        if ($installmentNumber !== null && $installmentNumber > 0) {
            $q->where('installment_number', $installmentNumber);
        }

        $rows = $q->orderBy('installment_number')->orderBy('component')->get();
        if ($rows->isEmpty()) {
            // no schedule → all principal
            return [
                'principal_amount' => round($total, 2),
                'interest_amount' => 0.0,
                'penalty_amount' => 0.0,
                'installment_number' => $installmentNumber,
            ];
        }

        $byNum = [];
        foreach ($rows as $r) {
            $n = (int) $r->installment_number;
            $byNum[$n] ??= ['principal' => 0.0, 'interest' => 0.0, 'penalty' => 0.0];
            $comp = (string) $r->component;
            if ($comp === 'principal' || $comp === 'combined') {
                $byNum[$n]['principal'] += max(0, (float) $r->principal_due - (float) $r->principal_paid);
            }
            if ($comp === 'interest' || $comp === 'combined') {
                $byNum[$n]['interest'] += max(0, (float) $r->interest_due - (float) $r->interest_paid);
            }
            $byNum[$n]['penalty'] += max(0, (float) $r->penalty_due - (float) $r->penalty_paid);
        }

        $remaining = round($total, 2);
        $principal = 0.0;
        $interest = 0.0;
        $penalty = 0.0;
        $firstNum = null;

        foreach ($byNum as $n => $due) {
            if ($remaining <= 0) {
                break;
            }
            $firstNum ??= $n;

            $takePenalty = min($remaining, round($due['penalty'], 2));
            $penalty += $takePenalty;
            $remaining = round($remaining - $takePenalty, 2);

            $takeInterest = min($remaining, round($due['interest'], 2));
            $interest += $takeInterest;
            $remaining = round($remaining - $takeInterest, 2);

            $takePrincipal = min($remaining, round($due['principal'], 2));
            $principal += $takePrincipal;
            $remaining = round($remaining - $takePrincipal, 2);

            // only first open installment for single payment UX
            break;
        }

        // leftover beyond schedule → principal
        if ($remaining > 0) {
            $principal = round($principal + $remaining, 2);
        }

        return [
            'principal_amount' => round($principal, 2),
            'interest_amount' => round($interest, 2),
            'penalty_amount' => round($penalty, 2),
            'installment_number' => $firstNum,
        ];
    }

    /**
     * @return array{row_id: int}|array{needs_clarification: bool, reason: string, message: string, candidates: list<array<string,mixed>>}
     */
    private function resolveCashAccount(string $hint): array
    {
        $list = $this->listAccounts([
            'query' => $hint,
            'cash_only' => true,
        ]);
        if ($list['match_count'] === 1) {
            return ['row_id' => $list['items'][0]['row_id']];
        }
        if ($list['match_count'] > 1) {
            // prefer exact-ish: "Kas Tunai" default when hint generic
            $lower = mb_strtolower($hint);
            foreach ($list['items'] as $item) {
                if (mb_strtolower($item['name']) === $lower || mb_strtolower($item['code']) === $lower) {
                    return ['row_id' => $item['row_id']];
                }
            }
            if (str_contains($lower, 'tunai') || $lower === 'kas' || $lower === 'kas tunai') {
                foreach ($list['items'] as $item) {
                    if (str_contains(mb_strtolower($item['name']), 'tunai') || $item['code'] === '1.1.01.01') {
                        return ['row_id' => $item['row_id']];
                    }
                }
            }

            return [
                'needs_clarification' => true,
                'reason' => 'ambiguous_cash_account',
                'message' => 'Beberapa akun kas/bank cocok. Pilih cash_account_row_id.',
                'candidates' => $list['items'],
            ];
        }

        // fallback first cash
        $fallback = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', '1.1.01.%')
            ->orderBy('code')
            ->first();
        if ($fallback === null) {
            return [
                'needs_clarification' => true,
                'reason' => 'cash_account_missing',
                'message' => 'Tidak ada akun kas postable (1.1.01.*).',
                'candidates' => [],
            ];
        }

        return ['row_id' => (int) $fallback->row_id];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function sendBillingNotices(array $params): array
    {
        $data = Validator::make($params, [
            'due_date' => ['required', 'date_format:Y-m-d'],
            'installment_row_ids' => ['required', 'array', 'min:1'],
            'installment_row_ids.*' => ['integer', 'min:1'],
        ])->validate();

        $due = CarbonImmutable::createFromFormat('Y-m-d', $data['due_date'])->startOfDay();
        $ids = array_map('intval', $data['installment_row_ids']);

        if (! $this->isConfirmed($params)) {
            return $this->previewResponse(
                action: 'send_billing_notices',
                summary: sprintf('Kirim WA tagihan %d angsuran untuk jatuh tempo %s', count($ids), $due->toDateString()),
                plan: [
                    'due_date' => $due->toDateString(),
                    'installment_row_ids' => $ids,
                    'count' => count($ids),
                ],
                warnings: count($ids) > 50 ? ['Jumlah penerima besar (>50)'] : [],
                proposedParams: array_merge($params, ['confirm' => true]),
            );
        }

        return $this->notices->sendBilling($ids, $due);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function looksLikeCashTransfer(string $text, array $params): bool
    {
        if (isset($params['bank_account_query']) || isset($params['bank_name']) || isset($params['to_account_query'])) {
            return true;
        }
        $t = mb_strtolower($text);
        foreach (['setor', 'stor', 'transfer', 'pindah saldo', 'pemindahan', 'ke bank', 'ke rekening'] as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kas/bank transfer: Dr bank, Cr kas tunai (setor ke bank).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function normalizeTransferParams(array $params, string $hintName): array
    {
        $bankHint = trim((string) (
            $params['bank_account_query']
            ?? $params['bank_name']
            ?? $params['to_account_query']
            ?? $params['debit_account_query']
            ?? ''
        ));
        if ($bankHint === '' && $hintName !== '') {
            if (preg_match('/\b(?:bank|rekening)\s+([a-z0-9 ._-]{2,40})/iu', $hintName, $m)) {
                $bankHint = trim($m[0]);
            } elseif (preg_match('/\b(?:ke|ke\s+bank)\s+([a-z0-9 ._-]{2,40})/iu', $hintName, $m)) {
                $bankHint = trim($m[1]);
            }
        }

        $cashHint = trim((string) (
            $params['cash_account_query']
            ?? $params['from_account_query']
            ?? $params['credit_account_query']
            ?? 'Kas Tunai'
        ));

        if (empty($params['debit_account_row_id'])) {
            $bank = $this->resolveBankAccount($bankHint !== '' ? $bankHint : 'Bank');
            if (isset($bank['needs_clarification'])) {
                return $bank;
            }
            $params['debit_account_row_id'] = $bank['row_id'];
        }

        if (empty($params['credit_account_row_id'])) {
            $cash = $this->resolveCashAccount($cashHint);
            if (isset($cash['needs_clarification'])) {
                return $cash;
            }
            $params['credit_account_row_id'] = $cash['row_id'];
        }

        if (empty($params['description'])) {
            $params['description'] = $hintName !== ''
                ? mb_substr($hintName, 0, 500)
                : 'Pemindahan saldo kas ke bank';
        }

        return $params;
    }

    /**
     * Bank accounts = postable 1.1.01.03–1.1.01.99 (tunai=01, kecil=02).
     * Match only against real COA names/codes for this tenant — no hard-coded bank brands.
     *
     * @return array{row_id: int}|array{needs_clarification: bool, reason: string, message: string, candidates: list<array<string,mixed>>}
     */
    private function resolveBankAccount(string $hint): array
    {
        $allCash = $this->listAccounts([
            'code_prefix' => '1.1.01.',
            'cash_only' => true,
        ]);

        $pool = array_values(array_filter(
            $allCash['items'],
            static function (array $a): bool {
                // 1.1.01.01 kas tunai, 1.1.01.02 kas kecil → not bank
                if (preg_match('/^1\.1\.01\.(\d+)$/', $a['code'], $m)) {
                    return (int) $m[1] >= 3;
                }

                return str_contains(mb_strtolower($a['name']), 'bank');
            },
        ));

        if ($pool === []) {
            return [
                'needs_clarification' => true,
                'reason' => 'bank_account_missing',
                'message' => 'Tidak ada akun bank (1.1.01.03+). Sebutkan kode/nama akun tujuan.',
                'candidates' => $allCash['items'],
            ];
        }

        $needle = mb_strtolower(trim($hint));
        if ($needle !== '') {
            usort($pool, function (array $a, array $b) use ($needle): int {
                return $this->accountNameScore($b['name'], $b['code'], $needle)
                    <=> $this->accountNameScore($a['name'], $a['code'], $needle);
            });
            $best = $this->accountNameScore($pool[0]['name'], $pool[0]['code'], $needle);
            $second = isset($pool[1]) ? $this->accountNameScore($pool[1]['name'], $pool[1]['code'], $needle) : -1;
            if ($best > 0 && $best > $second) {
                return ['row_id' => $pool[0]['row_id']];
            }
            // filter to positive scores only when hint given
            $scored = array_values(array_filter(
                $pool,
                fn (array $a): bool => $this->accountNameScore($a['name'], $a['code'], $needle) > 0,
            ));
            if (count($scored) === 1) {
                return ['row_id' => $scored[0]['row_id']];
            }
            if (count($scored) > 1) {
                $pool = $scored;
            }
        }

        if (count($pool) === 1) {
            return ['row_id' => $pool[0]['row_id']];
        }

        return [
            'needs_clarification' => true,
            'reason' => 'ambiguous_bank_account',
            'message' => 'Beberapa akun bank cocok. Pilih debit_account_row_id dari daftar (nama akun tenant).',
            'candidates' => $pool,
        ];
    }

    /**
     * Next unpaid/partial installment (merged principal+interest components).
     *
     * @return array{installment_number: int, due_date: ?string, principal_remaining: float, interest_remaining: float, penalty_remaining: float, total_remaining: float}|null
     */
    private function nextOpenInstallment(int $loanId): ?array
    {
        $tenantId = $this->context->id();
        $rows = DB::connection('tenant')
            ->table('loan_installments')
            ->where('tenant_id', $tenantId)
            ->where('loan_row_id', $loanId)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_number')
            ->orderBy('component')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $byNum = [];
        foreach ($rows as $r) {
            $n = (int) $r->installment_number;
            $byNum[$n] ??= [
                'installment_number' => $n,
                'due_date' => $r->due_date ? (string) $r->due_date : null,
                'principal_remaining' => 0.0,
                'interest_remaining' => 0.0,
                'penalty_remaining' => 0.0,
            ];
            $comp = (string) $r->component;
            if ($comp === 'principal' || $comp === 'combined') {
                $byNum[$n]['principal_remaining'] += max(0, (float) $r->principal_due - (float) $r->principal_paid);
            }
            if ($comp === 'interest' || $comp === 'combined') {
                $byNum[$n]['interest_remaining'] += max(0, (float) $r->interest_due - (float) $r->interest_paid);
            }
            $byNum[$n]['penalty_remaining'] += max(0, (float) $r->penalty_due - (float) $r->penalty_paid);
            if ($byNum[$n]['due_date'] === null && $r->due_date) {
                $byNum[$n]['due_date'] = (string) $r->due_date;
            }
        }

        foreach ($byNum as $row) {
            $total = round(
                $row['principal_remaining'] + $row['interest_remaining'] + $row['penalty_remaining'],
                2,
            );
            if ($total > 0) {
                $row['principal_remaining'] = round($row['principal_remaining'], 2);
                $row['interest_remaining'] = round($row['interest_remaining'], 2);
                $row['penalty_remaining'] = round($row['penalty_remaining'], 2);
                $row['total_remaining'] = $total;

                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array{member_row_id: int, name: string, status: string}>
     */
    private function groupMemberItems(int $groupRowId): array
    {
        if ($groupRowId <= 0) {
            return [];
        }
        $tenantId = $this->context->id();

        return DB::connection('tenant')
            ->table('group_members as gm')
            ->join('members as m', function ($join) use ($tenantId): void {
                $join->on('m.row_id', '=', 'gm.member_row_id')->where('m.tenant_id', '=', $tenantId);
            })
            ->join('people as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'm.person_row_id')->where('p.tenant_id', '=', $tenantId);
            })
            ->where('gm.tenant_id', $tenantId)
            ->where('gm.group_row_id', $groupRowId)
            ->whereNull('gm.left_at')
            ->whereNull('m.deleted_at')
            ->orderBy('p.full_name')
            ->limit(50)
            ->get(['m.row_id', 'p.full_name', 'm.status'])
            ->map(fn ($r): array => [
                'member_row_id' => (int) $r->row_id,
                'name' => (string) $r->full_name,
                'status' => (string) $r->status,
            ])->all();
    }

    /**
     * Score name/code against free-text hint. No tenant-specific bank aliases —
     * only actual account name/code tokens from the COA.
     */
    private function accountNameScore(string $name, string $code, string $needle): int
    {
        $n = mb_strtolower($name);
        $c = mb_strtolower($code);
        $needle = mb_strtolower(trim($needle));
        if ($needle === '') {
            return 0;
        }
        $score = 0;
        if ($n === $needle || $c === $needle) {
            $score += 100;
        }
        if (str_contains($n, $needle)) {
            $score += 40;
        }
        foreach (preg_split('/\s+/', $needle) ?: [] as $tok) {
            if (mb_strlen($tok) < 2) {
                continue;
            }
            // skip generic filler that matches every cash line
            if (in_array($tok, ['kas', 'di', 'ke', 'bank', 'rekening', 'setor', 'stor'], true)) {
                if ($tok === 'bank' && str_contains($n, 'bank')) {
                    $score += 3; // mild preference for bank-labelled accounts
                }

                continue;
            }
            if (str_contains($n, $tok)) {
                $score += 10;
            }
            if (str_contains($c, $tok)) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    private function withMatchMeta(array $items): array
    {
        $count = count($items);

        return [
            'items' => $items,
            'match_count' => $count,
            'needs_clarification' => $count !== 1,
        ];
    }

    /**
     * Write tools default to preview. Post only when confirm/confirmed/execute is true.
     *
     * @param  array<string, mixed>  $params
     */
    private function isConfirmed(array $params): bool
    {
        foreach (['confirm', 'confirmed', 'execute', 'commit'] as $key) {
            if (array_key_exists($key, $params) && filter_var($params[$key], FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Standard preview payload — asisten tampilkan ke user, tanya konfirmasi / opsi.
     *
     * @param  array<string, mixed>  $plan
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $proposedParams
     * @param  list<array{id: string, label: string}>  $options
     * @return array<string, mixed>
     */
    private function previewResponse(
        string $action,
        string $summary,
        array $plan,
        array $warnings,
        array $proposedParams,
        array $options = [],
    ): array {
        return [
            'preview' => true,
            'needs_confirmation' => true,
            'action' => $action,
            'summary' => $summary,
            'plan' => $plan,
            'warnings' => $warnings,
            'options' => $options,
            'message' => 'Ini rencana saja — belum diposting. Konfirmasi user lalu panggil ulang dengan confirm=true'
                .($options !== [] ? ' (atau pilih options[].id lewat allocation_choice).' : '.'),
            'proposed_params' => $proposedParams,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeJournal(JournalEntry $entry): array
    {
        $entry->loadMissing('lines.account');

        return [
            'journal_row_id' => (int) $entry->row_id,
            'journal_id' => (int) $entry->id,
            'journal_number' => $entry->journal_number,
            'transaction_date' => $entry->transaction_date?->toDateString(),
            'transaction_type' => $entry->transaction_type,
            'description' => $entry->description,
            'status' => $entry->status,
            'lines' => $entry->lines->map(fn ($l): array => [
                'account_code' => $l->account?->code,
                'account_name' => $l->account?->name,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
        ];
    }

    /**
     * Build direct download URL and button block for reports.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function downloadReport(array $params): array
    {
        $rawType = strtolower(trim((string) ($params['report_type'] ?? 'balance_sheet')));
        $format = strtolower(trim((string) ($params['format'] ?? 'pdf')));
        if (! in_array($format, ['pdf', 'excel'], true)) {
            $format = 'pdf';
        }

        $now = CarbonImmutable::now();
        $year = isset($params['year']) && is_numeric($params['year']) ? (int) $params['year'] : $now->year;
        $month = isset($params['month']) && is_numeric($params['month']) ? (int) $params['month'] : null;

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $periodLabel = $month && isset($monthNames[$month])
            ? "{$monthNames[$month]} {$year}"
            : "Tahun {$year}";

        // Normalize aliases
        $type = match ($rawType) {
            'neraca', 'balance_sheet' => 'balance_sheet',
            'laba_rugi', 'labarugi', 'surplus_defisit', 'income_statement' => 'income_statement',
            'arus_kas', 'cash_flow' => 'cash_flow',
            'neraca_saldo', 'trial_balance' => 'trial_balance',
            'perubahan_modal', 'perubahan_ekuitas', 'equity_change' => 'equity_change',
            'calk', 'catatan_atas_laporan_keuangan' => 'calk',
            'buku_besar', 'general_ledger' => 'general_ledger',
            'jurnal', 'jurnal_transaksi', 'journals' => 'journals',
            'kesehatan_keuangan', 'financial_health' => 'financial_health',
            'aset_tetap', 'fixed_assets', 'inventaris' => 'fixed_assets',
            'aset_takberwujud', 'intangible_assets' => 'intangible_assets',
            'portofolio', 'portfolio', 'perkembangan_pinjaman' => 'portfolio',
            'rencana_vs_realisasi', 'schedule_vs_actual' => 'schedule_vs_actual',
            'lpp_desa' => 'lpp_desa',
            'lpp_kelompok' => 'lpp_kelompok',
            'kolek_desa', 'kolektibilitas' => 'kolek_desa',
            'cadangan_penghapusan', 'ppap' => 'cadangan_penghapusan',
            'anggota', 'members' => 'members',
            'kelompok', 'groups' => 'groups',
            default => 'balance_sheet',
        };

        $reportMeta = match ($type) {
            'balance_sheet' => [
                'name' => 'Laporan Neraca',
                'short_name' => 'Neraca',
                'pdf' => '/accounting/reports/balance-sheet/pdf',
                'excel' => '/accounting/reports/balance-sheet/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'income_statement' => [
                'name' => 'Laporan Laba Rugi',
                'short_name' => 'Laba Rugi',
                'pdf' => '/accounting/reports/income-statement/pdf',
                'excel' => '/accounting/reports/income-statement/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'cash_flow' => [
                'name' => 'Laporan Arus Kas',
                'short_name' => 'Arus Kas',
                'pdf' => '/accounting/reports/cash-flow/pdf',
                'excel' => '/accounting/reports/cash-flow/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'trial_balance' => [
                'name' => 'Laporan Neraca Saldo',
                'short_name' => 'Neraca Saldo',
                'pdf' => '/accounting/reports/trial-balance/pdf',
                'excel' => '/accounting/reports/trial-balance/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'equity_change' => [
                'name' => 'Laporan Perubahan Ekuitas',
                'short_name' => 'Perubahan Modal',
                'pdf' => '/accounting/reports/equity-change/pdf',
                'excel' => '/accounting/reports/equity-change/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'calk' => [
                'name' => 'Catatan Atas Laporan Keuangan (CALK)',
                'short_name' => 'CALK',
                'pdf' => '/accounting/reports/calk/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'general_ledger' => [
                'name' => 'Laporan Buku Besar',
                'short_name' => 'Buku Besar',
                'pdf' => '/accounting/reports/general-ledger/pdf',
                'excel' => '/accounting/reports/general-ledger/excel',
                'query' => array_filter([
                    'month' => $month,
                    'year' => $year,
                    'account' => $params['account_id'] ?? null,
                ]),
            ],
            'journals' => [
                'name' => 'Laporan Jurnal Transaksi',
                'short_name' => 'Jurnal',
                'pdf' => '/accounting/reports/journals/pdf',
                'excel' => '/accounting/reports/journals/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'financial_health' => [
                'name' => 'Analisis Kesehatan Keuangan',
                'short_name' => 'Kesehatan Keuangan',
                'pdf' => '/accounting/reports/financial-health/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'fixed_assets' => [
                'name' => 'Daftar Aset Tetap',
                'short_name' => 'Aset Tetap',
                'pdf' => '/accounting/reports/assets/fixed/pdf',
                'excel' => '/accounting/reports/assets/fixed/excel',
                'query' => array_filter(['as_of' => $params['as_of_date'] ?? $now->toDateString()]),
            ],
            'intangible_assets' => [
                'name' => 'Daftar Aset Tidak Berwujud',
                'short_name' => 'Aset Tak Berwujud',
                'pdf' => '/accounting/reports/assets/intangible/pdf',
                'excel' => '/accounting/reports/assets/intangible/excel',
                'query' => array_filter(['as_of' => $params['as_of_date'] ?? $now->toDateString()]),
            ],
            'portfolio' => [
                'name' => 'Laporan Portofolio Pinjaman',
                'short_name' => 'Portofolio',
                'pdf' => '/lending/reports/portfolio/pdf',
                'excel' => null,
                'query' => array_filter([
                    'as_of' => $params['as_of_date'] ?? ($month ? CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString() : "{$year}-12-31"),
                ]),
            ],
            'schedule_vs_actual' => [
                'name' => 'Laporan Rencana vs Realisasi Pinjaman',
                'short_name' => 'Rencana vs Realisasi',
                'pdf' => '/lending/reports/schedule-vs-actual/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'lpp_desa' => [
                'name' => 'LPP per Desa',
                'short_name' => 'LPP Desa',
                'pdf' => '/lending/reports/lpp-desa/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'lpp_kelompok' => [
                'name' => 'LPP per Kelompok',
                'short_name' => 'LPP Kelompok',
                'pdf' => '/lending/reports/lpp-kelompok/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'kolek_desa' => [
                'name' => 'Laporan Kolektibilitas per Desa',
                'short_name' => 'Kolektibilitas',
                'pdf' => '/lending/reports/kolek-desa/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'cadangan_penghapusan' => [
                'name' => 'Laporan Cadangan Penghapusan Pinjaman',
                'short_name' => 'Cadangan PPAP',
                'pdf' => '/lending/reports/cadangan-penghapusan/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'members' => [
                'name' => 'Ekspor Data Anggota',
                'short_name' => 'Data Anggota',
                'pdf' => null,
                'excel' => '/members/export',
                'query' => [],
            ],
            'groups' => [
                'name' => 'Ekspor Data Kelompok',
                'short_name' => 'Data Kelompok',
                'pdf' => null,
                'excel' => '/groups/export',
                'query' => [],
            ],
        };

        // Fallback format if requested format is not supported for this report
        if ($format === 'excel' && empty($reportMeta['excel'])) {
            $format = 'pdf';
        } elseif ($format === 'pdf' && empty($reportMeta['pdf'])) {
            $format = 'excel';
        }

        $queryParams = $reportMeta['query'];
        if ($format === 'pdf') {
            $queryParams['download'] = 1;
        }

        $basePath = $format === 'excel' ? $reportMeta['excel'] : $reportMeta['pdf'];
        $queryString = http_build_query($queryParams);
        $url = $basePath.($queryString !== '' ? '?'.$queryString : '');

        $reportName = $reportMeta['name'];
        $shortName = $reportMeta['short_name'] ?? $reportName;
        $formatUpper = strtoupper($format);
        $btnLabel = "Unduh {$shortName} ({$formatUpper})";
        $actionButton = sprintf('::button{"label":"%s","url":"%s","icon":"download"}::', $btnLabel, $url);
        $markdownLink = sprintf('[%s](%s)', $btnLabel, $url);

        return [
            'ok' => true,
            'report_type' => $type,
            'report_name' => $reportName,
            'short_name' => $shortName,
            'format' => $format,
            'period' => $periodLabel,
            'download_url' => $url,
            'action_button' => $actionButton,
            'markdown_link' => $markdownLink,
            'instructions' => 'Sertakan tombol action_button ini dalam respon Anda agar pengguna bisa langsung mendownload file laporan dengan satu klik.',
        ];
    }

    public function simulateLoan(array $params): array
    {
        $productCode = isset($params['product_code']) ? strtolower(trim((string) $params['product_code'])) : null;
        if ($productCode !== null && $productCode !== '') {
            $product = LoanProduct::query()->where('code', $productCode)->where('is_active', true)->first();
            if ($product !== null) {
                if (! isset($params['interest_rate'])) {
                    $params['interest_rate'] = (float) $product->default_interest_rate;
                }
                if (! isset($params['term_months'])) {
                    $params['term_months'] = (int) $product->default_term_months;
                }
                if (! isset($params['rounding_step'])) {
                    $params['rounding_step'] = is_numeric($product->rounding_method) ? max(500, (int) $product->rounding_method) : 500;
                }
            }
        }

        $params['principal_amount'] = max(100000.0, (float) ($params['principal_amount'] ?? 10000000));
        $params['term_months'] = max(1, min(120, (int) ($params['term_months'] ?? 12)));
        $params['interest_rate'] = max(0.0, min(100.0, (float) ($params['interest_rate'] ?? 12.0)));
        $params['installment_method'] = (string) ($params['installment_method'] ?? 'flat');
        if (! in_array($params['installment_method'], ['flat', 'declining', 'annuity'], true)) {
            $params['installment_method'] = 'flat';
        }
        $params['principal_frequency'] = (string) ($params['principal_frequency'] ?? 'monthly');
        $params['interest_frequency'] = (string) ($params['interest_frequency'] ?? 'monthly');
        $params['rounding_step'] = max(500, (int) ($params['rounding_step'] ?? 500));
        $params['start_date'] = (string) ($params['start_date'] ?? date('Y-m-d'));

        $simulation = $this->loanSimulator->simulate($params);

        $borrowerName = (string) ($params['borrower_name'] ?? 'Calon Peminjam');
        $pdfQuery = http_build_query([
            'principal_amount' => $params['principal_amount'],
            'term_months' => $params['term_months'],
            'interest_rate' => $params['interest_rate'],
            'installment_method' => $params['installment_method'],
            'principal_frequency' => $params['principal_frequency'],
            'interest_frequency' => $params['interest_frequency'],
            'rounding_step' => $params['rounding_step'],
            'start_date' => $params['start_date'],
            'borrower_name' => $borrowerName,
            'download' => '1',
        ]);
        $pdfUrl = "/lending/simulation/pdf?{$pdfQuery}";
        $buttonMarkup = sprintf('::button{"label":"Unduh Jadwal Simulasi (PDF)","url":"%s","icon":"download"}::', $pdfUrl);

        return [
            'success' => true,
            'summary' => $simulation['summary'],
            'pdf_url' => $pdfUrl,
            'download_button' => $buttonMarkup,
            'schedule_preview' => array_slice($simulation['schedule'], 0, 3),
            'total_installments' => count($simulation['schedule']),
        ];
    }
}
