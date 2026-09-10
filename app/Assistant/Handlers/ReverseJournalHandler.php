<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class ReverseJournalHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'reverse_journal';
    }

    public function description(): string
    {
        return 'Batalkan jurnal posted (immutable ledger). Opsional post ulang entri koreksi.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'journal_row_id' => ['type' => 'integer', 'description' => 'ID jurnal yang akan di-reversal; atau gunakan filter lain'],
                'date_from' => ['type' => 'string', 'format' => 'date'],
                'date_to' => ['type' => 'string', 'format' => 'date'],
                'amount' => ['type' => 'number'],
                'account_query' => ['type' => 'string'],
                'wrong_group_query' => ['type' => 'string'],
                'correct_group_query' => ['type' => 'string'],
                'correct_loan_id' => ['type' => 'integer'],
                'reversal_date' => ['type' => 'string', 'format' => 'date'],
                'reason' => ['type' => 'string', 'maxLength' => 500],
                'repost' => ['type' => 'boolean', 'default' => false],
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
        return $this->tools->reverseJournal($params, $actor);
    }
}
