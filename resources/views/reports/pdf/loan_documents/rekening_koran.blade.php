@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $alokasi = (float) $loan['principal_amount'];
    $alokasiJasa = 0;
    $tPokok = 0;
    $tJasa = 0;
    $tDenda = 0;
    $termMonths = (int) $loan['term_months'];
    $jasaPersen = (float) $tokens['{jasa_persen}'];
    $alokasiJasa = $alokasi * ($jasaPersen / 100);
    $transaksiList = $transaksi ?? [];
    $alokasiFormatted = number_format((float) $alokasi, 0, ',', '.');
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
            <div style="font-size: 18px;">
                <b>REKENING KORAN</b>
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
<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="90">Loan ID.</td>
        <td width="5" align="center">:</td>
        <td>
            <b>{{ $group['name'] }} &ndash; {{ $loan['id'] }}</b>
        </td>
        <td width="90">Jangka waktu</td>
        <td width="5" align="center">:</td>
        <td>
            <b>{{ $termMonths }} Bulan</b>
        </td>
    </tr>
    <tr>
        <td>No. SPK</td>
        <td align="center">:</td>
        <td>
            <b>{{ $loan['loan_number'] }}</b>
        </td>
        <td>Sistem Angsuran</td>
        <td align="center">:</td>
        <td>
            <b>{{ '' }} {{ $termMonths }} Kali</b>
        </td>
    </tr>
    <tr>
        <td>Tanggal Pencairan</td>
        <td align="center">:</td>
        <td>
            <b>{{ $disbursementDate }}</b>
        </td>
        <td>Jenis Jasa</td>
        <td align="center">:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Alokasi Piutang</td>
        <td align="center">:</td>
        <td>
            <b>Rp. {{ $alokasiFormatted }}</b>
        </td>
        <td>Prosentase Jasa</td>
        <td align="center">:</td>
        <td>
            <b>{{ round($jasaPersen / max(1, $termMonths), 2) }}% per bulan</b>
        </td>
    </tr>
</table>

<table border="0" width="100%" align="center" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(233,233,233)">
        <th class="t l b" width="10" align="center" height="20">No</th>
        <th class="t l b" width="40" align="center">Tanggal</th>
        <th class="t l b" width="40" align="center">ID.Trx</th>
        <th class="t l b" align="center">Keterangan</th>
        <th class="t l b" width="50" align="center">Pencairan</th>
        <th class="t l b" width="50" align="center">Pokok</th>
        <th class="t l b" width="50" align="center">Jasa</th>
        <th class="t l b" width="50" align="center">Denda</th>
        <th class="t l b r" width="10" align="center">P</th>
    </tr>
    <tr>
        <th class="l b" colspan="4" align="center">Target pembayaran</th>
        <th class="l b" align="right">0</th>
        <th class="l b" align="right">0</th>
        <th class="l b" align="right">0</th>
        <th class="l b" align="right">0</th>
        <th class="l b r"></th>
    </tr>

    @foreach ($transaksiList as $trx)
        <tr>
            <td class="l r" align="center">{{ $loop->iteration }}.</td>
            <td class="l r" align="center">{{ $trx['tgl_transaksi'] ?? '' }}</td>
            <td class="l r" align="center">{{ $trx['idt'] ?? '' }}</td>
            <td class="l r">{{ $trx['keterangan'] ?? '' }}</td>
            <td class="l r" align="right">{{ number_format((float) ($trx['pencairan'] ?? 0), 0, ',', '.') }}</td>
            <td class="l r" align="right">{{ number_format((float) ($trx['pokok'] ?? 0), 0, ',', '.') }}</td>
            <td class="l r" align="right">{{ number_format((float) ($trx['jasa'] ?? 0), 0, ',', '.') }}</td>
            <td class="l r" align="right">{{ number_format((float) ($trx['denda'] ?? 0), 0, ',', '.') }}</td>
            <td class="l r" align="center">{{ $trx['inisial'] ?? '' }}</td>
        </tr>
    @endforeach

    <tr>
        <th class="l t" colspan="4" align="center">Jumlah Pembayaran</th>
        <th class="l t">{{ $alokasiFormatted }}</th>
        <th class="l t">{{ number_format((float) $tPokok, 0, ',', '.') }}</th>
        <th class="l t">{{ number_format((float) $tJasa, 0, ',', '.') }}</th>
        <th class="l t">{{ number_format((float) $tDenda, 0, ',', '.') }}</th>
        <th class="l t r"></th>
    </tr>
    <tr>
        <th class="t l b" colspan="4" align="center">Saldo</th>
        <th class="t l b">{{ number_format(0, 0, ',', '.') }}</th>
        <th class="t l b">{{ number_format((float) ($alokasi - $tPokok), 0, ',', '.') }}</th>
        <th class="t l b">{{ number_format((float) ($alokasiJasa - $tJasa), 0, ',', '.') }}</th>
        <th class="t l b">{{ number_format((float) $tDenda, 0, ',', '.') }}</th>
        <th class="t l b r"></th>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif