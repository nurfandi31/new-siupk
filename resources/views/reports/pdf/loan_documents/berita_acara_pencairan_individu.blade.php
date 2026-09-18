@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = trim(($borrower?->address?->address_line ?? '').' '.($borrower?->village?->name ?? ''));
    $village = $borrower?->village;
    $villageName = $village?->name ?? '';

    $loanNumber = $loan_obj->loan_number ?? 'PINJ-' . $loan_obj->id;
    $spkNo = $loan_obj->spk_no ?? '';
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $disbursedTime = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->format('H:i') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);

    $collateral = is_array($loan_obj->collateral) ? $loan_obj->collateral : [];
    $collateralType = match ($collateral['type'] ?? null) {
        'tanah' => 'Surat Tanah',
        'bpkb' => 'BPKB Kendaraan',
        'sk' => 'SK Pegawai',
        'cash' => 'Simpanan',
        default => 'Lain-lain',
    };

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';
    $secretaryName = $profile?->secretary_name ?? '________________';
    $secretaryTitle = $profile?->secretary_title ?? 'Sekretaris';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Berita Acara Pencairan Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 11pt; line-height: 1.5; }
        .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 4px; margin-bottom: 20px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 13pt; font-weight: bold; text-decoration: underline; margin: 20px 0 14px; }
        .tabel { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .tabel td { padding: 4px 6px; vertical-align: top; }
        .tabel-nomor td { border: 1px solid #000; padding: 4px 6px; }
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">BERITA ACARA PENCAIRAN PINJAMAN INDIVIDU<br>Nomor: BA-{{ $loan_obj->id }}/{{ $today->format('Y') }}</div>

    <p>Pada hari ini, {{ $today->translatedFormat('l') }} tanggal {{ $todayLabel }},
    telah dilakukan pencairan pinjaman individu atas nama:</p>

    <table class="tabel">
        <tr><td width="160">Nama Peminjam</td><td width="10">:</td><td><strong>{{ $borrowerName }}</strong></td></tr>
        <tr><td>NIK</td><td>:</td><td>{{ $borrowerNik }}</td></tr>
        <tr><td>Alamat</td><td>:</td><td>{{ $borrowerAddress }}</td></tr>
        <tr><td>Desa</td><td>:</td><td>{{ $villageName }}</td></tr>
        <tr><td>No. Pinjaman</td><td>:</td><td>{{ $loanNumber }}</td></tr>
        <tr><td>No. SPK</td><td>:</td><td>{{ $spkNo ?: '—' }}</td></tr>
    </table>

    <p>Dengan rincian pencairan sebagai berikut:</p>

    <table class="tabel tabel-nomor">
        <tr><td width="30">1.</td><td>Plafon Pinjaman</td><td align="right"><strong>{{ $principalFmt }}</strong></td></tr>
        <tr><td>2.</td><td>Jangka Waktu</td><td align="right">{{ $term }} bulan</td></tr>
        <tr><td>3.</td><td>Jasa</td><td align="right">{{ number_format($rate, 2, ',', '.') }}%</td></tr>
        <tr><td>4.</td><td>Tanggal Pencairan</td><td align="right">{{ $disbursedAt }}</td></tr>
        <tr><td>5.</td><td>Waktu Pencairan</td><td align="right">{{ $disbursedTime }} WIB</td></tr>
        <tr><td>6.</td><td>Tempat Pencairan</td><td align="right">{{ $legalName }}</td></tr>
        @if (! empty($collateral))
            <tr><td>7.</td><td>Jaminan</td><td align="right">{{ $collateralType }}{{ isset($collateral['nomor']) ? ' · No: '.$collateral['nomor'] : '' }}</td></tr>
        @endif
    </table>

    <p style="text-align: justify;">
        Pencairan dilakukan secara tunai / transfer kepada peminjam dan diterima langsung oleh yang bersangkutan
        pada tanggal {{ $disbursedAt }}. Dengan ditandatanganinya berita acara ini, maka kedua pihak menyatakan
        bahwa pencairan telah selesai dengan baik dan benar.
    </p>

    <p>Demikian Berita Acara Pencairan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

    <table class="ttd">
        <tr>
            <td width="50%">
                Saksi,<br>
                {{ $secretaryTitle }}<br><br><br><br><br>
                <strong>{{ $secretaryName }}</strong>
            </td>
            <td width="50%">
                {{ $district ? $district.', ' : '' }}{{ $todayLabel }}<br>
                {{ $managerTitle }} {{ $legalName }}<br><br><br><br><br>
                <strong>{{ $managerName }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <br><br>
                Penerima,<br><br><br><br><br>
                <strong>{{ $borrowerName }}</strong>
            </td>
        </tr>
    </table>
</body>
</html>
