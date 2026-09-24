@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $regency = strtoupper($profile?->regency_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $villageName = $borrower?->village?->name ?? '';

    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $verifiedAt = $loan_obj->verified_at ? CarbonImmutable::parse($loan_obj->verified_at)->translatedFormat('d F Y') : $proposedAt;

    $proposedAmount = (float) ($loan_obj->proposed_amount ?? $loan_obj->principal_amount ?? 0);
    $proposedFmt = 'Rp ' . number_format($proposedAmount, 0, ',', '.');

    $recomAmount = (float) ($loan_obj->verified_amount ?? $loan_obj->principal_amount ?? 0);
    $recomFmt = 'Rp ' . number_format($recomAmount, 0, ',', '.');

    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $ratePerMonth = $term > 0 ? round($rate / $term, 2) : 0;
    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Rekomendasi Verifikator Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 14pt; font-weight: bold; margin: 16px 0 8px; }
        .subjudul { text-align: center; font-size: 11pt; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        .justify { text-align: justify; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $regency ?: '' }}</h1>
        <p>KECAMATAN {{ $district }}</p>
        <p style="font-size: 9pt;">{{ $address }}</p>
    </div>

    <div class="judul">REKOMENDASI HASIL VERIFIKASI / ANALISA KREDIT INDIVIDU</div>
    <div class="subjudul">Nomor: ____________/{{ strtoupper($productName) }}/{{ \Carbon\CarbonImmutable::parse($loan_obj->proposed_at ?? 'now')->format('Y') }}</div>

    <p class="justify">
        Setelah dilakukan pengkajian dokumen permohonan kredit dan Analisa lapangan atas permohonan kredit / permohonan pinjaman sebagai berikut:
    </p>

    <table>
        <tr>
            <td width="30">&nbsp;</td>
            <td width="120">Nama Pemohon Kredit</td>
            <td width="5" align="center">:</td>
            <td>{{ $borrowerName }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>N I K</td>
            <td align="center">:</td>
            <td>{{ $borrowerNik }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Alamat</td>
            <td align="center">:</td>
            <td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Tanggal dan Nomor Permohonan Kredit</td>
            <td align="center">:</td>
            <td>____________/____________</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Nilai Permohonan</td>
            <td align="center">:</td>
            <td>{{ $proposedFmt }}</td>
        </tr>
        <tr>
            <td colspan="4">&nbsp;</td>
        </tr>
    </table>

    <p class="justify">
        Dengan ini selaku Tim Verifikasi/Analis Kredit menyatakan bahwa Permohonan Kredit sebagaimana dimaksud di atas dinyatakan <strong>LAYAK</strong> dengan rincian rekomendasi kredit sebagai berikut:
    </p>

    <table>
        <tr>
            <td width="30">&nbsp;</td>
            <td width="120">Nilai Rekomendasi</td>
            <td width="5" align="center">:</td>
            <td>{{ $recomFmt }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Jangka Waktu Pinjaman</td>
            <td align="center">:</td>
            <td>{{ $term }} bulan</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Jenis dan Besaran Jasa</td>
            <td align="center">:</td>
            <td>Flat {{ number_format($ratePerMonth, 2, ',', '.') }}% per bulan</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Sistem Angsuran Pokok</td>
            <td align="center">:</td>
            <td>Bulanan</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>Sistem Angsuran Jasa</td>
            <td align="center">:</td>
            <td>Bulanan</td>
        </tr>
        <tr>
            <td colspan="4">&nbsp;</td>
        </tr>
    </table>

    <p class="justify">
        Demikian rekomendasi ini kami terbitkan untuk dapat ditindaklanjuti sebagaimana mestinya oleh Tim Pemutus Pinjaman Bagian Kredit {{ $legalName }} {{ $district ? 'Kec. '.$district : '' }} {{ $regency ?: '' }}.
    </p>

    <table class="ttd">
        <tr>
            <td width="33%">&nbsp;</td>
            <td width="33%">&nbsp;</td>
            <td width="33%">&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td align="center">{{ $district ? $district.', ' : '' }}{{ $verifiedAt }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td align="center">Verifikator / Analis Kredit</td>
        </tr>
        <tr>
            <td colspan="3" height="40">&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td align="center">
                <u>
                    <p>({{ $managerName }})<br>
                    Ketua Tim
                    </p>
                </u>
            </td>
        </tr>
        <tr>
            <td colspan="3" align="center">{!! $signature ?? '' !!}</td>
        </tr>
    </table>
</body>
</html>