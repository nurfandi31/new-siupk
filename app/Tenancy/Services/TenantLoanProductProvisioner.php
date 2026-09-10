<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Domain\Lending\Models\LoanProduct;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class TenantLoanProductProvisioner
{
    public function ensureDefaults(): void
    {
        $tenantId = app(TenantContext::class)->id();
        DB::connection('tenant')->transaction(function (): void {
            LoanProduct::query()->whereNotIn('code', ['spp', 'uep', 'pl'])->delete();

            $this->ensure(LoanProduct::class, [
                'spp' => [
                    'name' => 'SPP (Simpan Pinjam Khusus Perempuan)',
                    'interest_method' => 'flat',
                    'default_interest_rate' => 1.5000,
                    'default_term_months' => 12,
                    'minimum_amount' => 500000,
                    'maximum_amount' => 50000000,
                    'borrower_scope' => 'group',
                ],
                'uep' => [
                    'name' => 'UEP (Usaha Ekonomi Produktif)',
                    'interest_method' => 'effective',
                    'default_interest_rate' => 1.2000,
                    'default_term_months' => 6,
                    'minimum_amount' => 1000000,
                    'maximum_amount' => 25000000,
                    'borrower_scope' => 'group',
                ],
                'pl' => [
                    'name' => 'PL (Pinjaman Lembaga Lain)',
                    'interest_method' => 'flat',
                    'default_interest_rate' => 0.7500,
                    'default_term_months' => 24,
                    'minimum_amount' => 5000000,
                    'maximum_amount' => 200000000,
                    'borrower_scope' => 'group',
                ],
            ]);
        });
    }

    /**
     * @param  array<string, array<string, mixed>|string>  $defaults
     */
    private function ensure(string $model, array $defaults): void
    {
        foreach ($defaults as $code => $payload) {
            $attributes = is_array($payload) ? $payload : ['name' => $payload];
            $model::query()->updateOrCreate(
                ['code' => $code],
                [...$attributes, 'is_active' => true],
            );
        }
    }
}
