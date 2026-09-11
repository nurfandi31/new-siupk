@extends('reports.pdf.regency.layout', ['title' => 'Neraca Konsolidasi - ' . ($regency_name ?? 'Kabupaten')])

@section('content')
<div class="text-center" style="margin-bottom: 12px;">
    <div class="header-title">NERACA KONSOLIDASI KABUPATEN</div>
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
            <th class="b r" style="text-align: left;">Nama Akun</th>
            @if ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1)
                @foreach ($report['kecamatans'] as $kec)
                    <th class="b r text-right" style="width: 80px;">{{ $kec['name'] }}</th>
                @endforeach
            @endif
            <th class="b text-right" style="width: 100px;">Total Gabungan (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($report['groups'] as $group)
            <tr class="bg-gray font-bold">
                <td class="b r">{{ $group['code'] }}</td>
                <td class="b r" colspan="{{ ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1) ? count($report['kecamatans']) + 1 : 1 }}">
                    {{ strtoupper($group['name']) }}
                </td>
                <td class="b text-right">{{ number_format($group['total'], 2, ',', '.') }}</td>
            </tr>

            @foreach ($group['subgroups'] as $sub)
                <tr class="font-bold" style="background-color: #fafafa;">
                    <td class="b r">{{ $sub['code'] }}</td>
                    <td class="b r" colspan="{{ ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1) ? count($report['kecamatans']) + 1 : 1 }}" style="padding-left: 12px;">
                        {{ $sub['name'] }}
                    </td>
                    <td class="b text-right">{{ number_format($sub['total'], 2, ',', '.') }}</td>
                </tr>

                @foreach ($sub['rows'] as $row)
                    @if ($row['total'] != 0 || count(array_filter($row['tenants'] ?? [])) > 0)
                        <tr>
                            <td class="b r" style="color: #4b5563;">{{ $row['code'] }}</td>
                            <td class="b r" style="padding-left: 24px;">{{ $row['name'] }}</td>
                            @if ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1)
                                @foreach ($report['kecamatans'] as $kec)
                                    <td class="b r text-right">{{ number_format($row['tenants'][$kec['id']] ?? 0, 2, ',', '.') }}</td>
                                @endforeach
                            @endif
                            <td class="b text-right">{{ number_format($row['total'], 2, ',', '.') }}</td>
                        </tr>
                    @endif
                @endforeach
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr class="bg-gray font-bold">
            <td class="t b r" colspan="2">TOTAL AKTIVA</td>
            @if ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1)
                <td class="t b r" colspan="{{ count($report['kecamatans']) }}"></td>
            @endif
            <td class="t b text-right">{{ number_format($report['summary']['total_assets'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        <tr class="bg-gray font-bold">
            <td class="b r" colspan="2">TOTAL KEWAJIBAN & EKUITAS</td>
            @if ($report['is_consolidated'] && count($report['kecamatans'] ?? []) > 1)
                <td class="b r" colspan="{{ count($report['kecamatans']) }}"></td>
            @endif
            <td class="b text-right">{{ number_format($report['summary']['total_liabilities_and_equity'] ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endsection
