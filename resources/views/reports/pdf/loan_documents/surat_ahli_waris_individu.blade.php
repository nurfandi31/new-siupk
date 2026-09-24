@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $villageName = $borrower?->village?->name ?? '';

    $guarantor = $borrower?->guarantor?->person;
    $guarantorName = $guarantor?->full_name ?? '________________';
    $guarantorNik = $guarantor?->national_identity_number ?? '-';

    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Pernyataan Ahli Waris (Individu) #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 16pt; font-weight: bold; margin: 16px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        .justify { text-align: justify; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">SURAT PERNYATAAN AHLI WARIS</div>

    <p>Yang bertanda tangan di bawah ini:</p>

    <table>
        <tr><td width="160">Nama Penjamin</td><td width="10" align="center">:</td><td>{{ $guarantorName }}</td></tr>
        <tr><td>NIK / No. KK</td><td align="center">:</td><td>{{ $guarantorNik }}</td></tr>
        <tr><td>Alamat</td><td align="center">:</td><td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td></tr>
        <tr><td>Hubungan dengan Peminjam</td><td align="center">:</td><td>Penjamin Kredit</td></tr>
    </table>

    <p class="justify" style="margin-top: 12px;">
        Adalah benar-benar ahli waris dari <b>{{ $borrowerName }}</b>. Dengan ini menyatakan
        bersedia menanggung beban pinjaman {{ $productName }} sampai lunas. Apabila terjadi
        hal-hal yang tidak diinginkan yang menyebabkan peminjam tidak bisa melunasi kewajibannya seperti:
        Meninggal Dunia, Melarikan Diri, Berpindah domisili di luar desa, gangguan kejiwaan, sakit parah,
        dan lain-lain.
    </p>

    <p class="justify">
        Demikian Surat Pernyataan Ahli Waris ini saya buat tanpa ada paksaan dari pihak manapun.
    </p>

    <table class="ttd">
        <tr>
            <td align="center" width="50%">&nbsp;</td>
            <td align="center" width="50%">{{ $district ? $district.', ' : '' }}{{ $disbursedAt }}</td>
        </tr>
        <tr>
            <td align="center">&nbsp;</td>
            <td align="center">Nama Penjamin / Ahli Waris</td>
        </tr>
        <tr>
            <td align="center" colspan="2" height="40">&nbsp;</td>
        </tr>
        <tr>
            <td align="center">&nbsp;</td>
            <td align="center" style="font-weight: bold;">{{ $guarantorName }}</td>
        </tr>
        <tr>
            <td colspan="2" align="center">{!! $signature ?? '' !!}</td>
        </tr>
    </table>
</body>
</html>