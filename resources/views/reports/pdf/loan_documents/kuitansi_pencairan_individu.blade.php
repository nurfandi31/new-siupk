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

    $loanNumber = $loan_obj->loan_number ?? 'PINJ-' . $loan_obj->id;
    $spkNo = $loan_obj->spk_no ?? '';
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $principalTerbilang = \App\Support\IndonesianNumber::spelledOut($principal);

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';
    $treasurerName = $profile?->treasurer_name ?? '________________';
    $treasurerTitle = $profile?->treasurer_title ?? 'Bendahara';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
    $kuitansiNo = 'KW-' . ($loan_obj->id) . '/' . $today->format('Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Kuitansi Pencairan Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 12pt; line-height: 1.5; }
        .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 4px; margin-bottom: 20px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 16pt; font-weight: bold; text-decoration: underline; margin: 24px 0; }
        .kw-no { text-align: center; margin: -10px 0 20px; font-size: 11pt; }
        .terbilang { padding: 12px; border: 1px dashed #555; margin: 12px 0; background: #fafafa; }
        .ttd { width: 100%; margin-top: 48px; }
        .ttd td { text-align: center; padding: 4px; }
        .nominal-big { font-size: 14pt; font-weight: bold; text-align: center; padding: 8px; border: 2px solid #000; margin: 12px 0; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">KUITANSI PENERIMAAN</div>
    <div class="kw-no">No: {{ $kuitansiNo }}@if ($spkNo) · SPK: {{ $spkNo }}@endif</div>

    <p style="text-align: justify;">
        Telah terima dari <strong>{{ $legalName }}</strong> uang sebesar:
    </p>

    <div class="nominal-big">{{ $principalFmt }}</div>

    <p style="text-align: center; font-style: italic; margin-top: -4px;">
        ({{ ucfirst($principalTerbilang) }} rupiah)
    </p>

    <p>Untuk pembayaran <strong>Pencairan Pinjaman Individu</strong> atas nama:</p>

    <table style="width: 100%; margin: 12px 0;">
        <tr><td width="160">Nama Peminjam</td><td width="10">:</td><td><strong>{{ $borrowerName }}</strong></td></tr>
        <tr><td>NIK</td><td>:</td><td>{{ $borrowerNik }}</td></tr>
        <tr><td>Alamat</td><td>:</td><td>{{ $borrowerAddress }}</td></tr>
        <tr><td>No. Pinjaman</td><td>:</td><td>{{ $loanNumber }}</td></tr>
        <tr><td>Tanggal Pencairan</td><td>:</td><td>{{ $disbursedAt }}</td></tr>
    </table>

    <p style="text-align: justify;">
        Uang tersebut telah diterima sepenuhnya oleh penerima pada tanggal {{ $disbursedAt }} dan
        tidak ada tunggakan sebelumnya.
    </p>

    <table class="ttd">
        <tr>
            <td width="33%">
                Penerima,<br><br><br><br><br>
                <strong>{{ $borrowerName }}</strong>
            </td>
            <td width="33%">
                Bendahara,<br><br><br><br><br>
                <strong>{{ $treasurerName }}</strong><br>
                {{ $treasurerTitle }}
            </td>
            <td width="33%">
                {{ $district ? $district.', ' : '' }}{{ $todayLabel }}<br>
                Yang Membayarkan,<br><br><br><br>
                <strong>{{ $managerName }}</strong><br>
                {{ $managerTitle }}
            </td>
        </tr>
    </table>
</body>
</html>
