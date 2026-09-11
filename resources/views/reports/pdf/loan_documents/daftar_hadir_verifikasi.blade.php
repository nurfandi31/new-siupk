@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $no = 0;
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

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td align="center">
            <div style="font-size: 18px; text-decoration: underline;">
                <b>DAFTAR HADIR VERIFIKASI {{ $loan['product_code'] }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td height="5"></td>
    </tr>
</table>
<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="70">Nomor Proposal</td>
        <td align="center" width="5">:</td>
        <td width="150"><b>{{ $group['code'] }} - {{ $loan['id'] }}</b></td>
        <td width="70">Tanggal</td>
        <td align="center" width="5">:</td>
        <td class="b" width="150">&nbsp;</td>
    </tr>
    <tr>
        <td>Kelompok</td>
        <td align="center">:</td>
        <td><b>{{ $group['name'] }}</b></td>
        <td>Waktu</td>
        <td align="center">:</td>
        <td class="b">&nbsp;</td>
    </tr>
    <tr>
        <td>Desa/Kelurahan/Kecamatan</td>
        <td align="center">:</td>
        <td><b>{{ $group['village'] }} / {{ $identity['district_name'] }}</b></td>
        <td>Tempat</td>
        <td align="center">:</td>
        <td class="b">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="6">&nbsp;</td>
    </tr>
</table>
<table border="1" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <th width="10" height="20" align="center">No</th>
        <th width="130" align="center">Nama Lengkap</th>
        <th width="70" align="center">Unsur / Jabatan</th>
        <th align="center">Alamat</th>
        <th width="70" align="center">Tanda Tangan</th>
    </tr>
    @foreach ($beneficiaries as $b)
        @php
            $no = $loop->iteration;
        @endphp
        <tr>
            <td height="15" align="center">{{ $no }}.</td>
            <td>
                <div style="font-size: 14px;">{{ $b['name'] }}</div>
            </td>
            <td align="center">Pemanfaat</td>
            <td>{{ $group['address'] }}</td>
            <td>{{ $no }}.</td>
        </tr>
    @endforeach

    @for ($i = $no + 1; $i <= 20; $i++)
        <tr>
            <td height="15" align="center">{{ $i }}.</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>{{ $i }}.</td>
        </tr>
    @endfor
</table>
<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="2">&nbsp;</td>
    </tr>
    <tr>
        <td width="66%">&nbsp;</td>
        <td width="33%" align="center">Mengetahui,</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">Ketua Kelompok {{ $group['name'] }}</td>
    </tr>
    <tr>
        <td colspan="2" height="40">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">
            <u>
                <b>{{ $committeeChair }}</b>
            </u>
        </td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif