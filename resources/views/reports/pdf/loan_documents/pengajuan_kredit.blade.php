@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $beneficiaryCount = count($beneficiaries);
    $alokasi = $tokens['{alokasi}'] ?? '';
    $termMonths = (int) $loan['term_months'];
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
    <tr class="b">
        <td colspan="3" align="center">
            <div style="font-size: 16px;">
                KELOMPOK PIUTANG {{ $loan['product_code'] }}
            </div>
            <div style="font-size: 20px;">
                <b>{{ $group['name'] }}</b>
            </div>
            <div style="font-size: 14px;">
                Alamat : {{ $group['address'] }}
                {{ $group['village'] }}
                {{ $identity['district_name'] }} {{ $identity['regency_name'] }} Telp: {{ $identity['phone'] }}
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="30">&nbsp;</td>
        <td width="30">Nomor</td>
        <td width="5" align="right">:</td>
        <td width="500">
            <b>
                ______/{{ $loan['product_code'] }}/{{ $proposalDate }}
            </b>
        </td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td width="30">Perihal</td>
        <td width="5" align="right">:</td>
        <td width="500">
            <b>Permohonan Kredit {{ $loan['product_code'] }}</b>
        </td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="175">&nbsp;</td>
        <td width="100">
            <b>
                <div>Kepada Yth.</div>
                <div>{{ '' }}</div>
                <div>{{ $identity['legal_name'] }}</div>
                <div>{{ $identity['district_name'] }}</div>
                <div>Di Tempat</div>
            </b>
        </td>
    </tr>

</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="30">&nbsp;</td>
        <td colspan="3">Yang bertanda tangan di bawah ini :</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td width="80">Nama Lengkap</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">{{ $committeeChair }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Alamat</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">{{ $group['village'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Jabatan</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">{{ 'Ketua Kelompok' }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Nama Lengkap</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">{{ $committeeSecretary }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Alamat</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">{{ $group['village'] }}</td>
    </tr>
    <tr>
        <td width="30">&nbsp;</td>
        <td>Jabatan</td>
        <td width="5" align="right">:</td>
        <td style="font-weight: bold;">
            {{ 'Sekretaris Kelompok' }}
        </td>
    </tr>
    <tr>
        <td colspan="4">
            <table border="0" width="100%" cellspacing="0" cellpadding="0">
                <td width="30">&nbsp;</td>
                <td colspan="3">
                    <div>
                        Dalam hal ini bertindak untuk dan atas nama seluruh anggota kelompok
                        {{ $loan['product_name'] }} ({{ $loan['product_code'] }})
                        {{ $group['name'] }} (daftar anggota terlampir), dengan ini bermaksud
                        mengajukan
                        permohonan kredit sebesar {{ $alokasi }}
                        ({{ '' }}) untuk memenuhi kebutuhan tambahan modal usaha
                        bagi
                        {{ $beneficiaryCount }} anggota. Kredit atau piutang tersebut di atas, akan kami
                        kembalikan dalam jangka waktu {{ $termMonths }} bulan, dengan sistem angsuran
                        {{ '' }} ({{ '' }}).
                    </div>
                    <div>
                        Sebagai bahan pertimbangan, bersama ini kami lampirkan:
                        <ol>
                            <li>Fotokopi KTP dari {{ $beneficiaryCount }} orang anggota kelompok kami
                                yang
                                mengajukan kredit;</li>
                            <li>Surat Rekomendasi dari Kepala Desa/Lurah;</li>
                            <li>Pernyataan kesediaan tanggung renteng dari seluruh anggota;</li>
                            <li>Surat pengakuan utang dan pertanggungan ahli waris</li>
                            <li>Rencana pengembalian kredit.</li>
                        </ol>
                    </div>
                    <div>Demikian permohonan kami, atas perhatiannya kami ucapkan terima kasih.</div>
                </td>
            </table>
            @if ($signatureHtml)
                <div style="margin-top: 24px;">
                    {!! $signatureHtml !!}
                </div>
            @else
                <table border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 14px; margin-top: 24px;">
                    <tr>
                        <td>&nbsp;</td>
                        <td align="center">{{ $identity['district_name'] }}, {{ $proposalDate }}</td>
                    </tr>
                    <tr>
                        <td align="center" width="50%">Ketua Kelompok,</td>
                        <td align="center" width="50%">Sekretaris Kelompok,</td>
                    </tr>
                    <tr>
                        <td colspan="2" height="30"></td>
                    </tr>
                    <tr>
                        <td align="center" width="50%" style="font-weight: bold; text-decoration: underline;">
                            {{ $committeeChair }}
                        </td>
                        <td align="center" width="50%" style="font-weight: bold; text-decoration: underline;">
                            {{ $committeeSecretary }}
                        </td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>
