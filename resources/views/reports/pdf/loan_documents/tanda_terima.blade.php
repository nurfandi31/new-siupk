@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $alokasi = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $jasaPersen = (float) $tokens['{jasa_persen}'];
    $alokasiTotal = 0;
    $minus = 0;
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

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;">
                <b>TANDA TERIMA</b>
            </div>
            <div style="font-size: 16px;">
                <b>PIUTANG KELOMPOK {{ $loan['product_code'] }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="70">Nama Kelompok</td>
        <td width="5" align="right">:</td>
        <td>{{ $group['name'] }} - {{ $loan['id'] }}</td>
        <td width="70">Alokasi Piutang</td>
        <td width="5" align="right">:</td>
        <td>Rp. {{ $alokasi }}</td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td align="right">:</td>
        <td>{{ $group['address'] }}</td>
        <td>Sistem Angsuran</td>
        <td align="right">:</td>
        <td>{{ '' }}</td>
    </tr>
    <tr>
        <td>Tanggal Pencairan</td>
        <td align="right">:</td>
        <td>{{ $disbursementDate }}</td>
        <td>Prosentase Jasa</td>
        <td align="right">:</td>
        <td>{{ $jasaPersen }}% / {{ $loan['term_months'] }} bulan</td>
    </tr>
    <tr>
        <td>Nomor SPK</td>
        <td align="right">:</td>
        <td>{{ $loan['loan_number'] }}</td>
        <td>Piutang Ke-</td>
        <td align="right">:</td>
        <td>______________</td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
    <tr style="background: rgb(232, 232, 232)">
        <th class="t l b" width="3%" height="15">No</th>
        <th class="t l b" width="18%">Nik</th>
        <th class="t l b" width="25%">Nama Anggota</th>
        <th class="t l b" width="26%">Alamat</th>
        <th class="t l b" width="14%">Alokasi</th>
        <th class="t l b r" width="14%">Ttd</th>
    </tr>

    @foreach ($beneficiaries as $b)
        @php
            if ((float) $b['allocated_amount'] == 0) {
                $minus += 1;
                continue;
            }

            $no = $loop->iteration - $minus;
        @endphp
        <tr>
            <td class="t l b" height="15" align="center">{{ $no }}</td>
            <td class="t l b">{{ $b['nik'] }}</td>
            <td class="t l b">{{ $b['name'] }}</td>
            <td class="t l b">{{ $group['address'] }}</td>
            <td class="t l b" align="right">{{ number_format((float) $b['allocated_amount'], 0, ',', '.') }}</td>
            <td class="t l b r">{{ $no }}.</td>
        </tr>
        @php
            $alokasiTotal += (float) $b['allocated_amount'];
        @endphp
    @endforeach

    <tr>
        <td colspan="6" style="padding: 0px !important;">
            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                style="font-size: 11px; table-layout: fixed;">
                <tr style="font-weight: bold;">
                    <td class="t l b" height="15" width="72%" align="center">JUMLAH</td>
                    <td class="t l b" align="right" width="14%">{{ number_format((float) $alokasiTotal, 0, ',', '.') }}</td>
                    <td class="t l b r" width="14%">&nbsp;</td>
                </tr>
            </table>

            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                style="font-size: 11px;">
                <tr>
                    <td width="60%">&nbsp;</td>
                    <td width="60">Diterima Di</td>
                    <td width="2">:</td>
                    <td>{{ '' }}</td>
                </tr>
                <tr>
                    <td width="60%">&nbsp;</td>
                    <td width="60">Pada Tanggal</td>
                    <td width="2">:</td>
                    <td>{{ $disbursementDate }}</td>
                </tr>
            </table>

            @if (!empty($signatureHtml))
                {!! $signatureHtml !!}
            @else
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 11px;">
                    <tr>
                        <td colspan="2" height="10">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" width="50%">Mengetahui,</td>
                        <td align="center" width="50%">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center">{{ '' }}</td>
                        <td align="center">Ketua Kelompok</td>
                    </tr>
                    <tr>
                        <td align="center" colspan="2" height="50">&nbsp;</td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td align="center">{{ '' }}</td>
                        <td align="center">{{ $committeeChair }}</td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>