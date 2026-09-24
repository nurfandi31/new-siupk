@php
    $id = $identity;
    $legalName = strtoupper($id['legal_name'] ?? '');
    $periodLabel = strtoupper($period['period_label'] ?? '');
    $headerLalu = $header_lalu ?? 'Bulan Lalu';
    $headerSekarang = $header_sekarang ?? 'Bulan Ini';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Laporan Laba Rugi — OJK</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 60px; margin-left: 75px; margin-right: 75px; }
        body { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .title { text-align: center; }
        .section-row td { background: rgb(150, 150, 150); font-weight: bold; padding: 4px; }
        .l3-row td { padding: 2px 6px; }
        .zebra-odd { background: rgb(230, 230, 230); }
        .zebra-even { background: rgb(255, 255, 255); }
        .group-total td { background: rgb(150, 150, 150); font-weight: bold; padding: 3px 4px; }
        .summary-row td { background: rgb(200, 200, 200); font-weight: bold; padding: 4px; }
        .summary-final td { background: rgb(74, 74, 74); color: #fff; font-weight: bold; padding: 4px; }
    </style>
</head>
<body>
    <table border="0" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td colspan="5" align="center">
                <div style="font-size: 14px;"><b>{{ $legalName }}</b></div>
                <div style="font-size: 16px;"><b>LAPORAN LABA RUGI</b></div>
                <div style="font-size: 14px;"><b>{{ $periodLabel }}</b></div>
            </td>
        </tr>
        <tr><td colspan="5" height="6"></td></tr>
    </table>

    <table border="0" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <thead>
            <tr style="background: rgb(232, 232, 232); font-weight: bold;">
                <td align="center" width="8%">No</td>
                <td align="left" width="47%">Rekening</td>
                <td align="center" width="15%">s.d. {{ $headerLalu }}</td>
                <td align="center" width="15%">{{ $headerSekarang }}</td>
                <td align="center" width="15%">s.d. {{ $headerSekarang }}</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($groups as $group)
                <tr class="section-row">
                    <td align="center">{{ $group['ojk_letter'] }}</td>
                    <td colspan="4">{{ $group['code'] }}. {{ $group['name'] }}</td>
                </tr>
                @php $idx = 0; @endphp
                @foreach ($group['children'] as $row)
                    @php $bg = $idx % 2 === 0 ? 'zebra-even' : 'zebra-odd'; @endphp
                    <tr class="l3-row {{ $bg }}">
                        <td align="center">{{ $idx + 1 }}</td>
                        <td align="left">{{ $row['code'] }}. {{ $row['name'] }}</td>
                        <td align="right">{{ number_format($row['prior'], 2) }}</td>
                        <td align="right">{{ number_format($row['current'], 2) }}</td>
                        <td align="right">{{ number_format($row['ytd'], 2) }}</td>
                    </tr>
                    @php $idx++; @endphp
                @endforeach
                <tr class="group-total">
                    <td align="center">{{ $group['ojk_letter'] }}</td>
                    <td align="left">Jumlah {{ $group['name'] }}</td>
                    <td align="right">{{ number_format($group['prior'], 2) }}</td>
                    <td align="right">{{ number_format($group['current'], 2) }}</td>
                    <td align="right">{{ number_format($group['ytd'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="summary-row">
                <td align="center">A</td>
                <td align="left">A. Laba (Rugi) OPERASIONAL (Pendapatan Ops - Beban Ops)</td>
                <td align="right">{{ number_format($summary['operating']['prior'], 2) }}</td>
                <td align="right">{{ number_format($summary['operating']['current'], 2) }}</td>
                <td align="right">{{ number_format($summary['operating']['ytd'], 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td align="center">B</td>
                <td align="left">B. Laba (Rugi) NON OPERASIONAL (Pendapatan Non Ops - Beban Non Ops)</td>
                <td align="right">{{ number_format($summary['non_operating']['prior'], 2) }}</td>
                <td align="right">{{ number_format($summary['non_operating']['current'], 2) }}</td>
                <td align="right">{{ number_format($summary['non_operating']['ytd'], 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td align="center">C</td>
                <td align="left">C. Laba (Rugi) Sebelum Pajak (A + B)</td>
                <td align="right">{{ number_format($summary['before_tax']['prior'], 2) }}</td>
                <td align="right">{{ number_format($summary['before_tax']['current'], 2) }}</td>
                <td align="right">{{ number_format($summary['before_tax']['ytd'], 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td align="center">D</td>
                <td align="left">D. Beban Pajak</td>
                <td align="right">{{ number_format($summary['tax']['prior'], 2) }}</td>
                <td align="right">{{ number_format($summary['tax']['current'], 2) }}</td>
                <td align="right">{{ number_format($summary['tax']['ytd'], 2) }}</td>
            </tr>
            <tr class="summary-final">
                <td align="center">E</td>
                <td align="left">E. Laba (Rugi) Bersih (C - D)</td>
                <td align="right">{{ number_format($summary['after_tax']['prior'], 2) }}</td>
                <td align="right">{{ number_format($summary['after_tax']['current'], 2) }}</td>
                <td align="right">{{ number_format($summary['after_tax']['ytd'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>