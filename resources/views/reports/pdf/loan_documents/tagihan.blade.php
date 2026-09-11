@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $jasaPersen = (float) $tokens['{jasa_persen}'];
    $termMonths = (int) $loan['term_months'];
    $todayRoman = IndonesianDate::roman($today ?? '');
    $todayLatin = IndonesianDate::latin($today ?? '');
    $tunggakanPokok = 0;
    $tunggakanJasa = 0;
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
        <td width="50">Nomor</td>
        <td width="10" align="center">:</td>
        <td colspan="2">
            ______/DBM/{{ $todayRoman }}
        </td>
    </tr>
    <tr>
        <td>Sifat</td>
        <td align="center">:</td>
        <td colspan="2">
            Penting dan Rahasia
        </td>
    </tr>
    <tr>
        <td>Perihal</td>
        <td align="center">:</td>
        <td colspan="2">
            <b>Surat Tagihan</b>
        </td>
    </tr>
    <tr>
        <td colspan="3">&nbsp;</td>
        <td align="left" width="140">
            <div>Kepada Yth.</div>
            <div>
                @if (!empty($committeeChair))
                    Ketua {{ $committeeChair }} dan Anggota Kelompok {{ $group['name'] }}
                @else
                    Ketua dan Anggota Kelompok {{ $group['name'] }}
                @endif
            </div>
            <div>Di</div>
            <div style="text-align: center;">
                {{ strtoupper($group['village']) }}
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="4">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td colspan="3">
            <div>Dengan hormat,</div>
            <div style="text-align: justify;">
                Mendasar kepada Surat Perjanjian Kredit ({{ $loan['product_code'] }}) antara
                {{ $group['name'] }} {{ $group['village'] }} dengan
                {{ $identity['legal_name'] }} Tanggal {{ $disbursementDate }} dengan rincian piutang
                sebagai berikut ;
            </div>
            <table>
                <tr>
                    <td width="10">1.</td>
                    <td width="140">Alokasi Piutang</td>
                    <td width="5">:</td>
                    <td>
                        <b>Rp. {{ $proposedAmount }}</b>
                    </td>
                </tr>
                <tr>
                    <td>2.</td>
                    <td>Tanggal Pencairan</td>
                    <td>:</td>
                    <td>
                        <b>{{ $disbursementDate }}</b>
                    </td>
                </tr>
                <tr>
                    <td>3.</td>
                    <td>Prosentase Jasa</td>
                    <td>:</td>
                    <td>
                        <b>
                            {{ number_format($jasaPersen / max(1, $termMonths), 2) }}% per Bulan
                        </b>
                    </td>
                </tr>
                <tr>
                    <td>4.</td>
                    <td>Masa Angsuran</td>
                    <td>:</td>
                    <td>
                        <b>{{ $termMonths }} Bulan</b>
                    </td>
                </tr>
                <tr>
                    <td>5.</td>
                    <td>Sistem Angsuran</td>
                    <td>:</td>
                    <td>
                        <b>{{ '' }}</b>
                    </td>
                </tr>
            </table>

            <div style="text-align: justify;">
                dan mendasar pada catatan pembukuan kami {{ $group['name'] }} sampai dengan
                diterbitkannya Surat Tagihan ini masih tercatat memiliki tunggakan sebagai berikut ;
            </div>

            <table>
                <tr>
                    <td width="10">1.</td>
                    <td width="140">Tunggakan Pokok</td>
                    <td width="5">:</td>
                    <td>
                        <b>Rp. {{ number_format((float) $tunggakanPokok, 0, ',', '.') }}</b>
                    </td>
                </tr>
                <tr>
                    <td>2.</td>
                    <td>Tunggakan Jasa</td>
                    <td>:</td>
                    <td>
                        <b>Rp. {{ number_format((float) $tunggakanJasa, 0, ',', '.') }}</b>
                    </td>
                </tr>
                <tr>
                    <td>3.</td>
                    <td><b>Total Tunggakan (Pokok+jasa)</b></td>
                    <td>:</td>
                    <td>
                        <b>Rp. {{ number_format((float) ($tunggakanPokok + $tunggakanJasa), 0, ',', '.') }}</b>
                    </td>
                </tr>
            </table>

            <p style="text-align: justify;">
                Demikian surat ini kami sampaikan, apabila terjadi perbedaan hasil perhitungan angsuran/ tunggakan
                mohon untuk melakukan klarifikasi dengan {{ $identity['legal_name'] }} dan terima kasih untuk
                segera melakukan pelunasan tunggakan piutangnya
            </p>
        </td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
    <tr>
        <td colspan="2" height="24">&nbsp;</td>
    </tr>
    <tr>
        <td width="50%">&nbsp;</td>
        <td width="50%" align="center">
            {{ $identity['district_name'] }}, {{ $todayLatin }}
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">{{ '' }}</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center" valign="bottom" height="60">
            {{ '' }}
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center"><b>{{ '' }}</b></td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif