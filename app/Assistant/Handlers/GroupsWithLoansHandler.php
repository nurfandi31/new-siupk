<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class GroupsWithLoansHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'groups_with_loans';
    }

    public function description(): string
    {
        return 'Daftar kelompok yang punya pinjaman aktif + ringkasan outstanding principal.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Opsional: filter nama/kode kelompok'],
                'include_inactive_groups' => ['type' => 'boolean', 'default' => true],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->groupsWithLoans($params);
    }
}
