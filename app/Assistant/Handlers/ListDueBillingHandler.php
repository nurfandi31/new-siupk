<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class ListDueBillingHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'list_due_billing';
    }

    public function description(): string
    {
        return 'Daftar angsuran yang jatuh tempo pada tanggal tertentu (default hari ini).';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'due_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Y-m-d, default today'],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->listDueBilling($params);
    }
}
