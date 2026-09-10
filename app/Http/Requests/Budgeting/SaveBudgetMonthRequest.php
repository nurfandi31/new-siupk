<?php

declare(strict_types=1);

namespace App\Http\Requests\Budgeting;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class SaveBudgetMonthRequest extends FormRequest
{
    use AuthorizesPermission;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amounts' => 'rencana anggaran',
            'amounts.*' => 'nominal',
        ];
    }
}
