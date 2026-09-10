<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class RecordInstallmentHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'record_installment';
    }

    public function description(): string
    {
        return 'Catat pembayaran angsuran: resolve loan + anggota penyetor + kas otomatis. Akan preview dulu.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['total_amount'],
            'properties' => [
                'loan_row_id' => ['type' => 'integer', 'description' => 'Opsional — bisa resolve dari group/loan_number'],
                'group_query' => ['type' => 'string'],
                'member_query' => ['type' => 'string', 'description' => 'Penyetor (bukan borrower)'],
                'loan_number' => ['type' => 'string'],
                'transaction_date' => ['type' => 'string', 'format' => 'date'],
                'total_amount' => ['type' => 'number', 'minimum' => 1, 'description' => 'Total bayar; split principal/interest dari jadwal'],
                'principal_amount' => ['type' => 'number'],
                'interest_amount' => ['type' => 'number'],
                'penalty_amount' => ['type' => 'number'],
                'cash_account_row_id' => ['type' => 'integer'],
                'cash_account_query' => ['type' => 'string'],
                'installment_number' => ['type' => 'integer'],
                'allocation_choice' => ['type' => 'string', 'description' => 'Untuk overpayment: apply_excess_to_principal / cap_to_due / cancel'],
                'description' => ['type' => 'string', 'maxLength' => 500],
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
        return $this->tools->recordInstallment($params, $actor);
    }
}
