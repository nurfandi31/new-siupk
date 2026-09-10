<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\LoanService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidLoanSchedule implements DataAwareRule, ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $frequencyKey = str_contains($attribute, 'interest')
            ? 'interest_frequency'
            : 'principal_frequency';

        $routeLoan = request()->route('loan');
        $loan = $routeLoan instanceof Loan
            ? $routeLoan
            : (is_numeric($routeLoan) ? Loan::query()->find((int) $routeLoan) : null);

        $frequency = (string) data_get($this->data, $frequencyKey, $loan?->{$frequencyKey} ?? 'monthly');
        $interval = LoanService::intervalMonths($frequency);
        if ($interval === 0) {
            return;
        }

        $termMonths = (int) data_get($this->data, 'term_months', $loan?->term_months ?? 0);
        if ($termMonths <= 0) {
            return;
        }

        $grace = max(0, (int) $value);
        if ($grace + $interval > $termMonths) {
            $fail('Interval angsuran tidak boleh melebihi jangka waktu setelah grace period.');
        }
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    private array $data = [];
}
