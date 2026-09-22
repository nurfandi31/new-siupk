<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;

/**
 * Substitusi keyword di template SPK (HTML/text body).
 *
 * Pattern pacuan: App\Utils\Pinjaman::keyword() dengan token {kepala_lembaga}, {namadepan}, dst.
 * Pattern siupknext: token-based replacement via array, exposed ke Blade via $tokens.
 *
 * Dipakai oleh LoanDocumentService::tokenReplacer() — extend dengan token tambahan
 * (collateral, official names, dll.) tanpa memodifikasi logic existing.
 */
final class SpkTokenResolver
{
    /**
     * Bangun map token untuk di-replace di template SPK.
     *
     * @return array<string, string>
     */
    public function resolve(Loan $loan, bool $isIndividual = false): array
    {
        $profile = OrganizationProfile::query()->first();
        $group = $loan->borrower?->group;
        $village = $group?->village ?? $loan->borrower?->member?->village;
        $district = $village?->parent;

        $committeeByPos = $this->committeeByPosition($loan);
        $firstBeneficiary = $loan->beneficiaries->sortBy('row_id')->first();
        $firstPerson = $firstBeneficiary?->member?->person;
        $firstGuarantor = $firstBeneficiary?->member?->guarantor?->person;

        $individualMember = $loan->borrower?->member;
        $individualPerson = $individualMember?->person;
        $individualGuarantor = $individualMember?->guarantor?->person;

        $primaryPerson = $isIndividual ? $individualPerson : $firstPerson;
        $primaryGuarantor = $isIndividual ? $individualGuarantor : $firstGuarantor;

        $managerName = (string) ($profile?->manager_name ?? '');
        $secretaryName = (string) ($profile?->secretary_name ?? '');
        $treasurerName = (string) ($profile?->treasurer_name ?? '');
        $verifierName = (string) ($profile?->verifier_name ?? '');

        $managerTitle = (string) ($profile?->manager_title ?? 'Direktur');
        $secretaryTitle = (string) ($profile?->secretary_title ?? 'Sekretaris');
        $treasurerTitle = (string) ($profile?->treasurer_title ?? 'Bendahara');
        $verifierTitle = (string) ($profile?->verifier_title ?? 'Verifikator');

        $managerNik = (string) ($profile?->manager_nik ?? '');
        $managerPosition = (string) ($profile?->manager_position ?? $managerTitle);
        $managerAddress = (string) ($profile?->manager_address ?? $profile?->address ?? '');
        $secretaryNik = (string) ($profile?->secretary_nik ?? '');
        $treasurerNik = (string) ($profile?->treasurer_nik ?? '');
        $kadesName = (string) ($profile?->kepala_desa_name ?? '');
        $kadesNip = (string) ($profile?->kepala_desa_nip ?? '');
        $courtOfJurisdiction = (string) ($profile?->court_of_jurisdiction ?? '');
        $level1 = (string) ($profile?->institution_level_1 ?? 'Lembaga');
        $level2 = (string) ($profile?->institution_level_2 ?? 'Pengurus');
        $level3 = (string) ($profile?->institution_level_3 ?? 'Pengurus Harian');

        $collateral = $loan->collateral;
        $collateralLabel = $this->collateralLabel($collateral);
        $collateralDetail = $this->collateralDetail($collateral);

        $alokasiNumeric = (float) ($isIndividual ? $loan->principal_amount : $loan->principal_amount);

        return [
            // Lembaga (existing compat)
            '{nama_lembaga}' => (string) ($profile?->legal_name ?: config('app.name')),
            '{nama_singkat}' => (string) ($profile?->short_name ?? ''),
            '{brand_short}' => (string) ($profile?->brand_short ?? $profile?->short_name ?? ''),
            '{alamat_lembaga}' => (string) ($profile?->address ?? ''),
            '{telepon_lembaga}' => (string) ($profile?->phone ?? ''),
            '{email_lembaga}' => (string) ($profile?->email ?? ''),
            '{email_sekunder}' => (string) ($profile?->contact_email_secondary ?? ''),
            '{nama_kabupaten}' => (string) ($profile?->regency_name ?? ''),
            '{nama_kecamatan}' => (string) ($profile?->district_name ?? $district?->name ?? ''),

            // Pejabat (customizable, fallback ke default)
            '{kepala_lembaga}' => $managerName,
            '{jabatan_kepala}' => $managerTitle,
            '{kepala_lembaga_nik}' => $managerNik,
            '{kepala_lembaga_jabatan}' => $managerPosition,
            '{kepala_lembaga_alamat}' => $managerAddress,
            '{sekretaris_lembaga}' => $secretaryName,
            '{jabatan_sekretaris}' => $secretaryTitle,
            '{sekretaris_lembaga_nik}' => $secretaryNik,
            '{bendahara_lembaga}' => $treasurerName,
            '{jabatan_bendahara}' => $treasurerTitle,
            '{bendahara_lembaga_nik}' => $treasurerNik,
            '{verifikator}' => $verifierName,
            '{jabatan_verifikator}' => $verifierTitle,
            '{nama_pengawas}' => $verifierName,
            '{jabatan_pengawas}' => $verifierTitle,

            // Pejabat struktural — akan di-override oleh base token jika ada
            // (base lebih diprioritaskan via array_merge di LoanDocumentService).
            // Di sini kita sediakan sebagai fallback dari profile (kepala_desa_*).
            '{pengadilan_negeri}' => $courtOfJurisdiction,
            '{sebutan_level_1}' => $level1,
            '{sebutan_level_2}' => $level2,
            '{sebutan_level_3}' => $level3,

            // Kelompok
            '{nama_kelompok}' => (string) ($group?->name ?? ''),
            '{kd_kelompok}' => (string) ($group?->code ?? ''),
            '{alamat_kelompok}' => (string) ($group?->address ?? ''),
            '{desa}' => (string) ($village?->name ?? ''),
            '{sebutan_desa}' => (string) ($village?->name ?? ''),
            '{kecamatan}' => (string) ($district?->name ?? ''),

            // Pengurus kelompok aktif (existing compat)
            '{nama_ketua}' => $this->activeOfficerName($group?->row_id, 'chair'),
            '{nama_sekretaris}' => $this->activeOfficerName($group?->row_id, 'secretary'),
            '{nama_bendahara}' => $this->activeOfficerName($group?->row_id, 'treasurer'),

            // Pengurus pinjaman (snapshot LoanCommittee)
            '{ketua_pengurus}' => (string) ($committeeByPos['chair'] ?? ''),
            '{sekretaris_pengurus}' => (string) ($committeeByPos['secretary'] ?? ''),
            '{bendahara_pengurus}' => (string) ($committeeByPos['treasurer'] ?? ''),

            // Pinjaman
            '{produk}' => (string) ($loan->product?->name ?? ''),
            '{kd_produk}' => (string) ($loan->product?->code ?? ''),
            '{no_pinjaman}' => (string) ($loan->loan_number ?? ''),
            '{no_spk}' => (string) ($loan->spk_no ?? $loan->loan_number ?? ''),
            '{alokasi}' => $this->money($alokasiNumeric),
            '{alokasi_angka}' => number_format($alokasiNumeric, 0, ',', '.'),
            '{alokasi_terbilang}' => $this->terbilang($alokasiNumeric),
            '{jasa_persen}' => number_format((float) ($loan->service_rate_total ?? $loan->interest_rate ?? 0), 2, ',', '.'),
            '{jangka}' => $loan->term_months ? $loan->term_months.' bulan' : '',
            '{jangka_angka}' => (string) ($loan->term_months ?? ''),
            '{metode_angsuran}' => (string) ($loan->installment_method ?? ''),

            // Tanggal-tanggal
            '{tgl_proposal}' => $this->formatDateIndo($loan->proposed_at?->toDateString()),
            '{tgl_verifikasi}' => $this->formatDateIndo($loan->verified_at?->toDateString()),
            '{tgl_persetujuan}' => $this->formatDateIndo($loan->approved_at?->toDateString()),
            '{tgl_cair}' => $this->formatDateIndo($loan->disbursed_at?->toDateString()),
            '{tgl_lunas}' => $this->formatDateIndo($loan->completed_at?->toDateString()),
            '{tgl_kondisi}' => $this->formatDateIndo(CarbonImmutable::now()->toDateString()),
            '{tgl_romawi}' => $this->formatRomawi(CarbonImmutable::now()),
            '{keterangan_verifikasi}' => (string) ($loan->verification_notes ?? ''),

            // Pemanfaat (default anggota pertama; loop di Blade via $beneficiaries)
            '{pemanfaat_nama}' => (string) ($primaryPerson?->full_name ?? ''),
            '{namadepan}' => (string) ($primaryPerson?->full_name ?? ''),
            '{pemanfaat_nik}' => (string) ($primaryPerson?->national_identity_number ?? ''),
            '{pemanfaat_ttl}' => $this->ttl($primaryPerson),
            '{pemanfaat_alamat}' => (string) ($primaryPerson?->address ?? ''),
            '{pemanfaat_pekerjaan}' => (string) ($primaryPerson?->occupation ?? ''),
            '{pemanfaat_penjamin}' => (string) ($primaryGuarantor?->full_name ?? ''),
            '{penjamin_nama}' => (string) ($primaryGuarantor?->full_name ?? ''),
            '{penjamin_nik}' => (string) ($primaryGuarantor?->national_identity_number ?? ''),
            '{pemanfaat_alokasi}' => $this->money((float) ($firstBeneficiary?->allocated_amount ?? 0)),

            // Jaminan (khusus individu)
            '{jenis_jaminan}' => $collateralLabel,
            '{detail_jaminan}' => $collateralDetail,
            '{nilai_jaminan}' => $this->money($this->collateralValue($collateral)),

            // Sign block injection (di-replace nanti oleh SignatureImageService)
            '{ttd_image}' => '',
        ];
    }

