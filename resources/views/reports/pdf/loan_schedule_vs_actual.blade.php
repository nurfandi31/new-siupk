@extends('reports.pdf.layout', ['title' => 'Rencana vs Realisasi Angsuran', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="9" align="center">
            <div style="font-size: 18px;"><b>RENCANA VS REALISASI ANGSURAN</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="9" height="5"></td></tr>
    <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
        <td height="20">ID</td>
        <td>Kelompok</td>
        <td>Produk</td>
        <td align="right">Rencana Pokok</td>
        <td align="right">Realisasi Pokok</td>
        <td align="right">Gap Pokok</td>
        <td align="right">Rencana Jasa</td>
        <td align="right">Realisasi Jasa</td>
        <td align="right">Gap Jasa</td>
    </tr>
    @forelse($rows as $row)
        <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td>{{ $row['id'] }}</td>
            <td>{{ $row['group_name'] }}</td>
            <td>{{ $row['product_code'] }}</td>
            <td align="right">
                @if(($row['plan_principal'] ?? 0) < 0)
                    ({{ number_format(abs($row['plan_principal']), 2) }})
                @else
                    {{ number_format($row['plan_principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($row['actual_principal'] ?? 0) < 0)
                    ({{ number_format(abs($row['actual_principal']), 2) }})
                @else
                    {{ number_format($row['actual_principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($row['gap_principal'] ?? 0) < 0)
                    ({{ number_format(abs($row['gap_principal']), 2) }})
                @else
                    {{ number_format($row['gap_principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($row['plan_interest'] ?? 0) < 0)
                    ({{ number_format(abs($row['plan_interest']), 2) }})
                @else
                    {{ number_format($row['plan_interest'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($row['actual_interest'] ?? 0) < 0)
                    ({{ number_format(abs($row['actual_interest']), 2) }})
                @else
                    {{ number_format($row['actual_interest'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($row['gap_interest'] ?? 0) < 0)
                    ({{ number_format(abs($row['gap_interest']), 2) }})
                @else
                    {{ number_format($row['gap_interest'], 2) }}
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="9" align="center" style="color: #666; font-size: 10px;">Tidak ada data.</td>
        </tr>
    @endforelse
    @if(count($rows) > 0)
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="3">Jumlah</td>
            <td align="right">
                @if(($totals['plan_principal'] ?? 0) < 0)
                    ({{ number_format(abs($totals['plan_principal']), 2) }})
                @else
                    {{ number_format($totals['plan_principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['actual_principal'] ?? 0) < 0)
                    ({{ number_format(abs($totals['actual_principal']), 2) }})
                @else
                    {{ number_format($totals['actual_principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['gap_principal'] ?? 0) < 0)
                    ({{ number_format(abs($totals['gap_principal']), 2) }})
                @else
                    {{ number_format($totals['gap_principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['plan_interest'] ?? 0) < 0)
                    ({{ number_format(abs($totals['plan_interest']), 2) }})
                @else
                    {{ number_format($totals['plan_interest'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['actual_interest'] ?? 0) < 0)
                    ({{ number_format(abs($totals['actual_interest']), 2) }})
                @else
                    {{ number_format($totals['actual_interest'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['gap_interest'] ?? 0) < 0)
                    ({{ number_format(abs($totals['gap_interest']), 2) }})
                @else
                    {{ number_format($totals['gap_interest'], 2) }}
                @endif
            </td>
        </tr>
    @endif
</table>
@endsection