<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class GetLoanHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'get_loan';
    }

    public function description(): string
    {
        return 'Detail lengkap satu pinjaman: kelompok, anggota, angsuran tersisa, jadwal.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['loan_row_id'],
            'properties' => [
                'loan_row_id' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->getLoan($params);
    }
}
