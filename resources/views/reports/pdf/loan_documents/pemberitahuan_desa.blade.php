@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $todayRoman = IndonesianDate::roman($today ?? '');
    $disbursementLabel = IndonesianDate::latin($loan['disbursed_at'] ?? '');
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
            ______________/{{ $todayRoman }}
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
            <b>Pemberitahuan Pencairan</b>
        </td>
    </tr>
    <tr>
        <td colspan="3">&nbsp;</td>
        <td align="left" width="140">
            <div>Kepada Yth.</div>
            <div>
                1. {{ '' }} {{ $group['village'] }},
            </div>
            <div>
                2. Ketua Kelompok {{ $group['name'] }}
            </div>
            <div>Di</div>
            <div style="font-weight: bold; text-align: center;">
                Tempat
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="4">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td colspan="3" align="justify">
            <div>Dengan hormat,</div>
            <div>
                Menindaklanjuti hasil keputusan rapat pendanaan {{ $identity['legal_name'] }}
                Tanggal {{ $disbursementLabel }} dengan ini memberitahukan bahwa akan dilakukan
                pencairan kredit kepada ;
            </div>

            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
                <tr>
                    <td width="10">1.</td>
                    <td width="120">Nama Kelompok</td>
                    <td width="5">:</td>
                    <td>{{ $group['name'] }}</td>
                </tr>
                <tr>
                    <td>2.</td>
                    <td>Alamat</td>
                    <td>:</td>
                    <td>
                        {{ ucwords(strtolower($group['address'])) }}
                        {{ '' }}
                        {{ ucwords(strtolower($group['village'])) }}
                    </td>
                </tr>
                <tr>
                    <td>3.</td>
                    <td>Tanggal Cair</td>
                    <td>:</td>
                    <td>{{ $disbursementLabel }} {{ 'Pukul ________' }}</td>
                </tr>
                <tr>
                    <td>4.</td>
                    <td>Tempat</td>
                    <td>:</td>
                    <td>{{ '' }}</td>
                </tr>
                <tr>
                    <td>5.</td>
                    <td colspan="4">
                        Data pemanfaat dan alokasi pencairannya adalah sebagai berikut :
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
    <tr>
        <th width="50">&nbsp;</th>
        <th style="background: rgb(233,233,233)" class="t l b" width="10" height="20">No</th>
        <th style="background: rgb(233,233,233)" class="t l b" width="100">Nama Pemanfaat</th>
        <th style="background: rgb(233,233,233)" class="t l b">Alamat</th>
        <th style="background: rgb(233,233,233)" class="t l b r" width="80">Alokasi (Rp)</th>
    </tr>

    @foreach ($beneficiaries as $b)
        <tr>
            <td width="50">&nbsp;</td>
            <td class="t l b" align="center">{{ $loop->iteration }}</td>
            <td class="t l b">{{ $b['name'] }}</td>
            <td class="t l b">{{ $group['address'] }}</td>
            <td class="t l b r" align="right">{{ number_format((float) $b['allocated_amount'], 0, ',', '.') }}</td>
        </tr>
    @endforeach
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
    <tr>
        <td width="50">&nbsp;</td>
        <td colspan="4">
            <p>
                Demikian surat pemberitahuan ini kami sampaikan, atas perhatian dan kerjasamanya kami
                ucapkan
                terimakasih.
            </p>

            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;"
                class="padding">
                <tr>
                    <td colspan="2">
                        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px;">
                            <tr>
                                <td width="50%" height="10">&nbsp;</td>
                                <td width="50%">&nbsp;</td>
                            </tr>
                            <tr>
                                <td>&nbsp;</td>
                                <td align="center">{{ $identity['district_name'] }}, {{ $disbursementLabel }}
                                </td>
                            </tr>
                            <tr>
                                <td>&nbsp;</td>
                                <td align="center">
                                    {{ '' }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" height="40">&nbsp;</td>
                            </tr>
                            <tr>
                                <td>&nbsp;</td>
                                <td align="center">
                                    <u>
                                        <b>{{ '' }}</b>
                                    </u>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif