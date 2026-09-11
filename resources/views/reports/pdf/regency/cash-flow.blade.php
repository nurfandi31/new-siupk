@extends('reports.pdf.regency.layout', ['title' => 'Arus Kas Konsolidasi - ' . ($regency_name ?? 'Kabupaten')])

@section('content')
<div class="text-center" style="margin-bottom: 12px;">
    <div class="header-title">LAPORAN ARUS KAS KONSOLIDASI KABUPATEN</div>
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
            <th class="b r" style="text-align: left;">Aktivitas Arus Kas</th>
            <th class="b r text-right" style="width: 100px;">Penerimaan (Rp)</th>
            <th class="b r text-right" style="width: 100px;">Pengeluaran (Rp)</th>
            <th class="b text-right" style="width: 110px;">Arus Bersih (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($report['sections'] as $key => $sec)
            <tr class="bg-gray font-bold">
                <td class="b r" colspan="2">{{ strtoupper($sec['title']) }}</td>
                <td class="b r text-right">{{ number_format($sec['total_in'], 2, ',', '.') }}</td>
                <td class="b r text-right">{{ number_format($sec['total_out'], 2, ',', '.') }}</td>
                <td class="b text-right">{{ number_format($sec['net'], 2, ',', '.') }}</td>
            </tr>
            @foreach ($sec['items'] as $item)
                <tr>
                    <td class="b r" style="color: #4b5563;">{{ $item['code'] }}</td>
                    <td class="b r" style="padding-left: 18px;">{{ $item['name'] }}</td>
                    <td class="b r text-right">{{ number_format($item['cash_in'], 2, ',', '.') }}</td>
                    <td class="b r text-right">{{ number_format($item['cash_out'], 2, ',', '.') }}</td>
                    <td class="b text-right">{{ number_format($item['net'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr class="bg-gray font-bold">
            <td class="t b r" colspan="4">SALDO KAS AWAL PERIODE</td>
            <td class="t b text-right">{{ number_format($report['reconciliation']['cash_opening'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        <tr class="font-bold">
            <td class="b r" colspan="4">KENAIKAN (PENURUNAN) BERSIH KAS</td>
            <td class="b text-right">{{ number_format($report['reconciliation']['net_change'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        <tr class="bg-gray font-bold">
            <td class="b r" colspan="4">SALDO KAS AKHIR PERIODE</td>
            <td class="b text-right">{{ number_format($report['reconciliation']['cash_closing'] ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endsection
