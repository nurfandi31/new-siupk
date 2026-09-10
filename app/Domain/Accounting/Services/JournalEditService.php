<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Assets\Services\AssetService;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * "Edit" jurnal yang sudah di-post dengan cara atomic: reverse jurnal lama +
 * buat jurnal baru dalam satu DB transaction (all-or-nothing).
 *
 * Hanya source_type=manual dan asset_purchase yang boleh di-edit.
 * Jurnal hasil reverse tidak boleh di-edit lagi (mencegah double-edit berantai).
 *
 * Pola atomic: outer DB transaction membungkus:
 *   1) JournalReversalService::reverse() — nested jadi savepoint
 *   2) Create draft entry baru
 *   3) JournalPostingService::post() — nested jadi savepoint
 *
 * Kalau step 3 gagal, rollback outer otomatis batalkan reversal juga.
 */
final readonly class JournalEditService
{
    /** Source type yang boleh di-edit via fitur ini. */
    public const EDITABLE_SOURCE_TYPES = ['manual', 'asset_purchase'];

    public function __construct(
        private JournalReversalService $reversals,
        private JournalPostingService $posting,
        private AssetService $assets,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated payload dari JournalEntryRequest
     * @return array{reversal: JournalEntry, new: JournalEntry}
     */
    public function edit(
        JournalEntry $original,
        array $data,
        DateTimeInterface|string $reversalDate,
        string $reason,
        int $platformUserId,
    ): array {
        if (! in_array($original->source_type, self::EDITABLE_SOURCE_TYPES, true)) {
            throw new DomainException(sprintf(
                'Jurnal sumber [%s] tidak dapat diedit. Hanya jurnal umum (manual) dan pembelian inventaris (asset_purchase).',
                (string) $original->source_type,
            ));
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Alasan edit wajib diisi.');
        }

        if ($original->reversed_entry_row_id !== null) {
            throw new DomainException('Jurnal ini adalah hasil reverse dan tidak dapat diedit lagi.');
        }

        if (JournalEntry::query()->where('reversed_entry_row_id', $original->row_id)->exists()) {
            throw new DomainException('Jurnal ini sudah pernah di-reverse.');
        }

        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');

        return DB::connection($connectionName)->transaction(
            function () use ($original, $data, $reversalDate, $reason, $platformUserId): array {
                // 1) Reverse jurnal lama
                $reversal = $this->reversals->reverse(
                    original: $original,
                    reversalDate: $reversalDate,
                    platformUserId: $platformUserId,
                    reason: $reason,
                );

                // 2) Buat draft jurnal baru dari data yang diedit user
                $newDraft = $this->createDraftFromData($data, $platformUserId, $reason, $original);

                // 3) Post jurnal baru (validasi periode, keseimbangan, dll.)
                $posted = $this->posting->post($newDraft, $platformUserId);

                return ['reversal' => $reversal, 'new' => $posted];
            },
            5,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createDraftFromData(
        array $data,
        int $userId,
        string $reason,
        JournalEntry $original,
    ): JournalEntry {
        $isInventory = JournalEntryOptionResolver::isAssetPurchase($data['transaction_type'] ?? null);

        if ($isInventory) {
            $qty = (int) ($data['asset_quantity'] ?? 0);
            $unit = (float) ($data['asset_unit_cost'] ?? 0);
            $data['amount'] = round($qty * $unit, 2);
            $name = trim((string) ($data['asset_name'] ?? ''));
            $userDescription = trim((string) ($data['description'] ?? ''));
            $data['description'] = $userDescription !== ''
                ? sprintf('[Koreksi jurnal #%d] %s', (int) $original->id, $userDescription)
                : sprintf('Pembelian inventaris: %s (%d unit)', $name, $qty);
        } else {
            $userDescription = trim((string) ($data['description'] ?? ''));
            $data['description'] = sprintf('[Koreksi jurnal #%d] %s', (int) $original->id, $userDescription);
        }

        $entry = new JournalEntry;
        $entry->forceFill([
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
        $entry->save();

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => (int) $data['disimpan_ke_row_id'],
            'organization_unit_row_id' => null,
            'description' => $data['description'],
            'debit' => (float) $data['amount'],
            'credit' => 0,
        ]);

        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => (int) $data['sumber_dana_row_id'],
            'organization_unit_row_id' => null,
            'description' => $data['description'],
            'debit' => 0,
            'credit' => (float) $data['amount'],
        ]);

        if ($isInventory) {
            // Daftarkan inventaris baru. Aset lama tetap terkait jurnal lama (sudah di-reverse).
            $asset = $this->assets->create([
                'name' => (string) $data['asset_name'],
                'purchased_at' => $data['transaction_date'],
                'quantity' => (int) $data['asset_quantity'],
                'unit_cost' => (float) $data['asset_unit_cost'],
                'useful_life_months' => (int) $data['asset_useful_life_months'],
                'status' => 'good',
                'category_code' => JournalEntryOptionResolver::ATB_PURCHASE_TYPES[$data['transaction_type']] ?? null,
            ], $userId);

            $entry->forceFill(['source_row_id' => (int) $asset->row_id])->save();
        }

        return $entry->fresh(['lines']) ?? $entry;
    }
}
