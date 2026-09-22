@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerPhone = $person?->phone ?? '-';
    $borrowerAddress = trim(($borrower?->address?->address_line ?? '').' '.($borrower?->village?->name ?? ''));
    $villageName = $borrower?->village?->name ?? '';
    $birthPlace = $person?->birth_place ?? '';
    $birthDate = $person?->birth_date ? CarbonImmutable::parse($person->birth_date)->translatedFormat('d F Y') : '';

    $guarantor = $borrower?->guarantor?->person;
    $guarantorName = strtoupper($guarantor?->full_name ?? '-');
    $guarantorNik = $guarantor?->national_identity_number ?? '-';

    $loanNumber = $loan_obj->loan_number ?? 'PINJ-' . $loan_obj->id;
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Pernyataan Pengikat Diri Sebagai Penjamin #{{ $loan_obj->id }}</title>
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
    <div class="title">
        SURAT PERNYATAAN<br/>
        PENGIKAT DIRI SEBAGAI PENJAMIN
    </div>

    <div>Yang bertanda tangan di bawah ini:</div>
    <table>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Nama Penjamin</td>
            <td class="sep">:</td>
            <td class="val">{{ $guarantorName }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">NIK/No. KK</td>
            <td class="sep">:</td>
            <td class="val">{{ $guarantorNik }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerAddress ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Hubungan dengan Peminjam</td>
            <td class="sep">:</td>
            <td class="val">Penjamin Kredit</td>
        </tr>
    </table>

    <div style="margin-top: 12px;">Dengan ini menyatakan bahwa:</div>
    <table>
        <tr>
            <td class="num">1.</td>
            <td colspan="3">Saya <b>menyetujui dan menjamin</b> sepenuhnya peminjam sebagai berikut:</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerName }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Tempat &amp; Tgl Lahir</td>
            <td class="sep">:</td>
            <td class="val">{{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">NIK</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerNik }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerAddress ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td class="label">No. HP</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerPhone }}</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td colspan="3" class="justify" style="padding-top: 8px;">
                untuk melakukan pinjaman dana di {{ $district ?: $legalName }}, yang akan
                <b>dicairkan pada tanggal {{ $disbursedAt }}</b> sesuai kartu rencana angsuran terlampir sebagai bagian yang
                tidak terpisahkan dari Surat Perjanjian Kredit (SPK).
            </td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td colspan="3" class="justify" style="padding-top: 6px;">
                Apabila orang tersebut di atas tidak memenuhi kewajibannya (membayar angsuran dan kewajiban lainnya) sesuai
                ketentuan dalam Surat Perjanjian Kredit (SPK), maka dengan ini saya mengikatkan diri dan menjamin untuk
                membayar seluruh tagihan yang menjadi kewajiban peminjam tersebut di atas sesuai hasil perhitungan saldo
                pinjaman dan tagihan jasa serta kewajiban lainnya di {{ $district ?: $legalName }}.
            </td>
        </tr>
    </table>

    <p class="justify" style="margin-top: 12px;">
        Demikian pernyataan penjaminan ini saya buat dengan sebenar-benarnya dan merupakan bagian tidak terpisahkan dari
        Surat Perjanjian Kredit, dalam kondisi sehat lahir dan batin serta tanpa paksaan dari pihak manapun serta bersedia
        dituntut di muka hukum apabila di kemudian hari saya mengingkari pernyataan ini.
    </p>

    <div class="signature-block">
        <table>
            <tr>
                <td style="width: 50%;">&nbsp;</td>
                <td style="width: 50%;">{{ $district ?: $legalName }}, {{ $todayLabel }}</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="text-align: center;">Nama Penjamin</td>
            </tr>
            <tr>
                <td style="height: 60px;">&nbsp;</td>
                <td style="height: 60px;">&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td style="text-align: center; font-weight: bold;">{{ $guarantorName }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
