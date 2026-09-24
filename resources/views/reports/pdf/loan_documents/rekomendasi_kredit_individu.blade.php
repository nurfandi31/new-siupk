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

    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) ($loan_obj->proposed_amount ?? $loan_obj->principal_amount ?? 0);
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $kadesName = $profile?->kepala_desa_name ?? '________________';
    $kadesNip = $profile?->kepala_desa_nip ?? '';

    $signature = $signature ?? null;
@endphp

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Rekomendasi Kredit Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        ul, ol { margin-left: -10px; page-break-inside: auto !important; }
        header { position: fixed; top: -10px; left: 0px; right: 0px; }
        footer { position: fixed; bottom: -50px; left: 0px; right: 0px; }
        table tr th, table tr td { padding: 2px 4px; }
        table tr td table tr td { padding: 0 !important; }
        .break { page-break-after: always; }
        li { text-align: justify; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
    </style>
</head>

<body>
    <main>
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; position: relative; top: -20px;">
            <tr class="b">
                <td align="center" width="120">
                    @if (!empty($identity['logo_url'] ?? null))
                        <img src="{{ $identity['logo_url'] }}" width="70" alt="logo" style="margin-bottom: 8px;">
                    @endif
                </td>
                <td align="center">
                    <div style="font-size: 18px;">
                        {{ $legalName }} {{ $regency ? strtoupper($regency) : '' }}
                    </div>
                    <div style="font-size: 18px;">
                        KECAMATAN {{ $district }}
                    </div>
                    <div style="font-size: 18px;">
                        <b>
                            DESA {{ strtoupper($villageName ?: '________________') }}
                        </b>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ $address }}</i>
                    </div>
                </td>
            </tr>
        </table>

        <table border="0" width="85%" align="center" cellspacing="0" cellpadding="0" style="font-size: 12px;">
            <tr>
                <td align="center">
                    <div style="font-size: 18px;">
                        <b>REKOMENDASI KREDIT {{ strtoupper($productName) }}</b>
                    </div>
                    <div style="font-size: 12px;">
                        Nomor:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    </div>
                </td>
            </tr>
            <tr>
                <td>&nbsp;</td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
            <tr>
                <td colspan="3" align="justify">
                    Yang bertanda tangan di bawah ini Kepala Desa {{ $villageName ?: '________' }} menerangkan dengan sebenarnya bahwa:
                </td>
            </tr>

            <tr>
                <td width="120" style="vertical-align: top;">Nama Lengkap </td>
                <td width="5" align="center">:</td>
                <td>{{ $borrowerName }}</td>
            </tr>
            <tr>
                <td width="120" style="vertical-align: top;">Jenis Kelamin </td>
                <td align="center">:</td>
                <td>{{ $borrowerGender }}</td>
            </tr>
            <tr>
                <td width="120" style="vertical-align: top;">N I K</td>
                <td align="center">:</td>
                <td>{{ $borrowerNik }}</td>
            </tr>
            <tr>
                <td width="120" style="vertical-align: top;">Tempat/Tanggal lahir </td>
                <td align="center">:</td>
                <td>{{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate }}</td>
            </tr>
            <tr>
                <td width="120" style="vertical-align: top;">Alamat </td>
                <td align="center">:</td>
                <td>{{ $borrowerAddress }} {{ $villageName ? '· '.$villageName : '' }} Kec. {{ $district ?: '________' }} {{ $regency ?: '' }}</td>
            </tr>
            <tr>
                <td width="120" style="vertical-align: top;">Telpon </td>
                <td align="center">:</td>
                <td>{{ $borrowerPhone }}</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
            </tr>

            <tr>
                <td align="justify" colspan="3">
                    Benar-benar warga Desa {{ $villageName ?: '________' }}
                    yang berkepribadian baik dan kami memberikan rekomendasi atas pengajuan kredit Modal
                    {{ $productName }} pada {{ $legalName }} Kecamatan {{ $district ?: '________' }}
                    {{ $regency ?: '' }}.
                </td>
            </tr>
            <tr>
                <td align="justify" colspan="3">
                    Demikian Surat Rekomendasi ini diberikan kepada yang bersangkutan untuk dapat dipergunakan
                    sebagaimana mestinya.
                </td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
            <tr>
                <td width="33%" height="30">&nbsp;</td>
                <td width="33%">&nbsp;</td>
                <td width="33%">&nbsp;</td>
            </tr>
            <tr>
                <td width="33%">&nbsp;</td>
                <td align="center" colspan="2">{{ $villageName ?: '________' }}, {{ $proposedAt }}</td>
            </tr>
            <tr>
                <td width="33%">&nbsp;</td>
                <td align="center" colspan="2">
                    Kepala Desa {{ $villageName ?: '________' }}
                </td>
            </tr>
            <tr>
                <td colspan="3" height="40">&nbsp;</td>
            </tr>
            <tr>
                <td width="33%">&nbsp;</td>
                <td align="center" colspan="2">
                    <u>
                        <b>{{ $kadesName }}</b>
                    </u>
                    {!! $signature ?? '' !!}
                    @if ($kadesNip)
                        <div><small>NIP. {{ $kadesNip }}</small></div>
                    @endif
                </td>
            </tr>
        </table>
    </main>
</body>