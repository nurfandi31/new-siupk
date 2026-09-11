@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $documentTitle = $document['label'] ?? '';
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
                <b>Susunan Pengurus</b>
            </div>
            <div style="font-size: 16px; text-decoration: underline;">
                <b>Kelompok {{ $group['name'] }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="40">Kode Kelompok</td>
        <td width="5" align="right">:</td>
        <td width="150">
            <b>{{ $group['code'] }}</b>
        </td>
        <td width="40">Tanggal</td>
        <td width="5" align="right">:</td>
        <td width="150">
            <b>{{ $proposalDate }}</b>
        </td>
    </tr>
    <tr>
        <td>Nama Kelompok</td>
        <td align="right">:</td>
        <td>
            <b>{{ $group['name'] }}</b>
        </td>
        <td>Ketua</td>
        <td align="right">:</td>
        <td>
            <b>{{ $committeeChair }}</b>
        </td>
    </tr>
    <tr>
        <td>Desa/Kelurahan</td>
        <td align="right">:</td>
        <td>
            <b>{{ $group['village'] }}</b>
        </td>
        <td>Telepon</td>
        <td align="right">:</td>
        <td>
            <b>{{ $identity['phone'] }}</b>
        </td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px; margin-top: 12px;">
    <tr style="background: rgb(232, 232, 232)">
        <th class="l t b" height="16" width="10" align="center">No</th>
        <th class="l t b" width="150" align="center">Jabatan</th>
        <th class="l t b r" width="150" align="center">Nama</th>
    </tr>
    <tr>
        <td class="l t b" height="14" align="center">1.</td>
        <td class="l t b">
            Ketua Kelompok
        </td>
        <td class="l t b r">{{ $committeeChair }}</td>
    </tr>
    <tr>
        <td class="l t b" height="14" align="center">2.</td>
        <td class="l t b">
            Sekretaris Kelompok
        </td>
        <td class="l t b r">{{ $committeeSecretary }}</td>
    </tr>
    <tr>
        <td class="l t b" height="14" align="center">3.</td>
        <td class="l t b">Bendahara Kelompok</td>
        <td class="l t b r">{{ $committeeTreasurer }}</td>
    </tr>
    <tr>
        <td colspan="3">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="2">&nbsp;</td>
        <td align="center">{{ $identity['district_name'] }}, {{ $proposalDate }}</td>
    </tr>
    <tr>
        <td colspan="2">&nbsp;</td>
        <td align="center">
            Ketua Kelompok
        </td>
    </tr>
    <tr>
        <td colspan="3" height="30">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="2">&nbsp;</td>
        <td align="center">
            <b>
                <u>{{ $committeeChair }}</u>
            </b>
        </td>
    </tr>
</table>