    /**
     * Replace token di template HTML/string dengan map token.
     */
    public function apply(string $template, array $tokens): string
    {
        if ($template === '') {
            return '';
        }

        return strtr($template, $tokens);
    }

    /**
     * @return array<string, string>
     */
    private function committeeByPosition(Loan $loan): array
    {
        $byPos = [];
        foreach ($loan->committee as $row) {
            $byPos[(string) $row->position] = (string) ($row->member_name_snapshot ?? '');
        }

        return $byPos;
    }

    private function activeOfficerName(?int $groupRowId, string $position): string
    {
        if ($groupRowId === null) {
            return '';
        }

        $officer = GroupOfficer::query()
            ->where('group_row_id', $groupRowId)
            ->where('position', $position)
            ->whereNull('ended_at')
            ->with('member.person:row_id,full_name')
            ->orderByDesc('started_at')
            ->first();

        return (string) ($officer?->member?->person?->full_name ?? '');
    }

    /**
     * @param  mixed  $collateral  raw value dari kolom JSON loan.collateral
     */
    private function collateralLabel(mixed $collateral): string
    {
        if (is_array($collateral)) {
            $type = $collateral['type'] ?? null;
        } else {
            $type = null;
        }

        return match ($type) {
            'tanah' => 'Surat Tanah',
            'bpkb' => 'BPKB Kendaraan',
            'sk' => 'SK Pegawai',
            'cash' => 'Simpanan',
            default => 'Lain-lain',
        };
    }

