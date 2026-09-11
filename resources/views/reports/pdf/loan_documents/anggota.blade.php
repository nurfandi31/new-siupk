@php
    $documentTitle = $document['label'] ?? '';
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

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;">
                <b>DAFTAR ANGGOTA</b>
            </div>
            <div style="font-size: 16px; text-decoration: underline;">
                <b>KELOMPOK {{ strtoupper($group['name']) }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr style="background: rgb(232, 232, 232)">
        <th width="10" height="20" align="center">No</th>
        <th width="80" align="center">NIK</th>
        <th width="130" align="center">Nama anggota</th>
        <th width="70" align="center">No HP</th>
        <th align="center">Alamat</th>
    </tr>
    @foreach ($beneficiaries as $b)
        @php
            $no = $loop->iteration;
        @endphp
        <tr>
            <td align="center">{{ $no }}.</td>
            <td align="center">{{ $b['nik'] }}</td>
            <td>{{ $b['name'] }}</td>
            <td align="center">{{ '' }}</td>
            <td>{{ $group['address'] }}</td>
        </tr>
    @endforeach

    @for ($i = $no + 1; $i <= 30; $i++)
        <tr>
            <td align="center">{{ $i }}.</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    @endfor
</table>