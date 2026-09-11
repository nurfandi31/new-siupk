@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
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
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
        <tr>
            <td colspan="3" align="center">
                <div style="font-size: 18px; text-decoration: underline;">
                    <b>SURAT PENGAKUAN UTANG DAN PERTANGGUNGAN AHLI WARIS</b>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="3" height="5"></td>
        </tr>
    </table>
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px; text-align: justify;">
        <tr>
            <td colspan="3">Yang bertanda tangan di bawah ini,</td>
        </tr>
        <tr>
            <td width="100">Nama Lengkap</td>
            <td width="5" align="right">:</td>
            <td>
                <b>{{ $b['name'] }}</b>
            </td>
        </tr>
        <tr>
            <td>Jenis Kelamin</td>
            <td align="right">:</td>
            <td>
                <b>{{ '' }}</b>
            </td>
        </tr>
        <tr>
            <td>Tempat, Tangal lahir</td>
            <td align="right">:</td>
            <td>
                <b>{{ '' }},
                    {{ '' }}</b>
            </td>
        </tr>
        <tr>
            <td>NIK</td>
            <td align="right">:</td>
            <td>
                <b>{{ $b['nik'] }}</b>
            </td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td align="right">:</td>
            <td>
                <b>{{ $group['address'] }}</b>
            </td>
        </tr>
        <tr>
            <td>Pekerjan/Usaha</td>
            <td align="right">:</td>
            <td>
                <b>{{ '' }}</b>
            </td>
        </tr>
        <tr>
            <td width="100" colspan="3">
                <div>
                    Dengan ini menyatakan dengan sebenarnya dan pernyataan ini tidak dapat ditarik kembali, bahwa:
                </div>

                <ol>
                    <li>
                        Saya selaku Anggota Kelompok {{ $group['name'] }} Kecamatan
                        {{ $identity['district_name'] }} melalui Desa/Kelurahan {{ $group['village'] }}, Kecamatan
                        {{ $identity['district_name'] }} {{ $identity['regency_name'] }}, benar-benar mengajukan piutang uang sebesar Rp.
                        {{ number_format((float) $b['proposed_amount'], 0, ',', '.') }} ({{ '' }}).
                    </li>
                    <li>
                        Saya berjanji akan mengembalikan piutang saya tersebut sesuai dengan peraturan yang ada di
                        {{ $identity['legal_name'] }},
                    </li>
                    <li>
                        Apabila di kemudian hari saya melanggar isi dari surat pernyataan ini, maka saya bersedia
                        dilaporkan kepada pihak yang berwajib dan/atau diproses secara hukum.
                    </li>
                    <li>
                        Jika dikemudian hari terjadi force majeure seperti banjir, gempa bumi, tanah longsor, petir,
                        angin
                        topan, kebakaran, huru-hara, kerusuhan, pemberontakan, dan perang atau saya berhalangan tetap
                        seperti sakit atau meninggal dunia yang mengakibatkan tidak dapat terpenuhinya kewajiban saya
                        sesuai
                        poin 4 (empat) diatas, maka sisa angsuran akan diselesaikan oleh ahli waris.
                    </li>
                </ol>

                <div>
                    Demikian surat pernyataan ini saya buat dengan sebenarnya dan dengan penuh kesadaran serta rasa
                    tanggung jawab.
                </div>
            </td>
        </tr>
    </table>

    @if (!empty($signatureHtml))
        {!! $signatureHtml !!}
    @else
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
            <tr>
                <td colspan="3" height="20">&nbsp;</td>
            </tr>
            <tr>
                <td width="40%">&nbsp;</td>
                <td width="20%">&nbsp;</td>
                <td width="40%" align="center">{{ $identity['district_name'] }}, {{ $proposalDate }}
                </td>
            </tr>
            <tr>
                <td align="center">Saksi 1</td>
                <td align="center">Saksi/Ahli Waris</td>
                <td align="center">Yang Menyatakan</td>
            </tr>
            <tr>
                <td colspan="3" height="50">&nbsp;</td>
            </tr>
            <tr>
                <td align="center">
                    <b>{{ $committeeChair }}</b>
                </td>
                <td align="center">
                    <b>{{ $b['guarantor'] }}</b>
                </td>
                <td align="center">
                    <b>{{ $b['name'] }}</b>
                </td>
            </tr>
        </table>
    @endif
@endforeach