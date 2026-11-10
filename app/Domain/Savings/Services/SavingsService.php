<?php

declare(strict_types=1);

namespace App\Domain\Savings\Services;

use App\Domain\Membership\Models\Member;
use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Savings\Models\SavingsInterestRun;
use App\Domain\Savings\Models\SavingsProduct;
use App\Domain\Savings\Models\SavingsTransaction;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika bisnis simpanan yang mengikuti SOP SIUPK:
 *
 *  - Nomor rekening  : {jenis_simpanan}-{lokasi 3 digit}.{anggota}-{urutan 2 digit}
 *  - Biaya buka buku : dipotong dari setoran awal sebagai mutasi tersendiri
 *  - Bunga           : persen per bulan dari saldo periode (lihat interestPeriod)
 *  - Metode bunga    : 1 = saldo terakhir, 2 = saldo terendah, 3 = saldo rata-rata
 *  - Bunga/pajak     : baru dihitung bila saldo >= min_bunga dan bunga >= min_pajak
 *  - Admin           : ditagih setiap periode bila admin rekening > 0
 *  - Posting bunga   : tanggal mengikuti tgl_bunga (boleh negatif = dari akhir bulan)
 *
 * Semua nominal dihitung memakai bcmath pada string decimal(18,2) supaya tidak
 * ada pembulatan floating point yang merusak saldo.
 */
