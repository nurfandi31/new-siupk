@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $termMonths = (int) $loan['term_months'];
    $beneficiaryCount = count($beneficiaries);
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
                <b>BERITA ACARA PERTEMUAN</b>
            </div>
            <div style="font-size: 16px;">
                <b>
                    KELOMPOK {{ strtoupper($loan['product_name']) }}
                </b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<div style="text-align: justify;">
    Sehubungan dengan rencana kelompok mengajukan piutang kepada {{ $identity['legal_name'] }} {{ $identity['district_name'] }}
    {{ $identity['district_name'] }}, maka pada hari ini _________ tanggal ___ bulan ____________ tahun _____ , bertempat di
    {{ $group['address'] }} {{ '' }}
    {{ $group['village'] }}, nama kelompok {{ $group['name'] }}
    {{ '' }} {{ $group['village'] }}
    {{ $identity['district_name'] }} {{ $identity['district_name'] }} telah diselenggarakan musyawarah kelompok yang dihadiri oleh
    anggota kelompok dan unsur lain yang terkait di desa sebagaimana tercantum dalam daftar hadir terlampir.
</div>

<div style="text-align: justify;">
    Adapun materi atau topik yang dibahas dalam musyawarah ini adalah sebagai berikut :
    <ol style="list-style: lower-alpha;">
        <li>Jumlah anggota peminjam dan besar piutang yang diajukan</li>
        <li>Penetapan bunga piutang kepada anggota</li>
        <li>Rencana penggunaan selisih angsuran bunga</li>
        <li>Sanksi kelompok bagi anggota peminjam yang menunggak</li>
    </ol>
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="130">Acara musyawarah dipimpin oleh</td>
        <td width="5" align="center">:</td>
        <td>{{ $committeeChair }}</td>
    </tr>
    <tr>
        <td>Notulen</td>
        <td>:</td>
        <td>{{ $committeeSecretary }}</td>
    </tr>
    <tr>
        <td>Narasumber</td>
        <td>:</td>
        <td>&nbsp;</td>
    </tr>
    <tr>
        <td colspan="3">_______________________________________</td>
    </tr>
    <tr>
        <td colspan="3">_______________________________________</td>
    </tr>
</table>

<div style="text-align: justify;">
    Setelah dilakukan diskusi dan musyawarah terhadap topik di atas, peserta memutuskan dan telah menyepakati sebagai
    berikut :

    <ol style="list-style: lower-alpha;">
        <li>
            Forum menyepakati jumlah anggota kelompok yang akan mengajukan piutang yaitu
            {{ $beneficiaryCount }} orang dan besar piutang yang akan diajukan kepada
            {{ $identity['legal_name'] }} sebesar Rp {{ $proposedAmount }}
        </li>
        <li>
            Forum menyepakati anggota kelompok wajib mengangsur setiap {{ '' }} sesuai
            dengan jumlah piutang yang diterima dan tanggal yang telah disepakati.
        </li>
        <li>
            Forum menyepakati bunga piutang yang akan diberlakukan kepada anggota kelompok sebesar
            {{ number_format((float) $tokens['{jasa_persen}'] / max(1, $termMonths), 2) }}% setiap bulan dalam {{ $termMonths }} bulan.
        </li>
        <li>
            Forum menyepakati penggunaan selisih angsuran bunga ke {{ $identity['legal_name'] }} akan digunakan
            untuk :

            <ul style="list-style: decimal;">
                <li>Honor Pengurus</li>
                <li>Transport Pengurus</li>
                <li>ATK Kelompok</li>
            </ul>
        </li>
        <li>
            Forum menyepakati besar tabungan beku tanggung renteng yaitu _____ % dan tabungan beku tanggung renteng
            tersebut akan disimpan di Rekening Bank.
        </li>
        <li>
            Forum menyepakati aturan dan sanksi kelompok bila anggota menunggak, yaitu :

            <ul style="list-style: decimal;">
                <li>Setiap Kelompok harus membuat proposal sesuai petunjuk</li>
                <li>Proposal Wajib melampirkan KK dan KTP</li>
                <li>
                    Setiap peminjam wajib melunasi piutangnya sesuai dengan jangka waktu yang disepakati atau
                    diperjanjikan angsuran pokok beserta bunganya
                </li>
                <li>
                    Setiap peminjam wajib menyimpan dana pada rekening tabungan beku atau tanggung renteng kelompok
                    sebesar ___% dari besar piutangnya
                </li>
                <li>
                    Apabila peminjam meninggal dunia dan masih memiliki sisa angsuran, maka sisa angsuran tersebut harus
                    dilunasi oleh Ahli Warisnya atau oleh pribadi yang menandatangani pada surat pernyataann persetujuan
                    piutang
                </li>
            </ul>

            Sanksi :
            <ul style="list-style: decimal;">
                <li>
                    Apabila peminjam tidak melunasi piutangnya, maka yang bersangkutan akan diberi peringatan dan atau
                    dilakukan proses hukum yang berlaku. Jika ahli waris atau penanggung jawab adalah warga yang tidak
                    mampu, maka bisa mengajukan permohonan Penghapusan Piutang sesuai dengan aturan yang berlaku di
                    {{ $identity['legal_name'] }} {{ $identity['district_name'] }} {{ $identity['district_name'] }}
                </li>
            </ul>
        </li>
    </ol>
    Keputusan di atas disepakati dengan cara musyawarah mukafat / voting
</div>

<div style="text-align: justify;">
    Demikian berita acara ini kami buat dengan sebenar-benarnya dan atas dasar musyawarah kelompok agar dapat
    dipergunakan sebagaimana mestinya.
</div>

<div class="break"></div>
<div style="text-align: justify">
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td height="15" style="padding: 0px;" colspan="3">Mengetahui dan menyetujui</td>
        </tr>
        <tr>
            <td height="15" style="padding: 0px;" colspan="3">Wakil Anggota/Peserta Musyawarah :</td>
        </tr>
        <tr>
            <td height="15" style="padding: 0px;" align="center">Nama Lengkap</td>
            <td style="padding: 0px;" align="center" colspan="2">Tanda Tangan</td>
        </tr>

        @for ($i = 1; $i <= $beneficiaryCount; $i++)
            <tr>
                <td height="15" style="padding: 0px;" width="50%">{{ $i }}.
                    ______________________________________________</td>
                @if ($i % 2 == 0)
                    <td style="padding: 0px;" width="25%">&nbsp;</td>
                    <td style="padding: 0px;" width="25%">{{ $i }}. _____________________</td>
                @else
                    <td style="padding: 0px;" width="25%">{{ $i }}. _____________________</td>
                    <td style="padding: 0px;" width="25%">&nbsp;</td>
                @endif
            </tr>
        @endfor

        <tr>
            <td colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td></td>
            <td align="center" colspan="2">{{ $identity['district_name'] }}, _______________________</td>
        </tr>
        <tr>
            <td align="center">Ketua Kelompok</td>
            <td align="center" colspan="2">Sekretaris Kelompok</td>
        </tr>
        <tr>
            <td align="center" colspan="3" height="40">&nbsp;</td>
        </tr>
        <tr>
            <td align="center">
                <b>{{ $committeeChair }}</b>
            </td>
            <td align="center" colspan="2">
                <b>{{ $committeeSecretary }}</b>
            </td>
        </tr>
    </table>
</div>