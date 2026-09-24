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
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $borrowerPhone = $person?->phone ?? '-';
    $birthPlace = $person?->birth_place ?? '';
    $birthDate = $person?->birth_date ? CarbonImmutable::parse($person->birth_date)->translatedFormat('d F Y') : '';
    $borrowerGender = $person?->gender === 'P' ? 'Perempuan' : ($person?->gender === 'L' ? 'Laki-laki' : '-');
    $villageName = $borrower?->village?->name ?? '';

    $guarantor = $borrower?->guarantor?->person;
    $guarantorName = $guarantor?->full_name ?? '________________';
    $guarantorNik = $guarantor?->national_identity_number ?? '-';

    $loanNumber = $loan_obj->loan_number ?? ('PINJ-' . $loan_obj->id);
    $spkNo = $loan_obj->spk_no ?? '';
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $principalTerb = ucwords(IndonesianNumber::spelledOut($principal));
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Berita Acara Pencairan Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 16pt; font-weight: bold; margin: 16px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
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

    <div class="judul">BERITA ACARA PENCAIRAN<br>PINJAMAN INDIVIDU {{ strtoupper($productName) }}<br>Nomor: {{ $loanNumber }}</div>

    <p class="justify">
        Sesuai Surat Perjanjian Kredit (SPK) nomor: {{ $spkNo ?: '—' }}. Pada hari ini
        {{ $today->translatedFormat('l') }} tanggal
        {{ $principalTerb }} (Rp {{ number_format($principal, 0, ',', '.') }}) bulan __________ tahun
        {{ $today->translatedFormat('Y') }}, telah diadakan pencairan dana pinjaman individu
        {{ $legalName }} {{ $district ? 'Kec. '.$district : '' }} {{ $regency ?: '' }} dengan detail identitas
        pemanfaat dan detail pinjaman sebagai berikut:
    </p>

    <table>
        <tr>
            <td align="center" width="4%">1.</td><td width="28%">Nama Pemanfaat</td><td width="3%" align="center">:</td><td><b>{{ $borrowerName }}</b></td>
            <td align="center" width="4%">9.</td><td width="28%">KK / NIK Penjamin</td><td width="3%" align="center">:</td><td><b>{{ $guarantorNik }}</b></td>
        </tr>
        <tr>
            <td align="center">2.</td><td>NIK</td><td align="center">:</td><td><b>{{ $borrowerNik }}</b></td>
            <td align="center">10.</td><td>Nama Penjamin</td><td align="center">:</td><td><b>{{ $guarantorName }}</b></td>
        </tr>
        <tr>
            <td align="center">3.</td><td>Jenis Kelamin</td><td align="center">:</td><td><b>{{ $borrowerGender }}</b></td>
            <td align="center">11.</td><td>Jenis Pinjaman</td><td align="center">:</td><td><b>{{ $productName }}</b></td>
        </tr>
        <tr>
            <td align="center">4.</td><td>Tempat &amp; Tgl Lahir</td><td align="center">:</td><td><b>{{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate }}</b></td>
            <td align="center">12.</td><td>Alokasi Pinjaman</td><td align="center">:</td><td><b>{{ $principalFmt }}</b></td>
        </tr>
        <tr>
            <td align="center">5.</td><td>Alamat</td><td align="center">:</td><td><b>{{ $borrowerAddress }}</b></td>
            <td align="center">13.</td><td>Tanggal Pencairan</td><td align="center">:</td><td><b>{{ $disbursedAt }}</b></td>
        </tr>
        <tr>
            <td align="center">6.</td><td>Desa</td><td align="center">:</td><td><b>{{ $villageName }}</b></td>
            <td align="center">14.</td><td>Tempo</td><td align="center">:</td><td><b>{{ $term }} bulan</b></td>
        </tr>
        <tr>
            <td align="center">7.</td><td>Contact Person</td><td align="center">:</td><td><b>{{ $borrowerPhone }}</b></td>
            <td align="center">15.</td><td>Sistem Angsuran Pokok &amp; Jasa</td><td align="center">:</td><td><b>Bulanan &amp; Bulanan</b></td>
        </tr>
        <tr>
            <td align="center">8.</td><td>Jenis Usaha</td><td align="center">:</td><td><b>—</b></td>
            <td align="center">16.</td><td>Prosentase Jasa</td><td align="center">:</td><td><b>{{ number_format($term > 0 ? $rate / $term : 0, 2, ',', '.') }}% per bulan</b></td>
        </tr>
    </table>

    <p class="justify" style="margin-top: 12px;">
        Demikian, berita acara ini dibuat sekaligus sebagai bukti pencairan dana pinjaman di atas.
    </p>

    <table class="ttd">
        <tr>
            <td width="50%">&nbsp;</td>
            <td width="25%">&nbsp;</td>
            <td width="25%">&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td align="center" colspan="2">{{ $district ? $district.', ' : '' }}{{ $todayLabel }}</td>
        </tr>
        <tr>
            <td align="center">{{ $managerTitle }} {{ $legalName }}</td>
            <td colspan="2" align="center">Peminjam</td>
        </tr>
        <tr>
            <td colspan="3" height="40">&nbsp;</td>
        </tr>
        <tr>
            <td align="center" style="font-weight: bold;">{{ $managerName }}</td>
            <td colspan="2" align="center" style="font-weight: bold;">{{ $borrowerName }}</td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center;">{!! $signature ?? '' !!}</td>
        </tr>
    </table>
</body>
</html>