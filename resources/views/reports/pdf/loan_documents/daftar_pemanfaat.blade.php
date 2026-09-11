@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $proposalTotal = 0;
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

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;">
                <b>DAFTAR CALON PEMANFAAT</b>
            </div>
            <div style="font-size: 16px; text-decoration: underline;">
                <b>PIUTANG {{ $loan['product_code'] }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
    <tr>
        <td width="70">Kelompok</td>
        <td width="5" align="right">:</td>
        <td>{{ $group['name'] }} - {{ $loan['id'] }}</td>
        <td width="70">Pengajuan</td>
        <td width="5" align="right">:</td>
        <td>Rp. {{ $proposedAmount }}</td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td align="right">:</td>
        <td>{{ $group['address'] }}</td>
        <td>Sistem Angs.</td>
        <td align="right">:</td>
        <td>{{ '' }}</td>
    </tr>
    <tr>
        <td>Tgl. Proposal</td>
        <td align="right">:</td>
        <td>{{ $proposalDate }}</td>
        <td>Pros. Jasa</td>
        <td align="right">:</td>
        <td>{{ $tokens['{jasa_persen}'] }}% / {{ $loan['term_months'] }} bulan</td>
    </tr>
    <tr>
        <td>Piutang Ke-</td>
        <td align="right">:</td>
        <td>{{ str_pad($loan['id'], 2, '0', STR_PAD_LEFT) }}</td>
        <td colspan="3"></td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px; table-layout: fixed;">
    <tr style="background: rgb(232, 232, 232)">
        <th class="t l b" height="20" width="5%">No</th>
        <th class="t l b" width="20%">Nik</th>
        <th class="t l b" width="15%">Nama Anggota</th>
        <th class="t l b" width="5%">JK</th>
        <th class="t l b" width="5%">Usia</th>
        <th class="t l b" width="15%">Penjamin</th>
        <th class="t l b" width="15%">Pengajuan</th>
        <th class="t l b r" width="10%">Ttd</th>
    </tr>

    @foreach ($beneficiaries as $b)
        <tr>
            <td class="t l b" height="15" align="center">{{ $loop->iteration }}</td>
            <td class="t l b">{{ $b['nik'] }}</td>
            <td class="t l b">{{ $b['name'] }}</td>
            <td class="t l b" align="center">{{ '' }}</td>
            <td class="t l b">{{ '' }}</td>
            <td class="t l b">{{ $b['guarantor'] }}</td>
            <td class="t l b" align="right">{{ number_format((float) $b['proposed_amount'], 0, ',', '.') }}</td>
            <td class="t l b r">&nbsp;</td>
        </tr>
        @php
            $proposalTotal += (float) $b['proposed_amount'];
        @endphp
    @endforeach

    <tr style="font-weight: bold;">
        <td class="t l b" height="15" align="center" colspan="6">JUMLAH</td>
        <td class="t l b" align="right">{{ number_format((float) $proposalTotal, 0, ',', '.') }}</td>
        <td class="t l b r">&nbsp;</td>
    </tr>

    <tr>
        <td colspan="8" style="padding: 0px !important;">
            @if ($signatureHtml)
                <div>
                    {!! $signatureHtml !!}
                </div>
            @else
                <table class="p0" border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 12px;">
                    <tr>
                        <td colspan="3" height="10">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" width="33%">Ketua</td>
                        <td align="center" width="33%">Sekretaris</td>
                        <td align="center" width="33%">Bendahara</td>
                    </tr>
                    <tr>
                        <td align="center" colspan="3" height="30">&nbsp;</td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td align="center">{{ $committeeChair }}</td>
                        <td align="center">{{ $committeeSecretary }}</td>
                        <td align="center">{{ $committeeTreasurer }}</td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>