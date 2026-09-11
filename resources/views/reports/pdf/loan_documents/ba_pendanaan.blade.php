@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $verificationDate = $tokens['{tgl_verifikasi}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $alokasi = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $jumlah = 0;
    $beneficiaryCount = count($beneficiaries);
    $verificationYmd = $loan['verified_at'] ?? '';
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
                <b>BERITA ACARA</b>
            </div>
            <div style="font-size: 18px;">
                <b>RAPAT PENETAPAN PENDANAAN</b>
            </div>
        </td>
    </tr>
</table>

<p style="text-align: justify;">
    Dalam rangka menindak lanjuti proses tahapan perguliran atas kelompok kelompok permohonan piutang
    {{ $identity['legal_name'] }} yang sudah diterbitkan rekomendasi pada tahapan verifikasi maka pada hari ini
    {{ IndonesianDate::dayName($verificationYmd) }} tanggal {{ IndonesianDate::day($verificationYmd) }} bulan
    {{ IndonesianDate::monthName($verificationYmd) }} tahun {{ IndonesianDate::year($verificationYmd) }} bertempat di kantor
    {{ $identity['legal_name'] }} telah dilakukan pembahasan dan ditetapkan alokasi pendanaan dan rencana tanggal
    pencairan kepada kelompok sebagai berikut:
</p>

<table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
    <thead>
        <tr style="background: rgb(232,232,232)">
            <th width="4%">No</th>
            <th width="18%">Nama Kelompok</th>
            <th width="25%">Alamat</th>
            <th width="7%">Jenis</th>
            <th width="7%">Anggota</th>
            <th width="16%">Ketua Kelompok</th>
            <th width="14%">Alokasi Pendanaan</th>
            <th width="9%">Tenor</th>
        </tr>
    </thead>

    <tbody>
        @php
            $jumlah += (float) $loan['principal_amount'];
        @endphp
        <tr>
            <td align="center">
                {{ 1 }}
            </td>
            <td>
                {{ $group['name'] }}
            </td>
            <td>
                {{ $group['address'] }} {{ '' }}
                {{ $group['village'] }}
            </td>
            <td align="center">
                {{ $loan['product_code'] }}
            </td>
            <td align="center">
                {{ $beneficiaryCount }}
            </td>
            <td>
                {{ $committeeChair }}
            </td>
            <td align="right">
                {{ $alokasi }}
            </td>
            <td align="right">
                {{ $loan['term_months'] }} Bulan
            </td>
        </tr>

        <tr style="font-weight: bold;">
            <td colspan="6" align="center">
                JUMLAH
            </td>
            <td align="right">
                {{ number_format((float) $jumlah, 0, ',', '.') }}
            </td>
            <td align="right">
                &nbsp;
            </td>
        </tr>
    </tbody>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
    <tr>
        <td style="padding: 0px !important;">
            <p style="text-align: justify;">
                Demikian Berita Acara ini dibuat dan ditanda tangani untuk menjadi dasar pencairan piutang kepada
                kelompok kelompok
                tersebut diatas.
            </p>

            <table border="0" width="100%" cellspacing="0" cellpadding="0"
                style="font-size: 11px; table-layout: fixed;" class="p0">
                <tr>
                    <td width="17%">&nbsp;</td>
                    <td width="33%">&nbsp;</td>
                    <td width="25%">Ditanda tangani di</td>
                    <td width="25%">: {{ $identity['district_name'] }} {{ $identity['district_name'] }}</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>Pada tanggal</td>
                    <td>: {{ $disbursementDate }}</td>
                </tr>

                <tr>
                    <td colspan="4">&nbsp;</td>
                </tr>
            </table>

            @if ($signatureHtml)
                {!! $signatureHtml !!}
            @else
                <table border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 11px; table-layout: fixed;" class="p0">
                    <tr class="vt">
                        <td height="20">
                            <div>{{ '' }}</div>
                            <div>
                                <b>{{ '' }}</b>
                            </div>
                        </td>
                        <td align="right">
                            <div>&nbsp;</div>
                            <div>________________________________</div>
                        </td>
                        <td colspan="2" rowspan="1">
                            <table width="100%" border="0" width="100%" cellspacing="0" cellpadding="0"
                                style="font-size: 11px; table-layout: fixed;" class="p0">
                                <tr>
                                    <td align="center">Mengetahui</td>
                                </tr>
                                <tr>
                                    <td align="center">{{ '' }}</td>
                                </tr>
                                <tr>
                                    <td height="35">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        {{ '' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endif

        </td>
    </tr>
</table>