@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
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

    $principal = (float) ($loan_obj->proposed_amount ?? $loan_obj->principal_amount ?? 0);
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $installment = (float) ($loan_obj->installment_amount ?? 0);
    $installmentFmt = 'Rp ' . number_format($installment, 0, ',', '.');

    $verifiedAt = $loan_obj->verified_at ? CarbonImmutable::parse($loan_obj->verified_at)->translatedFormat('d F Y') : '—';

    $verifierName = $profile?->manager_name ?? '________________';
    $verifierTitle = $profile?->manager_title ?? 'Direktur';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Form Verifikasi Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 13pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 9pt; }
        .judul { text-align: center; font-size: 13pt; font-weight: bold; margin: 16px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        .section { font-weight: bold; margin-top: 12px; margin-bottom: 4px; font-size: 11pt; }
        .ttd { width: 100%; margin-top: 32px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">PENILAIAN PERMOHONAN PINJAMAN INDIVIDU</div>

    <div class="section">A. IDENTITAS PEMINJAM</div>
    <table>
        <tr><td width="4%" align="right">1.</td><td width="25%">Nama Peminjam</td><td>: {{ $borrowerName }}</td></tr>
        <tr><td align="right">2.</td><td>Jenis Kelamin</td><td>: {{ $borrowerGender }}</td></tr>
        <tr><td align="right">3.</td><td>NIK</td><td>: {{ $borrowerNik }}</td></tr>
        <tr><td align="right">4.</td><td>Tempat, Tanggal Lahir</td><td>: {{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate }}</td></tr>
        <tr><td align="right">5.</td><td>Alamat</td><td>: {{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td></tr>
        <tr><td align="right">6.</td><td>No. Handphone</td><td>: {{ $borrowerPhone }}</td></tr>
        <tr><td align="right">7.</td><td>Jumlah Kredit yang diminta</td><td>: {{ $principalFmt }}</td></tr>
        <tr><td align="right">8.</td><td>Angsuran per Bulan</td><td>: {{ $installmentFmt }}</td></tr>
        <tr><td align="right">9.</td><td>Jangka Waktu</td><td>: {{ $term }} bulan</td></tr>
    </table>

    <div class="section">B. INFORMASI PENDAPATAN &amp; PENGELUARAN</div>
    <table>
        <tr><td width="4%" align="right" valign="top">1.</td><td width="60%" colspan="2">Pendapatan Keluarga 1 (satu) bulan</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pendapatan dari usaha suami</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pendapatan dari usaha istri</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pendapatan dari hasil kebun, sawah, ladang</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pendapatan lain-lain</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2" align="center"><strong>Jumlah Pendapatan</strong></td><td><strong>: Rp. </strong></td></tr>
        <tr><td align="right" valign="top">2.</td><td colspan="2">Pengeluaran keluarga</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pembelian alat/barang dagangan</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pengeluaran kebutuhan makan/minum</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pengeluaran sabun-cuci-mandi</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pengeluaran untuk sekolah</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pengeluaran untuk sosial</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pengeluaran listrik, air, telpon dll</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Angsuran pinjaman di bank/koperasi/perorangan</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2">Pengeluaran lain-lain</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2" align="center"><strong>Jumlah Pengeluaran</strong></td><td><strong>: Rp. </strong></td></tr>
    </table>

    <div class="section">C. IDENTITAS JAMINAN</div>
    <table>
        <tr><td align="right" valign="top">1.</td><td colspan="2">Tabungan di Bank/Koperasi/BMT atas nama pribadi</td><td>: Rp. </td></tr>
        <tr><td align="right" valign="top">2.</td><td colspan="2">Nilai harta lain berupa ..........................</td><td>: Rp. </td></tr>
        <tr><td></td><td colspan="2" align="center"><strong>Total Nilai Jaminan</strong></td><td><strong>: Rp. </strong></td></tr>
    </table>

    <div class="section">D. PENILAIAN</div>
    <table>
        <tr><td align="right" valign="top">1.</td><td colspan="2">Rasio pendapatan keluarga (bersih) per bulan dibagi angsuran per bulan</td><td>: ............% (min 200%)</td></tr>
        <tr><td align="right" valign="top">2.</td><td colspan="2">Rasio tabungan di lembaga dibagi kredit yang diajukan</td><td>: ............% (min 20%)</td></tr>
    </table>

    <div class="section">E. KESIMPULAN</div>
    <table>
        <tr>
            <td width="45%" valign="top">
                <p style="text-align: justify;">
                    Peminjam ini <strong>LAYAK / TIDAK LAYAK</strong> *) untuk diberikan kredit sebesar:<br><br>
                    <strong><u>{{ $principalFmt }}</u></strong>
                </p>
                <p>Dengan Catatan:<br><br><br><br>*) coret yang tidak perlu.</p>
            </td>
            <td width="5%"></td>
            <td width="50%" valign="top">
                <p>Diverifikasi Pada : {{ $verifiedAt }}<br>
                Oleh : Tim Verifikasi {{ $legalName }}<br></p>
                <p style="margin-top: 16px;">
                    {{ $district ? $district.', ' : '' }}{{ $todayLabel }}<br>
                    {{ $verifierTitle }}<br><br><br><br>
                    <strong>{{ $verifierName }}</strong>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>