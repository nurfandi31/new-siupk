@php
    use App\Support\IndonesianNumber;
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = trim(($borrower?->address?->address_line ?? '').' '.($borrower?->village?->name ?? ''));

    $guarantor = $borrower?->guarantor?->person;
    $spouseName = strtoupper($guarantor?->full_name ?? '-');
    $spouseNik = $guarantor?->national_identity_number ?? '-';

    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $principalTerbilang = ucwords(IndonesianNumber::spelledOut($principal));

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Pernyataan/Persetujuan Suami #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 11pt; line-height: 1.5; }
        .title { text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        .num { width: 5%; text-align: center; }
        .label { width: 28%; }
        .sep { width: 3%; text-align: center; }
        .val { width: 64%; }
        .justify { text-align: justify; }
        .signature-block { margin-top: 32px; }
    </style>
</head>
<body>
    <div class="title">SURAT PERNYATAAN / PERSETUJUAN SUAMI</div>

    <div>Saya yang bertanda tangan di bawah ini:</div>
    <table>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td class="val">{{ $spouseName }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerAddress ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">No. KTP</td>
            <td class="sep">:</td>
            <td class="val">{{ $spouseNik }}</td>
        </tr>
    </table>

    <div style="margin-top: 10px;">Adalah suami/istri dari:</div>
    <table>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerName }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerAddress ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">No. KTP</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerNik }}</td>
        </tr>
    </table>

    <p class="justify" style="margin-top: 10px;">
        Menerangkan dengan sebenarnya, bahwa saya mengetahui dan menyetujui pinjaman sebesar
        {{ $principalFmt }} ({{ $principalTerbilang }}) yang akan diajukan kepada
        {{ $district ?: $legalName }} sebagai salah satu syarat pengajuan pinjaman.
    </p>
    <p class="justify">
        Sebagai bentuk tanggung jawab saya sebagai suami/istri, maka saya akan turut bertanggung jawab dalam
        melaksanakan kewajiban pengembalian dana tersebut.
    </p>
    <p class="justify">
        Demikian surat pernyataan/persetujuan ini saya buat dengan sebenarnya tanpa ada unsur paksaan dari
        pihak manapun dan untuk dapat digunakan sebagaimana mestinya.
    </p>

    <div class="signature-block">
        <table>
            <tr>
                <td style="width: 50%;">&nbsp;</td>
                <td style="width: 50%;">{{ $district ?: $legalName }}, {{ $todayLabel }}</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="text-align: center;">Suami/Istri</td>
            </tr>
            <tr>
                <td style="height: 60px;">&nbsp;</td>
                <td style="height: 60px;">&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="text-align: center; font-weight: bold;">{{ $spouseName }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
