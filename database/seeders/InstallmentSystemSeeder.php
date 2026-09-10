<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Lending\Models\InstallmentSystem;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

final class InstallmentSystemSeeder extends Seeder
{
    /**
     * @return list<array{legacy_id: int, name: string, description: string, interval_months: int, sort_order: int, principal_grace_months: int, interest_grace_months: int, principal_interval_months: int, interest_interval_months: int}>
     */
    public static function systems(): array
    {
        return [
            ['legacy_id' => 1, 'name' => 'Bulanan', 'description' => 'satu kali dalam sebulan', 'interval_months' => 1, 'sort_order' => 1, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 2, 'name' => '3 Bulan', 'description' => 'satu kali dalam tiga bulan', 'interval_months' => 3, 'sort_order' => 2, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 3, 'interest_interval_months' => 3],
            ['legacy_id' => 3, 'name' => '4 Bulan', 'description' => 'satu kali dalam empat bulan', 'interval_months' => 4, 'sort_order' => 3, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 4, 'interest_interval_months' => 4],
            ['legacy_id' => 4, 'name' => '6 Bulan', 'description' => 'satu kali dalam enam bulan', 'interval_months' => 6, 'sort_order' => 4, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 6, 'interest_interval_months' => 6],
            ['legacy_id' => 5, 'name' => 'Musiman', 'description' => 'sesuai pola pembayaran', 'interval_months' => 1, 'sort_order' => 5, 'principal_grace_months' => 2, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 6, 'name' => '5 Bulan', 'description' => 'Satu kali dalam lima bulan', 'interval_months' => 5, 'sort_order' => 6, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 5, 'interest_interval_months' => 5],
            ['legacy_id' => 7, 'name' => '7 Bulan', 'description' => 'Satu kali dalam tujuh bulan', 'interval_months' => 7, 'sort_order' => 7, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 7, 'interest_interval_months' => 7],
            ['legacy_id' => 8, 'name' => '8 Bulan', 'description' => 'Satu kali dalam delapan bulan', 'interval_months' => 8, 'sort_order' => 8, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 8, 'interest_interval_months' => 8],
            ['legacy_id' => 9, 'name' => '2 Bulan', 'description' => 'Satu kali dalam dua bulan', 'interval_months' => 2, 'sort_order' => 9, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 2, 'interest_interval_months' => 2],
            ['legacy_id' => 10, 'name' => '12 Bulan', 'description' => 'Satu kali dalam satu Tahun', 'interval_months' => 12, 'sort_order' => 10, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 12, 'interest_interval_months' => 12],
            ['legacy_id' => 11, 'name' => 'M24', 'description' => 'Satu kali dalam satu bulan setelah 2 tahun', 'interval_months' => 1, 'sort_order' => 11, 'principal_grace_months' => 24, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 12, 'name' => 'Mingguan', 'description' => 'satu minggu sekali', 'interval_months' => 1, 'sort_order' => 12, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 13, 'name' => '9 Bulan', 'description' => 'Satu kali dalam sembilan bulan', 'interval_months' => 9, 'sort_order' => 13, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 9, 'interest_interval_months' => 9],
            ['legacy_id' => 14, 'name' => 'M3', 'description' => 'Pokok ditunda 3 bulan setelah cair', 'interval_months' => 1, 'sort_order' => 14, 'principal_grace_months' => 3, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 15, 'name' => 'M2', 'description' => 'Pokok ditunda 2 bulan setelah cair', 'interval_months' => 1, 'sort_order' => 15, 'principal_grace_months' => 2, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 16, 'name' => '24 Bulan', 'description' => 'Pokok 24 bulan sekali', 'interval_months' => 24, 'sort_order' => 16, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 24, 'interest_interval_months' => 1],
            ['legacy_id' => 20, 'name' => 'M12', 'description' => 'Pokok ditunda 12 bulan setelah cair', 'interval_months' => 1, 'sort_order' => 17, 'principal_grace_months' => 12, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 21, 'name' => '10 Bulan', 'description' => 'Satu kali dalam 10 bulan', 'interval_months' => 10, 'sort_order' => 18, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 10, 'interest_interval_months' => 10],
            ['legacy_id' => 22, 'name' => '24 Bulan', 'description' => 'Satu kali dalam Dua Tahun', 'interval_months' => 24, 'sort_order' => 19, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 24, 'interest_interval_months' => 24],
            ['legacy_id' => 23, 'name' => '36 Bulan', 'description' => 'Satu kali dalam Tiga Tahun', 'interval_months' => 36, 'sort_order' => 20, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 36, 'interest_interval_months' => 36],
            ['legacy_id' => 24, 'name' => '11 Bulan', 'description' => 'Satu kali dalam 11 bulan', 'interval_months' => 11, 'sort_order' => 21, 'principal_grace_months' => 0, 'interest_grace_months' => 0, 'principal_interval_months' => 11, 'interest_interval_months' => 11],
            ['legacy_id' => 25, 'name' => 'M1', 'description' => 'Angsuran ditunda 1 bulan setelah cair', 'interval_months' => 1, 'sort_order' => 22, 'principal_grace_months' => 1, 'interest_grace_months' => 1, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
            ['legacy_id' => 26, 'name' => 'M6', 'description' => 'Pokok ditunda 6 bulan setelah cair', 'interval_months' => 1, 'sort_order' => 23, 'principal_grace_months' => 6, 'interest_grace_months' => 0, 'principal_interval_months' => 1, 'interest_interval_months' => 1],
        ];
    }

    public function run(): void
    {
        foreach (self::systems() as $system) {
            InstallmentSystem::query()->updateOrCreate([
                'tenant_id' => app(TenantContext::class)->id(),
                'legacy_id' => $system['legacy_id'],
            ], $system);
        }
    }
}
