@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
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

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px; text-decoration: underline;">
                <b>SURAT PERNYATAAN TANGGUNG RENTENG</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<div style="text-align: justify;">
    Yang bertanda tangan di bawah ini,
    <table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
        <tr style="background: rgb(233,233,233);">
            <th width="10">No</th>
            <th width="80">NIK</th>
            <th width="100">Nama Anggota</th>
            <th width="10">JK</th>
            <th width="70">Tanda Tangan</th>
        </tr>

        @php
            $nomor = 1;
        @endphp
        @foreach ($beneficiaries as $b)
            @if ((float) $b['allocated_amount'] == 0)
                @continue
            @endif
            <tr>
                <td align="center">{{ $nomor }}.</td>
                <td align="center">{{ $b['nik'] }}</td>
                <td>{{ $b['name'] }}</td>
                <td align="center">{{ '' }}</td>
                <td>{{ $nomor }}.</td>
            </tr>

            @php
                $nomor++;
            @endphp
        @endforeach
    </table>
</div>
<div style="text-align: justify; font-size: 14px;">
    Selaku anggota pemanfaat dari Nama Kelompok {{ $group['name'] }} yang beralamatkan di
    {{ $group['address'] }} {{ '' }}
    {{ $group['village'] }}.
</div>
<div style="text-align: justify; font-size: 14px;">
    Dengan ini menyatakan, apabila terjadi tunggakan angsuran piutang {{ $loan['product_code'] }}
    {{ $identity['legal_name'] }} yang disebabkan adanya anggota pemanfaat yang belum mampu melunasi kewajibannya
    sesuai jadwal angsuran yang ditetapkan, maka masing-masing pemanfaat dalam kedudukan sebagai pribadi anggota
    kelompok, secara sadar dan penuh tanggung jawab menyatakan :

    <ol style="font-size: 14px;">
        <li>
            Sanggup menanggung pelunasan sisa angsuran dengan sistem tanggung renteng yang pelaksanaannya dikoordinir
            oleh ketua kelompok demi kelancaran penyetoran angsuran dengan batas waktu yang telah disepakati dengan
            penuh tanggung jawab apabila seluruh tabungan anggota dan hasil penjualan jaminan belum mencukupi jumlah
            kewajiban pelunasan angsuran.
        </li>
        <li>
            Sanggup menerima sanksi dari {{ $identity['legal_name'] }} yang disepakati dalam forum Musyawarah Antar
            Desa/Kelurahan
            (MAD) dan/atau penyelesaian
            secara hukum
            yang berlaku, apabila kami ingkar terhadap pernyataan ini.
        </li>
    </ol>
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px; padding: 0px;">
    <tr>
        <td style="padding: 0px !important; text-align: justify;">
            <div style="margin: 0px; padding: 0px;">
                Demikian surat pernyataan Kesanggupan Tanggung Renteng ini dibuat dengan penuh kesadaran dan tanpa
                paksaan
                dari
                pihak manapun serta untuk dipergunakan dan/ atau dilaksanakan sebagaimana mestinya.
            </div>

            @if ($signatureHtml)
                {!! $signatureHtml !!}
            @else
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 14px;">
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>
                        <td align="center">{{ $identity['district_name'] }}, {{ $disbursementDate }}</td>
                    </tr>
                    <tr>
                        <td align="center" width="50%">
                            Mengetahui,
                        </td>
                        <td align="center" width="50%">Ketua Kelompok</td>
                    </tr>
                    <tr>
                        <td align="center">
                            {{ '' }}
                            {{ $group['village'] }}
                        </td>
                        <td align="center">{{ $group['name'] }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" height="30"></td>
                    </tr>
                    <tr>
                        <td align="center">
                            <b>{{ '' }}</b>
                        </td>
                        <td align="center">
                            <b>{{ $committeeChair }}</b>
                        </td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>