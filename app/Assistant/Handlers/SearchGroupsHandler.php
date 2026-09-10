<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class SearchGroupsHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'search_groups';
    }

    public function description(): string
    {
        return 'Cari kelompok koperasi berdasarkan nama atau kode.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 2],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->searchGroups($params);
    }
}