    private function collateralDetail(mixed $collateral): string
    {
        if (! is_array($collateral)) {
            return '';
        }

        $parts = [];
        foreach (['nomor', 'atas_nama', 'luas', 'lokasi', 'kendaraan', 'nopol', 'tahun'] as $key) {
            if (! empty($collateral[$key])) {
                $parts[] = $key.': '.$collateral[$key];
            }
        }

        return implode(' | ', $parts);
    }

    private function collateralValue(mixed $collateral): float
    {
        if (is_array($collateral)) {
            return (float) ($collateral['nilai'] ?? 0);
        }

        return 0.0;
    }

    private function ttl(mixed $person): string
    {
        $birthPlace = (string) ($person?->birth_place ?? '');
        $birthDate = $person?->birth_date;

        if ($birthPlace === '' && ! $birthDate) {
            return '';
        }

        $dateLabel = '';
        if ($birthDate) {
            try {
                $dateLabel = CarbonImmutable::parse($birthDate)->locale('id')->translatedFormat('d F Y');
            } catch (\Throwable) {
                $dateLabel = (string) $birthDate;
            }
        }

        return trim($birthPlace.', '.$dateLabel, ', ');
    }

    private function formatDateIndo(?string $ymd): string
    {
        if ($ymd === null || $ymd === '') {
            return '—';
        }

        try {
            return CarbonImmutable::parse($ymd)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private function formatRomawi(CarbonImmutable $date): string
    {
        $romawiMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];
        $month = $romawiMonths[(int) $date->format('n')] ?? '?';

        return $month.'/'.$date->format('Y');
    }

    private function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    private function terbilang(float $value): string
    {
        if ($value <= 0) {
            return 'Nol';
        }

        $units = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        $terbilang = '';

        if ($value < 12) {
            $terbilang = ' '.$units[(int) $value];
        } elseif ($value < 20) {
            $terbilang = $this->terbilang($value - 10).' Belas';
        } elseif ($value < 100) {
            $terbilang = $this->terbilang((int) ($value / 10)).' Puluh'.$this->terbilang($value % 10);
        } elseif ($value < 200) {
            $terbilang = ' Seratus'.($value - 100 > 0 ? $this->terbilang($value - 100) : '');
        } elseif ($value < 1000) {
            $terbilang = $this->terbilang((int) ($value / 100)).' Ratus'.$this->terbilang($value % 100);
        } elseif ($value < 2000) {
            $terbilang = ' Seribu'.($value - 1000 > 0 ? $this->terbilang($value - 1000) : '');
        } elseif ($value < 1000000) {
            $terbilang = $this->terbilang((int) ($value / 1000)).' Ribu'.$this->terbilang($value % 1000);
        } elseif ($value < 1000000000) {
            $terbilang = $this->terbilang((int) ($value / 1000000)).' Juta'.$this->terbilang($value % 1000000);
        } elseif ($value < 1000000000000) {
            $terbilang = $this->terbilang((int) ($value / 1000000000)).' Milyar'.$this->terbilang($value % 1000000000);
        } else {
            $terbilang = $this->terbilang((int) ($value / 1000000000000)).' Triliun'.$this->terbilang($value % 1000000000000);
        }

        return trim(preg_replace('/\s+/', ' ', $terbilang) ?? '');
    }
}
