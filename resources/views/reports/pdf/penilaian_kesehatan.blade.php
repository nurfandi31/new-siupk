@extends('reports.pdf.layout')

@section('content')
    <style>
        .num { text-align: right; white-space: nowrap; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 12px;">
        <tr>
            <td align="center">
                <div style="font-size: 15px; font-weight: bold;">
                    PENILAIAN TINGKAT KESEHATAN KEUANGAN
                </div>
                <div style="font-size: 12px; font-weight: bold; color: #444;">
                    PERIODE: {{ strtoupper($period_label) }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Ringkasan Predikat -->
    <table border="0" width="100%" cellspacing="0" cellpadding="6" style="margin-bottom: 12px; border: 1px solid rgb(232,232,232); background: rgb(232,232,232);">
        <tr>
            <td width="30%" align="center" style="border-right: 1px solid rgb(232,232,232);">
                <div style="font-size: 10px; color: #718096; font-weight: bold;">SKOR TINGKAT KESEHATAN</div>
                <div style="font-size: 26px; font-weight: bold; color: #2b6cb0;">{{ $total_score }} <span style="font-size: 12px; color: #718096;">/ 100</span></div>
            </td>
            <td width="70%" style="padding-left: 15px;">
                <div style="font-size: 10px; color: #718096; font-weight: bold;">PREDIKAT KELAYAKAN USAHA</div>
                <div style="font-size: 18px; font-weight: bold; color: {{ $total_score >= 80 ? '#276749' : ($total_score >= 66 ? '#d69e2e' : '#c53030') }};">
                    {{ $predicate }}
                </div>
                <div style="font-size: 9px; color: #718096; margin-top: 2px;">
                    Standar evaluasi kesehatan keuangan mengacu pada ketentuan Permendesa / Kepmendesa No. 136/2022 tentang Tata Kelola Keuangan BUMDesma LKD.
                </div>
            </td>
        </tr>
    </table>

    <!-- Rincian Indikator Rasio Finansial -->
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(232,232,232); font-weight: bold; text-align: center;">
                <th class="t l b" width="4%">No</th>
                <th class="t l b" width="28%" align="left">Indikator Kinerja Keuangan</th>
                <th class="t l b" width="22%" align="left">Formula / Rasio</th>
                <th class="t l b" width="12%">Nilai Rasio</th>
                <th class="t l b" width="8%">Bobot</th>
                <th class="t l b" width="10%">Skor Perolehan</th>
                <th class="t l b r" width="16%">Status / Kategori</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($indicators as $idx => $ind)
                <tr>
                    <td class="l b" align="center">{{ $idx + 1 }}</td>
                    <td class="l b" style="font-weight: bold;">
                        {{ $ind['name'] }}
                        <div style="font-size: 7.5px; font-weight: normal; color: #718096;">{{ $ind['desc'] }}</div>
                    </td>
                    <td class="l b" style="color: #4a5568;">{{ $ind['formula'] }}</td>
                    <td class="l b" align="center" style="font-weight: bold;">{{ number_format($ind['value'], 2, ',', '.') }}{{ $ind['unit'] }}</td>
                    <td class="l b" align="center">{{ $ind['weight'] }}</td>
                    <td class="l b" align="center" style="font-weight: bold;">{{ number_format($ind['score'], 1, ',', '.') }}</td>
                    <td class="l b r" align="center" style="font-weight: bold; color: {{ $ind['status'] === 'Sehat' ? '#276749' : ($ind['status'] === 'Cukup Sehat' ? '#d69e2e' : '#c53030') }};">
                        {{ $ind['status'] }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="4" align="left">TOTAL SKOR PENILAIAN</th>
                <th class="t l b" align="center">100</th>
                <th class="t l b" align="center">{{ number_format($total_score, 1, ',', '.') }}</th>
                <th class="t l b r" align="center">{{ $predicate }}</th>
            </tr>
        </tfoot>
    </table>

    <table border="0" width="100%" style="margin-top: 25px; font-size: 10px;">
        <tr>
            <td width="50%" align="center">
                <div>Mengetahui,</div>
                <div><b>Badan Pengawas</b></div>
                <div style="height: 45px;"></div>
                <div><u>( {{ $identity['supervisor_name'] ?? '........................' }} )</u></div>
            </td>
            <td width="50%" align="center">
                <div>{{ $identity['district_name'] ?? '' }}, {{ date('d F Y') }}</div>
                <div><b>Direktur Utama</b></div>
                <div style="height: 45px;"></div>
                <div><u>( {{ $identity['director_name'] ?? '........................' }} )</u></div>
            </td>
        </tr>
    </table>
@endsection