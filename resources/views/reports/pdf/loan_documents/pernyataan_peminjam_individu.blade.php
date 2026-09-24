@php
    use Carbon\CarbonImmutable;
    use App\Support\IndonesianNumber;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $regency = strtoupper($profile?->regency_name ?? '');

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $borrowerGender = $person?->gender === 'P' ? 'Perempuan' : ($person?->gender === 'L' ? 'Laki-laki' : '-');
    $birthPlace = $person?->birth_place ?? '';
    $birthDate = $person?->birth_date ? CarbonImmutable::parse($person->birth_date)->translatedFormat('d F Y') : '';
    $villageName = $borrower?->village?->name ?? '';
    $borrowerPhone = $person?->phone ?? '-';

    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $principalTerb = ucwords(IndonesianNumber::spelledOut($principal));

    $guarantor = $borrower?->guarantor?->person;
    $guarantorName = $guarantor?->full_name ?? '________________';

    $collateral = is_array($loan_obj->collateral) ? $loan_obj->collateral : [];

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';
    $secretaryName = $profile?->secretary_name ?? '________________';
    $secretaryTitle = $profile?->secretary_title ?? 'Sekretaris';
    $treasurerName = $profile?->treasurer_name ?? '________________';
    $treasurerTitle = $profile?->treasurer_title ?? 'Bendahara';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Pernyataan Peminjam Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .judul { text-align: center; font-size: 14pt; font-weight: bold; text-decoration: underline; margin: 18px 0 12px; }
        .tabel { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .tabel td { padding: 4px 6px; vertical-align: top; }
        ol { margin-left: 8px; }
        ol li { text-align: justify; margin-bottom: 4px; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
    </div>

    <div class="judul">SURAT PERNYATAAN PEMINJAM INDIVIDU</div>

    <p style="text-align: justify;">Yang bertanda tangan di bawah ini,</p>

    <table class="tabel">
        <tr><td width="160">Nama Lengkap</td><td width="10">:</td><td>{{ $borrowerName }}</td></tr>
        <tr><td>Jenis Kelamin</td><td>:</td><td>{{ $borrowerGender }}</td></tr>
        <tr><td>Tempat, Tanggal Lahir</td><td>:</td><td>{{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate }}</td></tr>
        <tr><td>NIK</td><td>:</td><td>{{ $borrowerNik }}</td></tr>
        <tr><td>Alamat</td><td>:</td><td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }}</td></tr>
        <tr><td>Telpon</td><td>:</td><td>{{ $borrowerPhone }}</td></tr>
    </table>

    <p>Dengan ini menyatakan dengan sebenarnya dan pernyataan ini tidak dapat ditarik kembali, bahwa:</p>

    <ol>
        <li>
            Saya selaku pemanfaat pinjaman individu pada {{ $legalName }} Kecamatan {{ $district ?: '________' }}
            {{ $regency ?: '' }} melalui Desa {{ $villageName ?: '________' }}, benar-benar telah meminjam uang
            sebesar {{ $principalFmt }} ({{ $principalTerb }}), dengan jaminan berupa:
            <ul style="list-style: disc;">
                @if (! empty($collateral))
                    <li>
                        <table border="0" width="100%" cellspacing="0" cellpadding="5" style="font-size: 11px; border-collapse: collapse;">
                            @foreach ($collateral as $key => $value)
                                <tr>
                                    <td height="12" width="120">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                                    <td width="10" align="center">:</td>
                                    <td>
                                        @if (is_numeric($value))
                                            Rp {{ number_format((float) $value, 0, ',', '.') }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </li>
                @else
                    <li>
                        <table border="0" width="100%" cellspacing="0" cellpadding="5" style="font-size: 11px; border-collapse: collapse;">
                            <tr>
                                <td height="12" width="120">Jenis Jaminan</td>
                                <td width="10" align="center">:</td>
                                <td>________________</td>
                            </tr>
                            <tr>
                                <td height="12" width="120">Nilai Jual</td>
                                <td width="10" align="center">:</td>
                                <td><b>Rp. _______________________</b></td>
                            </tr>
                        </table>
                    </li>
                @endif
                @for ($i = 0; $i < 2; $i++)
                    <li>
                        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                            <tr>
                                <td height="12" width="120">Nama barang</td>
                                <td width="10" align="center">:</td>
                                <td><b>________________________________________________________</b></td>
                            </tr>
                            <tr>
                                <td height="12" width="120">Nilai Jual</td>
                                <td width="10" align="center">:</td>
                                <td><b>Rp. _______________________</b></td>
                            </tr>
                        </table>
                    </li>
                @endfor
            </ul>
        </li>
        <li>
            Barang yang saya jaminkan adalah benar-benar milik saya sendiri dan bukan milik orang lain, dan saya bersedia bertanggung jawab secara hukum apabila di kemudian hari terbukti bahwa barang jaminan tersebut bukan milik saya sendiri.
        </li>
        <li>
            Saya berkewajiban merawat dan melindungi barang jaminan tersebut dan tidak akan menjual,
            menggadaikan, dan/atau memindahtangankan kepada pihak lain sebelum kredit/pinjaman saya tersebut
            lunas.
        </li>
        <li>
            Apabila terjadi kemacetan atas kredit saya tersebut, saya bersedia menyerahkan barang jaminan
            tersebut kepada pihak yang berwenang, guna menyelesaikan kredit/pinjaman saya kepada
            {{ $legalName }}.
        </li>
        <li>
            Saya berjanji akan mengembalikan pinjaman saya tersebut sesuai dengan peraturan yang ada di
            {{ $legalName }}.
        </li>
        <li>
            Apabila di kemudian hari saya melanggar isi dari surat pernyataan ini, maka saya bersedia dilaporkan
            kepada pihak yang berwajib dan/atau diproses secara hukum.
        </li>
        <li>
            Jika dikemudian hari terjadi force majeure seperti banjir, gempa bumi, tanah longsor, petir, angin
            topan, kebakaran, huru-hara, kerusuhan, pemberontakan, dan perang atau saya berhalangan tetap
            seperti sakit atau meninggal dunia yang mengakibatkan tidak dapat terpenuhinya kewajiban saya,
            maka sisa angsuran akan ditanggung oleh ahli waris.
        </li>
    </ol>

    <p style="text-align: justify;">
        Demikian surat pernyataan ini saya buat dengan sebenarnya dan dengan penuh kesadaran serta rasa tanggung jawab.
    </p>

    <table class="ttd">
        <tr>
            <td width="33%">
                Saksi 1<br><br><br><br><br>
                <strong>{{ $secretaryName }}</strong><br>
                {{ $secretaryTitle }}
            </td>
            <td width="33%">
                Saksi 2<br><br><br><br><br>
                <strong>{{ $treasurerName }}</strong><br>
                {{ $treasurerTitle }}
            </td>
            <td width="33%">
                {{ $district ? $district.', ' : '' }}{{ $todayLabel }}<br>
                Yang Menyatakan<br><br><br><br><br>
                <strong>{{ $borrowerName }}</strong>
                {!! $signature ?? '' !!}
            </td>
        </tr>
    </table>
</body>
</html>