<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->getKey();

        return [
            'username' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($userId, 'row_id'),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required_with:password'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'username',
            'password' => 'password',
            'password_confirmation' => 'konfirmasi password',
        ];
    }
}
