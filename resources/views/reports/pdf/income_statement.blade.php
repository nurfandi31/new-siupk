@extends('reports.pdf.layout', ['title' => 'Laporan Laba Rugi', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="4" align="center">
            <div style="font-size: 18px;"><b>LAPORAN LABA RUGI</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="4" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <thead>
        <tr style="background: rgb(232, 232, 232); font-weight: bold; font-size: 12px;">
            <td align="center" width="55%" height="16">Rekening</td>
            <td align="center" width="15%">s.d. {{ $period['prior_label'] ?? '' }}</td>
            <td align="center" width="15%">{{ $period['current_label'] ?? '' }}</td>
            <td align="center" width="15%">s.d. {{ $period['current_label'] ?? '' }}</td>
        </tr>
    </thead>
    <tbody>
    @foreach($groups as $group)
        <tr style="background: rgb(150, 150, 150); font-weight: bold;">
            <td colspan="4" height="14">{{ $group['code'] }}. {{ $group['name'] }}</td>
        </tr>
        @if(!empty($group['children']))
            @php $hasSubGroups = isset($group['children'][0]['children']); @endphp
            @if($hasSubGroups)
                @foreach($group['children'] as $subGroup)
                    @foreach($subGroup['children'] as $idx => $row)
                        <tr style="background: {{ $idx % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                            <td align="left">{{ $row['code'] }}. {{ $row['name'] }}</td>
                            <td align="right">{{ number_format($row['prior'], 2) }}</td>
                            <td align="right">{{ number_format($row['current'], 2) }}</td>
                            <td align="right">{{ number_format($row['ytd'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: rgb(150, 150, 150); font-weight: bold;">
                        <td align="left" height="14">Jumlah {{ $subGroup['code'] }}. {{ $subGroup['name'] }}</td>
                        <td align="right">{{ number_format($subGroup['prior'], 2) }}</td>
                        <td align="right">{{ number_format($subGroup['current'], 2) }}</td>
                        <td align="right">{{ number_format($subGroup['ytd'], 2) }}</td>
                    </tr>
                @endforeach
            @else
                @foreach($group['children'] as $idx => $row)
                    <tr style="background: {{ $idx % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                        <td align="left">{{ $row['code'] }}. {{ $row['name'] }}</td>
                        <td align="right">{{ number_format($row['prior'], 2) }}</td>
                        <td align="right">{{ number_format($row['current'], 2) }}</td>
                        <td align="right">{{ number_format($row['ytd'], 2) }}</td>
                    </tr>
                @endforeach
            @endif
            <tr style="background: rgb(150, 150, 150); font-weight: bold;">
                <td align="left" height="14">Jumlah {{ $group['code'] }}. {{ $group['name'] }}</td>
                <td align="right">{{ number_format($group['prior'], 2) }}</td>
                <td align="right">{{ number_format($group['current'], 2) }}</td>
                <td align="right">{{ number_format($group['ytd'], 2) }}</td>
            </tr>
        @endif
    @endforeach

    <tr><td colspan="4" height="2"></td></tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td align="left">A. Laba Rugi OPERASIONAL (Kode Akun 4.1 - 5.1 - 5.2)</td>
        <td align="right">{{ number_format($summary['operating']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['operating']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['operating']['ytd'], 2) }}</td>
    </tr>

    <tr><td colspan="4" height="2"></td></tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td align="left">B. Laba Rugi NON OPERASIONAL (Kode Akun 4.2 - 5.3)</td>
        <td align="right">{{ number_format($summary['non_operating']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['non_operating']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['non_operating']['ytd'], 2) }}</td>
    </tr>

    <tr><td colspan="4" height="2"></td></tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td align="left">C. Laba Rugi Sebelum Taksiran Pajak (A + B)</td>
        <td align="right">{{ number_format($summary['before_tax']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['before_tax']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['before_tax']['ytd'], 2) }}</td>
    </tr>

    <tr><td colspan="4" height="2"></td></tr>
    <tr style="background: rgb(150, 150, 150); font-weight: bold;">
        <td colspan="4" height="14">5.4 Beban Pajak</td>
    </tr>
    <tr style="background: rgb(230, 230, 230);">
        <td align="left">5.4.01.01. Taksiran PPh</td>
        <td align="right">{{ number_format($summary['tax']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['tax']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['tax']['ytd'], 2) }}</td>
    </tr>

    <tr><td colspan="4" height="2"></td></tr>
    <tr>
        <td colspan="4" style="padding: 0px !important;">
            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                <tr style="background: rgb(200, 200, 200); font-weight: bold;">
                    <td width="55%" align="left">C. Laba Rugi Setelah Taksiran Pajak (A + B)</td>
                    <td width="15%" align="right">{{ number_format($summary['after_tax']['prior'], 2) }}</td>
                    <td width="15%" align="right">{{ number_format($summary['after_tax']['current'], 2) }}</td>
                    <td width="15%" align="right">{{ number_format($summary['after_tax']['ytd'], 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
    </tbody>
</table>
@endsection
