@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
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

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;">
                <b>PROFIL KELOMPOK {{ $loan['product_code'] }}</b>
            </div>
            <div style="font-size: 16px;">
                <b>{{ $group['name'] }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="30">&nbsp;</td>
        <td width="10" align="center">A.</td>
        <td width="100">Nama kelompok</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">{{ $group['name'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">B.</td>
        <td colspan="3">Alamat Lengkap</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>1.&nbsp; Alamat</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $group['address'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>2.&nbsp; {{ 'Desa/Kelurahan' }}</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $group['village'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>3.&nbsp; Kecamatan</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $identity['district_name'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>4.&nbsp; Kabupaten</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $identity['regency_name'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>5.&nbsp; Provinsi</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ '' }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">C.</td>
        <td>Tingkat Kelompok</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ '' }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">D.</td>
        <td colspan="3">Susunan Pengurus</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>1.&nbsp;{{ 'Ketua' }}</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $committeeChair }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>2.&nbsp;{{ 'Sekretaris' }}</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $committeeSecretary }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td>3.&nbsp;Bendahara</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $committeeTreasurer }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">E.</td>
        <td>Telepon</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ $identity['phone'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">F.</td>
        <td>Tanggal Berdiri</td>
        <td align="right">:</td>
        <td style="font-weight: bold;">{{ '' }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">G.</td>
        <td colspan="3">Deskripsi Kelompok</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td align="center">&nbsp;</td>
        <td colspan="3" align="justify">Kelompok
            {{ $group['name'] }} adalah salah satu
            kelompok yang berada di
            Desa/Kelurahan {{ $group['village'] }} Kec.
            {{ $identity['district_name'] }} {{ $identity['regency_name'] }}.
            Kelompok yang diketuai oleh {{ $committeeChair }} ini berfokus pada
            jenis piutang {{ $loan['product_name'] }} ({{ $loan['product_code'] }}).
        </td>
    </tr>
    <tr>
        <td colspan="5">&nbsp;</td>
    </tr>
</table>

@if ($signatureHtml)
    {!! $signatureHtml !!}
@else
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
        <tr>
            <td width="50%">&nbsp;</td>
            <td align="center">{{ $identity['district_name'] }}, {{ $proposalDate }}</td>
        </tr>
        <tr>
            <td width="50%">&nbsp;</td>
            <td align="center">Ketua Kelompok</td>
        </tr>
        <tr>
            <td colspan="2" height="30">&nbsp;</td>
        </tr>
        <tr>
            <td width="50%">&nbsp;</td>
            <td align="center">
                <b>{{ $committeeChair }}</b>
            </td>
        </tr>
    </table>
@endif