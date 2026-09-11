@extends('reports.pdf.layout')

@section('content')
    <table border="0" width="100%" style="font-size: 11px; text-align: center; margin-bottom: 15px;">
        <tr>
            <td>
                <div style="font-size: 13px; font-weight: bold;">NASKAH PERJANJIAN KERJASAMA ANTAR DESA (MoU)</div>
                <div style="font-size: 12px; font-weight: bold; color: #2b6cb0;">TENTANG PENGELOLAAN DANA BERGULIR & USAHA BERSAMA {{ strtoupper($identity['legal_name']) }}</div>
            </td>
        </tr>
    </table>

    <div style="font-size: 10.5px; line-height: 1.6; text-align: justify;">
        <p>
            Pada hari ini, <b>{{ $date_formatted }}</b>, kami yang bertandatangan di bawah ini para Kepala Desa di wilayah Kecamatan {{ $identity['district_name'] }} Kabupaten {{ $identity['regency_name'] }}, bertindak untuk dan atas nama Pemerintah Desa masing-masing, menyatakan bersepakat untuk mengikatkan diri dalam Perjanjian Kerjasama Pengelolaan Usaha Bersama dan Dana Bergulir Masyarakat melalui {{ $identity['legal_name'] }} dengan ketentuan:
        </p>
        <ol style="margin-left: 20px;">
            <li><b>Pasal 1: Tujuan Kerjasama</b> — Meningkatkan kesejahteraan ekonomi masyarakat desa, menanggulangi kemiskinan, serta memperkuat permodalan kelompok usaha mikro perempuan (SPP) dan usaha ekonomi produktif (UEP).</li>
            <li><b>Pasal 2: Kepemilikan Bersama</b> — Seluruh aset, modal, dan surplus dana bergulir adalah milik bersama desa-desa pendiri yang dikelola secara profesional, transparan, dan akuntabel sesuai standar akuntansi yang berlaku.</li>
            <li><b>Pasal 3: Tanggung Jawab Pengawasan</b> — Masing-masing Pemerintah Desa berhak mendapatkan laporan periodik dan melakukan pengawasan partisipatif melalui perwakilan Badan Pengawas dan forum Musyawarah Antar Desa (MAD).</li>
        </ol>
    </div>

    <div style="margin-top: 20px; font-size: 10.5px;">
        <div style="font-weight: bold; margin-bottom: 8px;">Para Pihak Kepala Desa se-Kecamatan {{ $identity['district_name'] }}:</div>
        <table border="0" width="100%" style="font-size: 10px;">
            @foreach (array_chunk($villages, 3) as $row)
                <tr>
                    @foreach ($row as $v)
                        <td width="33%" align="center" style="padding-bottom: 25px;">
                            <div>Kepala Desa <b>{{ $v['name'] }}</b></div>
                            <div style="height: 35px;"></div>
                            <div><u>( {{ $v['head_name'] }} )</u></div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
@endsection