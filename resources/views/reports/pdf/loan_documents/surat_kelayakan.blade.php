@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $verificationDate = $tokens['{tgl_verifikasi}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $beneficiaryCount = count($beneficiaries);
    $todayRoman = IndonesianDate::roman($today ?? '');
    $todayLatin = IndonesianDate::latin($today ?? '');
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
        <td>Tanggal</td>
        <td align="center">:</td>
        <td colspan="2">
            {{ $todayLatin }}
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
            <b>Kelayakan Piutang</b>
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
        <td colspan="4" height="16">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td colspan="3">
            <div>Dengan hormat,</div>
            <div style="text-align: justify;">
                Dengan ini memberitahukan bahwa keputusan rapat pendanaan Perguliran {{ $identity['legal_name'] }}
                Tanggal {{ $todayLatin }}. yang merupakan tindak lanjut hasil verifikasi atas
                Proposal Permohonan Kredit dari ;
            </div>
            <table>
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
                        {{ $group['address'] }}
                        {{ '' }} {{ $group['village'] }}
                    </td>
                </tr>
                <tr>
                    <td>3.</td>
                    <td>Tanggal Proposal</td>
                    <td>:</td>
                    <td>{{ $proposalDate }}</td>
                </tr>
                <tr>
                    <td>4.</td>
                    <td>Nilai Kelayakan</td>
                    <td>:</td>
                    <td>Rp {{ $proposedAmount }}</td>
                </tr>
                <tr>
                    <td>5.</td>
                    <td>Jumlah Pemanfaat</td>
                    <td>:</td>
                    <td>{{ $beneficiaryCount }} orang</td>
                </tr>
            </table>

            <div style="text-align: justify;">
                Dinyatakan Layak/Tidak Layak didanai sebesar Rp. {{ $proposedAmount }}
                ({{ '' }}) dan dengan
                jadwal pencairan besok pada tanggal {{ $disbursementDate }} bertempat di
                {{ '' }}.
            </div>

            <div style="text-align: justify;">
                Demikian surat pemberitahuan ini kami sampaikan, atas perhatian dan kerjasamanya kami ucapkan
                terimakasih.
            </div>
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
        <td colspan="2" height="40">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">{{ '' }}</td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif