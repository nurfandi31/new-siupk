@php
    use App\Support\IndonesianDate;
    use App\Support\IndonesianNumber;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $disbursedYmd = $loan['disbursed_at'] ?? '';
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
                <b>SURAT PERNYATAAN</b>
            </div>
            <div style="font-size: 18px;">
                <b>KESANGGUPAN TANGGUNG RENTENG KEMATIAN</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3">Kami yang bertanda tangan di bawah ini :</td>
    </tr>
    <tr>
        <td width="15%">Nama</td>
        <td width="2%" align="center">:</td>
        <td>
            <b>{{ '' }} {{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>NIK</td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td>:</td>
        <td>
            <b>{{ '' }} {{ $identity['legal_name'] }}</b>
        </td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td colspan="3" align="justify">
            Dalam hal ini bertindak untuk dan atas nama {{ $identity['legal_name'] }} selaku pengelola dana iuran
            tanggung renteng kematian bagi pemanfaat {{ $loan['product_code'] }}, selanjutnya disebut Pihak Pertama,
            dan
        </td>
    </tr>
    <tr>
        <td>Nama</td>
        <td>:</td>
        <td>
            <b>{{ $committeeChair }}</b>
        </td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td>:</td>
        <td>
            <b>Ketua Kelompok</b>
        </td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td>:</td>
        <td>
            <b>
                {{ $group['address'] }} {{ '' }}
                {{ $group['village'] }}
            </b>
        </td>
    </tr>
    <tr>
        <td colspan="3">
            <p style="text-align: justify;">
                Dalam hal ini bertindak untuk dan atas nama diri sendiri dan anggota-anggota kelompok
                {{ $group['name'] }} yang telah memberikan kuasa secara tertulis sebagaimana Surat
                Kuasa terlampir yang menjadi bagian tidak terpisahkan dari dokumen perjanjian iuran tanggung renteng
                kematian ini, selanjutnya disebut Pihak Kedua
            </p>
            <p style="text-align: justify;">
                Pihak Pertama dan Pihak Kedua dalam kedudukan masing-masing seperti telah diterangkan di atas, pada hari
                ini {{ IndonesianDate::dayName($disbursedYmd) }} tanggal
                {{ IndonesianNumber::spelledOut((float) IndonesianDate::day($disbursedYmd)) }} bulan
                {{ IndonesianDate::monthName($disbursedYmd) }} tahun
                {{ IndonesianNumber::spelledOut((float) IndonesianDate::year($disbursedYmd)) }}, bertempat di {{ '' }} sadar
                dan suka rela menyatakan telah membuat perjanjian iuran tanggung renteng kematian bagi anggota kelompok
                pemanfaat yang meninggal dunia dengan ketentuan-ketentuan yang disepakati.
            </p>
            <p style="text-align: justify;">
                Pihak Kedua menyatakan secara sadar dan suka rela telah menanda-tangani akad atau perjanjian iuran
                tanggung renteng kematian ini, setelah terlebih dahulu membaca isi perjanjian ini kepada para Pemberi
                kuasa dengan sejelas-jelasnya dan tidak seorangpun diantaranya menyatakan keberatan.
            </p>
        </td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="2" height="24">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">{{ $identity['district_name'] }}, {{ $disbursementDate }}</td>
    </tr>
    <tr>
        <td align="center">
            Pihak Pertama
        </td>
        <td align="center">Pihak Kedua</td>
    </tr>
    <tr>
        <td colspan="2" height="30"></td>
    </tr>
    <tr>
        <td align="center">
            <b>{{ '' }} {{ '' }}</b>
        </td>
        <td align="center">
            <b>{{ $committeeChair }}</b>
        </td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif