<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class SearchMembersHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'search_members';
    }

    public function description(): string
    {
        return 'Cari anggota koperasi berdasarkan nama/NIK/phone, opsional difilter kelompok.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 2, 'description' => 'Nama/NIK/phone anggota'],
                'group_query' => ['type' => 'string', 'description' => 'Opsional: nama/kode kelompok'],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->searchMembers($params);
    }
}
