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

    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $verifiedAt = $loan_obj->verified_at ? CarbonImmutable::parse($loan_obj->verified_at)->translatedFormat('d F Y') : $disbursedAt;

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Pemberitahuan ke Desa (Individu) #{{ $loan_obj->id }}</title>
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
                    <tr><td style="width: 60px;">Nomor</td><td style="width: 8px; text-align: center;">:</td><td>&nbsp;</td></tr>
                    <tr><td>Sifat</td><td style="text-align: center;">:</td><td>Penting dan Rahasia</td></tr>
                    <tr><td>Perihal</td><td style="text-align: center;">:</td><td><b>Pemberitahuan Pencairan</b></td></tr>
                </table>
            </td>
            <td class="header-to" valign="top">
                <div>Kepada Yth.</div>
                <div style="font-weight: bold;">Kepala Desa {{ $villageName ?: '________' }}</div>
                <div>{{ $district ? 'Kec. '.$district : '' }} {{ $regency ?: '' }}</div>
                <div>Di</div>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Tempat</div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 12px;">Dengan hormat,</div>

    <p class="justify">
        Menindaklanjuti hasil keputusan rapat pendanaan {{ $legalName }}
        Tanggal {{ $verifiedAt }}, dengan ini memberitahukan bahwa akan dilakukan
        pencairan kredit kepada:
    </p>

    <table>
        <tr><td width="4%">1.</td><td width="32%">Nama Pemanfaat</td><td width="3%" align="center">:</td><td>{{ $borrowerName }}</td></tr>
        <tr><td>2.</td><td>Alamat</td><td align="center">:</td><td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td></tr>
        <tr><td>3.</td><td>Tanggal Cair</td><td align="center">:</td><td>{{ $disbursedAt }}</td></tr>
        <tr><td>4.</td><td>Tempat</td><td align="center">:</td><td>{{ $legalName }}</td></tr>
    </table>

    <p class="justify" style="margin-top: 12px;">
        Demikian surat pemberitahuan ini kami sampaikan, atas perhatian dan kerjasamanya kami
        ucapkan terima kasih.
    </p>

    <table class="ttd">
        <tr>
            <td width="66%">&nbsp;</td>
            <td width="33%" align="center">{{ $district ? $district.', ' : '' }}{{ $disbursedAt }}</td>
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
            <td align="center">
                <u>
                    <b>{{ $managerName }}</b>
                </u>
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">{!! $signature ?? '' !!}</td>
        </tr>
    </table>
</body>
</html>