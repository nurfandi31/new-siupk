<?php

declare(strict_types=1);

namespace App\Http\Requests\Assets;

use App\Domain\Assets\Models\Asset;
use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssetRequest extends FormRequest
{
    use AuthorizesPermission;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'asset_code' => ['nullable', 'string', 'max:80'],
            'asset_category_row_id' => ['nullable', 'integer'],
            'organization_unit_row_id' => ['nullable', 'integer'],
            'purchased_at' => ['nullable', 'date'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:0', 'max:1200'],
            'status' => ['required', Rule::in(array_keys(Asset::STATUSES))],
            'validated_at' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
