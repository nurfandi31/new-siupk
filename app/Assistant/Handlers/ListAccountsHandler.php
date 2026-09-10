<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class ListAccountsHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'list_accounts';
    }

    public function description(): string
    {
        return 'Daftar akun postable dari COA. Filter by code_prefix, cash_only, atau query nama.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'code_prefix' => ['type' => 'string'],
                'cash_only' => ['type' => 'boolean', 'default' => false],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->listAccounts($params);
    }
}
