<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class GetAssetHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'get_asset';
    }

    public function description(): string
    {
        return 'Detail inventaris: nilai perolehan, akumulasi depresiasi, nilai buku, spesifikasi.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['asset_row_id'],
            'properties' => [
                'asset_row_id' => ['type' => 'integer', 'minimum' => 1],
                'as_of' => ['type' => 'string', 'format' => 'date'],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->getAsset($params);
    }
}
