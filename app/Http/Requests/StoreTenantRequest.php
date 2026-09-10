<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\RegencyGeoService;
use App\Services\RegionalCodeApi;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'province_code' => ['required', 'digits:2'],
            'regency_code' => ['required', 'digits:4'],
            'district_code' => ['required', 'digits:6', Rule::unique('tenants', 'district_code')],
            'map_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'map_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'map_zoom' => ['nullable', 'integer', 'between:3,19'],
            'user_name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $regencies = app(RegionalCodeApi::class)->regencies((string) $this->string('province_code'));
                $regency = collect($regencies)->firstWhere('code', (string) $this->string('regency_code'));
                if ($regency === null) {
                    $validator->errors()->add('regency_code', 'Kabupaten/kota tidak sesuai dengan provinsi.');

                    return;
                }

                $districts = app(RegionalCodeApi::class)->districts((string) $this->string('regency_code'));
                if (collect($districts)->firstWhere('code', (string) $this->string('district_code')) === null) {
                    $validator->errors()->add('district_code', 'Kecamatan tidak sesuai dengan kabupaten/kota.');
                }
            } catch (\Throwable) {
                $validator->errors()->add('district_code', 'Data wilayah tidak dapat diverifikasi.');
            }

            RegencyGeoService::validateWithinRegency(
                $this->string('regency_code'),
                $this->input('map_latitude'),
                $this->input('map_longitude'),
                $validator,
            );
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama tenant',
            'map_latitude' => 'latitude peta',
            'map_longitude' => 'longitude peta',
            'map_zoom' => 'zoom peta',
            'user_name' => 'nama pengguna pertama',
            'username' => 'nama pengguna',
            'email' => 'surel',
            'password' => 'kata sandi',
            'district_code' => 'kecamatan',
            'regency_code' => 'kabupaten/kota',
            'province_code' => 'provinsi',
        ];
    }
}
