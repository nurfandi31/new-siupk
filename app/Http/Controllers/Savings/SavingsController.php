<?php

declare(strict_types=1);

namespace App\Http\Controllers\Savings;

use App\Domain\Membership\Models\Member;
use App\Domain\Savings\Models\SavingsProduct;
use App\Domain\Savings\Services\SavingsModuleManager;
use App\Domain\Savings\Services\SavingsService;
use App\Http\Requests\Savings\SavingsTransactionRequest;
use App\Http\Requests\Savings\StoreSavingsAccountRequest;
use App\Http\Requests\Savings\UpdateSavingsAccountRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekening simpanan: daftar, pembukaan, detail, mutasi, dan penutupan.
 */
final class SavingsController
{
    public function __construct(
        private readonly SavingsService $savings,
        private readonly SavingsModuleManager $modules,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'product' => (int) $request->query('product', 0),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'account_number'),
            'direction' => (string) $request->query('direction', 'asc'),
        ];

        return Inertia::render('Savings/Index', [
            'accounts' => $this->savings->paginateAccounts($filters, $this->perPage($request->query('per_page'))),
            'stats' => $this->savings->stats(),
            'products' => $this->savings->productFilterOptions(),
            'filters' => $filters,
            'perPage' => $this->perPage($request->query('per_page')),
        ]);
    }

    public function create(Request $request): Response
    {
        $settings = $this->modules->settings();
        $selectedMember = null;
        $memberId = (int) $request->query('member', 0);

        if ($memberId > 0) {
            $member = Member::query()->with('person')->whereKey($memberId)->first();

            if ($member !== null) {
                $selectedMember = [
                    'value' => (int) $member->row_id,
                    'label' => ($member->person?->full_name ?? 'Anggota').' - '.$member->member_number,
                    'name' => (string) ($member->person?->full_name ?? ''),
                    'member_number' => (string) $member->member_number,
                    'nik' => $member->person?->national_identity_number,
                ];
            }
        }

        return Inertia::render('Savings/Create', [
            'products' => SavingsProduct::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get()
                ->map(fn (SavingsProduct $product): array => [
                    'row_id' => (int) $product->row_id,
                    'code' => (string) $product->code,
                    'name' => (string) $product->name,
                    'category' => (string) $product->category,
                    'category_label' => $product->categoryLabel(),
                    'form_type' => (int) $product->form_type,
                    'form_type_label' => $product->formTypeLabel(),
                    'interest_rate' => (float) $product->interest_rate,
                    'tax_rate' => (float) $product->tax_rate,
                    'admin_fee' => (float) $product->admin_fee,
                    'opening_fee' => (float) $product->opening_fee,
                    'minimum_balance' => (float) $product->minimum_balance,
                ])
                ->values()
                ->all(),
            'settings' => [
                'interest_rate' => (float) $settings['interest_rate'],
                'tax_rate' => (float) $settings['tax_rate'],
                'admin_fee' => (float) $settings['admin_fee'],
                'opening_fee' => (float) $settings['opening_fee'],
                'minimum_balance' => (float) $settings['minimum_balance'],
                'minimum_taxable_interest' => (float) $settings['minimum_taxable_interest'],
                'interest_day' => (int) $settings['interest_day'],
                'interest_method' => (int) $settings['interest_method'],
                'minimum_withdrawal_balance' => (float) $settings['minimum_withdrawal_balance'],
            ],
            'selectedMember' => $selectedMember,
        ]);
    }

    public function memberOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        return response()->json([
            'data' => $this->savings->searchMembers($search),
        ]);
    }

    public function store(StoreSavingsAccountRequest $request): RedirectResponse
    {
        $account = $this->savings->openAccount(
            $request->validated(),
            (int) $request->user()->row_id,
        );

        return to_route('savings.show', $account->row_id)
            ->with('success', "Rekening simpanan {$account->account_number} berhasil dibuka.");
    }

    public function show(Request $request, int $account): Response
    {
        $model = $this->savings->findAccount($account);
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', 0);

        if ($month < 0 || $month > 12) {
            $month = 0;
        }

        return Inertia::render('Savings/Show', [
            'account' => $this->savings->accountDetail($model),
            'transactions' => $this->savings->paginateTransactions(
                $model,
                $this->perPage($request->query('per_page')),
                $year > 0 ? $year : null,
                $month > 0 ? $month : null,
            ),
            'statement' => $year > 0 ? $this->savings->statement($model, $year, $month) : null,
            'filters' => ['year' => $year, 'month' => $month],
            'settings' => $this->modules->settings(),
        ]);
    }

    public function update(UpdateSavingsAccountRequest $request, int $account): RedirectResponse
    {
        $model = $this->savings->findAccount($account);
        $this->savings->updateAccount($model, $request->validated());

        return back()->with('success', 'Pengaturan rekening simpanan berhasil diperbarui.');
    }

    public function storeTransaction(SavingsTransactionRequest $request, int $account): RedirectResponse
    {
        $model = $this->savings->findAccount($account);
        $transaction = $this->savings->postTransaction(
            $model,
            $request->validated(),
            (int) $request->user()->row_id,
        );

        $label = $transaction->typeLabel();

        return back()->with('success', "{$label} berhasil dicatat pada rekening {$model->account_number}.");
    }

    public function close(Request $request, int $account): RedirectResponse
    {
        $model = $this->savings->findAccount($account);
        $this->savings->closeAccount($model, (int) $request->user()->row_id);

        return back()->with('success', "Rekening {$model->account_number} telah ditutup.");
    }

    public function destroy(int $account): RedirectResponse
    {
        $model = $this->savings->findAccount($account);
        $number = $model->account_number;

        try {
            $this->savings->deleteAccount($model);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('savings.index')
            ->with('success', "Rekening {$number} berhasil dihapus.");
    }

    private function perPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [15, 30, 50, 100], true) ? $value : 15;
    }
}
