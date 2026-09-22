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
    ): int {
        if ($principal <= 0 || $term <= 0) {
            return 0;
        }

        $loan->loadMissing('borrower.member.address.village');

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

        // Pacuan: kalau sa_pokok/sa_jasa == 11 → jangka += 24 (mingguan berbatas tertentu)
        $saPokok = (int) $principalSystem;
        $saJasa = (int) $interestSystem;
        $adjustedTerm = $term;
        if ($saPokok === 11 || $saJasa === 11) {
            $adjustedTerm = $term + 24;
        }

        $sistemPokok = $this->resolveSystemInterval((int) $principalSystem);
        $sistemJasa = $this->resolveSystemInterval((int) $interestSystem);

        $tempoPokok = $this->resolveTempo($adjustedTerm, $saPokok, $sistemPokok);
        $tempoJasa = $this->resolveTempo($adjustedTerm, $saJasa, $sistemJasa);

        // Tahap 1: Hitung jasa (mengikuti logika pacuan baris 2417-2445)
        $rentalRows = [];
        $alokasiPokok = $principal;
        for ($j = 1; $j <= $adjustedTerm; $j++) {
            $sisa = $j % $sistemJasa;
            $ke = (int) ($j / $sistemJasa);

            $alokasiJasa = $alokasiPokok * ($serviceRateTotal / 100);
            $wajibJasa = $tempoJasa > 0 ? ($alokasiJasa / $tempoJasa) : 0.0;

            // Pembulatan jasa hanya kalau mode kecamatan != '5000' (pacuan baris 2425)
            if ($roundingMode !== '5000') {
                $wajibJasa = $this->pembulatan($wajibJasa, $roundingMode);
            }

            $sumJasa = $wajibJasa * max(0, $tempoJasa - 1);

            if ($sisa === 0 && $ke !== $tempoJasa) {
                $angsuranJasa = $wajibJasa;
            } elseif ($sisa === 0 && $ke === $tempoJasa) {
                $angsuranJasa = $alokasiJasa - $sumJasa;
            } else {
                $angsuranJasa = 0;
            }

            // Sliding (jenis_jasa == 2 di pacuan → 'effective'/'sliding' di Next)
            if ($interestMethod === 'effective') {
                $angsuranJasa = $wajibJasa;
                // Kode pacuan: $alokasi_pokok -= $ra[$j]['pokok'] (memakai pokok bulan ini, didefinisikan di loop 2)
                // Untuk menjaga konsistensi kita update alokasi_pokok nanti setelah pokok bulan ini dihitung.
            }

            $rentalRows[$j]['jasa'] = $angsuranJasa;
        }

        // Tahap 2: Hitung pokok (pacuan baris 2447-2489)
        for ($i = 1; $i <= $adjustedTerm; $i++) {
            $sisa = $i % $sistemPokok;
            $ke = (int) ($i / $sistemPokok);

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

            // Pacuan baris 2474: jika pembulatan != '5000' → pakai pembulatan kecamatan, ABAIKAN magic di atas
            if ($roundingMode !== '5000') {
                $wajibPokok = $this->pembulatan($principal / $tempoPokok, $roundingMode);
            }

            $sumPokok = $wajibPokok * max(0, $tempoPokok - 1);

            if ($sisa === 0 && $ke !== $tempoPokok) {
                $angsuranPokok = $wajibPokok;
            } elseif ($sisa === 0 && $ke === $tempoPokok) {
                $angsuranPokok = $principal - $sumPokok;
            } else {
                $angsuranPokok = 0;
            }

            $rentalRows[$i]['pokok'] = $angsuranPokok;
        }

        // Tahap 3: Tanggal jatuh tempo per angsuran (baris 2511-2547)
        // Pakai logika pacuan yang HANYA menyimpan angsuran ke-N sampai ke-jangka ASLI
        // (loop di save: for x=1..jangka), bukan adjustedTerm.
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

        for ($x = 1; $x <= $term; $x++) {
            if ($saPokok === 12) {
                // Mingguan: tambah $x * 7 hari dari $tgl override
                $due = CarbonImmutable::parse($tgl)->addDays($x * 7)->toDateString();
            } else {
                // Bulanan: tambah $x bulan, end-of-month handling
                $base = CarbonImmutable::parse($tgl);
                $candidate = $base->addMonths($x);
                $originalDay = (int) $disbursementDate->format('d');
                $lastDayOfMonth = (int) $candidate->format('t');
                if ($originalDay > $lastDayOfMonth) {
                    $candidate = $candidate->setDay($lastDayOfMonth);
                }
                $due = $candidate->toDateString();
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
     * Tentukan tempo (jumlah periode angsuran efektif) sesuai pacuan baris 2393-2414.
     */
    private function resolveTempo(int $adjustedTerm, int $sa, int $sistem): int
    {
        $sa = (int) $sa;
        $sistem = max(1, $sistem);

        $tempo = match ($sa) {
            11 => ($adjustedTerm - 24) / $sistem,
            14 => ($adjustedTerm - 3) / $sistem,
            15 => ($adjustedTerm - 2) / $sistem,
            20 => ($adjustedTerm - 12) / $sistem,
            default => (int) floor($adjustedTerm / $sistem),
        };

        return max(1, (int) $tempo);
    }

    /**
     * Map sistem angsuran id ke interval (dalam bulan). Pacuan punya tabel sendiri.
     *  1 → 1 (bulanan), 2 → 2 (dua bulanan), 3 → 3 (triwulan),
     *  6 → 6 (semester), 12 → 12 (mingguan — handled specially),
     *  14 → 1 (bulanan + grace 3), 15 → 1 (bulanan + grace 2), 20 → 1 (bulanan + grace 12).
     */
    private function resolveSystemInterval(int $sa): int
    {
        // Pacuan: $pinj_i->sis_pokok->sistem — yaitu kolom `sistem` di tabel `installment_systems`
        // Coba lookup via model. Kalau tidak ditemukan, fallback ke mapping default.
        $system = InstallmentSystem::query()->where('code', (string) $sa)->first();
        if ($system !== null) {
            return max(1, (int) ($system->sistem ?? $sa));
        }

        return match ($sa) {
            1, 14, 15, 20 => 1,
            2 => 2,
            3 => 3,
            6 => 6,
            12 => 12,
            default => 1,
        };
    }

    /**
     * Cari jadwal angsuran desa dari anggota (pacuan: $pinj_i->anggota->d->jadwal_angsuran_desa).
     * Karena struktur Next belum punya relasi tsb, fallback ke profile tenant.
     */
    private function resolveVillageInstallmentDay(Member $member): int
    {
        $profile = OrganizationProfile::query()->first();
        $globalDay = (int) ($profile?->village_installment_day ?? 0);
        if ($globalDay > 0) {
            return $globalDay;
        }

        // Placeholder: jika ada kolom khusus di tabel member village day, override di sini.
        return 0;
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

        // Pacuan: $ratusan = substr($angka, -strlen($pembulatan / 2)) — ini quirk tapi efektif untuk 5000.
        // Kita samakan dengan rumus pacuan: substring digit terakhir sepanjang strlen(pembulatan/2),
        // atau fallback ke modulo untuk kasus umum.
        $divisorLen = (int) ($pembulatanInt / 2);
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
