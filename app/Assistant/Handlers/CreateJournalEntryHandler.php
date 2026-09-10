<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;
use Enpii\Assistant\Contracts\CompensatableToolHandler;
use Enpii\Assistant\Contracts\ToolContext;

final class CreateJournalEntryHandler extends ToolHandlerBase implements CompensatableToolHandler
{
    public function name(): string
    {
        return 'create_journal_entry';
    }

    public function description(): string
    {
        return 'Posting jurnal umum (pembelian inventaris, setor ke bank, dll). Akan preview dulu, butuh confirm.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['transaction_date', 'amount'],
            'properties' => [
                'transaction_date' => ['type' => 'string', 'format' => 'date'],
                'transaction_type' => ['type' => 'string', 'description' => 'Jenis jurnal (lihat daftar di sistem)'],
                'amount' => ['type' => 'number', 'minimum' => 1],
                'description' => ['type' => 'string', 'maxLength' => 500],
                'debit_account_row_id' => ['type' => 'integer'],
                'credit_account_row_id' => ['type' => 'integer'],
                'asset_name' => ['type' => 'string', 'maxLength' => 180],
                'asset_quantity' => ['type' => 'integer', 'minimum' => 1],
                'asset_unit_cost' => ['type' => 'number', 'minimum' => 1],
                'asset_useful_life_months' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1200],
                'confirm' => ['type' => 'boolean'],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->createJournalEntry($params, $actor);
    }

    public function rollback(array $params, array $output, ToolContext $ctx): array
    {
        $actor = $this->resolveActor($ctx);
        $journalRowId = $output['journal_entry_row_id'] ?? $output['row_id'] ?? $output['entry_id'] ?? null;
        if (! $journalRowId) {
            return ['ok' => false, 'error' => 'No journal entry row_id found in execution output to rollback.'];
        }

        return $this->tools->reverseJournal([
            'journal_entry_row_id' => (int) $journalRowId,
            'reason' => 'Rollback otomatis dari asisten AI',
            'confirm' => true,
        ], $actor);
    }
}
