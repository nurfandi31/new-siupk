<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Lending\Models\InstallmentSystem;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kalkulator rencana angsuran pinjaman individu.
 *
 * COPY RUMUS 1:1 dari pacuan (PinjamanIndividuController::generate() line 2317-2607
 * dan PinjamanKelompokController::generate() line 2317-2607 — keduany identik).
 *
 * Tahapan sesuai pacuan:
 *  - Hitung tempo_pokok & tempo_jasa (berdasarkan sistem angsuran dan jangka).
 *  - Hitung jadwal jasa: alokasi_jasa = alokasi_pokok * pros_jasa/100; wajib_jasa = alokasi/tempo.
 *    Tiap kelipatan ke-sistem_pokok bayar wajib_jasa; terakhir bayar selisih (alokasi_jasa - sum).
 *  - Hitung jadwal pokok:
 *      * Default: wajib_pokok = pembulatan(alokasi / tempo_pokok, mode_kec).
 *      * Magic jangka==24: wajib_pokok = pembulatan((alokasi/10 - jasa)/2, -500), kalau alokasi > 1jt pakai 5000;
 *        adjustment ±5000 berdasar alokasi 6/8/12/14/18/20 juta.
 *  - Tanggal: override jadwal_angsuran_desa, batas_angsuran kecamatan (saat cair >= cutoff +1 bulan),
 *    mode mingguan (sistem==12 → +x*7 hari), end-of-month handling.
 *
 * Sliding (effective): angsuran_jasa = wajib_jasa (konstan), dihitung dari sisa pokok.
 */
final class MemberLoanScheduleCalculator
{
    /**
     * Recalculate jadwal angsuran pinjaman.
     *
     * @param  int  $term  Jangka waktu (bulan, atau minggu kalau sa_pokok=12)
     * @param  string  $principalSystem  Sistem angsuran pokok (pacuan: 1/2/3/4/6/12/14/15/20)
     * @param  string  $interestSystem  Sistem angsuran jasa (pacuan: 1/2/3/4/6/11/14/15/20)
     * @param  float  $serviceRateTotal  prosentase jasa total (mis. 24 untuk 24%)
     * @param  string  $interestMethod  'flat' (=1) atau 'effective' (=2 sliding)
     */
    public function recalculate(
        Loan $loan,
        int $term,
        float $principal,
        string $principalSystem,
        string $interestSystem,
        float $serviceRateTotal,
        string $interestMethod,
        CarbonImmutable $disbursementDate,
        int $principalGraceMonths = 0,
        int $interestGraceMonths = 0,
    ): int {
        if ($principal <= 0 || $term <= 0) {
            return 0;
        }

        $loan->loadMissing(['borrower.member.village']);

        $profile = OrganizationProfile::query()->first();
        $roundingMode = (string) ($profile?->installment_rounding ?? '5000');
        $cutoffDay = (int) ($profile?->disbursement_cutoff_day ?? 0);

        // Override tgl jadwal desa — pacuan lihat di anggota->d->jadwal_angsuran_desa
        $villageDay = 0;
        $member = $loan->borrower?->member;
        if ($member !== null) {
            $villageDay = (int) ($this->resolveVillageInstallmentDay($member));
        }

        $tgl = $disbursementDate->toDateString();
        $tanggalCair = (int) $disbursementDate->format('d');

        if ($villageDay > 0) {
            $tgl = $disbursementDate->format('Y-m').'-'.str_pad((string) $villageDay, 2, '0', STR_PAD_LEFT);
        }

        if ($cutoffDay > 0 && $tanggalCair >= $cutoffDay) {
            $tgl = CarbonImmutable::parse($tgl)->addMonth()->toDateString();
        }

        [$adjustedTerm, $saPokok, $rentalRows] = $this->calculateAmounts(
            term: $term,
            principal: $principal,
            principalSystem: $principalSystem,
            interestSystem: $interestSystem,
            serviceRateTotal: $serviceRateTotal,
            interestMethod: $interestMethod,
            principalGraceMonths: $principalGraceMonths,
            interestGraceMonths: $interestGraceMonths,
            roundingMode: $roundingMode,
        );

        // Tahap 3: Tanggal jatuh tempo per angsuran (baris 2511-2547)
        // Pacuan menyimpan sampai `$jangka` SETELAH penambahan 24 periode untuk sistem 11
        // (baris 2507-2509), jadi pakai adjustedTerm juga di loop simpan.
        $persisted = [];
        $targetPokok = 0.0;
        $targetJasa = 0.0;

        // Pacuan menyimpan baris ke-0 (header tanggal) dengan due_date = $tgl hasil override.
        $now = now();
        $headerRow = [
            'installment_number' => 0,
            'due_date' => $tgl,
            'principal_due' => 0,
            'interest_due' => 0,
            'running_principal' => 0,
            'running_interest' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        for ($x = 1; $x <= $adjustedTerm; $x++) {
            if ($saPokok === 12) {
                // Mingguan: tambah $x * 7 hari dari $tgl override
                $due = CarbonImmutable::parse($tgl)->addDays($x * 7)->toDateString();
            } else {
                // Bulanan: pacuan `generate($id, true)` memakai `+$x month` langsung
                // dari tanggal asli (overflow), bukan end-of-month clamp.
                $due = CarbonImmutable::parse($tgl)->addMonths($x)->toDateString();
            }

            $pokok = (float) ($rentalRows[$x]['pokok'] ?? 0);
            $jasa = (float) ($rentalRows[$x]['jasa'] ?? 0);

            $targetPokok = ($x === 1) ? $pokok : ($targetPokok + $pokok);
            $targetJasa = ($x === 1) ? $jasa : ($targetJasa + $jasa);

            $persisted[] = [
                'installment_number' => $x,
                'due_date' => $due,
                'principal_due' => $pokok,
                'interest_due' => $jasa,
                'running_principal' => $targetPokok,
                'running_interest' => $targetJasa,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Hapus jadwal lama dan ganti dengan yang baru (REPLACE — sama dengan pacuan RencanaAngsuranI::where(...)->delete())
        DB::connection('tenant')->transaction(function () use ($loan, $headerRow, $persisted): void {
            LoanInstallment::query()->where('loan_row_id', $loan->row_id)->delete();

            LoanInstallment::query()->create([
                'loan_row_id' => $loan->row_id,
                ...$headerRow,
                'principal_paid' => 0,
                'interest_paid' => 0,
                'penalty_due' => 0,
                'penalty_paid' => 0,
                'status' => 'pending',
                'paid_at' => null,
            ]);

            foreach ($persisted as $row) {
                LoanInstallment::query()->create([
                    'loan_row_id' => $loan->row_id,
                    ...$row,
                    'principal_paid' => 0,
                    'interest_paid' => 0,
                    'penalty_due' => 0,
                    'penalty_paid' => 0,
                    'status' => 'pending',
                    'paid_at' => null,
                ]);
            }
        });

        return count($persisted);
    }

    /**
     * Hitung rencana angsuran tanpa menyimpan ke database.
     *
     * Dipakai dokumen tahap P/V/W/L/R/H. Pada pacuan, `generate($id)` tanpa
     * parameter save memakai cabang tanggal preview: awal bulan + x bulan,
     * lalu di-clamp ke hari terakhir bulan tujuan. Ini berbeda dari baris
     * tersimpan yang memakai overflow tanggal.
     *
     * @return array<int, array{angsuran_ke: int, jatuh_tempo: string, wajib_pokok: float, wajib_jasa: float, target_pokok: float, target_jasa: float}>
     */
    public function preview(
        Loan $loan,
        int $term,
        float $principal,
        string $principalSystem,
        string $interestSystem,
        float $serviceRateTotal,
        string $interestMethod,
        CarbonImmutable $disbursementDate,
        int $principalGraceMonths = 0,
        int $interestGraceMonths = 0,
    ): array {
        if ($principal <= 0 || $term <= 0) {
            return [];
        }

        $loan->loadMissing(['borrower.member.village']);

        $profile = OrganizationProfile::query()->first();
        $roundingMode = (string) ($profile?->installment_rounding ?? '5000');
        $cutoffDay = (int) ($profile?->disbursement_cutoff_day ?? 0);

        $villageDay = 0;
        $member = $loan->borrower?->member;
        if ($member !== null) {
            $villageDay = (int) $this->resolveVillageInstallmentDay($member);
        }

        $tgl = $disbursementDate->toDateString();
        $tanggalCair = (int) $disbursementDate->format('d');

        if ($villageDay > 0) {
            $tgl = $disbursementDate->format('Y-m').'-'.str_pad((string) $villageDay, 2, '0', STR_PAD_LEFT);
        }

        if ($cutoffDay > 0 && $tanggalCair >= $cutoffDay) {
            $tgl = CarbonImmutable::parse($tgl)->addMonth()->toDateString();
        }

        [$adjustedTerm, , $rentalRows] = $this->calculateAmounts(
            term: $term,
            principal: $principal,
            principalSystem: $principalSystem,
            interestSystem: $interestSystem,
            serviceRateTotal: $serviceRateTotal,
            interestMethod: $interestMethod,
            principalGraceMonths: $principalGraceMonths,
            interestGraceMonths: $interestGraceMonths,
            roundingMode: $roundingMode,
        );

        $base = CarbonImmutable::parse($tgl);
        $rows = [];
        $targetPokok = 0.0;
        $targetJasa = 0.0;

        for ($x = 1; $x <= $adjustedTerm; $x++) {
            // Cabang non-save pacuan: `$tanggal_cair = date('Y-m', $tgl)`,
            // lalu `strtotime("+$x month")`, kemudian hari di-clamp ke
            // tanggal akhir bulan. Termasuk untuk sistem mingguan (quirk legacy).
            $candidate = $base->startOfMonth()->addMonths($x);
            $day = min((int) $base->format('d'), (int) $candidate->format('t'));

            $pokok = (float) ($rentalRows[$x]['pokok'] ?? 0);
            $jasa = (float) ($rentalRows[$x]['jasa'] ?? 0);

            $targetPokok = ($x === 1) ? $pokok : ($targetPokok + $pokok);
            $targetJasa = ($x === 1) ? $jasa : ($targetJasa + $jasa);

            $rows[] = [
                'angsuran_ke' => $x,
                'jatuh_tempo' => $candidate->setDay($day)->toDateString(),
                'wajib_pokok' => $pokok,
                'wajib_jasa' => $jasa,
                'target_pokok' => $targetPokok,
                'target_jasa' => $targetJasa,
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: int, 1: int, 2: array<int, array{pokok: float, jasa: float}>}
     */
    private function calculateAmounts(
        int $term,
        float $principal,
        string $principalSystem,
        string $interestSystem,
        float $serviceRateTotal,
        string $interestMethod,
        int $principalGraceMonths,
        int $interestGraceMonths,
        string $roundingMode,
    ): array {
        // Pacuan: kalau sa_pokok/sa_jasa == 11 -> jangka += 24 (mingguan berbatas tertentu)
        $saPokok = $this->normalizeSystemId($principalSystem, $principalGraceMonths, true);
        $saJasa = $this->normalizeSystemId($interestSystem, $interestGraceMonths, false);
        $adjustedTerm = $term;
        if ($saPokok === 11 || $saJasa === 11) {
            $adjustedTerm = $term + 24;
        }

        $sistemPokok = $this->resolveSystemInterval($saPokok, true);
        $sistemJasa = $this->resolveSystemInterval($saJasa, false);

        $tempoPokok = $this->resolveTempo($adjustedTerm, $saPokok, $sistemPokok);
        $tempoJasa = $this->resolveTempo($adjustedTerm, $saJasa, $sistemJasa);

        // Tahap 1: Hitung jasa (mengikuti logika pacuan baris 2417-2445)
        $rentalRows = [];
        $alokasiPokok = $principal;
        for ($j = 1; $j <= $adjustedTerm; $j++) {
            $sisa = $j % $sistemJasa;
            $ke = $j / $sistemJasa;

            $alokasiJasa = $alokasiPokok * ($serviceRateTotal / 100);
            $wajibJasa = $tempoJasa > 0 ? ($alokasiJasa / $tempoJasa) : 0.0;

            // Pembulatan jasa hanya kalau mode kecamatan != '5000' (pacuan baris 2425)
            if ($roundingMode !== '5000') {
                $wajibJasa = $this->pembulatan($wajibJasa, $roundingMode);
            }

            $sumJasa = $wajibJasa * max(0, $tempoJasa - 1);

            if ($sisa == 0 && $ke != $tempoJasa) {
                $angsuranJasa = $wajibJasa;
            } elseif ($sisa == 0 && $ke == $tempoJasa) {
                $angsuranJasa = $alokasiJasa - $sumJasa;
            } else {
                $angsuranJasa = 0;
            }

            // Sliding (jenis_jasa == 2 di pacuan -> 'effective'/'sliding' di Next)
            if ($interestMethod === 'effective') {
                $angsuranJasa = $wajibJasa;
            }

            $rentalRows[$j]['jasa'] = $angsuranJasa;
        }

        // Tahap 2: Hitung pokok (pacuan baris 2447-2489)
        for ($i = 1; $i <= $adjustedTerm; $i++) {
            $sisa = $i % $sistemPokok;
            $ke = $i / $sistemPokok;

            $wajibPokok = ($principal / 10) - ($rentalRows[$i]['jasa'] ?? 0);

            if ($term === 24) {
                $wajibPokok = $this->pembulatan((($principal / 10) - ($rentalRows[$i]['jasa'] ?? 0)) / 2, '-500');

                if ($principal > 1_000_000) {
                    $wajibPokok = $this->pembulatan((($principal / 10) - ($rentalRows[$i]['jasa'] ?? 0)) / 2, '5000');
                }

                if ((int) $principal !== 20_000_000) {
                    if ($principal >= 8_000_000) {
                        $wajibPokok -= 5000;
                    }
                    if ($principal === 12_000_000 || $principal >= 14_000_000) {
                        $wajibPokok -= 5000;
                    }
                    if ($principal === 18_000_000 || $principal === 6_000_000) {
                        $wajibPokok -= 5000;
                    }
                }
            }

            // Pacuan baris 2474: jika pembulatan != '5000' -> pakai pembulatan kecamatan, ABAIKAN magic di atas
            if ($roundingMode !== '5000') {
                $wajibPokok = $this->pembulatan($principal / $tempoPokok, $roundingMode);
            }

            $sumPokok = $wajibPokok * max(0, $tempoPokok - 1);

            if ($sisa == 0 && $ke != $tempoPokok) {
                $angsuranPokok = $wajibPokok;
            } elseif ($sisa == 0 && $ke == $tempoPokok) {
                $angsuranPokok = $principal - $sumPokok;
            } else {
                $angsuranPokok = 0;
            }

            $rentalRows[$i]['pokok'] = $angsuranPokok;
        }

        return [$adjustedTerm, $saPokok, $rentalRows];
    }

    /**
     * Tentukan tempo (jumlah periode angsuran efektif) sesuai pacuan baris 2514-2536.
     */
    private function resolveTempo(int $adjustedTerm, int $sa, int $sistem): int
    {
        $sa = (int) $sa;
        $sistem = max(1, $sistem);

        // Pacuan memakai ekspresi `($jangka) - 24 / $sistem_pokok`.
        // Operator `/` lebih tinggi dari `-`, jadi 24 dibagi dulu, bukan (jangka - 24).
        $tempo = match ($sa) {
            11 => $adjustedTerm - (24 / $sistem),
            14 => $adjustedTerm - (3 / $sistem),
            15 => $adjustedTerm - (2 / $sistem),
            20 => $adjustedTerm - (12 / $sistem),
            default => floor($adjustedTerm / $sistem),
        };

        return max(1, (int) $tempo);
    }

    /**
     * Normalisasi sistem angsuran. Nilai numerik dianggap legacy id; string
     * frekuensi Next dipetakan ke legacy id yang tepat (memperhatikan grace).
     */
    private function normalizeSystemId(string $value, int $graceMonths, bool $principal): int
    {
        $value = trim($value);

        if ($value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        $grace = max(0, $graceMonths);

        if ($value === 'weekly' || $value === 'biweekly') {
            return 12;
        }

        if ($value === 'monthly') {
            return match ($grace) {
                24 => 11, // M24
                12 => 20, // M12
                6 => 26,  // M6
                3 => 14,  // M3
                2 => 15,  // M2
                1 => 25,  // M1
                default => 1,
            };
        }

        return match ($value) {
            'bimonthly' => 9,        // 2 Bulan
            'quarterly' => 2,        // 3 Bulan
            'every_4_months' => 3,   // 4 Bulan
            'every_5_months' => 6,   // 5 Bulan
            'every_6_months' => 4,   // 6 Bulan
            'every_7_months' => 7,   // 7 Bulan
            'every_8_months' => 8,   // 8 Bulan
            'every_9_months' => 13,  // 9 Bulan
            'every_10_months' => 21, // 10 Bulan
            'every_11_months' => 24, // 11 Bulan
            'every_12_months' => 10, // 12 Bulan
            'every_24_months' => 22, // 24 Bulan
            'every_36_months' => 23, // 36 Bulan
            'at_maturity' => 22,     // 24 Bulan (fallback)
            default => 1,
        };
    }

    /**
     * Map sistem angsuran id ke interval (dalam bulan) dari tabel installment_systems.
     * Principal dan interest bisa punya interval berbeda (mis. legacy id 16).
     */
    private function resolveSystemInterval(int $sa, bool $principal): int
    {
        $system = InstallmentSystem::query()->where('legacy_id', $sa)->first();
        if ($system !== null) {
            $interval = $principal
                ? ($system->principal_interval_months ?: $system->interval_months)
                : ($system->interest_interval_months ?: $system->interval_months);

            return max(1, (int) $interval);
        }

        return match ($sa) {
            2 => 3,        // 3 Bulan
            3 => 4,        // 4 Bulan
            4 => 6,        // 6 Bulan
            6 => 5,        // 5 Bulan
            7 => 7,
            8 => 8,
            9 => 2,        // 2 Bulan
            10 => 12,      // 12 Bulan
            13 => 9,       // 9 Bulan
            16 => $principal ? 24 : 1,
            21 => 10,      // 10 Bulan
            22 => 24,      // 24 Bulan
            23 => 36,      // 36 Bulan
            24 => 11,      // 11 Bulan
            default => 1,  // 1, 5, 11, 12, 14, 15, 20, 25, 26
        };
    }

    /**
     * Cari jadwal angsuran desa dari anggota (pacuan: $pinj_i->anggota->d->jadwal_angsuran_desa).
     * Karena struktur Next belum punya relasi tsb, fallback ke profile tenant.
     */
    private function resolveVillageInstallmentDay(Member $member): int
    {
        // Pacuan memakai `anggota->d->jadwal_angsuran_desa` (per desa).
        $schedule = trim((string) ($member->village?->installment_schedule ?? ''));
        if (ctype_digit($schedule)) {
            $day = (int) $schedule;
            if ($day >= 1 && $day <= 31) {
                return $day;
            }
        }

        $profile = OrganizationProfile::query()->first();

        return max(0, (int) ($profile?->village_installment_day ?? 0));
    }

    /**
     * Pembulatan ala Keuangan::pembulatan pacuan (baris 34-75).
     *
     * Mode:
     *  '5000' (auto) — < 2500 → turun, >= 2500 → naik ke 5000
     *  '+5000' selalu ke atas
     *  '-5000' selalu ke bawah
     *  numeric lain — perlakukan auto
     */
    private function pembulatan(float $value, string $mode): float
    {
        $value = (float) round($value);

        $sistem = 'auto';
        $pembulatan = $mode;

        if (str_starts_with($mode, '+')) {
            $sistem = 'keatas';
            $pembulatan = (string) ((int) $mode);
        }
        if (str_starts_with($mode, '-')) {
            $sistem = 'kebawah';
            $pembulatan = (string) abs((int) $mode);
        }

        $pembulatanInt = (int) $pembulatan;
        if ($pembulatanInt <= 0) {
            return $value;
        }

        // Pacuan: $ratusan = substr($angka, -strlen($pembulatan / 2)) — strlen dari
        // representasi angka pembulatan/2, bukan nilai pembulatan/2 itu sendiri.
        $divisorLen = strlen((string) ($pembulatanInt / 2));
        $divisorLen = max(1, $divisorLen);

        // Hitung ratusan / pecahan
        $digitCount = strlen((string) $value);
        if ($digitCount < $divisorLen) {
            $ratusan = (int) $value;
        } else {
            $ratusan = (int) substr((string) $value, -$divisorLen);
        }

        $nilaiTengah = $pembulatanInt / 2;

        return match ($sistem) {
            'keatas' => $value + ($pembulatanInt - $ratusan),
            'kebawah' => $value - $ratusan,
            default => $ratusan <= $nilaiTengah
                ? $value - $ratusan
                : $value + ($pembulatanInt - $ratusan),
        };
    }
}
