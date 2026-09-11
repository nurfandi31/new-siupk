@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $beneficiaryCount = count($beneficiaries);
    $catatanList = $catatan ?? [];
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

@foreach ($catatanList as $ct)
    @if ($loop->iteration > 1)
        <div class="break"></div>
    @endif

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td colspan="3" align="center">
                <div style="font-size: 18px;">
                    <b>CATATAN BIMBINGAN KELOMPOK</b>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="3" height="5"></td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td width="22%">Nama Kelompok</td>
            <td width="2%" align="center">:</td>
            <td width="25%">{{ $group['name'] }} - {{ $loan['id'] }}</td>

            <td width="2%">&nbsp;</td>

            <td width="22%">Pengajuan</td>
            <td width="2%" align="center">:</td>
            <td width="25%">Rp. {{ $proposedAmount }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td align="center">:</td>
            <td>{{ $group['address'] }}</td>

            <td>&nbsp;</td>

            <td>Pencairan</td>
            <td align="center">:</td>
            <td>Rp. {{ $proposedAmount }}</td>
        </tr>
        <tr>
            <td>Tanggal Cair</td>
            <td align="center">:</td>
            <td>{{ $disbursementDate }}</td>

            <td>&nbsp;</td>

            <td>Jumlah Pemanfaat</td>
            <td align="center">:</td>
            <td>
                @if ($beneficiaryCount > 0)
                    {{ $beneficiaryCount }} orang
                @else
                    Tidak ada
                @endif
            </td>
        </tr>
        <tr>
            <td>Nomor SPK</td>
            <td align="center">:</td>
            <td>{{ $loan['loan_number'] }}</td>

            <td colspan="4">&nbsp;</td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td width="22%">Hasil Catatan Bimbingan</td>
            <td width="2%" align="center">:</td>
            <td width="26%">&nbsp;</td>
            <td width="50%">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="4">
                {!! $ct['catatan'] ?? '' !!}
            </td>
        </tr>

        <tr>
            <td colspan="4">&nbsp;</td>
        </tr>

        <tr>
            <td colspan="3">&nbsp;</td>
            <td align="center">{{ $identity['district_name'] }}, {{ IndonesianDate::latin($ct['tanggal'] ?? '') }}</td>
        </tr>
        <tr>
            <td colspan="4" height="40">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="3">&nbsp;</td>
            <td align="center">{{ '' }}</td>
        </tr>
    </table>
@endforeach

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif