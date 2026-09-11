@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
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

@foreach ($beneficiaries as $b)
    @if ($loop->iteration > 1)
        <div class="break"></div>
    @endif

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="3" align="center">
                <div style="font-size: 16px; text-decoration: underline;">
                    SURAT PERNYATAAN AHLI WARIS
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="3" height="5"></td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td colspan="3">
                Yang bertanda tangan dibawah ini,
            </td>
        </tr>
        <tr>
            <td width="28%">Nama Penjamin</td>
            <td width="2%" align="center">:</td>
            <td width="70%">{{ $b['guarantor'] }}</td>
        </tr>
        <tr>
            <td>Nik/No KK.</td>
            <td align="center">:</td>
            <td>{{ '' }}/{{ '' }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td align="center">:</td>
            <td>
                {{ $group['address'] }}
                {{ '' }} {{ $group['village'] }}
            </td>
        </tr>
        <tr>
            <td>Hubungan dengan Peminjam</td>
            <td align="center">:</td>
            <td>
                {{ '' }}
            </td>
        </tr>
        <tr>
            <td colspan="3" align="justify">
                <p>
                    Adalah benar-benar ahli waris dari <b>{{ $b['name'] }}</b> Dengan ini menyatakan
                    bersedia menanggung beban piutang {{ $loan['product_code'] }} sampai lunas. Apabila terjadi
                    hal-hal yang tidak diinginkan yang menyebabkan peminjam tidak bisa melunasi kewajibannya seperti :
                    Meninggal Dunia, Melarikan Diri, Berpindah domisili di luar desa, gangguan kejiwaan, sakit parah,
                    dll.
                </p>
                <p>
                    Demikian Surat Pernyataan Ahli Waris ini saya buat tanpa ada paksaan dari pihak manapun.
                </p>
            </td>
        </tr>
    </table>

    @if (!empty($signatureHtml))
        {!! $signatureHtml !!}
    @else
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td align="center" width="50%">&nbsp;</td>
                <td align="center" width="50%">{{ $identity['district_name'] }}, {{ $disbursementDate }}</td>
            </tr>
            <tr>
                <td align="center">Peminjam</td>
                <td align="center">Nama Penjamin</td>
            </tr>

            <tr>
                <td align="center" colspan="2" height="30">&nbsp;</td>
            </tr>
            <tr style="font-weight: bold;">
                <td align="center">{{ $b['name'] }}</td>
                <td align="center">{{ $b['guarantor'] }}</td>
            </tr>
        </table>
    @endif
@endforeach