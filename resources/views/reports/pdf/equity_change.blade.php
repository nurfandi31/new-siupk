@extends('reports.pdf.layout', ['title' => 'Laporan Perubahan Ekuitas', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>LAPORAN PERUBAHAN EKUITAS</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
</table>

<table width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(232, 232, 232);">
        <th class="l t" width="5%" height="20">No</th>
        <th class="l t" width="55%">Rekening Modal</th>
        <th class="l r t" width="20%">&nbsp;</th>
    </tr>

    @foreach($rows as $row)
        <tr>
            <td class="l t" align="center">{{ $loop->iteration }}</td>
            <td class="l t">{{ $row['name'] }}</td>
            <td class="l t r" align="right">
                @if($row['closing'] < 0)
                    ({{ number_format(abs($row['closing']), 2) }})
                @else
                    {{ number_format($row['closing'], 2) }}
                @endif
            </td>
        </tr>
    @endforeach

    <tr>
        <td class="l t b" colspan="2" height="15">&nbsp;</td>
        <td class="l t b r" align="right">
            @if(($summary['closing_total'] ?? 0) < 0)
                ({{ number_format(abs($summary['closing_total'] ?? 0), 2) }})
            @else
                {{ number_format($summary['closing_total'] ?? 0, 2) }}
            @endif
        </td>
    </tr>
</table>
@endsection
