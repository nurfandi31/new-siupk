@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $alokasi = number_format((float) $loan['principal_amount'], 0, ',', '.');
@endphp

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

    table tr th,
    table tr td {
        padding: 2px 4px;
    }

    table.p tr th,
    table.p tr td {
        padding: 4px 4px;
    }

    table.p0 tr th,
    table.p0 tr td {
        padding: 0px !important;
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

<title>{{ $documentTitle }}</title>

<div style="padding: 60px; padding-top: 0px; border: 1px solid #000; height: 82%;">
    <table border="0" width="100%" class="p">
        <tr>
            <td colspan="3" height="40" align="center" style="text-transform: uppercase; font-size: 16px;">
                <b>K u i t a n s i</b>
            </td>
        </tr>
        <tr>
            <td width="90">Telah Diterima Dari</td>
            <td width="10" align="center">:</td>
            <td class="b">
                <b>{{ '' }}</b>
            </td>
        </tr>
        <tr>
            <td>Uang Sebanyak</td>
            <td align="center">:</td>
            <td class="b">
                <b>{{ '' }} Rupiah</b>
            </td>
        </tr>
        <tr>
            <td>Untuk Pembayaran</td>
            <td align="center">:</td>
            <td class="b">
                <b>Pencairan Piutang Kel. {{ $group['name'] }}</b>
            </td>
        </tr>
        <tr>
            <td colspan="2">&nbsp;</td>
            <td class="b">
                <b>
                    Beralamat Di {{ $group['address'] }}
                    {{ '' }}
                    {{ $group['village'] }}
                </b>
            </td>
        </tr>
        <tr>
            <td colspan="2">&nbsp;</td>
            <td class="b">
                <b>Loan ID. {{ $loan['id'] }} &mdash; SPK No. {{ $loan['loan_number'] }}</b>
            </td>
        </tr>
        <tr>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan="2" class="t b" align="center">
                Rp. {{ $alokasi }}
            </td>
            <td>&nbsp;</td>
        </tr>
    </table>

    @if ($signatureHtml)
        {!! $signatureHtml !!}
    @else
        <table border="0" width="100%" style="font-size: 11px;">
            <tr>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
                <td width="10%">&nbsp;</td>
            </tr>
            <tr>
                <td colspan="6">&nbsp;</td>
                <td colspan="3" align="center">
                    {{ $identity['district_name'] }}, {{ $disbursementDate }}
                </td>
            </tr>
            <tr>
                <td align="center" colspan="6">
                    Dikeluarkan Oleh
                </td>
                <td align="center" colspan="3">
                    Diterima Oleh
                </td>
            </tr>
            <tr>
                <td align="center" colspan="3">
                    {{ '' }}
                </td>
                <td align="center" colspan="3">
                    {{ '' }}
                </td>
                <td align="center" colspan="3">
                    Ketua Kelompok
                </td>
            </tr>
            <tr>
                <td colspan="9" height="55">&nbsp;</td>
            </tr>
            <tr>
                <td align="center" colspan="3">
                    <b>{{ '' }}</b>
                </td>
                <td align="center" colspan="3">
                    <b>{{ '' }}</b>
                </td>
                <td align="center" colspan="3">
                    <b>{{ $committeeChair }}</b>
                </td>
            </tr>
        </table>
    @endif
</div>