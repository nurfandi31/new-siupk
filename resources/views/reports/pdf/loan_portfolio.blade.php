@extends('reports.pdf.layout', ['title' => 'Portofolio Pinjaman', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="10" align="center">
            <div style="font-size: 18px;"><b>PORTOFOLIO PINJAMAN</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="10" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 14px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td height="20">Aging</td>
        <td align="center">Jumlah</td>
        <td align="right">Sisa Pokok</td>
        <td align="right">Nilai Tunggakan</td>
    </tr>
    @foreach($aging as $bucket)
        <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td>{{ $bucket['label'] }}</td>
            <td align="center">{{ $bucket['count'] }}</td>
            <td align="right">
                @if(($bucket['principal'] ?? 0) < 0)
                    ({{ number_format(abs($bucket['principal']), 2) }})
                @else
                    {{ number_format($bucket['principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($bucket['overdue'] ?? 0) < 0)
                    ({{ number_format(abs($bucket['overdue']), 2) }})
                @else
                    {{ number_format($bucket['overdue'], 2) }}
                @endif
            </td>
        </tr>
    @endforeach
    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
        <td>Total</td>
        <td align="center">{{ $totals['count'] }}</td>
        <td align="right">
            @if(($totals['principal_remaining'] ?? 0) < 0)
                ({{ number_format(abs($totals['principal_remaining']), 2) }})
            @else
                {{ number_format($totals['principal_remaining'], 2) }}
            @endif
        </td>
        <td align="right">
            @if(($totals['overdue_amount'] ?? 0) < 0)
                ({{ number_format(abs($totals['overdue_amount']), 2) }})
            @else
                {{ number_format($totals['overdue_amount'], 2) }}
            @endif
        </td>
    </tr>
</table>

@if(!empty($by_village))
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 14px;">
        <tr style="background: rgb(232, 232, 232); font-weight: bold;">
            <td height="20">Desa</td>
            <td align="center">Pinjaman</td>
            <td align="right">Sisa Pokok</td>
            <td align="right">Tunggakan</td>
            <td align="center"># Nunggak</td>
        </tr>
        @foreach($by_village as $v)
            <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td>{{ $v['village'] }}</td>
                <td align="center">{{ $v['count'] }}</td>
                <td align="right">
                    @if(($v['principal'] ?? 0) < 0)
                        ({{ number_format(abs($v['principal']), 2) }})
                    @else
                        {{ number_format($v['principal'], 2) }}
                    @endif
                </td>
                <td align="right">
                    @if(($v['overdue'] ?? 0) < 0)
                        ({{ number_format(abs($v['overdue']), 2) }})
                    @else
                        {{ number_format($v['overdue'], 2) }}
                    @endif
                </td>
                <td align="center">{{ $v['overdue_count'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

@php
    $sections = [];
    foreach ($rows as $row) {
        $key = $row['village_name'] ?? '—';
        if (! isset($sections[$key])) {
            $sections[$key] = [
                'village' => $key,
                'rows' => [],
                'count' => 0,
                'principal_disbursed' => 0.0,
                'principal_remaining' => 0.0,
                'overdue_principal' => 0.0,
                'overdue_interest' => 0.0,
            ];
        }
        $sections[$key]['rows'][] = $row;
        $sections[$key]['count']++;
        $sections[$key]['principal_disbursed'] += (float) ($row['principal_disbursed'] ?? 0);
        $sections[$key]['principal_remaining'] += (float) ($row['principal_remaining'] ?? 0);
        $sections[$key]['overdue_principal'] += (float) ($row['overdue_principal'] ?? 0);
        $sections[$key]['overdue_interest'] += (float) ($row['overdue_interest'] ?? 0);
    }
@endphp

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
        <td height="20">ID</td>
        <td>Kelompok</td>
        <td>Produk</td>
        <td>Cair</td>
        <td align="right">Alokasi</td>
        <td align="right">Sisa Pokok</td>
        <td align="right">Tungg. Pokok</td>
        <td align="right">Tungg. Jasa</td>
        <td align="center">Hari</td>
        <td>Jatuh tempo</td>
    </tr>
    @forelse($sections as $section)
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="10">Desa {{ $section['village'] }} &middot; {{ $section['count'] }} pinjaman</td>
        </tr>
        @foreach($section['rows'] as $row)
            <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td>{{ $row['id'] }}</td>
                <td>{{ $row['group_name'] }}</td>
                <td>{{ $row['product_code'] }}</td>
                <td>{{ $row['disbursed_at'] ? \Carbon\CarbonImmutable::parse($row['disbursed_at'])->format('d/m/Y') : '—' }}</td>
                <td align="right">
                    @if(($row['principal_disbursed'] ?? 0) < 0)
                        ({{ number_format(abs($row['principal_disbursed']), 2) }})
                    @else
                        {{ number_format($row['principal_disbursed'], 2) }}
                    @endif
                </td>
                <td align="right">
                    @if(($row['principal_remaining'] ?? 0) < 0)
                        ({{ number_format(abs($row['principal_remaining']), 2) }})
                    @else
                        {{ number_format($row['principal_remaining'], 2) }}
                    @endif
                </td>
                <td align="right">
                    @if(($row['overdue_principal'] ?? 0) < 0)
                        ({{ number_format(abs($row['overdue_principal']), 2) }})
                    @else
                        {{ number_format($row['overdue_principal'] ?? 0, 2) }}
                    @endif
                </td>
                <td align="right">
                    @if(($row['overdue_interest'] ?? 0) < 0)
                        ({{ number_format(abs($row['overdue_interest']), 2) }})
                    @else
                        {{ number_format($row['overdue_interest'] ?? 0, 2) }}
                    @endif
                </td>
                <td align="center">{{ $row['days_overdue'] > 0 ? $row['days_overdue'] : '—' }}</td>
                <td>{{ $row['next_due_date'] ? \Carbon\CarbonImmutable::parse($row['next_due_date'])->format('d/m/Y') : '—' }}</td>
            </tr>
        @endforeach
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="4">Total {{ $section['village'] }}</td>
            <td align="right">
                @if($section['principal_disbursed'] < 0)
                    ({{ number_format(abs($section['principal_disbursed']), 2) }})
                @else
                    {{ number_format($section['principal_disbursed'], 2) }}
                @endif
            </td>
            <td align="right">
                @if($section['principal_remaining'] < 0)
                    ({{ number_format(abs($section['principal_remaining']), 2) }})
                @else
                    {{ number_format($section['principal_remaining'], 2) }}
                @endif
            </td>
            <td align="right">
                @if($section['overdue_principal'] < 0)
                    ({{ number_format(abs($section['overdue_principal']), 2) }})
                @else
                    {{ number_format($section['overdue_principal'], 2) }}
                @endif
            </td>
            <td align="right">
                @if($section['overdue_interest'] < 0)
                    ({{ number_format(abs($section['overdue_interest']), 2) }})
                @else
                    {{ number_format($section['overdue_interest'], 2) }}
                @endif
            </td>
            <td colspan="2"></td>
        </tr>
    @empty
        <tr>
            <td colspan="10" align="center" style="color: #666; font-size: 10px;">Tidak ada pinjaman aktif.</td>
        </tr>
    @endforelse
    @if(count($rows) > 0)
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="4">Jumlah seluruh desa</td>
            <td align="right">
                @if(($totals['principal_disbursed'] ?? 0) < 0)
                    ({{ number_format(abs($totals['principal_disbursed']), 2) }})
                @else
                    {{ number_format($totals['principal_disbursed'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['principal_remaining'] ?? 0) < 0)
                    ({{ number_format(abs($totals['principal_remaining']), 2) }})
                @else
                    {{ number_format($totals['principal_remaining'], 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['overdue_principal'] ?? 0) < 0)
                    ({{ number_format(abs($totals['overdue_principal']), 2) }})
                @else
                    {{ number_format($totals['overdue_principal'] ?? 0, 2) }}
                @endif
            </td>
            <td align="right">
                @if(($totals['overdue_interest'] ?? 0) < 0)
                    ({{ number_format(abs($totals['overdue_interest']), 2) }})
                @else
                    {{ number_format($totals['overdue_interest'] ?? 0, 2) }}
                @endif
            </td>
            <td colspan="2"></td>
        </tr>
    @endif
</table>
@endsection