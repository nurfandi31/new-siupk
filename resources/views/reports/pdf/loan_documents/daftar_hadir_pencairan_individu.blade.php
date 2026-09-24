@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $villageName = $borrower?->village?->name ?? '';

    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $disbursedTime = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->format('H:i') : '—';
    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $managerName = $profile?->manager_name ?? '________________';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Daftar Hadir Pencairan Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 16pt; font-weight: bold; margin: 16px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 4px; vertical-align: top; }
        th { font-size: 11px; font-weight: bold; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">DAFTAR HADIR PENCAIRAN {{ strtoupper($productName) }}</div>

    <table>
        <tr>
            <td width="120">Tempat</td>
            <td width="5" align="center">:</td>
            <td width="200">{{ $legalName }}</td>
            <td width="120">Tanggal</td>
            <td width="5" align="center">:</td>
            <td width="200"><b>{{ $disbursedAt }}</b></td>
        </tr>
        <tr>
            <td>Desa / Kecamatan</td>
            <td align="center">:</td>
            <td>{{ $villageName ?: '________' }} / {{ $district ?: '________' }}</td>
            <td>Waktu</td>
            <td align="center">:</td>
            <td><b>{{ $disbursedTime }} WIB</b></td>
        </tr>
        <tr>
            <td colspan="6">&nbsp;</td>
        </tr>
    </table>

    <table border="1" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <th width="40" height="22" align="center">No</th>
            <th width="200" align="center">Nama Lengkap</th>
            <th width="120" align="center">Unsur / Jabatan</th>
            <th align="center">Alamat</th>
            <th width="120" align="center">Tanda Tangan</th>
        </tr>
        <tr>
            <td height="20" align="center"><b>1.</b></td>
            <td>{{ $borrowerName }}</td>
            <td align="center">Pemanfaat</td>
            <td>{{ $villageName ?: '________' }}</td>
            <td align="center">1.</td>
        </tr>
        @for ($i = 2; $i <= 25; $i++)
            <tr>
                <td height="20" align="center"><b>{{ $i }}.</b></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td align="center">{{ $i }}.</td>
            </tr>
        @endfor
    </table>

    <table class="ttd">
        <tr>
            <td width="66%">&nbsp;</td>
            <td width="33%" align="center">Mengetahui,</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td align="center">{{ 'Pinjaman ' . $productName }}</td>
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
    </table>
</body>
</html>