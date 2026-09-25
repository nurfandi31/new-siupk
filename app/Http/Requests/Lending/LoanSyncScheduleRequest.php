<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest untuk sinkronisasi jadwal angsuran (regenerate installments
 * dari parameter loan yang sekarang). Hanya untuk status draft/verified.
 *
 * Mirror SIUPK original `sinkronisasiKelompok`/`sinkronisasiIndividu` AJAX
 * button yang regenerate RencanaAngsuran.
 */
final class LoanSyncScheduleRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
