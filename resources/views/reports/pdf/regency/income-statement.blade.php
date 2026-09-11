@extends('reports.pdf.regency.layout', ['title' => 'Laba Rugi Konsolidasi - ' . ($regency_name ?? 'Kabupaten')])

@section('content')
<div class="text-center" style="margin-bottom: 12px;">
    <div class="header-title">LAPORAN LABA RUGI KONSOLIDASI KABUPATEN</div>
    <div class="header-sub">
        {{ $report['period']['period_label'] ?? '' }}
        @if (!empty($report['is_consolidated']))
            · Gabungan Seluruh Kecamatan
        @endif
    </div>
</div>

<table class="t l r b" style="font-size: 10px;">
    <thead>
        <tr class="bg-gray">
            <th class="b r" style="width: 80px;">Kode</th>
            <th class="b r" style="text-align: left;">Uraian Pendapatan / Beban</th>
            <th class="b r text-right" style="width: 90px;">{{ $report['header_lalu'] }}</th>
            <th class="b r text-right" style="width: 90px;">{{ $report['header_sekarang'] }}</th>
            <th class="b text-right" style="width: 100px;">Total YTD (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($report['groups'] as $group)
            <tr class="bg-gray font-bold">
                <td class="b r">{{ $group['code'] }}</td>
                <td class="b r" style="text-align: left;">{{ strtoupper($group['name']) }}</td>
                <td class="b r text-right">{{ number_format($group['prior'], 2, ',', '.') }}</td>
                <td class="b r text-right">{{ number_format($group['current'], 2, ',', '.') }}</td>
                <td class="b text-right">{{ number_format($group['ytd'], 2, ',', '.') }}</td>
            </tr>

            @foreach ($group['children'] as $child)
                <tr>
                    <td class="b r" style="color: #4b5563;">{{ $child['code'] }}</td>
                    <td class="b r" style="padding-left: 18px;">{{ $child['name'] }}</td>
                    <td class="b r text-right">{{ number_format($child['prior'], 2, ',', '.') }}</td>
                    <td class="b r text-right">{{ number_format($child['current'], 2, ',', '.') }}</td>
                    <td class="b text-right">{{ number_format($child['ytd'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr class="bg-gray font-bold">
            <td class="t b r" colspan="2">LABA (RUGI) OPERASIONAL</td>
            <td class="t b r text-right">{{ number_format($report['summary']['operating']['prior'] ?? 0, 2, ',', '.') }}</td>
            <td class="t b r text-right">{{ number_format($report['summary']['operating']['current'] ?? 0, 2, ',', '.') }}</td>
            <td class="t b text-right">{{ number_format($report['summary']['operating']['ytd'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        <tr class="bg-gray font-bold">
            <td class="b r" colspan="2">LABA (RUGI) BERSIH SETELAH PAJAK</td>
            <td class="b r text-right">{{ number_format($report['summary']['after_tax']['prior'] ?? 0, 2, ',', '.') }}</td>
            <td class="b r text-right">{{ number_format($report['summary']['after_tax']['current'] ?? 0, 2, ',', '.') }}</td>
            <td class="b text-right">{{ number_format($report['summary']['after_tax']['ytd'] ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endsection
