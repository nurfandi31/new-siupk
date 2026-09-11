@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $jasaPersen = (float) $tokens['{jasa_persen}'];
    $termMonths = (int) $loan['term_months'];
    $alokasiTotal = 0;
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
                <b>DAFTAR PENERIMAAN PREMI</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="20%">Kelompok</td>
        <td align="center" width="2%">:</td>
        <td width="28%">
            <b>{{ $group['name'] }} / {{ $loan['id'] }}</b>
        </td>
        <td width="20%">Tanggal Cair</td>
        <td align="center" width="2%">:</td>
        <td width="28%">
            <b>{{ $disbursementDate }}</b>
        </td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td align="center">:</td>
        <td>
            <b>{{ $group['address'] }}</b>
        </td>
        <td>Alokasi Piutang</td>
        <td align="center">:</td>
        <td>
            <b>Rp. {{ $proposedAmount }}</b>
        </td>
    </tr>
    <tr>
        <td>
            {{ '' }}
        </td>
        <td align="center">:</td>
        <td>
            <b>{{ $group['village'] }}</b>
        </td>
        <td>Alokasi Piutang</td>
        <td align="center">:</td>
        <td>
            <b>{{ '' }} ({{ $termMonths }} Bulan)</b>
        </td>
    </tr>
    <tr>
        <td>Ketua</td>
        <td align="center">:</td>
        <td>
            <b>{{ $committeeChair }}</b>
        </td>
        <td>Sistem Bagi Hasil</td>
        <td align="center">:</td>
        <td>
            <b>{{ number_format($jasaPersen / max(1, $termMonths), 2) }}%/Bulan, {{ '' }}</b>
        </td>
    </tr>
</table>

<table border="1" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <th rowspan="2" width="4%">No</th>
        <th rowspan="2" width="16%">Nama Anggota</th>
        <th rowspan="2" width="20%">TTL</th>
        <th colspan="2" width="20%">Piutang</th>
        <th rowspan="2" width="10%">Jumlah</th>
        <th rowspan="2" width="10%">Premi</th>
        <th rowspan="2" width="10%">Ket.</th>
        <th rowspan="2" width="10%">TTD</th>
    </tr>
    <tr>
        <td width="10%">Pokok</td>
        <td width="10%">Jasa</td>
    </tr>

    @php
        $tJasa = 0;
        $tPokok = 0;
        $tAsuransi = 0;
        $no = 1;
    @endphp
    @foreach ($beneficiaries as $b)
        @php
            $pokok = (float) $b['allocated_amount'];
            $jasa = $pokok * ($jasaPersen / 100);
            $asuransi = 0;
            $tJasa += $jasa;
            $tPokok += $pokok;
            $tAsuransi += $asuransi;
        @endphp
        <tr>
            <td align="center">{{ $no++ }}</td>
            <td>{{ $b['name'] }}</td>
            <td>
                {{ '' }}, {{ '' }}
            </td>
            <td align="right">{{ number_format((float) $pokok, 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) $jasa, 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) ($pokok + $jasa), 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) $asuransi, 0, ',', '.') }}</td>
            <td></td>
            <td></td>
        </tr>
    @endforeach
    <tr>
        <th colspan="3" align="center">Total</th>
        <th align="right">{{ number_format((float) $tPokok, 0, ',', '.') }}</th>
        <th align="right">{{ number_format((float) $tJasa, 0, ',', '.') }}</th>
        <th align="right">{{ number_format((float) ($tPokok + $tJasa), 0, ',', '.') }}</th>
        <th align="right">{{ number_format((float) $tAsuransi, 0, ',', '.') }}</th>
        <th></th>
        <th></th>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="2">&nbsp;</td>
    </tr>
    <tr>
        <td align="center" colspan="5">&nbsp;</td>
        <td align="center" colspan="3">
            {{ $identity['district_name'] }}, {{ $disbursementDate }}
        </td>
    </tr>
    <tr>
        <td align="center" colspan="5">
            {{ '' }}
        </td>
        <td align="center" colspan="3">
            Ketua Kelompok {{ $group['name'] }}
        </td>
    </tr>
    <tr>
        <td align="center" colspan="8" height="40">&nbsp;</td>
    </tr>
    <tr>
        <td align="center" colspan="5">
            <b>{{ '' }}</b>
        </td>
        <td align="center" colspan="3">
            <b>{{ $committeeChair }}</b>
        </td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif