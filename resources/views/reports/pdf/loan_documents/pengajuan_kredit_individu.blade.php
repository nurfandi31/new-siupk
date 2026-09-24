@php
    use Carbon\CarbonImmutable;
    use App\Support\IndonesianNumber;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';
    $phone = $profile?->phone ?? '';
    $managerTitle = $profile?->manager_title ?? 'Direktur';
    $managerName = $profile?->manager_name ?? '________________';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $birthPlace = $person?->birth_place ?? '';
    $birthDate = $person?->birth_date ? CarbonImmutable::parse($person->birth_date)->translatedFormat('d F Y') : '';
    $borrowerPhone = $person?->phone ?? '-';
    $borrowerGender = $person?->gender === 'P' ? 'Perempuan' : ($person?->gender === 'L' ? 'Laki-laki' : '-');
    $villageName = $borrower?->village?->name ?? '';

    $guarantor = $borrower?->guarantor?->person;
    $guarantorName = $guarantor?->full_name ?? '________________';

    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) ($loan_obj->proposed_amount ?? $loan_obj->principal_amount ?? 0);
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $principalTerb = ucwords(IndonesianNumber::spelledOut($principal));
    $term = (int) $loan_obj->term_months;
    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $signature = $signature ?? null;

    $romanMonth = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];
    $romawi = $loan_obj->proposed_at
        ? $romanMonth[(int) CarbonImmutable::parse($loan_obj->proposed_at)->format('n')] . '/' . CarbonImmutable::parse($loan_obj->proposed_at)->format('Y')
        : '________';
@endphp

<title>Surat Pengajuan Kredit Individu #{{ $loan_obj->id }}</title>

<style>
    * { font-family: Arial, Helvetica, sans-serif; }
    html { margin: 75.59px; margin-left: 94.48px; }
    ul, ol { margin-left: -10px; page-break-inside: auto !important; }
    header { position: fixed; top: -10px; left: 0px; right: 0px; }
    table tr th, table tr td { padding: 2px 4px; }
    .break { page-break-after: always; }
    li { text-align: justify; }
    .l { border-left: 1px solid #000; }
    .t { border-top: 1px solid #000; }
    .r { border-right: 1px solid #000; }
    .b { border-bottom: 1px solid #000; }
</style>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="30">&nbsp;</td>
        <td width="30">Nomor</td>
        <td width="5" align="right">:</td>
        <td width="500">
            ______/{{ strtoupper($productName) }}/{{romawi}}
        </td>
    </tr>

    <tr>
        <td width="30">&nbsp;</td>
        <td width="30">Perihal</td>
        <td width="5" align="right">:</td>
        <td width="500">
            <b>Pengajuan Pinjaman {{ $productName }}</b>
        </td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="175">&nbsp;</td>
        <td width="100">
            <div>Kepada Yth.</div>
            <div>{{ $managerTitle }}</div>
            <div>{{ $legalName }}</div>
            <div>{{ $district ? 'Kec. ' . $district : '' }}</div>
            <div>Di Tempat</div>
        </td>
    </tr>
    <tr>
        <td colspan="2">&nbsp;</td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3">&nbsp;</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td colspan="3">Yang bertanda tangan di bawah ini :</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td width="80">Nama Lengkap</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">{{ $borrowerName }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Jenis Kelamin</td>
        <td width="5" align="right">:</td>
        <td>{{ $borrowerGender }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>NIK</td>
        <td width="5" align="right">:</td>
        <td>{{ $borrowerNik }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Tempat/Tanggal lahir</td>
        <td width="5" align="right">:</td>
        <td>{{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Alamat</td>
        <td width="5" align="right">:</td>
        <td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Telpon</td>
        <td width="5" align="right">:</td>
        <td>{{ $borrowerPhone }}</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
    </tr>

    <td width="30">&nbsp;</td>
    <td colspan="3">
        <div style="text-align: justify;">
            Dalam hal ini bertindak untuk dan atas nama diri sendiri, dengan ini bermaksud mengajukan
            permohonan kredit sebesar {{ $principalFmt }}
            ({{ $principalTerb }}) untuk memenuhi kebutuhan tambahan modal usaha.
            Kredit atau pinjaman tersebut di atas, akan kami
            kembalikan dalam jangka waktu {{ $term }} bulan.
        </div>
        <div>
            Sebagai bahan pertimbangan, bersama ini kami lampirkan:
        </div>
        <ol>
            <li>Fotokopi KTP dan KK;</li>
            <li>Surat Rekomendasi dari Kepala Desa/Lurah;</li>
            <li>Surat Kesanggupan Penyerahan Jaminan;</li>
            <li>Surat Pernyataan Peminjam;</li>
            <li>Tabel Rencana Angsuran;</li>
        </ol>
        <div>Demikian permohonan kami, atas perhatiannya kami ucapkan terima kasih.</div>
    </td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 40px;">
    <tr>
        <td width="30%" class="style9 align-justify">&nbsp;</td>
        <td align="center"></td>
        <td width="30%" class="style9 align-justify">
            <div align="center">{{ $district ? $district.', ' : '' }}{{ $proposedAt }}<br>
            </div>
        </td>
    </tr>
    <tr>
        <td align="center">&nbsp;<br>&nbsp;<br>Penjamin</td>
        <td width="20%" align="center" class="style9 align-justify">Mengetahui,</td>
        <td align="center">&nbsp;<br>&nbsp;<br>Pemohon</td>
    </tr>
    <tr>
        <td colspan="3" height="40">&nbsp;</td>
    </tr>
    <tr>
        <td align="center">
            <b>{{ $guarantorName }}</b>
        </td>
        <td align="center">
            <b>{!! $signature ?? '' !!}</b>
        </td>
        <td align="center">
            <b>{{ $borrowerName }}</b>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="30"></td>
    </tr>
</table>