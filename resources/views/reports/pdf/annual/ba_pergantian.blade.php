@extends('reports.pdf.layout')

@section('content')
    <table border="0" width="100%" style="font-size: 11px; text-align: center; margin-bottom: 15px;">
        <tr>
            <td>
                <div style="font-size: 13px; font-weight: bold;">BERITA ACARA PENGESAHAN & PERTANGGUNGJAWABAN LAPORAN KEUANGAN</div>
                <div style="font-size: 12px; font-weight: bold; color: #2b6cb0;">{{ strtoupper($identity['legal_name']) }}</div>
                <div style="font-size: 11px;">Nomor: BA/{{ $year }}/LPJ/{{ strtoupper($identity['short_name'] ?? 'LKD') }}</div>
            </td>
        </tr>
    </table>

    <div style="font-size: 11px; line-height: 1.6; text-align: justify;">
        <p>
            Pada hari ini, <b>{{ $date_formatted }}</b>, bertempat di Kantor {{ $identity['legal_name'] }} Kecamatan {{ $identity['district_name'] }}, telah diselenggarakan Musyawarah Antar Desa (MAD) Laporan Pertanggungjawaban Tahunan Periode Tahun Buku <b>{{ $year }}</b> yang dihadiri oleh Pengelola/Direksi, Badan Pengawas, Kepala Desa, dan perwakilan tokoh masyarakat desa se-Kecamatan {{ $identity['district_name'] }}.
        </p>
        <p>
            Setelah melakukan pemeriksaan, klarifikasi, dan pembahasan terhadap seluruh berkas pembukuan, laporan posisi keuangan, arus kas, laba rugi, perkembangan piutang, dan catatan atas laporan keuangan (CALK), para pihak menyepakati dan menetapkan hal-hal sebagai berikut:
        </p>
        <ol style="margin-left: 20px;">
            <li>Menerima dan mengesahkan seluruh Laporan Keuangan dan Kinerja Usaha Periode Tahun Buku {{ $year }}.</li>
            <li>Menyetujui pembagian dan alokasi surplus/laba bersih usaha sesuai dengan AD/ART dan ketentuan perundang-undangan (Pendapatan Asli Desa, Cadangan Modal, Dana Sosial, dan Jasa Pengelola).</li>
            <li>Memberikan pelepasan tanggung jawab sepenuhnya (<i>acquit et de charge</i>) kepada Direksi/Pengelola atas tindakan kepengurusan keuangan dalam periode yang bersangkutan.</li>
        </ol>
        <p>
            Demikian Berita Acara ini dibuat dan ditandatangani dengan sebenarnya dalam rangkap secukupnya untuk dipergunakan sebagaimana mestinya.
        </p>
    </div>

    <table border="0" width="100%" style="margin-top: 30px; font-size: 11px;">
        <tr>
            <td width="33%" align="center">
                <div>Direktur Utama</div>
                <div style="height: 45px;"></div>
                <div><u>( {{ $identity['director_name'] ?? '........................' }} )</u></div>
            </td>
            <td width="33%" align="center">
                <div>Badan Pengawas</div>
                <div style="height: 45px;"></div>
                <div><u>( {{ $identity['supervisor_name'] ?? '........................' }} )</u></div>
            </td>
            <td width="33%" align="center">
                <div>Wakil Kepala Desa / MAD</div>
                <div style="height: 45px;"></div>
                <div><u>( {{ $identity['advisor_name'] ?? '........................' }} )</u></div>
            </td>
        </tr>
    </table>
@endsection