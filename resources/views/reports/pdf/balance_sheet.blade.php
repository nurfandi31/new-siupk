@extends('reports.pdf.layout', ['title' => 'Neraca', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>NERACA</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="3"></td></tr>
    <tr style="background: #000; color: #fff;">
        <td width="10%">Kode</td>
        <td width="70%">Nama Akun</td>
        <td width="20%" align="right">Saldo</td>
    </tr>
    <tr><td colspan="3" height="1"></td></tr>
    @foreach($sections as $l1)
        <tr style="background: rgb(74, 74, 74); color: #fff;">
            <td colspan="3" align="center" height="20"><b>{{ $l1['code'] }}. {{ $l1['name'] }}</b></td>
        </tr>
        @foreach($l1['children'] as $l2)
            <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                <td>{{ $l2['code'] }}</td>
                <td colspan="2">{{ $l2['name'] }}</td>
            </tr>
            @foreach($l2['children'] as $idx => $l3)
                <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                    <td>{{ $l3['code'] }}</td>
                    <td>{{ $l3['name'] }}</td>
                    <td align="right">
                        @if($l3['balance'] < 0)
                            ({{ number_format(abs($l3['balance']), 2) }})
                        @else
                            {{ number_format($l3['balance'], 2) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        @endforeach
        @php
            $sectionLabel = match ($l1['account_type'] ?? '') {
                'asset' => 'Jumlah Aset',
                'liability' => 'Jumlah Utang',
                'equity' => 'Jumlah Modal',
                default => 'Jumlah '.$l1['name'],
            };
        @endphp
        <tr>
            <td colspan="3" style="padding: 0px !important;">
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                        <td colspan="2">{{ $sectionLabel }}</td>
                        <td align="right">
                            @if(($l1['balance'] ?? 0) < 0)
                                ({{ number_format(abs($l1['balance']), 2) }})
                            @else
                                {{ number_format($l1['balance'] ?? 0, 2) }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td colspan="2">Jumlah Liabilitas + Ekuitas</td>
        <td align="right">{{ number_format($totals['liabilities_equity'], 2) }}</td>
    </tr>
</table>
@endsection
