@extends('reports.pdf.regency.layout', ['title' => 'Laporan Perubahan Modal - ' . ($regency_name ?? 'Kabupaten')])

@section('content')
<div class="text-center" style="margin-bottom: 12px;">
    <div class="header-title">LAPORAN PERUBAHAN MODAL</div>
    <div class="header-title">REKAP KABUPATEN {{ strtoupper($regency_name ?? 'KABUPATEN') }}</div>
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
            <th class="b r" style="width: 40px;">No</th>
            <th class="b r" style="width: 90px;">Kode</th>
            <th class="b r" style="text-align: left;">Rekening Modal</th>
            @if ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1)
                @foreach ($report['kecamatans'] as $kec)
                    <th class="b r text-right" style="width: 80px;">{{ $kec['name'] }}</th>
                @endforeach
            @endif
            <th class="b text-right" style="width: 100px;">Total Gabungan (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($report['rows'] ?? [] as $row)
            <tr>
                <td class="b r text-center">{{ $loop->iteration }}</td>
                <td class="b r">{{ $row['code'] }}</td>
                <td class="b r">{{ $row['name'] }}</td>
                @if ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1)
                    @foreach ($report['kecamatans'] as $kec)
                        <td class="b r text-right">{{ number_format($row['tenants'][$kec['id']] ?? 0, 2, ',', '.') }}</td>
                    @endforeach
                @endif
                <td class="b text-right">{{ number_format($row['total'] ?? 0, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td class="b r text-center" colspan="{{ ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1) ? count($report['kecamatans']) + 4 : 4 }}">
                    Belum ada rekening modal pada periode ini.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="bg-gray font-bold">
            <td class="t b r" colspan="3">TOTAL PERUBAHAN MODAL</td>
            @if ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1)
                <td class="t b r" colspan="{{ count($report['kecamatans']) }}"></td>
            @endif
            <td class="t b text-right">{{ number_format($report['summary']['total_equity'] ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endsection
