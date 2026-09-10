<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class SearchJournalsHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'search_journals';
    }

    public function description(): string
    {
        return 'Cari jurnal posted untuk koreksi / cek duplikat. Filter tanggal, nominal, akun, deskripsi.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'journal_row_id' => ['type' => 'integer'],
                'date_from' => ['type' => 'string', 'format' => 'date'],
                'date_to' => ['type' => 'string', 'format' => 'date'],
                'transaction_type' => ['type' => 'string'],
                'source_type' => ['type' => 'string'],
                'amount' => ['type' => 'number'],
                'account_query' => ['type' => 'string'],
                'group_query' => ['type' => 'string'],
                'query' => ['type' => 'string', 'description' => 'Cari di deskripsi'],
                'recent' => ['type' => 'boolean', 'default' => false, 'description' => 'Default 48 jam terakhir'],
                'limit' => ['type' => 'integer', 'maximum' => 30, 'default' => 15],
                'exclude_reversed' => ['type' => 'boolean', 'default' => true],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->searchJournals($params);
    }
}
