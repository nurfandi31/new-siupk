<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class SimulateLoanHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'simulate_loan';
    }

    public function description(): string
    {
        return 'Menghitung simulasi perhitungan angsuran pinjaman (Flat, Efektif Menurun, atau Anuitas) dan menghasilkan tombol download jadwal simulasi PDF.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'principal_amount' => [
                    'type' => 'number',
                    'description' => 'Plafon pinjaman (minimal 100.000). Contoh: 10000000',
                ],
                'term_months' => [
                    'type' => 'integer',
                    'description' => 'Jangka waktu pinjaman dalam bulan (1 - 120). Contoh: 12',
                ],
                'interest_rate' => [
                    'type' => 'number',
                    'description' => 'Suku bunga tahunan dalam persen. Contoh: 12.0 (12%)',
                ],
                'installment_method' => [
                    'type' => 'string',
                    'enum' => ['flat', 'declining', 'annuity'],
                    'description' => 'Metode angsuran: flat (tetap), declining (menurun), atau annuity (anuitas). Default flat.',
                ],
                'principal_frequency' => [
                    'type' => 'string',
                    'enum' => ['monthly', 'quarterly', 'semi_annually', 'annually', 'at_maturity'],
                    'description' => 'Frekuensi angsuran pokok. Default monthly.',
                ],
                'interest_frequency' => [
                    'type' => 'string',
                    'enum' => ['monthly', 'quarterly', 'semi_annually', 'annually', 'at_maturity'],
                    'description' => 'Frekuensi pembayaran bunga/jasa. Default monthly.',
                ],
                'rounding_step' => [
                    'type' => 'integer',
                    'minimum' => 500,
                    'description' => 'Langkah pembulatan angsuran (minimal 500, contoh: 500, 1000, 5000, 10000). Default 500.',
                ],
                'borrower_name' => [
                    'type' => 'string',
                    'description' => 'Nama calon peminjam atau kelompok.',
                ],
                'product_code' => [
                    'type' => 'string',
                    'description' => 'Kode template produk kredit (contoh: spp, uep, pl).',
                ],
                'start_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Tanggal mulai atau estimasi pencairan (YYYY-MM-DD).',
                ],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->simulateLoan($params);
    }
}
