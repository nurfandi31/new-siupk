@php
    use App\Support\IndonesianDate;
    use App\Support\IndonesianNumber;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $alokasi = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $jangka = (int) $loan['term_months'];
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

    table.p tr th,
    table.p tr td {
        padding: 4px 4px;
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

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;">
                <b>SURAT PERJANJIAN KREDIT (SPK)</b>
            </div>
            <div style="font-size: 14px;">
                Nomor: {{ $loan['loan_number'] }}
            </div>
            <div style="font-size: 14px;">
                Tanggal: {{ $disbursementDate }}
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<div style="text-align: justify; font-size: 14px;">
    Dengan memohon rahmat Tuhan Yang Maha Kuasa serta kesadaran akan cita-cita luhur pemberdayaan masyarakat desa untuk
    mencapai kemajuan ekonomi dan kemakmuran bersama, pada hari {{ IndonesianDate::dayName($loan['disbursed_at'] ?? '') }} tanggal
    {{ IndonesianNumber::spelledOut((float) IndonesianDate::day($loan['disbursed_at'] ?? '')) }} bulan {{ IndonesianDate::monthName($loan['disbursed_at'] ?? '') }} tahun
    {{ IndonesianNumber::spelledOut((float) IndonesianDate::year($loan['disbursed_at'] ?? '')) }}, bertempat di {{ 'Kantor DBM' }} kami yang bertanda
    tangan dibawah ini;
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="90">Nama Lengkap</td>
        <td width="10" align="center">:</td>
        <td>{{ '' }}</td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td align="center">:</td>
        <td>{{ '' }} {{ $identity['legal_name'] }}</td>
    </tr>
    <tr>
        <td>NIK</td>
        <td align="center">:</td>
        <td>{{ '' }}</td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td align="center">:</td>
        <td>{{ '' }}</td>
    </tr>
</table>

<div style="text-align: justify; font-size: 14px;">
    Bertindak untuk dan atas nama Manajemen {{ $identity['legal_name'] }} {{ $identity['district_name'] }}
    selaku pengelola Dana Bergulir Masyarakat untuk {{ $loan['product_name'] }}
    ({{ $loan['product_code'] }}) di {{ $identity['district_name'] }}, selanjutnya disebut PIHAK
    PERTAMA, dan
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
    <tr>
        <td width="90">Nama Lengkap</td>
        <td width="10" align="center">:</td>
        <td>{{ $committeeChair }}</td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td align="center">:</td>
        <td>Ketua Kelompok</td>
    </tr>
    <tr>
        <td>Nama Lengkap</td>
        <td align="center">:</td>
        <td>{{ $committeeSecretary }}</td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td align="center">:</td>
        <td>Sekretaris Kelompok</td>
    </tr>
    <tr>
        <td>Nama Lengkap</td>
        <td align="center">:</td>
        <td>{{ $committeeTreasurer }}</td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td align="center">:</td>
        <td>Bendahara Kelompok</td>
    </tr>
</table>

<div style="text-align: justify; font-size: 14px;">
    Bertindak untuk dan atas nama kelompok {{ $loan['product_code'] }} {{ $group['name'] }} yang
    berkedudukan di {{ $group['address'] }} {{ '' }}
    {{ $group['village'] }} {{ $identity['district_name'] }}, dan beserta anggota yang
    memberikan kuasa secara tertulis sebagaimana Surat Kuasa terlampir sebagai bagian yang tidak terpisahkan dari
    dokumen perjanjian kredit ini, selanjutnya disebut PIHAK KEDUA.
</div>

<p style="text-align: justify; font-size: 14px;">
    Dalam kedudukan para pihak sebagaimana tertulis diatas, dengan sadar dan sukarela serta rasa penuh tanggung jawab
    menyatakan telah membuat surat perjanjian kredit (SPK) dengan ketentuan-ketentuan yang disepakati bersama sebagai
    berikut :
</p>

<div style="text-align: center;">
    <div>
        <b style="font-size: 14px;">PASAL 1</b>
    </div>

    <ol style="text-align: justify; font-size: 14px;">
        <li>
            @php
                $text = 'setuju memberikan kredit/piutang kepada Pihak Kedua sebesar';
            @endphp

            Pihak Pertama {{ $text }} Rp.
            {{ $alokasi }} ({{ '' }} Rupiah) yaitu
            jumlah
            yang telah diputuskan dalam rapat penetapan pendanaan, berdasarkan permohonan dari Pihak Kedua dan para
            pemberi kuasa yang dilakukan secara kelompok sesuai Surat Permohonan Kredit tanggal
            {{ $proposalDate }}.
        </li>
        <li>
            @php
                $text = 'menyatakan telah menerima uang dengan jumlah sebagaimana yang tertulis pada ayat 1 diatas';
            @endphp

            Pihak Kedua dan Pemberi kuasa, {{ $text }}., dan telah diterima oleh para anggota pemanfaat
            sesuai
            kelayakan kredit
            masing-masing anggota pemanfaat yang dibuktikan secara sah dengan daftar penerima dana terlampir,
            dan sekaligus berlaku sebagai Surat Pengakuan Hutang, baik bagi setiap anggota penerima manfaat
            maupun secara kelompok dalam pernyataan ketaatan tanggung-renteng.
        </li>
    </ol>
</div>

<div style="text-align: center;">
    <div>
        <b style="font-size: 14px;">PASAL 2</b>
    </div>

    <div style="text-align: justify; font-size: 14px;">
        Kedua belah Pihak secara sukarela menerima syarat-syarat perjanjian utang-piutang sebagaimana
        dinyatakan dalam ketentuan-ketentuan dibawah ini :

        <ol style="text-align: justify;">
            <li>
                Dana piutang dari {{ $identity['legal_name'] }} akan dipergunakan untuk kegiatan usaha
                dan/atau pembiayaan hal-hal yang bermanfaat untuk meningkatkan pendapatan dan mutu kehidupan
                keluarga guna memberikan manfaat sebesar-besarnya bagi pertumbuhan ekonomi dan kesejahteraan
                keluarga pengurus dan anggota kelompok {{ $group['name'] }}.
            </li>
            <li>
                Menjunjung tinggi dan ikut menyepakati hasil Musyawarah pendanaan yang telah menetapkan piutang
                kelompok sebagaimana kelompok {{ $group['name'] }} adalah termasuk dalam kategori
                kelompok yang sepakat memberikan dukungan operasional dan pengembangan kepada
                {{ $identity['legal_name'] }} secara progresif proporsional berupa jasa piutang sebesar
                {{ number_format((float) $tokens['{jasa_persen}'] / max(1, $jangka), 2) }}% {{ '' }}
                per-bulan
                dikalikan
                pokok
                piutang.
            </li>
            <li>
                Kelompok menyepakati akan melakukan angsuran kredit dalam jangka waktu {{ $jangka }}
                ({{ '' }}) bulan dengan cara membayar angsuran Pokok
                {{ '' }} ({{ '' }}) dan angsuran
                jasa
                {{ '' }} ({{ '' }}) sebagaimana
                jadwal
                angsuran terlampir yang tidak terpisahkan dari Surat Perjanjian Kredit (SPK).
            </li>
        </ol>
    </div>
</div>

<div style="text-align: center;">
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;" class="p0">
        <tr>
            <td style="padding: 0px !important;">
                <table class="p0" border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 14px;">
                    <tr>
                        <td style="padding: 0px !important;">
                            <div style="text-align: center;">
                                <b style="font-size: 14px; text-align: center;">PASAL 3</b>
                            </div>

                            <ol style="text-align: justify;">
                                <li>
                                    Pihak kedua dan pemberi kuasa sadar dan mengerti bahwa mengembalikan kredit
                                    secara
                                    lancar sesuai
                                    jadwal yang disepakati, merupakan kewajiban hukum sekaligus menunjukkan budi
                                    pekerti
                                    luhur untuk
                                    mengembangkan semangat tolong menolong dengan saudaranya sesama warga desa lain.
                                    Pengembalian kredit secara lancar akan memperluas kesempatan untuk memperoleh
                                    kredit
                                    berikutnya
                                    serta membuka peluang bagi orang lain mendapatkan giliran pelayanan.
                                </li>
                                <li>
                                    Apabila terjadi saling selisih berkenaan dengan hak serta kewajiban yang timbul
                                    atas
                                    perjanjian
                                    utang-piutang ini, akan diselesaikan secara musyawarah untuk mencapai kata
                                    sepakat.
                                    Apabila tidak
                                    dapat dicapai kata sepakat, kedua belah pihak setuju untuk menunjuk Pengadilan
                                    Negeri {{ $identity['regency_name'] }}
                                    sebagai upaya hukum menyelesaikan persengketaan tersebut.
                                </li>
                                <li>
                                    Pihak kedua menyatakan secara sadar dan sukarela telah menanda tangani akad atau
                                    perjanjian kredit
                                    ini, setelah terlebih dahulu membacakan isi perjanjian ini kepada para pemberi
                                    kuasa
                                    dengan
                                    sejelas-jelasnya dan tidak seorangpun diantaranya menyatakan keberatan, serta
                                    untuk
                                    menjadikan
                                    periksa bagi yang berwenang.
                                </li>
                            </ol>
                        </td>
                    </tr>
                </table>

                @if ($signatureHtml)
                    {!! $signatureHtml !!}
                @else
                    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;"
                        class="p">
                        <tr>
                            <td width="50%" align="center">Pihak Pertama</td>
                            <td width="25%" colspan="2" align="center">Pihak Kedua</td>
                        </tr>
                        <tr>
                            <td colspan="3" height="50"></td>
                        </tr>
                        <tr>
                            <td align="center">
                                <b>{{ '' }}</b>
                            </td>
                            <td align="center">
                                <b>{{ $committeeChair }}</b>
                            </td>
                            <td align="center">
                                <b>{{ $committeeSecretary }}</b>
                            </td>
                        </tr>
                        <tr>
                            <td align="center">{{ $identity['legal_name'] }}</td>
                            <td align="center">Ketua</td>
                            <td align="center">Sekretaris</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>