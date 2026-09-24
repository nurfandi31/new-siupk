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

    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Kuasa Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 16pt; font-weight: bold; margin: 16px 0 14px; }
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

    <div class="judul">SURAT KUASA KHUSUS</div>

    <p>Yang bertanda tangan dan/atau membubuhkan cap jempol di bawah ini:</p>

    <table>
        <tr><td width="160">Nama Lengkap</td><td width="10" align="center">:</td><td><strong>{{ $borrowerName }}</strong></td></tr>
        <tr><td>Jenis Kelamin</td><td align="center">:</td><td>{{ $borrowerGender }}</td></tr>
        <tr><td>NIK</td><td align="center">:</td><td>{{ $borrowerNik }}</td></tr>
        <tr><td>Tempat, Tanggal Lahir</td><td align="center">:</td><td>{{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate }}</td></tr>
        <tr><td>Alamat</td><td align="center">:</td><td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td></tr>
        <tr><td>Telpon / HP</td><td align="center">:</td><td>{{ $borrowerPhone }}</td></tr>
    </table>

    <p style="margin-top: 12px;">Dengan ini memberikan persetujuan dan kuasa sepenuhnya kepada:</p>

    <table>
        <tr><td width="160">Nama Lengkap</td><td width="10" align="center">:</td><td>{{ $managerName }}</td></tr>
        <tr><td>Jabatan</td><td align="center">:</td><td>{{ $managerTitle }} {{ $legalName }}</td></tr>
    </table>

    <p class="justify" style="margin-top: 12px;">
        Untuk itu, dengan ini saya memberikan <strong>kuasa khusus</strong> kepada {{ $legalName }} untuk melakukan tindakan yang diperlukan terhadap barang jaminan yang telah saya serahterimakan sebagaimana tercantum dalam Bukti Serah Terima Barang Jaminan, yang merupakan bagian tidak terpisahkan dari kelengkapan dokumen pencairan pinjaman.
    </p>
    <p class="justify">
        Apabila dalam pelaksanaan kewajiban pembayaran pinjaman terjadi keterlambatan atau kemacetan angsuran, maka saya memberikan kuasa kepada {{ $legalName }} untuk melakukan tindakan terhadap barang jaminan tersebut sesuai dengan ketentuan yang berlaku, sebagai bagian dari upaya penyelesaian kewajiban pengembalian pinjaman saya kepada {{ $legalName }}.
    </p>
    <p class="justify">
        Demikian Surat Persetujuan/Pernyataan sekaligus Surat Kuasa Khusus ini saya buat dengan sebenar-benarnya, dalam keadaan sadar dan tanpa adanya paksaan, tekanan, maupun pengaruh dari pihak mana pun, untuk dapat dipergunakan sebagaimana mestinya.
    </p>

    <table class="ttd">
        <tr>
            <td width="50%">&nbsp;</td>
            <td width="50%" align="right">{{ $district ? $district.', ' : '' }}{{ $proposedAt }}</td>
        </tr>
        <tr>
            <td align="center">Pemberi Kuasa<br><br><br><br><br>
                <strong>{{ $borrowerName }}</strong>
                {!! $signature ?? '' !!}
            </td>
            <td align="center">Penerima Kuasa<br><br><br><br><br>
                <strong>{{ $managerName }}</strong><br>
                {{ $managerTitle }}
            </td>
        </tr>
    </table>
</body>
</html>