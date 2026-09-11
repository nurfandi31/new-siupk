@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $verificationDate = $tokens['{tgl_verifikasi}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $verificationYmd = $loan['verified_at'] ?? '';
    $todayRoman = IndonesianDate::roman($today ?? '');
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
            ______/{{ $todayRoman }}
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
            <b>Pemberitahuan dan Undangan Verifikasi</b>
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
                Menindaklanjuti tahapan perguliran, dengan ini diberitahukan tentang <b><u>JADWAL VERIFIKASI</u></b> di
                desa setempat dan diharap kehadirannya untuk kegiatan tersebut yang akan dilaksanakan dengan ketentuan
                sebagai berikut;
            </div>
            <table class="p0">
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
                    <td>Tanggal Verifikasi</td>
                    <td>:</td>
                    <td>{{ $verificationDate }}</td>
                </tr>
                <tr>
                    <td>4.</td>
                    <td>Waktu</td>
                    <td>:</td>
                    <td>Jam : {{ '________' }}</td>
                </tr>
                <tr>
                    <td>5.</td>
                    <td>Tempat</td>
                    <td>:</td>
                    <td>Rumah Ketua kelompok / {{ $committeeChair }}</td>
                </tr>
                <tr>
                    <td>6.</td>
                    <td>Kegiatan</td>
                    <td>:</td>
                    <td>
                        Verifikasi usulan kelompok {{ $group['name'] }} Rp.
                        {{ $proposedAmount }}
                    </td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td colspan="3">
                        Catatan :
                        <ul style="margin: 0px;">
                            <li>
                                Peminjam harus hadir dan tidak boleh diwakilkan, bagi yang tidak hadir dianggap tidak
                                mengajukan pinjaman.
                            </li>
                            <li>
                                Untuk kelompok perguliran, buku-buku administrasi perguliran sebelumnya harap dibawa
                                untuk
                                pembinaan dan penilaian perguliran sebelumnya.
                            </li>
                            <li>
                                Tempat yang akan di kunjungi Tim verifikasi meliputi Balai desa, Kelompok dan anggota,
                                Lingkungan kelompok.
                            </li>
                            <li>
                                Dimohon kepala desa ikut hadir secara pribadi.
                            </li>
                        </ul>
                    </td>
                </tr>
            </table>

            <p style="text-align: justify;">
                Terkait hal tersebut kepada semua kelompok yang mengajukan pinjaman kelompok untuk mempersiapkan semua
                ketentuan di atas.
            </p>

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
            {{ $identity['district_name'] }}, {{ $verificationDate }}
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">{{ '' }} DBM</td>
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