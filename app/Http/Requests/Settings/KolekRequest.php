<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Domain\Lending\Services\CollectibilityConfigService;
use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form kolektabilitas 5 tingkat.
 * Mirror `SopController::kolek()` di SIUPK original (lines 686-721).
 *
 * Field dikirim sebagai `nama_kolek1..5`, `pros_kolek1..5`, `durasi1..5`, `satuan1..5`.
 * `null` diizinkan untuk semua field (tingkat nonaktif → nama kosong).
 */
final class KolekRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $rules = [];
        for ($i = 1; $i <= CollectibilityConfigService::MAX_LEVELS; $i++) {
            $rules["nama_kolek{$i}"] = ['nullable', 'string', 'max:80'];
            $rules["pros_kolek{$i}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $rules["durasi{$i}"] = ['nullable', 'numeric', 'min:0', 'max:999'];
            $rules["satuan{$i}"] = ['nullable', 'string', 'in:hari,bulan'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $out = [];
        for ($i = 1; $i <= CollectibilityConfigService::MAX_LEVELS; $i++) {
            $out["nama_kolek{$i}"] = "nama kolek tingkat {$i}";
            $out["pros_kolek{$i}"] = "prosentase kolek tingkat {$i}";
            $out["durasi{$i}"] = "durasi kolek tingkat {$i}";
            $out["satuan{$i}"] = "satuan kolek tingkat {$i}";
        }

        return $out;
    }

    /**
     * Ambil array terstruktur 5 slot (format identik dengan yang disimpan di
     * `OrganizationProfile.collectibility_rules` JSON).
     *
     * @return list<array{nama:string, prosentase:string, durasi:string, satuan:string}>
     */
    public function kolekRows(): array
    {
        $rows = [];
        for ($i = 1; $i <= CollectibilityConfigService::MAX_LEVELS; $i++) {
            $rows[] = [
                'nama' => (string) ($this->input("nama_kolek{$i}") ?? ''),
                'prosentase' => (string) ($this->input("pros_kolek{$i}") ?? ''),
                'durasi' => (string) ($this->input("durasi{$i}") ?? ''),
                'satuan' => (string) ($this->input("satuan{$i}", 'bulan') ?: 'bulan'),
            ];
        }

        return $rows;
    }
}