final class SavingsService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SavingsModuleManager $modules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();

        $totalBalance = (string) SavingsAccount::query()->sum('balance');
        $activeCount = (int) SavingsAccount::query()
            ->where('status', SavingsAccount::STATUS_ACTIVE)
            ->count();
        $accountCount = (int) SavingsAccount::query()->count();

        $inflow = (string) SavingsTransaction::query()
            ->where('direction', 'in')
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $outflow = (string) SavingsTransaction::query()
            ->where('direction', 'out')
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $byProduct = SavingsAccount::query()
            ->selectRaw('product_row_id, COUNT(*) as accounts, COALESCE(SUM(balance), 0) as balance')
            ->where('status', SavingsAccount::STATUS_ACTIVE)
            ->groupBy('product_row_id')
            ->get()
            ->map(function ($row): array {
                $product = SavingsProduct::query()->find((int) $row->product_row_id);

                return [
                    'product_row_id' => (int) $row->product_row_id,
                    'name' => $product?->name ?? 'Tanpa jenis',
                    'category' => $product?->category ?? 'lainnya',
                    'category_label' => $product?->categoryLabel() ?? 'Simpanan Lainnya',
                    'accounts' => (int) $row->accounts,
                    'balance' => (float) $row->balance,
                ];
            })
            ->sortByDesc('balance')
            ->values()
            ->all();

        return [
            'total_balance' => (float) $totalBalance,
            'account_count' => $accountCount,
            'active_count' => $activeCount,
            'month_inflow' => (float) $inflow,
            'month_outflow' => (float) $outflow,
            'month_net' => (float) bcsub($this->money($inflow), $this->money($outflow), 2),
            'by_product' => $byProduct,
        ];
    }

    /**
     * @param  array{search?: string|null, product?: int|string|null, status?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateAccounts(array $filters, int $perPage): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $product = (int) ($filters['product'] ?? 0);
        $status = (string) ($filters['status'] ?? '');
        $sort = in_array($filters['sort'] ?? '', ['account_number', 'balance', 'opened_at', 'status'], true)
            ? (string) $filters['sort']
            : 'account_number';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return SavingsAccount::query()
            ->with(['member.person', 'member.address', 'product'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('account_number', 'like', "%{$search}%")
                ->orWhereHas('member', fn ($member) => $member
                    ->where('member_number', 'like', "%{$search}%")
                    ->orWhereHas('person', fn ($person) => $person
                        ->where('full_name', 'like', "%{$search}%")))))
            ->when($product > 0, fn ($query) => $query->where('product_row_id', $product))
            ->when(in_array($status, ['active', 'closed'], true), fn ($query) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SavingsAccount $account): array => $this->accountRow($account));
    }

    /**
     * @return array<string, mixed>
     */
    public function accountRow(SavingsAccount $account): array
    {
        $account->loadMissing(['member.person', 'member.address', 'product']);

        return [
            'row_id' => (int) $account->row_id,
            'account_number' => (string) $account->account_number,
            'balance' => (float) $account->balance,
            'status' => (string) $account->status,
            'opened_at' => $account->opened_at?->toDateString(),
            'minimum_closing_date' => $account->minimum_closing_date?->toDateString(),
            'closed_at' => $account->closed_at?->toDateString(),
            'interest_rate' => (float) $account->interest_rate,
            'tax_rate' => (float) $account->tax_rate,
            'admin_fee' => (float) $account->admin_fee,
            'opening_fee' => (float) $account->opening_fee,
            'authority_type' => (int) $account->authority_type,
            'institution_name' => $account->institution_name,
            'institution_position' => $account->institution_position,
            'guardian_name' => $account->guardian_name,
            'relationship' => $account->relationship,
            'notes' => $account->notes,
            'member' => [
                'row_id' => (int) $account->member_row_id,
                'name' => $account->member?->person?->full_name ?? $account->member?->member_number ?? 'Anggota',
                'member_number' => $account->member?->member_number,
                'nik' => $account->member?->person?->national_identity_number,
                'phone' => $account->member?->person?->phone,
                'gender' => $account->member?->person?->gender,
                'birth_place' => $account->member?->person?->birth_place,
                'birth_date' => $account->member?->person?->birth_date?->toDateString(),
                'address' => $account->member?->address?->address,
            ],
            'product' => [
                'row_id' => (int) $account->product_row_id,
                'code' => $account->product?->code,
                'name' => $account->product?->name ?? 'Simpanan',
                'category' => $account->product?->category,
                'category_label' => $account->product?->categoryLabel() ?? 'Simpanan Lainnya',
                'form_type' => (int) ($account->product?->form_type ?? 1),
                'form_type_label' => $account->product?->formTypeLabel() ?? 'Tanpa tanggal minimal tutup',
                'minimum_balance' => (float) ($account->product?->minimum_balance ?? 0),
            ],
        ];
    }

    public function findAccount(int $rowId): SavingsAccount
    {
        return SavingsAccount::query()
            ->with(['member.person', 'member.address', 'product'])
            ->whereKey($rowId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function accountDetail(SavingsAccount $account): array
    {
        $transactions = SavingsTransaction::query()
            ->where('account_row_id', $account->row_id);

        $totalIn = (string) (clone $transactions)->where('direction', 'in')->sum('amount');
        $totalOut = (string) (clone $transactions)->where('direction', 'out')->sum('amount');
        $last = (clone $transactions)->orderByDesc('transaction_date')->orderByDesc('row_id')->first();

        return [
            ...$this->accountRow($account),
            'stats' => [
                'total_in' => (float) $totalIn,
                'total_out' => (float) $totalOut,
                'transaction_count' => (int) (clone $transactions)->count(),
                'last_transaction_at' => $last?->transaction_date?->toDateString(),
            ],
            'address' => $account->member?->address?->address,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function openAccount(array $data, int $userId): SavingsAccount
    {
        $product = SavingsProduct::query()
            ->where('is_active', true)
            ->whereKey((int) $data['product_row_id'])
            ->first();

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_row_id' => 'Jenis simpanan tidak ditemukan atau sudah tidak aktif.',
            ]);
        }

        $member = Member::query()->with('person')->whereKey((int) $data['member_row_id'])->first();

        if ($member === null) {
            throw ValidationException::withMessages([
                'member_row_id' => 'Anggota tidak ditemukan.',
            ]);
        }

        $settings = $this->modules->settings();
        $openedAt = (string) ($data['opened_at'] ?? now()->toDateString());
        $openingAmount = $this->money($data['opening_amount'] ?? 0);

        if (bccomp($openingAmount, '0.00', 2) < 0) {
            throw ValidationException::withMessages([
                'opening_amount' => 'Setoran awal tidak boleh negatif.',
            ]);
        }

        $minimumClosingDate = $this->nullableDate($data['minimum_closing_date'] ?? null);

        if ((int) $product->form_type !== 1 && $minimumClosingDate === null) {
            throw ValidationException::withMessages([
                'minimum_closing_date' => 'Tanggal minimal tutup rekening wajib diisi untuk jenis simpanan ini.',
            ]);
        }

        // Jenis "Umum" tidak memakai tanggal minimal tutup; SIUPK menyimpan
        // tanggal buka sebagai tgl_tutup agar laporan tetap konsisten.
        $minimumClosingDate ??= $openedAt;

        if ($minimumClosingDate < $openedAt) {
            throw ValidationException::withMessages([
                'minimum_closing_date' => 'Tanggal minimal tutup tidak boleh sebelum tanggal buka rekening.',
            ]);
        }

        $interestRate = $this->numericOr($data['interest_rate'] ?? null, $product->interest_rate);
        $taxRate = $this->numericOr($data['tax_rate'] ?? null, $product->tax_rate);
        $adminFee = $this->numericOr($data['admin_fee'] ?? null, $product->admin_fee);
        $openingFee = $this->numericOr(
            $data['opening_fee'] ?? null,
            $product->opening_fee ?? $settings['opening_fee'],
        );

        return DB::connection($this->connectionName())->transaction(function () use (
            $data,
            $userId,
            $product,
            $member,
            $openedAt,
            $openingAmount,
            $minimumClosingDate,
            $interestRate,
            $taxRate,
            $adminFee,
            $openingFee
        ): SavingsAccount {
            // Kunci baris anggota agar dua permintaan bersamaan tidak dapat
            // membuka dua rekening aktif untuk jenis simpanan yang sama.
            Member::query()->whereKey($member->row_id)->lockForUpdate()->first();

            // SIUPK tidak membatasi satu anggota hanya boleh punya satu rekening
            // per jenis simpanan; urutan nomor rekening yang membedakannya.
            $accountNumber = $this->nextAccountNumber($product, $member);

            $account = SavingsAccount::query()->create([
                'account_number' => $accountNumber,
                'member_row_id' => $member->row_id,
                'product_row_id' => $product->row_id,
                'balance' => '0.00',
                'interest_rate' => $this->rate($interestRate),
                'tax_rate' => $this->rate($taxRate),
                'admin_fee' => $this->money($adminFee),
                'opening_fee' => $this->money($openingFee),
                'opened_at' => $openedAt,
                'minimum_closing_date' => $minimumClosingDate,
                'status' => SavingsAccount::STATUS_ACTIVE,
                'authority_type' => (int) ($data['authority_type'] ?? 1),
                'institution_name' => $data['institution_name'] ?? null,
                'institution_position' => $data['institution_position'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'relationship' => $data['relationship'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $userId,
            ]);

            if (bccomp($openingAmount, '0.00', 2) > 0) {
                $this->insertTransaction($account, [
                    'type' => SavingsTransaction::TYPE_OPENING,
                    'code' => SavingsTransaction::CODE_OPENING,
                    'direction' => 'in',
                    'amount' => $openingAmount,
                    'transaction_date' => $openedAt,
                    'reference' => 'SETORAN-AWAL',
                    'description' => 'Setoran Awal '.$product->name,
                ], $userId);
            }

            // SIUPK memotong biaya buka buku dari setoran awal sebagai mutasi
            // debit tersendiri, bukan mengurangi nominal setoran.
            if (bccomp($this->money($openingFee), '0.00', 2) > 0) {
                $this->insertTransaction($account, [
                    'type' => SavingsTransaction::TYPE_ADMIN,
                    'code' => SavingsTransaction::CODE_ADMIN,
                    'direction' => 'out',
                    'amount' => $this->money($openingFee),
                    'transaction_date' => $openedAt,
                    'reference' => 'BIAYA-BUKA',
                    'description' => 'Biaya Buka Buku '.$product->name,
                ], $userId);
            }

            $this->recalculateAccount((int) $account->row_id);

            return $account->fresh(['member.person', 'member.address', 'product']) ?? $account;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function postTransaction(SavingsAccount $account, array $data, int $userId): SavingsTransaction
    {
        $type = (string) $data['type'];

        $allowed = in_array($type, [
            SavingsTransaction::TYPE_DEPOSIT,
            SavingsTransaction::TYPE_WITHDRAWAL,
            SavingsTransaction::TYPE_ADJUSTMENT,
        ], true);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'type' => 'Jenis transaksi tidak valid. Bunga, pajak, dan admin hanya dapat dibuat dari proses bunga.',
            ]);
        }

        $amount = $this->money($data['amount'] ?? 0);

        if (bccomp($amount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal harus lebih besar dari nol.',
            ]);
        }

        $date = (string) ($data['transaction_date'] ?? now()->toDateString());
        $settings = $this->modules->settings();

        return DB::connection($this->connectionName())->transaction(function () use ($account, $type, $amount, $date, $data, $userId, $settings): SavingsTransaction {
            /** @var SavingsAccount $locked */
            $locked = SavingsAccount::query()->with('product')->whereKey($account->row_id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'amount' => 'Rekening sudah ditutup dan tidak dapat menerima transaksi.',
                ]);
            }

            if ($locked->opened_at !== null && $date < $locked->opened_at->toDateString()) {
                throw ValidationException::withMessages([
                    'transaction_date' => 'Tanggal transaksi tidak boleh sebelum tanggal pembukaan rekening.',
                ]);
            }

            $direction = match ($type) {
                SavingsTransaction::TYPE_WITHDRAWAL => 'out',
                SavingsTransaction::TYPE_ADJUSTMENT => ($data['direction'] ?? 'in') === 'out' ? 'out' : 'in',
                default => 'in',
            };

            // Saldo efektif pada tanggal transaksi. Untuk transaksi backdate,
            // saldo akhir hari ini belum tentu tersedia pada tanggal tersebut,
            // sehingga validasi memakai saldo pada tanggal itu dan saldo
            // terendah setelahnya agar tidak ada saldo negatif historis.
            $balanceAtDate = $this->money(
                SavingsTransaction::query()
                    ->where('account_row_id', $locked->row_id)
                    ->whereDate('transaction_date', '<=', $date)
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('row_id')
                    ->value('balance_after')
                    ?? '0.00'
            );

            $lowestAfter = SavingsTransaction::query()
                ->where('account_row_id', $locked->row_id)
                ->whereDate('transaction_date', '>=', $date)
                ->min('balance_after');

            $available = $balanceAtDate;
            if ($lowestAfter !== null) {
                $lowestAfter = $this->money($lowestAfter);

                if (bccomp($lowestAfter, $available, 2) < 0) {
                    $available = $lowestAfter;
                }
            }

            if ($direction === 'out' && ! $settings['allow_negative_balance']) {
                // SIUPK: saldo setelah penarikan tidak boleh kurang dari
                // saldo_minimal jenis simpanan (bukan hanya tidak negatif).
                $minimum = $this->money($locked->product?->minimum_balance ?? 0);
                $after = bcsub($available, $amount, 2);

                if (bccomp($after, $minimum, 2) < 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Saldo setelah penarikan tidak boleh kurang dari saldo minimal (Rp '
                            .number_format((float) $minimum, 0, ',', '.')
                            .'). Saldo tersedia: Rp '.number_format((float) $available, 0, ',', '.').'.',
                    ]);
                }
            }

            $code = match (true) {
                $type === SavingsTransaction::TYPE_DEPOSIT => SavingsTransaction::CODE_DEPOSIT,
                $type === SavingsTransaction::TYPE_WITHDRAWAL => SavingsTransaction::CODE_WITHDRAWAL,
                $direction === 'out' => SavingsTransaction::CODE_ADJUSTMENT_DEBIT,
                default => SavingsTransaction::CODE_ADJUSTMENT_CREDIT,
            };

            $description = $data['description'] ?? null;
            if ($description === null || $description === '') {
                $description = $type === SavingsTransaction::TYPE_WITHDRAWAL
                    ? 'Tarik Tunai Rekening '.$locked->account_number
                    : 'Setor Tunai Rekening '.$locked->account_number;
            }

            $transaction = $this->insertTransaction($locked, [
                'type' => $type,
                'code' => $code,
                'direction' => $direction,
                'amount' => $amount,
                'transaction_date' => $date,
                'reference' => $data['reference'] ?? null,
                'description' => $description,
            ], $userId);

            $this->recalculateAccount((int) $locked->row_id);

            return $transaction;
        });
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateTransactions(
        SavingsAccount $account,
        int $perPage,
        ?int $year = null,
        ?int $month = null,
    ): LengthAwarePaginator {
        return SavingsTransaction::query()
            ->where('account_row_id', $account->row_id)
            ->when($year !== null && $year > 0, fn ($query) => $query->whereYear('transaction_date', $year))
            ->when($month !== null && $month > 0, fn ($query) => $query->whereMonth('transaction_date', $month))
            ->orderByDesc('transaction_date')
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SavingsTransaction $transaction): array => $this->transactionRow($transaction));
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionRow(SavingsTransaction $transaction): array
    {
        $user = $transaction->created_by_user_id
            ? User::query()->find((int) $transaction->created_by_user_id)
            : null;

        return [
            'row_id' => (int) $transaction->row_id,
            'transaction_date' => $transaction->transaction_date?->toDateString(),
            'type' => (string) $transaction->type,
            'type_label' => $transaction->typeLabel(),
            'code' => (int) $transaction->code,
            'direction' => (string) $transaction->direction,
            'amount' => (float) $transaction->amount,
            'balance_after' => (float) $transaction->balance_after,
            'reference' => $transaction->reference,
            'description' => $transaction->description,
            'created_by' => $user?->initials ?: $user?->name,
            'created_at' => $transaction->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Data rekening koran: saldo sebelum periode, mutasi periode, dan saldo akhir.
     *
     * @return array<string, mixed>
     */
    public function statement(SavingsAccount $account, int $year, int $month = 0): array
    {
        if ($year <= 0) {
            $year = (int) now()->year;
        }

        $start = $month > 0
            ? CarbonImmutable::create($year, $month, 1)->startOfMonth()
            : CarbonImmutable::create($year, 1, 1)->startOfMonth();
        $end = $month > 0 ? $start->endOfMonth() : $start->endOfYear();

        $opening = $this->openingBalance((int) $account->row_id, $start);

        $transactions = SavingsTransaction::query()
            ->where('account_row_id', $account->row_id)
            ->whereDate('transaction_date', '>=', $start->toDateString())
            ->whereDate('transaction_date', '<=', $end->toDateString())
            ->orderBy('transaction_date')
            ->orderBy('row_id')
            ->get();

        $last = $transactions->last();
        $closing = $last !== null ? $this->money($last->balance_after) : $opening;

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return [
            'account' => $this->accountRow($account),
            'year' => $year,
            'month' => $month,
            'period_label' => $month > 0
                ? $months[$month].' '.$year
                : 'Sepanjang Tahun '.$year,
            'starts_at' => $start->toDateString(),
            'ends_at' => $end->toDateString(),
            'opening_balance' => (float) $opening,
            'closing_balance' => (float) $closing,
            'transactions' => $transactions
                ->map(fn (SavingsTransaction $transaction): array => $this->transactionRow($transaction))
                ->values()
                ->all(),
        ];
    }

    public function closeAccount(SavingsAccount $account, int $userId): SavingsAccount
    {
        return DB::connection($this->connectionName())->transaction(function () use ($account): SavingsAccount {
            /** @var SavingsAccount $locked */
            $locked = SavingsAccount::query()->whereKey($account->row_id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['status' => 'Rekening sudah ditutup.']);
            }

            $minimumClosing = $locked->minimum_closing_date;

            if ($minimumClosing !== null && now()->toDateString() < $minimumClosing->toDateString()) {
                throw ValidationException::withMessages([
                    'status' => 'Rekening belum dapat ditutup sebelum tanggal minimal tutup ('
                        .$minimumClosing->format('d/m/Y').').',
                ]);
            }

            if (bccomp($this->money($locked->balance), '0.00', 2) !== 0) {
                throw ValidationException::withMessages([
                    'status' => 'Rekening hanya dapat ditutup jika saldo sudah nol. Tarik seluruh saldo terlebih dahulu.',
                ]);
            }

            $locked->forceFill([
                'status' => SavingsAccount::STATUS_CLOSED,
                'closed_at' => now()->toDateString(),
            ])->save();

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAccount(SavingsAccount $account, array $data): SavingsAccount
    {
        // Jenis simpanan tidak dapat diubah: nomor rekening memakai kode jenis
        // saat rekening dibuka dan aturan satu rekening aktif per anggota+jenis
        // akan dilanggar bila jenis dipindah diam-diam.
        $payload = [
            'interest_rate' => $this->rate($this->numericOr($data['interest_rate'] ?? null, $account->interest_rate)),
            'tax_rate' => $this->rate($this->numericOr($data['tax_rate'] ?? null, $account->tax_rate)),
            'admin_fee' => $this->money($this->numericOr($data['admin_fee'] ?? null, $account->admin_fee)),
            'minimum_closing_date' => $this->nullableDate($data['minimum_closing_date'] ?? null)
                ?? $account->minimum_closing_date?->toDateString(),
            'authority_type' => (int) ($data['authority_type'] ?? $account->authority_type),
            'institution_name' => $data['institution_name'] ?? $account->institution_name,
            'institution_position' => $data['institution_position'] ?? $account->institution_position,
            'guardian_name' => $data['guardian_name'] ?? $account->guardian_name,
            'relationship' => $data['relationship'] ?? $account->relationship,
            'notes' => $data['notes'] ?? $account->notes,
        ];

        $account->forceFill($payload)->save();

        return $account->fresh(['member.person', 'member.address', 'product']) ?? $account;
    }

    /**
     * Hapus rekening beserta mutasinya. Hanya boleh jika belum pernah ada
     * mutasi, sehingga histori keuangan tidak bisa hilang tanpa jejak.
     */
    public function deleteAccount(SavingsAccount $account): void
    {
        DB::connection($this->connectionName())->transaction(function () use ($account): void {
            $count = SavingsTransaction::query()->where('account_row_id', $account->row_id)->count();

            if ($count > 0) {
                throw new DomainException(
                    'Rekening tidak dapat dihapus karena sudah memiliki '.$count.' mutasi. Gunakan penutupan rekening.',
                );
            }

            $account->delete();
        });
    }

    /**
     * Periode bunga mengikuti SIUPK:
     *  - tgl_bunga >= 0 : periode bulan terpilih mulai tanggal itu s.d. sehari
     *    sebelum tanggal yang sama bulan berikutnya; posting bulan berikutnya.
     *  - tgl_bunga < 0  : tanggal dihitung dari akhir bulan (mis. -1 = akhir
     *    bulan); periode mulai bulan sebelumnya s.d. sehari sebelum tanggal
     *    tersebut di bulan terpilih; posting di bulan terpilih.
     *  - tgl_bunga 0    : sama dengan tanggal 1 (periode satu bulan penuh).
     *
     * @return array{year:int, month:int, start:CarbonImmutable, end:CarbonImmutable, posting:CarbonImmutable, days:int, label:string, range_label:string}
     */
    public function interestPeriod(int $year, int $month): array
    {
        $current = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $previous = $current->subMonthNoOverflow();
        $day = (int) $this->modules->settings()['interest_day'];

        if ($day < 0) {
            $startDay = max(1, min((int) $previous->daysInMonth, (int) $previous->daysInMonth + $day + 1));
            $postingDay = max(1, min((int) $current->daysInMonth, (int) $current->daysInMonth + $day + 1));

            $start = $previous->setDay($startDay);
            $posting = $current->setDay($postingDay);
        } else {
            $effectiveDay = $day === 0 ? 1 : $day;
            $next = $current->addMonthNoOverflow();

            $startDay = max(1, min((int) $current->daysInMonth, $effectiveDay));
            $postingDay = max(1, min((int) $next->daysInMonth, $effectiveDay));

            $start = $current->setDay($startDay);
            $posting = $next->setDay($postingDay);
        }

        $end = $posting->subDay();
        $days = max(1, (int) $start->diffInDays($end) + 1);

        return [
            'year' => $year,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'posting' => $posting,
            'days' => $days,
            'label' => $current->translatedFormat('F Y'),
            'range_label' => $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y'),
        ];
    }

    /**
     * Pratinjau bunga untuk satu periode. Tidak menulis apa pun ke database.
     *
     * @param  list<int>|null  $accountIds
     * @return array<string, mixed>
     */
    public function interestPreview(int $year, int $month, ?int $productId = null, ?array $accountIds = null): array
    {
        $period = $this->interestPeriod($year, $month);
        $settings = $this->modules->settings();
        $method = (int) $settings['interest_method'];
        $periodKey = sprintf('%04d-%02d', $year, $month);

        $accounts = SavingsAccount::query()
            ->with(['member.person', 'product'])
            ->where('status', SavingsAccount::STATUS_ACTIVE)
            ->whereDate('opened_at', '<=', $period['end']->toDateString())
            ->when($productId !== null && $productId > 0, fn ($query) => $query->where('product_row_id', $productId))
            ->when(
                $accountIds !== null && $accountIds !== [],
                fn ($query) => $query->whereIn('row_id', array_map('intval', $accountIds)),
            )
            ->orderBy('account_number')
            ->get();

        $rows = [];
        $totals = [
            'basis' => 0.0,
            'gross_interest' => 0.0,
            'tax_total' => 0.0,
            'admin_total' => 0.0,
            'net_total' => 0.0,
        ];
        $postableTotals = [
            'gross_interest' => 0.0,
            'tax_total' => 0.0,
            'admin_total' => 0.0,
            'net_total' => 0.0,
        ];

        foreach ($accounts as $account) {
            $row = $this->previewAccount($account, $period['start'], $period['end'], $periodKey, $method, $settings);
            $rows[] = $row;

            $totals['basis'] = round($totals['basis'] + (float) $row['basis'], 2);
            $totals['gross_interest'] = round($totals['gross_interest'] + (float) $row['gross_interest'], 2);
            $totals['tax_total'] = round($totals['tax_total'] + (float) $row['tax_total'], 2);
            $totals['admin_total'] = round($totals['admin_total'] + (float) $row['admin_fee'], 2);
            $totals['net_total'] = round($totals['net_total'] + (float) $row['net_total'], 2);

            $postableTotals['gross_interest'] = round(
                $postableTotals['gross_interest'] + (float) $row['postable_gross'],
                2,
            );
            $postableTotals['tax_total'] = round(
                $postableTotals['tax_total'] + (float) $row['postable_tax'],
                2,
            );
            $postableTotals['admin_total'] = round(
                $postableTotals['admin_total'] + (float) $row['postable_admin'],
                2,
            );
            $postableTotals['net_total'] = round(
                $postableTotals['net_total'] + (float) $row['postable_net'],
                2,
            );
        }

        $existing = $this->existingRun($year, $month);

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'label' => $period['label'],
                'starts_at' => $period['start']->toDateString(),
                'ends_at' => $period['end']->toDateString(),
                'posting_date' => $period['posting']->toDateString(),
                'days' => $period['days'],
                'range_label' => $period['range_label'],
                'method' => $method,
                'method_label' => $this->methodLabel($method),
                'interest_day' => (int) $settings['interest_day'],
            ],
            'rows' => $rows,
            'totals' => $totals,
            'postable_totals' => $postableTotals,
            'account_count' => count($rows),
            'postable_count' => count(array_filter($rows, static fn (array $row): bool => (bool) $row['is_postable'])),
            'existing_run' => $existing !== null ? $this->runRow($existing) : null,
            'settings' => [
                'interest_rate' => (float) $settings['interest_rate'],
                'tax_rate' => (float) $settings['tax_rate'],
                'admin_fee' => (float) $settings['admin_fee'],
                'opening_fee' => (float) $settings['opening_fee'],
                'minimum_balance' => (float) $settings['minimum_balance'],
                'minimum_taxable_interest' => (float) $settings['minimum_taxable_interest'],
                'interest_day' => (int) $settings['interest_day'],
            ],
        ];
    }

    /**
     * Posting bunga, pajak, dan biaya admin untuk satu periode.
     *
     * Aman dijalankan berulang: komponen yang sudah ada per rekening dilewati,
     * sehingga proses bisa dilanjutkan untuk CIF yang belum diproses.
     *
     * @param  list<int>|null  $accountIds
     * @return array<string, mixed>
     */
    public function postInterest(
        int $year,
        int $month,
        int $userId,
        ?string $postingDate = null,
        ?array $accountIds = null,
    ): array {
        $period = $this->interestPeriod($year, $month);
        $periodKey = sprintf('%04d-%02d', $year, $month);
        $date = $period['posting']->toDateString();

        if ($period['posting']->greaterThan(CarbonImmutable::now()->startOfDay())) {
            throw new DomainException(
                'Bunga periode ini belum dapat diposting. Tanggal posting adalah '
                .$period['posting']->translatedFormat('j F Y').'.',
            );
        }

        $preview = $this->interestPreview($year, $month, null, $accountIds);

        try {
            return DB::connection($this->connectionName())->transaction(function () use (
                $preview,
                $period,
                $periodKey,
                $date,
                $userId,
                $year,
                $month
            ): array {
                $run = SavingsInterestRun::query()
                    ->where('period', $periodKey)
                    ->lockForUpdate()
                    ->first();

                $run ??= SavingsInterestRun::query()->create([
                    'period' => $periodKey,
                    'period_start' => $period['start']->toDateString(),
                    'period_end' => $period['end']->toDateString(),
                    'posting_date' => $date,
                    'method' => (int) $preview['period']['method'],
                    'accounts_count' => 0,
                    'gross_interest' => '0.00',
                    'tax_total' => '0.00',
                    'admin_total' => '0.00',
                    'net_total' => '0.00',
                    'status' => SavingsInterestRun::STATUS_POSTED,
                    'posted_at' => now(),
                    'posted_by_user_id' => $userId,
                    'notes' => 'Periode '.$preview['period']['label'],
                ]);

                $processed = 0;
                $skipped = 0;

                foreach ($preview['rows'] as $row) {
                    if (! $row['is_postable']) {
                        $skipped++;

                        continue;
                    }

                    /** @var SavingsAccount $account */
                    $account = SavingsAccount::query()->whereKey($row['account_row_id'])->lockForUpdate()->firstOrFail();

                    if (! $account->isActive()) {
                        $skipped++;

                        continue;
                    }

                    $gross = $this->money($row['gross_interest']);
                    $tax = $this->money($row['tax_total']);
                    $admin = $this->money($row['admin_fee']);
                    $inserted = false;

                    if (! $row['posted_interest'] && bccomp($gross, '0.00', 2) > 0) {
                        $this->insertTransaction($account, [
                            'type' => SavingsTransaction::TYPE_INTEREST,
                            'code' => SavingsTransaction::CODE_INTEREST,
                            'direction' => 'in',
                            'amount' => $gross,
                            'transaction_date' => $date,
                            'reference' => 'BUNGA-'.$periodKey,
                            'description' => 'Bunga '.$account->account_number.' '.$row['member_name']
                                .' bulan '.$month.' '.$year,
                            'interest_run_row_id' => $run->row_id,
                        ], $userId);

                        $inserted = true;
                    }

                    if (! $row['posted_tax'] && bccomp($tax, '0.00', 2) > 0) {
                        $this->insertTransaction($account, [
                            'type' => SavingsTransaction::TYPE_TAX,
                            'code' => SavingsTransaction::CODE_TAX,
                            'direction' => 'out',
                            'amount' => $tax,
                            'transaction_date' => $date,
                            'reference' => 'PAJAK-'.$periodKey,
                            'description' => 'Pajak Bunga '.$account->account_number.' '.$row['member_name']
                                .' bulan '.$month.' '.$year,
                            'interest_run_row_id' => $run->row_id,
                        ], $userId);

                        $inserted = true;
                    }

                    if (! $row['posted_admin'] && bccomp($admin, '0.00', 2) > 0) {
                        // SIUPK menagih admin penuh setiap periode, tanpa
                        // membatasi pada saldo yang tersedia.
                        $this->insertTransaction($account, [
                            'type' => SavingsTransaction::TYPE_ADMIN,
                            'code' => SavingsTransaction::CODE_ADMIN,
                            'direction' => 'out',
                            'amount' => $admin,
                            'transaction_date' => $date,
                            'reference' => 'ADMIN-'.$periodKey,
                            'description' => 'Admin '.$account->account_number.' '.$row['member_name']
                                .' bulan '.$month.' '.$year,
                            'interest_run_row_id' => $run->row_id,
                        ], $userId);

                        $inserted = true;
                    }

                    if ($inserted) {
                        $this->recalculateAccount((int) $account->row_id);
                        $processed++;
                    } else {
                        $skipped++;
                    }
                }

                $totals = $this->runTotals((int) $run->row_id);

                $run->forceFill([
                    'period_start' => $period['start']->toDateString(),
                    'period_end' => $period['end']->toDateString(),
                    'posting_date' => $date,
                    'method' => (int) $preview['period']['method'],
                    'accounts_count' => $totals['accounts'],
                    'gross_interest' => $totals['gross'],
                    'tax_total' => $totals['tax'],
                    'admin_total' => $totals['admin'],
                    'net_total' => $totals['net'],
                    'posted_at' => $run->posted_at ?? now(),
                    'posted_by_user_id' => $run->posted_by_user_id ?? $userId,
                ])->save();

                return [
                    ...$this->runRow($run->fresh() ?? $run),
                    'processed' => $processed,
                    'skipped' => $skipped,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Bunga periode ini sedang diproses. Muat ulang halaman lalu coba lagi.');
        }
    }

    public function voidInterestRun(SavingsInterestRun $run): void
    {
        DB::connection($this->connectionName())->transaction(function () use ($run): void {
            $transactions = SavingsTransaction::query()
                ->where('interest_run_row_id', $run->row_id)
                ->get(['row_id', 'account_row_id']);

            $accountIds = $transactions->pluck('account_row_id')->unique()->map(fn ($id): int => (int) $id)->all();

            SavingsTransaction::query()->where('interest_run_row_id', $run->row_id)->delete();

            foreach ($accountIds as $accountId) {
                $this->recalculateAccount($accountId);
            }

            $run->delete();
        });
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateInterestRuns(int $perPage): LengthAwarePaginator
    {
        return SavingsInterestRun::query()
            ->orderByDesc('period')
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SavingsInterestRun $run): array => $this->runRow($run));
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function productOptions(): array
    {
        return SavingsProduct::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (SavingsProduct $product): array => [
                'value' => (int) $product->row_id,
                'label' => $product->code.' — '.$product->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function productFilterOptions(): array
    {
        return SavingsProduct::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (SavingsProduct $product): array => [
                'value' => (int) $product->row_id,
                'label' => $product->name,
            ])
            ->all();
    }

    /**
     * Rekening simpanan milik satu anggota, dipakai pada halaman detail anggota.
     *
     * @return array<int, array<string, mixed>>
     */
    public function memberAccounts(int $memberRowId): array
    {
        return SavingsAccount::query()
            ->with('product')
            ->where('member_row_id', $memberRowId)
            ->orderByDesc('opened_at')
            ->orderByDesc('row_id')
            ->get()
            ->map(fn (SavingsAccount $account): array => [
                'row_id' => (int) $account->row_id,
                'account_number' => (string) $account->account_number,
                'product_name' => $account->product?->name ?? '—',
                'category_label' => $account->product?->categoryLabel() ?? '—',
                'form_type_label' => $account->product?->formTypeLabel() ?? '—',
                'balance' => (float) $account->balance,
                'status' => (string) $account->status,
                'status_label' => $account->isActive() ? 'Aktif' : 'Ditutup',
                'opened_at' => $account->opened_at?->toDateString(),
                'href' => '/simpanan/'.$account->row_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string, name: string, member_number: string, nik: string|null}>
     */
    public function searchMembers(string $search): array
    {
        return Member::query()
            ->with('person')
            ->where('status', 'active')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('member_number', 'like', "%{$search}%")
                ->orWhereHas('person', fn ($person) => $person
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('national_identity_number', 'like', "%{$search}%"))))
            ->orderBy('member_number')
            ->limit(25)
            ->get()
            ->map(fn (Member $member): array => [
                'value' => (int) $member->row_id,
                'label' => ($member->person?->full_name ?? 'Anggota').' — '.$member->member_number,
                'name' => (string) ($member->person?->full_name ?? ''),
                'member_number' => (string) $member->member_number,
                'nik' => $member->person?->national_identity_number,
            ])
            ->all();
    }

    /**
     * Hitung ulang seluruh balance_after secara kronologis lalu sinkronkan
     * saldo rekening. Dipakai setelah insert/backdate/void agar saldo tidak
     * pernah menyimpang.
     */
    public function recalculateAccount(int $accountRowId): string
    {
        $transactions = SavingsTransaction::query()
            ->where('account_row_id', $accountRowId)
            ->orderBy('transaction_date')
            ->orderBy('row_id')
            ->get();

        $running = '0.00';

        foreach ($transactions as $transaction) {
            $amount = $this->money($transaction->amount);
            $running = $transaction->direction === 'in'
                ? bcadd($running, $amount, 2)
                : bcsub($running, $amount, 2);

            if ($this->money($transaction->balance_after) !== $running) {
                $transaction->forceFill(['balance_after' => $running])->saveQuietly();
            }
        }

        $account = SavingsAccount::query()->whereKey($accountRowId)->first();

        if ($account !== null && $this->money($account->balance) !== $running) {
            $account->forceFill(['balance' => $running])->saveQuietly();
        }

        return $running;
    }

    public function methodLabel(int $method): string
    {
        return match ($method) {
            1 => 'Saldo terakhir',
            2 => 'Saldo terendah',
            default => 'Saldo rata-rata',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertTransaction(SavingsAccount $account, array $payload, int $userId): SavingsTransaction
    {
        return SavingsTransaction::query()->create([
            'account_row_id' => $account->row_id,
            'transaction_date' => $payload['transaction_date'],
            'type' => $payload['type'],
            'code' => (int) ($payload['code'] ?? 0),
            'direction' => $payload['direction'],
            'amount' => $this->money($payload['amount']),
            'balance_after' => $this->money($account->balance),
            'reference' => $payload['reference'] ?? null,
            'description' => $payload['description'] ?? null,
            'interest_run_row_id' => $payload['interest_run_row_id'] ?? null,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function previewAccount(
        SavingsAccount $account,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $periodKey,
        int $method,
        array $settings,
    ): array {
        $opening = $this->openingBalance((int) $account->row_id, $start);
        $transactions = SavingsTransaction::query()
            ->where('account_row_id', $account->row_id)
            ->whereDate('transaction_date', '>=', $start->toDateString())
            ->whereDate('transaction_date', '<=', $end->toDateString())
            ->orderBy('transaction_date')
            ->orderBy('row_id')
            ->get(['row_id', 'transaction_date', 'direction', 'amount']);

        $byDate = $transactions->groupBy(
            static fn (SavingsTransaction $transaction): string => (string) $transaction->transaction_date?->toDateString(),
        );

        $days = max(1, (int) $start->diffInDays($end) + 1);
        $closing = $opening;
        $dailyTotal = '0.00';
        $hasTransactions = false;
        $lowestTransactionBalance = null;

        for ($index = 0; $index < $days; $index++) {
            $date = $start->addDays($index)->toDateString();

            foreach ($byDate->get($date, []) as $transaction) {
                $amount = $this->money($transaction->amount);
                $closing = $transaction->direction === 'in'
                    ? bcadd($closing, $amount, 2)
                    : bcsub($closing, $amount, 2);

                $hasTransactions = true;

                if ($lowestTransactionBalance === null
                    || bccomp($closing, $lowestTransactionBalance, 2) < 0) {
                    $lowestTransactionBalance = $closing;
                }
            }

            $dailyTotal = bcadd($dailyTotal, $closing, 2);
        }

        // Pemetaan persis SIUPK: 1 = saldo terakhir, 2 = saldo terendah,
        // 3 = saldo rata-rata. Metode 1/2 legacy hanya membaca baris mutasi
        // di dalam periode; rekening tanpa mutasi menghasilkan dasar 0.
        // Metode 3 tetap memakai saldo awal sebelum periode sebagai dasar.
        $basis = match ($method) {
            1 => $hasTransactions ? $closing : '0.00',
            2 => $lowestTransactionBalance ?? '0.00',
            default => bcdiv($dailyTotal, (string) $days, 8),
        };

        $minimumBalance = $this->money($settings['minimum_balance']);
        $minimumTaxable = $this->money($settings['minimum_taxable_interest']);
        $rate = (float) ($account->interest_rate ?? $account->product?->interest_rate ?? $settings['interest_rate']);
        $taxRate = (float) ($account->tax_rate ?? $account->product?->tax_rate ?? $settings['tax_rate']);
        $admin = $this->money($account->admin_fee);

        $reason = null;
        $gross = '0.00';

        if (bccomp($basis, $minimumBalance, 2) < 0) {
            $reason = 'Saldo di bawah minimum perhitungan bunga.';
        } else {
            $gross = $this->round2(bcmul($basis, bcdiv((string) $rate, '100', 10), 10));

            if (bccomp($gross, '0.00', 2) <= 0) {
                $reason = 'Bunga membulat menjadi nol.';
            }
        }

        $tax = '0.00';
        if (bccomp($gross, '0.00', 2) > 0 && bccomp($gross, $minimumTaxable, 2) >= 0) {
            $tax = $this->round2(bcmul($gross, bcdiv((string) $taxRate, '100', 10), 10));
        }

        $postedInterest = $this->componentPosted((int) $account->row_id, SavingsTransaction::TYPE_INTEREST, $periodKey);
        $postedTax = $this->componentPosted((int) $account->row_id, SavingsTransaction::TYPE_TAX, $periodKey);
        $postedAdmin = $this->componentPosted((int) $account->row_id, SavingsTransaction::TYPE_ADMIN, $periodKey);

        $postableGross = ! $postedInterest && bccomp($gross, '0.00', 2) > 0 ? $gross : '0.00';
        $postableTax = ! $postedTax && bccomp($tax, '0.00', 2) > 0 ? $tax : '0.00';
        $postableAdmin = ! $postedAdmin && bccomp($admin, '0.00', 2) > 0 ? $admin : '0.00';
        $postableNet = bcsub(bcsub($postableGross, $postableTax, 2), $postableAdmin, 2);

        $isPostable = bccomp($postableGross, '0.00', 2) > 0
            || bccomp($postableTax, '0.00', 2) > 0
            || bccomp($postableAdmin, '0.00', 2) > 0;

        if ($isPostable === false && $reason === null) {
            $reason = 'Bunga, pajak, dan admin periode ini sudah diposting.';
        }

        return [
            'account_row_id' => (int) $account->row_id,
            'account_number' => (string) $account->account_number,
            'member_name' => $account->member?->person?->full_name ?? '-',
            'product_name' => $account->product?->name ?? 'Simpanan',
            'opening_balance' => (float) $opening,
            'closing_balance' => (float) $closing,
            'basis' => (float) $this->round2($basis),
            'days' => $days,
            'interest_rate' => $rate,
            'tax_rate' => $taxRate,
            'gross_interest' => (float) $gross,
            'tax_total' => (float) $tax,
            'admin_fee' => (float) $admin,
            'net_total' => (float) bcsub(bcsub($gross, $tax, 2), $admin, 2),
            'posted_interest' => $postedInterest,
            'posted_tax' => $postedTax,
            'posted_admin' => $postedAdmin,
            'postable_gross' => (float) $postableGross,
            'postable_tax' => (float) $postableTax,
            'postable_admin' => (float) $postableAdmin,
            'postable_net' => (float) $postableNet,
            'is_postable' => $isPostable,
            'reason' => $reason,
        ];
    }

    private function componentPosted(int $accountRowId, string $type, string $periodKey): bool
    {
        $prefix = match ($type) {
            SavingsTransaction::TYPE_INTEREST => 'BUNGA-',
            SavingsTransaction::TYPE_TAX => 'PAJAK-',
            SavingsTransaction::TYPE_ADMIN => 'ADMIN-',
            default => null,
        };

        if ($prefix === null) {
            return false;
        }

        return SavingsTransaction::query()
            ->where('account_row_id', $accountRowId)
            ->where('type', $type)
            ->where('reference', $prefix.$periodKey)
            ->exists();
    }

    private function openingBalance(int $accountRowId, CarbonImmutable $start): string
    {
        $value = SavingsTransaction::query()
            ->where('account_row_id', $accountRowId)
            ->whereDate('transaction_date', '<', $start->toDateString())
            ->orderByDesc('transaction_date')
            ->orderByDesc('row_id')
            ->value('balance_after');

        return $this->money($value ?? 0);
    }

    /**
     * @return array{accounts:int, gross:string, tax:string, admin:string, net:string}
     */
    private function runTotals(int $runRowId): array
    {
        $transactions = SavingsTransaction::query()
            ->where('interest_run_row_id', $runRowId)
            ->get(['account_row_id', 'type', 'amount']);

        $gross = '0.00';
        $tax = '0.00';
        $admin = '0.00';

        foreach ($transactions as $transaction) {
            $amount = $this->money($transaction->amount);

            if ($transaction->type === SavingsTransaction::TYPE_INTEREST) {
                $gross = bcadd($gross, $amount, 2);
            } elseif ($transaction->type === SavingsTransaction::TYPE_TAX) {
                $tax = bcadd($tax, $amount, 2);
            } elseif ($transaction->type === SavingsTransaction::TYPE_ADMIN) {
                $admin = bcadd($admin, $amount, 2);
            }
        }

        return [
            'accounts' => $transactions->pluck('account_row_id')->unique()->count(),
            'gross' => $gross,
            'tax' => $tax,
            'admin' => $admin,
            'net' => bcsub(bcsub($gross, $tax, 2), $admin, 2),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function existingRun(int $year, int $month): ?SavingsInterestRun
    {
        return SavingsInterestRun::query()
            ->where('period', sprintf('%04d-%02d', $year, $month))
            ->where('status', SavingsInterestRun::STATUS_POSTED)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function runRow(SavingsInterestRun $run): array
    {
        return [
            'row_id' => (int) $run->row_id,
            'period' => (string) $run->period,
            'period_start' => $run->period_start?->toDateString(),
            'period_end' => $run->period_end?->toDateString(),
            'posting_date' => $run->posting_date?->toDateString(),
            'method' => (int) $run->method,
            'method_label' => $this->methodLabel((int) $run->method),
            'accounts_count' => (int) $run->accounts_count,
            'gross_interest' => (float) $run->gross_interest,
            'tax_total' => (float) $run->tax_total,
            'admin_total' => (float) $run->admin_total,
            'net_total' => (float) $run->net_total,
            'status' => (string) $run->status,
            'posted_at' => $run->posted_at?->toDateTimeString(),
        ];
    }

    private function nextAccountNumber(SavingsProduct $product, Member $member): string
    {
        $tenantId = (int) $this->context->id();
        $memberId = (int) ($member->id ?? $member->row_id);
        $productCode = is_numeric($product->code) ? (int) $product->code : (int) $product->row_id;
        $sequence = SavingsAccount::query()->where('member_row_id', $member->row_id)->count() + 1;

        // Format SIUPK: {jenis}-{lokasi 3 digit}.{anggota}-{urutan 2 digit}
        do {
            $candidate = sprintf(
                '%d-%03d.%03d-%02d',
                $productCode,
                $tenantId,
                $memberId,
                $sequence,
            );

            $exists = SavingsAccount::query()->where('account_number', $candidate)->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }

    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = CarbonImmutable::parse((string) $value);

        return $date->toDateString();
    }

    private function numericOr(mixed $value, mixed $fallback): string
    {
        if ($value === null || $value === '') {
            $value = $fallback;
        }

        return is_numeric($value) ? (string) $value : '0';
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function rate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        return number_format((float) $value, 3, '.', '');
    }

    /**
     * Bulatkan string decimal ke 2 angka di belakang koma (half-up).
     */
    private function round2(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        $rounded = bcadd($absolute, '0.005', 2);

        return $negative ? '-'.$rounded : $rounded;
    }
}
