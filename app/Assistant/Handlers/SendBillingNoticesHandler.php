<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class SendBillingNoticesHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'send_billing_notices';
    }

    public function description(): string
    {
        return 'Kirim notifikasi WA tagihan untuk daftar angsuran yang akan jatuh tempo. Preview dulu.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['due_date', 'installment_row_ids'],
            'properties' => [
                'due_date' => ['type' => 'string', 'format' => 'date'],
                'installment_row_ids' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => ['type' => 'integer', 'minimum' => 1],
                ],
                'confirm' => ['type' => 'boolean'],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->sendBillingNotices($params);
    }
}
