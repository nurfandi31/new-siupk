<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\JournalBrowseService;
use App\Domain\Accounting\Services\JournalEditService;
use App\Domain\Accounting\Services\JournalEntryOptionResolver;
use App\Domain\Accounting\Services\JournalReversalService;
use App\Domain\Assets\Models\Asset;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class JournalBrowseController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly JournalBrowseService $browse,
        private readonly JournalReversalService $reversals,
        private readonly JournalEntryOptionResolver $resolver,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'journals.view');

        $from = (string) $request->query('from', CarbonImmutable::today()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', CarbonImmutable::today()->toDateString());
        $q = $request->query('q');
        $source = $request->query('source');
        $page = max(1, (int) $request->query('page', 1));

        $payload = $this->browse->list(
            from: $from,
            to: $to,
            q: is_string($q) ? $q : null,
            source: is_string($source) ? $source : null,
            page: $page,
        );

        return Inertia::render('Accounting/Journals/Index', [
            ...$payload,
            'sourceOptions' => [
                ['value' => 'all', 'label' => 'Semua sumber'],
                ['value' => 'loan_installment', 'label' => 'Angsuran'],
                ['value' => 'loan', 'label' => 'Pencairan'],
                ['value' => 'manual', 'label' => 'Jurnal umum'],
                ['value' => 'asset_purchase', 'label' => 'Pembelian Aset'],
                ['value' => 'journal_reversal', 'label' => 'Reversal'],
                ['value' => 'loan_write_off', 'label' => 'Penghapusan'],
                ['value' => 'loan_reschedule_close', 'label' => 'Reschedule'],
                ['value' => 'profit_allocation', 'label' => 'Alokasi laba'],
            ],
            'can_reverse' => $this->permissions->allows($request->user(), 'journals.create'),
        ]);
    }

    public function reverse(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        $data = $request->validate([
            'reversal_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $reversal = $this->reversals->reverse(
                original: $entry,
                reversalDate: $data['reversal_date'],
                platformUserId: (int) $request->user()->row_id,
                reason: $data['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Jurnal #'.$entry->id.' dibatalkan. Reversal #'.$reversal->id.' dibuat.',
        );
    }

    public function bulkReverse(Request $request): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer'],
            'reversal_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $entryRowIds = array_map('intval', $data['entry_ids']);
        $result = $this->reversals->bulkReverse(
            entryRowIds: $entryRowIds,
            reversalDate: $data['reversal_date'],
            platformUserId: (int) $request->user()->row_id,
            reason: $data['reason'] ?? null,
        );

        $reversedCount = count($result['reversed']);
        $errors = $result['errors'];

        if ($reversedCount === 0 && ! empty($errors)) {
            return back()->with('error', 'Gagal membatalkan transaksi: '.implode(', ', $errors));
        }

        $message = $reversedCount.' transaksi berhasil dibatalkan (reverse).';
        if (! empty($errors)) {
            $message .= ' Namun '.count($errors).' transaksi gagal: '.implode(', ', $errors);

            return back()->with('warning', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * Tampilkan form edit jurnal yang sudah di-post.
     * Hanya source_type=manual atau asset_purchase yang boleh di-edit.
     */
    public function edit(Request $request, JournalEntry $entry): Response
    {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        if (! in_array($entry->source_type, JournalEditService::EDITABLE_SOURCE_TYPES, true)) {
            abort(422, sprintf(
                'Jurnal sumber [%s] tidak dapat diedit. Hanya jurnal umum dan pembelian inventaris.',
                (string) $entry->source_type,
            ));
        }

        if ($entry->reversed_entry_row_id !== null) {
            abort(422, 'Jurnal ini adalah hasil reverse dan tidak dapat diedit.');
        }

        if (JournalEntry::query()->where('reversed_entry_row_id', $entry->row_id)->exists()) {
            abort(422, 'Jurnal ini sudah pernah di-reverse.');
        }

        $entry->loadMissing('lines.account');

        $types = $this->resolver->getTransactionTypes();
        $allowed = array_column($types, 'value');

        $debitLine = $entry->lines->firstWhere('line_number', 1);
        $creditLine = $entry->lines->firstWhere('line_number', 2);

        $prefill = [
            'transaction_date' => $entry->transaction_date?->toDateString() ?? '',
            'transaction_type' => $entry->transaction_type,
            'description' => $entry->description ?? '',
            'reference' => $entry->legacy_relation ?? '',
            'amount' => (float) ($debitLine?->debit ?? 0),
            'disimpan_ke_row_id' => $debitLine?->account_row_id ? (int) $debitLine->account_row_id : null,
            'sumber_dana_row_id' => $creditLine?->account_row_id ? (int) $creditLine->account_row_id : null,
            'asset_name' => null,
            'asset_quantity' => null,
            'asset_unit_cost' => null,
            'asset_useful_life_months' => null,
        ];

        if ($entry->source_type === 'asset_purchase') {
            $asset = Asset::query()
                ->where('purchased_at', $entry->transaction_date)
                ->where('cost', $prefill['amount'])
                ->latest('row_id')
                ->first();

            if ($asset !== null) {
                $prefill['asset_name'] = $asset->name;
                $prefill['asset_quantity'] = (int) $asset->quantity;
                $prefill['asset_unit_cost'] = (float) $asset->unit_cost;
                $prefill['asset_useful_life_months'] = (int) $asset->useful_life_months;
            }
        }

        return Inertia::render('Accounting/JournalEntries/Edit', [
            'originalEntry' => [
                'row_id' => (int) $entry->row_id,
                'id' => (int) $entry->id,
                'journal_number' => $entry->journal_number ?: (string) $entry->id,
                'transaction_date' => $entry->transaction_date?->toDateString(),
                'description' => $entry->description,
                'amount' => $prefill['amount'],
                'source_type' => $entry->source_type,
            ],
            'transactionTypes' => $types,
            'labels' => $this->resolver->getLabels(),
            'options' => $this->resolver->getOptionsForAllTypes(),
            'accountOptions' => $this->resolver->getAllAccountOptions(),
            'prefill' => $prefill,
        ]);
    }

    /**
     * Submit edit: reverse jurnal lama + buat jurnal baru (atomic).
     */
    public function update(
        Request $request,
        JournalEntry $entry,
        JournalEditService $editor,
    ): RedirectResponse {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        if (! in_array($entry->source_type, JournalEditService::EDITABLE_SOURCE_TYPES, true)) {
            abort(422, 'Jurnal ini tidak dapat diedit.');
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan edit wajib diisi.']);
        }
        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Alasan edit maksimal 500 karakter.']);
        }

        $isInventory = JournalEntryOptionResolver::isAssetPurchase((string) $request->input('transaction_type', ''));

        $rules = [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_type' => ['required', 'string', 'in:'.implode(',', array_merge(array_keys(JournalEntryOptionResolver::TYPES), ['pembelian_inventaris', 'angsuran']))],
            'description' => [$isInventory ? 'nullable' : 'required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'sumber_dana_row_id' => ['required', 'integer', 'different:disimpan_ke_row_id'],
            'disimpan_ke_row_id' => ['required', 'integer', 'different:sumber_dana_row_id'],
            'asset_name' => [$isInventory ? 'required' : 'nullable', 'string', 'max:180'],
            'asset_quantity' => [$isInventory ? 'required' : 'nullable', 'integer', 'min:1', 'max:999999'],
            'asset_unit_cost' => [$isInventory ? 'required' : 'nullable', 'numeric', 'min:1'],
            'asset_useful_life_months' => [
                $isInventory ? 'required' : 'nullable',
                'integer',
                'min:'.($request->input('transaction_type') === 'pembelian_aset_tanah' ? 0 : 1),
                'max:1200',
            ],
        ];

        $data = $request->validate($rules);

        // Validasi tambahan: akun COA harus aktif dan postable.
        $tenantId = app(TenantContext::class)->id();
        $validAccountIds = Account::on('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->pluck('row_id')
            ->all();
        if (! in_array((int) $data['sumber_dana_row_id'], array_map('intval', $validAccountIds), true)
            || ! in_array((int) $data['disimpan_ke_row_id'], array_map('intval', $validAccountIds), true)) {
            throw ValidationException::withMessages(['disimpan_ke_row_id' => 'Akun yang dipilih tidak aktif atau tidak dapat diposting.']);
        }

        // Validasi inventory: amount harus sama dengan qty × unit_cost.
        if ($isInventory) {
            $qty = (int) ($data['asset_quantity'] ?? 0);
            $unit = (float) ($data['asset_unit_cost'] ?? 0);
            $computed = round($qty * $unit, 2);
            $amount = round((float) $data['amount'], 2);
            if ($qty > 0 && $unit > 0 && $computed !== $amount) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Harga perolehan harus sama dengan jml unit × harga satuan (%s).',
                        number_format($computed, 0, ',', '.'),
                    ),
                ]);
            }
        }

        try {
            $result = $editor->edit(
                original: $entry,
                data: $data,
                reversalDate: $data['transaction_date'],
                reason: $reason,
                platformUserId: (int) $request->user()->row_id,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with(
            'success',
            sprintf(
                'Jurnal #%d dikoreksi. Reversal #%d + jurnal baru #%d dibuat.',
                $entry->id,
                $result['reversal']->id,
                $result['new']->id,
            ),
        );
    }
}
