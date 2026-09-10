<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class SearchLoansHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'search_loans';
    }

    public function description(): string
    {
        return 'Cari pinjaman berdasarkan kelompok/anggota/nomor pinjaman. Default hanya pinjam workable (active/disbursed/ongoing/approved/funded).';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_query' => ['type' => 'string'],
                'member_query' => ['type' => 'string'],
                'loan_number' => ['type' => 'string'],
                'loan_row_id' => ['type' => 'integer'],
                'status' => ['type' => 'string', 'description' => 'Filter status spesifik; kosong = workable'],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->searchLoans($params);
    }
}
