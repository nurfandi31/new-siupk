@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $regency = strtoupper($profile?->regency_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $villageName = $borrower?->village?->name ?? '';
    $salutation = $person?->gender === 'P' ? 'Ibu' : 'Bpk';

    $loanNumber = $loan_obj->loan_number ?? ('PINJ-' . $loan_obj->id);
    $spkNo = $loan_obj->spk_no ?? '';
    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $ratePerMonth = $term > 0 ? round($rate / $term, 2) : 0;
    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $tunggakanPokok = (float) ($loan_obj->outstanding_principal ?? 0);
    $tunggakanJasa = (float) ($loan_obj->outstanding_interest ?? 0);

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');

    $romanMonth = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];
    $romawi = $romanMonth[(int) $today->format('n')] . '/' . $today->format('Y');

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Tagihan Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        .justify { text-align: justify; }
        .header-meta { width: 55%; }
        .header-to { width: 45%; padding-left: 12px; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td class="header-meta" valign="top">
                <table>
                    <tr><td style="width: 60px;">Nomor</td><td style="width: 8px; text-align: center;">:</td><td>__________/{{ $romawi }}</td></tr>
                    <tr><td>Sifat</td><td style="text-align: center;">:</td><td>Penting dan Rahasia</td></tr>
                    <tr><td>Perihal</td><td style="text-align: center;">:</td><td><b>Surat Tagihan</b></td></tr>
                </table>
            </td>
            <td class="header-to" valign="top">
                <div>Kepada Yth.</div>
                <div>{{ $salutation }} <b>{{ $borrowerName }}</b></div>
                <div>di</div>
                <div>&nbsp;&nbsp;{{ $borrowerAddress ?: '-' }} {{ $villageName ? '· '.$villageName : '' }}</div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 12px;">Dengan hormat,</div>

    <p class="justify">
        Mendasar kepada Surat Perjanjian Kredit ({{ $productName }})
        antara {{ $salutation }} {{ $borrowerName }}
        Desa {{ $villageName ?: '________' }}
        dengan {{ $legalName }} {{ $district ? 'Kec. '.$district : '' }} {{ $regency ?: '' }}
        Tanggal {{ $disbursedAt }} dengan rincian pinjaman sebagai berikut:
    </p>

    <table>
        <tr><td width="4%">1.</td><td width="32%">Alokasi Pinjaman</td><td width="3%" align="center">:</td><td><b>{{ $principalFmt }}</b></td></tr>
        <tr><td>2.</td><td>Tanggal Pencairan</td><td align="center">:</td><td><b>{{ $disbursedAt }}</b></td></tr>
        <tr><td>3.</td><td>Prosentase Jasa</td><td align="center">:</td><td><b>{{ number_format($ratePerMonth, 2, ',', '.') }}% per Bulan</b></td></tr>
        <tr><td>4.</td><td>Masa Angsuran</td><td align="center">:</td><td><b>{{ $term }} Bulan</b></td></tr>
        <tr><td>5.</td><td>Sistem Angsuran</td><td align="center">:</td><td><b>Bulanan</b></td></tr>
    </table>

    <p class="justify" style="margin-top: 8px;">
        dan mendasar pada catatan pembukuan kami {{ $salutation }} {{ $borrowerName }}
        Desa {{ $villageName ?: '________' }} sampai dengan diterbitkannya Surat Tagihan ini masih tercatat memiliki tunggakan sebagai berikut:
    </p>

    <table>
        <tr><td width="4%">1.</td><td width="32%">Tunggakan Pokok</td><td width="3%" align="center">:</td><td><b>Rp. {{ number_format($tunggakanPokok, 0, ',', '.') }}</b></td></tr>
        <tr><td>2.</td><td>Tunggakan Jasa</td><td align="center">:</td><td><b>Rp. {{ number_format($tunggakanJasa, 0, ',', '.') }}</b></td></tr>
        <tr><td>3.</td><td><b>Total Tunggakan (Pokok + Jasa)</b></td><td align="center">:</td><td><b>Rp. {{ number_format($tunggakanPokok + $tunggakanJasa, 0, ',', '.') }}</b></td></tr>
    </table>

    <p class="justify" style="margin-top: 8px;">
        Demikian surat ini kami sampaikan, apabila terjadi perbedaan hasil perhitungan angsuran/tunggakan
        mohon untuk melakukan klarifikasi dengan {{ $legalName }}. Pembayaran dimohon untuk dapat dilakukan paling lambat tanggal .................... .
        Terima kasih atas perhatian dan kerjasamanya.
    </p>

    <table class="ttd">
        <tr>
            <td width="50%">&nbsp;</td>
            <td width="50%" align="center">{{ $district ? $district.', ' : '' }}{{ $todayLabel }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td align="center">{{ $managerTitle }} {{ $legalName }}</td>
        </tr>
        <tr>
            <td colspan="2" height="40">&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td align="center"><b>{{ $managerName }}</b></td>
        </tr>
        <tr>
            <td colspan="2" align="center">{!! $signature ?? '' !!}</td>
        </tr>
    </table>
</body>
</html>