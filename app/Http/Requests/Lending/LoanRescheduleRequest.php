<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Enums\Frequency;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Rules\ValidLoanSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanRescheduleRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'rescheduled_at' => ['required', 'date', 'before_or_equal:today'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'service_rate_total' => ['required', 'numeric', 'min:0', 'max:100'],
            'installment_method' => ['required', Rule::in(['flat', 'annuity', 'effective'])],
            'principal_frequency' => ['required', 'string', Rule::in(Frequency::values())],
            'interest_frequency' => ['required', 'string', Rule::in(Frequency::values())],
            'principal_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
            'interest_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
        ];
    }

    public function attributes(): array
    {
        return [
            'rescheduled_at' => 'tanggal reschedule',
            'term_months' => 'jangka waktu',
            'service_rate_total' => 'prosentase jasa',
            'installment_method' => 'metode hitung jasa',
            'principal_frequency' => 'frekuensi pokok',
            'interest_frequency' => 'frekuensi jasa',
            'principal_grace_months' => 'grace period pokok',
            'interest_grace_months' => 'grace period jasa',
        ];
    }
}
