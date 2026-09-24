@php
    use Carbon\CarbonImmutable;
    use App\Support\IndonesianNumber;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $regency = strtoupper($profile?->regency_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $villageName = $borrower?->village?->name ?? '';

    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $principalTerb = ucwords(IndonesianNumber::spelledOut($principal));
    $proposedAmount = (float) ($loan_obj->proposed_amount ?? $loan_obj->principal_amount ?? 0);
    $proposedFmt = 'Rp ' . number_format($proposedAmount, 0, ',', '.');

    $romanMonth = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];
    $romawi = $proposedAt !== '—'
        ? $romanMonth[(int) CarbonImmutable::parse($loan_obj->proposed_at)->format('n')] . '/' . CarbonImmutable::parse($loan_obj->proposed_at)->format('Y')
        : '________';

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Kelayakan Piutang Individu #{{ $loan_obj->id }}</title>
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
                    <tr><td style="width: 60px;">Nomor</td><td style="width: 8px; text-align: center;">:</td><td>______/UPK/{{ $romawi }}</td></tr>
                    <tr><td>Tanggal</td><td style="text-align: center;">:</td><td>{{ $proposedAt }}</td></tr>
                    <tr><td>Sifat</td><td style="text-align: center;">:</td><td>Penting dan Rahasia</td></tr>
                    <tr><td>Perihal</td><td style="text-align: center;">:</td><td><b>Kelayakan Pinjaman</b></td></tr>
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
        Dengan ini memberitahukan bahwa keputusan rapat pendanaan {{ $legalName }}
        Tanggal {{ $proposedAt }}, yang merupakan tindak lanjut hasil verifikasi atas
        Proposal Permohonan Kredit dari:
    </p>

    <table>
        <tr><td width="4%">1.</td><td width="32%">Nama Pemanfaat</td><td width="3%" align="center">:</td><td>{{ $borrowerName }}</td></tr>
        <tr><td>2.</td><td>Alamat</td><td align="center">:</td><td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td></tr>
        <tr><td>3.</td><td>Tanggal Proposal</td><td align="center">:</td><td>{{ $proposedAt }}</td></tr>
        <tr><td>4.</td><td>Jumlah Permohonan</td><td align="center">:</td><td>{{ $proposedFmt }}</td></tr>
    </table>

    <p class="justify" style="margin-top: 8px;">
        Dinyatakan <strong>Layak / Tidak Layak</strong> *) didanai sebesar {{ $principalFmt }}
        ({{ $principalTerb }}) dan dengan jadwal pencairan besok pada tanggal {{ $disbursedAt }}.
    </p>

    <p class="justify">
        Demikian surat pemberitahuan ini kami sampaikan, atas perhatian dan kerjasamanya kami ucapkan terima kasih.
    </p>

    <table class="ttd">
        <tr>
            <td width="50%">&nbsp;</td>
            <td width="50%" align="center">{{ $district ? $district.', ' : '' }}{{ $proposedAt }}</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td align="center">{{ $managerTitle }} UPK</td>
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