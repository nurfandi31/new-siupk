@extends('reports.pdf.layout')

@section('content')
    <table border="0" width="100%" style="font-size: 11px; line-height: 1.6;">
        <tr>
            <td width="10%">Nomor</td>
            <td width="50%">: 001/LPJ/{{ strtoupper($identity['short_name'] ?? 'BUMDESMA') }}/{{ $year }}</td>
            <td width="40%" align="right">{{ $identity['district_name'] }}, {{ $date_formatted }}</td>
        </tr>
        <tr>
            <td>Lampiran</td>
            <td>: 1 (Satu) Bendel Buku Laporan</td>
            <td></td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td>: <b>Penyampaian Laporan Pertanggungjawaban Tahunan</b></td>
            <td></td>
        </tr>
        <tr><td colspan="3" height="15"></td></tr>
        <tr>
            <td></td>
            <td colspan="2">
                <div><b>Kepada Yth.</b></div>
                <div><b>1. Dewan Penasihat / Musyawarah Antar Desa (MAD)</b></div>
                <div><b>2. Badan Pengawas {{ $identity['legal_name'] }}</b></div>
                <div><b>3. Kepala Desa & BPD se-Kecamatan {{ $identity['district_name'] }}</b></div>
                <div>di Tempat</div>
            </td>
        </tr>
        <tr><td colspan="3" height="15"></td></tr>
        <tr>
            <td></td>
            <td colspan="2" style="text-align: justify;">
                <p>Dengan hormat,</p>
                <p>
                    Puji syukur kita panjatkan ke hadirat Tuhan Yang Maha Esa. Bersama ini kami Direksi/Pengelola <b>{{ $identity['legal_name'] }}</b> menyampaikan Buku Laporan Keuangan dan Perkembangan Kegiatan Usaha Periode <b>{{ $period_label }}</b> yang disusun berdasarkan Standar Akuntansi Keuangan Entitas Privat (SAK EP) dan Peraturan Pemerintah No. 11 Tahun 2021.
                </p>
                <p>
                    Laporan terlampir terdiri dari:
                </p>
                <ol style="margin-left: 20px;">
                    <li>Laporan Posisi Keuangan (Neraca)</li>
                    <li>Laporan Laba Rugi Komprehensif</li>
                    <li>Laporan Arus Kas (Metode Langsung)</li>
                    <li>Laporan Perubahan Ekuitas</li>
                    <li>Catatan Atas Laporan Keuangan (CALK)</li>
                    <li>Laporan Perkembangan Piutang (LPP) & Kolektibilitas Pinjaman</li>
                    <li>Laporan Penilaian Tingkat Kesehatan Keuangan Usaha</li>
                    <li>Laporan Rekapitulasi Inventaris & Aset Tetap</li>
                </ol>
                <p>
                    Demikian surat pengantar ini kami sampaikan. Atas perhatian, arahan, dan kerjasama seluruh pihak kami ucapkan terima kasih.
                </p>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" style="margin-top: 30px; font-size: 11px;">
        <tr>
            <td width="50%" align="center">
                <div>Mengetahui,</div>
                <div><b>Badan Pengawas</b></div>
                <div style="height: 50px;"></div>
                <div><u>( {{ $identity['supervisor_name'] ?? '........................' }} )</u></div>
            </td>
            <td width="50%" align="center">
                <div>{{ $identity['district_name'] }}, {{ $date_formatted }}</div>
                <div><b>Direktur Utama</b></div>
                <div style="height: 50px;"></div>
                <div><u>( {{ $identity['director_name'] ?? '........................' }} )</u></div>
            </td>
        </tr>
    </table>
@endsection