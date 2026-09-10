<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Enums\Frequency;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Rules\ValidLoanSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanVerifyRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'verified_at' => ['required', 'date', 'before_or_equal:today'],
            'verification_amount' => ['nullable', 'numeric', 'min:0'],
            'verification_notes' => ['nullable', 'string', 'min:3', 'max:5000'],
            'verified_amounts' => ['nullable', 'array'],
            'verified_amounts.*' => ['numeric', 'min:0'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'service_rate_total' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'principal_frequency' => ['nullable', 'string', Rule::enum(Frequency::class)],
            'interest_frequency' => ['nullable', 'string', Rule::enum(Frequency::class)],
            'principal_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
            'interest_grace_months' => ['nullable', 'integer', 'min:0', 'max:120', new ValidLoanSchedule],
        ];
    }

    public function attributes(): array
    {
        return [
            'verified_at' => 'tanggal verifikasi',
            'verification_amount' => 'nominal verifikasi',
            'verification_notes' => 'catatan verifikasi',
            'verified_amounts' => 'nominal verifikasi per pemanfaat',
            'verified_amounts.*' => 'nominal verifikasi per pemanfaat',
            'term_months' => 'jangka waktu',
            'service_rate_total' => 'prosentase jasa',
            'principal_frequency' => 'sistem angsuran pokok',
            'interest_frequency' => 'sistem angsuran jasa',
            'principal_grace_months' => 'grace period pokok',
            'interest_grace_months' => 'grace period jasa',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'term_months' => $this->input('term_months') ?? $this->route('loan')?->term_months,
            'principal_frequency' => $this->input('principal_frequency') ?? $this->route('loan')?->principal_frequency,
            'interest_frequency' => $this->input('interest_frequency') ?? $this->route('loan')?->interest_frequency,
        ]);
    }
}
