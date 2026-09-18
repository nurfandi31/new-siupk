@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = $profile?->district_name ?? '';
    $address = $profile?->address ?? '';
    $phone = $profile?->phone ?? '';

    $borrower = $loan->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = $borrower?->address?->address_line ?? '-';
    $villageName = $borrower?->village?->name ?? '';

    $loanNumber = $loan->loan_number ?? 'PINJ-' . $loan->id;
    $spkNo = $loan->spk_no ?? null;
    $disbursedAt = $loan->disbursed_at ? CarbonImmutable::parse($loan->disbursed_at) : null;
    $completedAt = $loan->completed_at ? CarbonImmutable::parse($loan->completed_at) : ($as_of ? CarbonImmutable::parse($as_of) : CarbonImmutable::now());
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Keterangan Lunas #{{ $loan->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 12pt; line-height: 1.5; }
        .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px; margin-bottom: 24px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 2px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 13pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 24px 0 16px; }
        .tabel { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .tabel td { padding: 4px 8px; vertical-align: top; }
        .ttd { width: 100%; margin-top: 48px; }
        .ttd td { text-align: center; }
        .signature-line { margin-top: 60px; border-bottom: 1px solid #000; width: 200px; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· ' . strtoupper($district) : '' }}</h1>
        <p>{{ $address }}</p>
        @if ($phone)
            <p>Telp. {{ $phone }}</p>
        @endif
    </div>

    <div class="judul">
        Surat Keterangan Pelunasan Pinjaman Individu
    </div>

    <p style="text-align: justify;">
        Yang bertanda tangan di bawah ini, pengurus {{ $legalName }}, dengan ini menerangkan bahwa:
    </p>

    <table class="tabel">
        <tr>
            <td width="180">Nama Peminjam</td>
            <td width="10">:</td>
            <td><strong>{{ $borrowerName }}</strong></td>
        </tr>
        <tr>
            <td>NIK</td>
            <td>:</td>
            <td>{{ $borrowerNik }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td>:</td>
            <td>{{ $borrowerAddress }} {{ $villageName }}</td>
        </tr>
        <tr>
            <td>No. Pinjaman</td>
            <td>:</td>
            <td>{{ $loanNumber }}</td>
        </tr>
        @if ($spkNo)
            <tr>
                <td>No. SPK</td>
                <td>:</td>
                <td>{{ $spkNo }}</td>
            </tr>
        @endif
        <tr>
            <td>Tanggal Pencairan</td>
            <td>:</td>
            <td>{{ $disbursedAt?->translatedFormat('d F Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td>Plafon</td>
            <td>:</td>
            <td>Rp {{ number_format((float) $loan->principal_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Jangka</td>
            <td>:</td>
            <td>{{ $loan->term_months }} bulan</td>
        </tr>
    </table>

    <p style="text-align: justify;">
        Telah <strong>LUNAS</strong> dilunasi seluruhnya pada tanggal
        <strong>{{ $completedAt->translatedFormat('d F Y') }}</strong>.
        Dengan demikian, pinjaman atas nama yang bersangkutan telah ditutup secara resmi dan tidak
        memiliki tunggakan dalam bentuk apapun kepada {{ $legalName }}.
    </p>

    <p>
        Surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.
    </p>

    <table class="ttd">
        <tr>
            <td width="50%"></td>
            <td width="50%">
                {{ $district ? strtoupper($district) . ', ' : '' }}{{ $completedAt->translatedFormat('d F Y') }}<br>
                {{ $legalName }}<br>
                <div style="height: 70px;"></div>
                <strong>Direktur / Pengurus</strong>
            </td>
        </tr>
    </table>
</body>
</html>
