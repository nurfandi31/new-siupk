@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $proposal = 0;
    $jasa = 0;
    $iptw = 0;
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
        <td colspan="3" align="center">
            <div style="font-size: 18px; text-decoration: underline;">
                <b>DAFTAR PENERIMA IPTW</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="100">Kode Kelompok/Loan ID</td>
        <td align="center" width="5">:</td>
        <td>
            <b>{{ $group['code'] }} / {{ $loan['id'] }}</b>
        </td>
        <td width="100">Tanggal</td>
        <td align="center" width="5">:</td>
        <td>
            <b>{{ $proposalDate }}</b>
        </td>
    </tr>
    <tr>
        <td>Kelompok</td>
        <td>:</td>
        <td>
            <b>{{ $group['name'] }}</b>
        </td>
        <td>Alokasi</td>
        <td>:</td>
        <td>
            <b>Rp. {{ $proposedAmount }}</b>
        </td>
    </tr>
    <tr>
        <td>{{ '' }}</td>
        <td>:</td>
        <td>
            <b>{{ $group['village'] }}</b>
        </td>
        <td>Nomor SPK</td>
        <td>:</td>
        <td>
            <b>{{ $loan['loan_number'] }}</b>
        </td>
    </tr>
</table>
<table border="1" width="100%" align="center"cellspacing="0" cellpadding="0"
    style="font-size: 11px; margin-top: 12px;">
    <tr style="background: rgb(232,232,232)">
        <th height="20" width="10" align="center">No</th>
        <th align="center">Nama Anggota</th>
        <th width="80" align="center">Alokasi</th>
        <th width="80" align="center">Total Jasa</th>
        <th width="80" align="center">Jumlah IPTW</th>
        <th width="70" align="center">Tanda Tangan</th>
    </tr>

    @foreach ($beneficiaries as $b)
        @php
            $_proposal = (float) $b['allocated_amount'];
            $_jasa = 0;
            $_iptw = 0;

            $proposal += $_proposal;
            $jasa += $_jasa;
            $iptw += $_iptw;
        @endphp
        <tr>
            <td height="15" align="center">{{ $loop->iteration }}</td>
            <td>{{ $b['name'] }}</td>
            <td align="right">{{ number_format((float) $_proposal, 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) $_jasa, 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) $_iptw, 0, ',', '.') }}</td>
            <td align="right">&nbsp;</td>
        </tr>
    @endforeach

    <tr style="font-weight: bold;">
        <td align="center" colspan="2">Total</td>
        <td align="right">{{ number_format((float) $proposal, 0, ',', '.') }}</td>
        <td align="right">{{ number_format((float) $jasa, 0, ',', '.') }}</td>
        <td align="right">{{ number_format((float) $iptw, 0, ',', '.') }}</td>
        <td align="right">&nbsp;</td>
    </tr>
</table>
<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="50%">&nbsp;</td>
        <td width="50%">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">Mengetahui,</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">Ketua Kelompok {{ $group['name'] }}</td>
    </tr>
    <tr>
        <td colspan="2" height="40"></td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">
            <b>{{ $committeeChair }}</b>
        </td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif