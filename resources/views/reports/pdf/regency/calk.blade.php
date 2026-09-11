@extends('reports.pdf.regency.layout', ['title' => 'CALK Konsolidasi - ' . ($regency_name ?? 'Kabupaten')])

@section('content')
<div class="text-center" style="margin-bottom: 12px;">
    <div class="header-title">CATATAN ATAS LAPORAN KEUANGAN (CALK) KONSOLIDASI</div>
    <div class="header-sub">
        {{ $report['period']['period_label'] ?? '' }}
        @if (!empty($report['is_consolidated']))
            · Gabungan Seluruh Kecamatan di Kabupaten {{ $regency_name ?? '' }}
        @endif
    </div>
</div>

<div style="margin-bottom: 15px;">
    <div class="font-bold" style="font-size: 11px; margin-bottom: 6px;">I. RINGKASAN KINERJA KEUANGAN GABUNGAN</div>
    <table class="t l r b" style="font-size: 10px;">
        <thead>
            <tr class="bg-gray">
                <th class="b r" style="text-align: left;">Indikator Keuangan</th>
                <th class="b text-right" style="width: 160px;">Nilai Gabungan (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['highlights'] as $hl)
                <tr>
                    <td class="b r">{{ $hl['label'] }}</td>
                    <td class="b text-right font-bold">{{ number_format((float) $hl['value'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-bottom: 15px;">
    <div class="font-bold" style="font-size: 11px; margin-bottom: 6px;">II. REKAPITULASI DANA BERGULIR & KINERJA PER KECAMATAN</div>
    <table class="t l r b" style="font-size: 10px;">
        <thead>
            <tr class="bg-gray">
                <th class="b r" style="width: 30px;">No</th>
                <th class="b r" style="text-align: left;">Kecamatan</th>
                <th class="b r text-right" style="width: 100px;">Kas & Setara (Rp)</th>
                <th class="b r text-right" style="width: 60px;">Pinjaman Aktif</th>
                <th class="b r text-right" style="width: 110px;">Alokasi Pokok (Rp)</th>
                <th class="b text-right" style="width: 60px;">Kelompok</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['kecamatans'] as $idx => $kec)
                <tr>
                    <td class="b r text-center">{{ $idx + 1 }}</td>
                    <td class="b r font-bold">{{ $kec['name'] }}</td>
                    <td class="b r text-right">{{ number_format($kec['cash'], 2, ',', '.') }}</td>
                    <td class="b r text-right">{{ number_format($kec['active_loans']) }}</td>
                    <td class="b r text-right">{{ number_format($kec['active_principal'], 2, ',', '.') }}</td>
                    <td class="b text-right">{{ number_format($kec['groups_count']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
