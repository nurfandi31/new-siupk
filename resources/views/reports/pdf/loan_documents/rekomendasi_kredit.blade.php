@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $logoUrl = $identity['logo_url'] ?? '';
    $beneficiaryCount = count($beneficiaries);
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ str_replace('_', ' ', $documentTitle) }}</title>
    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
        }

        html {
            margin: 75.59px;
            margin-left: 94.48px;
        }

        ul,
        ol {
            margin-left: -10px;
            page-break-inside: auto !important;
        }

        header {
            position: fixed;
            top: -10px;
            left: 0px;
            right: 0px;
        }

        footer {
            position: fixed;
            bottom: -50px;
            left: 0px;
            right: 0px;
        }

        table tr th,
        table tr td {
            padding: 2px 4px;
        }

        table tr td table tr td {
            padding: 0 !important;
        }

        .break {
            page-break-after: always;
        }

        li {
            text-align: justify;
        }

        .l {
            border-left: 1px solid #000;
        }

        .t {
            border-top: 1px solid #000;
        }

        .r {
            border-right: 1px solid #000;
        }

        .b {
            border-bottom: 1px solid #000;
        }
    </style>
</head>

<body>
    <main>
        <table border="0" width="100%" cellspacing="0" cellpadding="0"
            style="font-size: 11px; position: relative; top: -20px;">
            <tr class="b">
                <td align="center">
                    @if (!empty($logoUrl))
                        <img src="{{ $logoUrl }}" width="70" alt="{{ $logoUrl }}" style="margin-bottom: 8px;">
                    @else
                        &nbsp;
                    @endif
                </td>
                <td align="center">
                    <div style="font-size: 18px;">
                        PEMERINTAH {{ strtoupper($identity['regency_name']) }}
                    </div>
                    <div style="font-size: 18px;">
                        {{ strtoupper($identity['district_name']) }}
                    </div>
                    <div style="font-size: 18px;">
                        <b>
                            {{ strtoupper($group['village']) }}
                        </b>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ $identity['address'] }}</i>
                    </div>
                </td>
            </tr>
        </table>

        <table border="0" width="85%" align="center"cellspacing="0" cellpadding="0" style="font-size: 14px;">
            <tr>
                <td align="center">
                    <div style="font-size: 18px;">
                        <b>SURAT REKOMENDASI KREDIT {{ strtoupper($loan['product_code']) }}</b>
                    </div>
                    <div style="font-size: 14px;">
                        Nomor: &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                        &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                        &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                    </div>
                </td>
            </tr>
            <tr>
                <td height="5"></td>
            </tr>
        </table>
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
            <tr>
                <td colspan="3" align="justify">
                    Yang bertanda tangan di bawah ini {{ '' }}
                    {{ $group['village'] }} menerangkan bahwa Kelompok dan Pengurus tersebut namanya di
                    bawah ini :
                </td>
            </tr>

            <tr>
                <td width="120" rowspan="3" style="vertical-align: top;">Nama Lengkap / Jabatan</td>
                <td width="5" align="center">:</td>
                <td>{{ $committeeChair }} / Ketua Kelompok</td>
            </tr>
            <tr>
                <td align="center">:</td>
                <td>
                    {{ $committeeSecretary }} / Sekretaris Kelompok
                </td>
            </tr>
            <tr>
                <td align="center">:</td>
                <td>{{ $committeeTreasurer }} / Bendahara Kelompok</td>
            </tr>
            <tr>
                <td></td>
            </tr>
            <tr>
                <td>Nama Kelompok</td>
                <td align="center">:</td>
                <td>{{ $group['name'] }}</td>
            </tr>
            <tr>
                <td>Alamat</td>
                <td align="center">:</td>
                <td>
                    {{ $group['address'] }} {{ '' }}
                    {{ $group['village'] }} {{ $identity['district_name'] }}
                    {{ $identity['regency_name'] }}
                </td>
            </tr>
            <tr>
                <td>Jumlah anggota</td>
                <td align="center">:</td>
                <td>{{ $beneficiaryCount }}
                    ({{ '' }}) Orang</td>
            </tr>
            <tr>
                <td>Jumlah Pengajuan</td>
                <td align="center">:</td>
                <td>{{ $proposedAmount }} ({{ '' }})</td>
            </tr>
            <tr>
                <td align="justify" colspan="3">
                    Benar keberadaannya dan berhak mengajukan
                    Kredit Modal {{ $loan['product_name'] }} ({{ $loan['product_code'] }}) pada
                    {{ $identity['legal_name'] }} {{ $identity['district_name'] }}
                    {{ $identity['regency_name'] }}.
                </td>
            </tr>
            <tr>
                <td align="justify" colspan="3">
                    Demikian Surat Rekomendasi ini diberikan kepada yang bersangkutan untuk dipergunakan seperlunya.
                </td>
            </tr>
        </table>

        @if ($signatureHtml)
            <div style="margin-top: 24px;">
                {!! $signatureHtml !!}
            </div>
        @else
            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
                <tr>
                    <td width="40%" height="30">&nbsp;</td>
                    <td width="60%">&nbsp;</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td align="center">{{ $identity['regency_name'] }}, {{ $proposalDate }}</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td align="center">
                        {{ '' }} {{ $group['village'] }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" height="40">&nbsp;</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td align="center">
                        <u>
                            <b>{{ '' }}</b>
                        </u>
                    </td>
                </tr>
            </table>
        @endif
    </main>
</body>
</html>