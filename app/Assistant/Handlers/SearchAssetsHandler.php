<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class SearchAssetsHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'search_assets';
    }

    public function description(): string
    {
        return 'Cari inventaris/aset tetap berdasarkan nama atau kode. Mengembalikan nilai buku & depresiasi.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 2],
                'status' => ['type' => 'string', 'description' => 'good/damaged/disposed'],
                'as_of' => ['type' => 'string', 'format' => 'date'],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->searchAssets($params);
    }
}
