@php
    $id = $identity;
    $legalName = strtoupper($id['legal_name'] ?? '');
    $district = $id['district_name'] ?? '';
    $regency = $id['regency_name'] ?? '';
    $periodLabel = strtoupper($period['period_label'] ?? '');
    $asOf = $period['as_of'] ?? '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Laporan Posisi Keuangan — OJK</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 60px; margin-left: 75px; margin-right: 75px; }
        body { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .title { text-align: center; }
        .title .big { font-size: 16px; font-weight: bold; }
        .title .med { font-size: 14px; font-weight: bold; }
        .title .sub { font-size: 12px; }
        .section-row td { background: rgb(74, 74, 74); color: #fff; font-weight: bold; text-align: center; padding: 4px; }
        .l2-row td { background: rgb(167, 167, 167); font-weight: bold; padding: 3px; }
        .l3-row td { padding: 2px 4px; }
        .zebra-odd { background: rgb(230, 230, 230); }
        .zebra-even { background: rgb(255, 255, 255); }
        .total-row td { background: rgb(200, 200, 200); font-weight: bold; padding: 4px; }
        .footer-row td { background: rgb(74, 74, 74); color: #fff; font-weight: bold; padding: 4px; }
        .ratio-table { margin-top: 14px; }
        .ratio-table th, .ratio-table td { border: 1px solid #000; padding: 3px 5px; }
        .ratio-table th { background: rgb(74, 74, 74); color: #fff; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <table border="0" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td colspan="4" align="center">
                <div style="font-size: 14px;"><b>{{ $legalName }}</b></div>
                <div style="font-size: 16px;"><b>LAPORAN POSISI KEUANGAN</b></div>
                <div style="font-size: 14px;"><b>{{ $periodLabel }}</b></div>
                <div style="font-size: 10px;">per {{ $asOf }}</div>
            </td>
        </tr>
        <tr><td colspan="4" height="6"></td></tr>
    </table>

    <table border="0" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr style="background: rgb(74, 74, 74); color: #fff; font-weight: bold;">
            <td width="14%" align="center">No</td>
            <td width="16%">Kode Akun</td>
            <td width="55%">Nama Akun</td>
            <td width="15%" align="right">Jumlah</td>
        </tr>
        @foreach ($sections as $l1)
            <tr class="section-row">
                <td colspan="4" align="center">{{ $l1['letter'] }}. {{ strtoupper($l1['ojk_label']) }}</td>
            </tr>
            @php $idxL3 = 0; @endphp
            @foreach ($l1['children'] as $l2)
                <tr class="l2-row">
                    <td>{{ $l1['letter'] }}</td>
                    <td>{{ $l2['code'] }}</td>
                    <td colspan="2">{{ $l2['name'] }}</td>
                </tr>
                @foreach ($l2['children'] as $l3)
                    @php
                        $bg = $idxL3 % 2 === 0 ? 'zebra-even' : 'zebra-odd';
                    @endphp
                    <tr class="l3-row {{ $bg }}">
                        <td align="center">{{ $idxL3 + 1 }}</td>
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
                    @php $idxL3++; @endphp
                @endforeach
            @endforeach
            <tr class="total-row">
                <td align="center">{{ $l1['letter'] }}</td>
                <td colspan="2">Jumlah {{ $l1['ojk_label'] }}</td>
                <td align="right">
                    @if(($l1['balance'] ?? 0) < 0)
                        ({{ number_format(abs($l1['balance']), 2) }})
                    @else
                        {{ number_format($l1['balance'] ?? 0, 2) }}
                    @endif
                </td>
            </tr>
        @endforeach
        <tr class="footer-row">
            <td colspan="3">Jumlah Liabilitas + Ekuitas</td>
            <td align="right">{{ number_format($totals['liabilities_equity'], 2) }}</td>
        </tr>
    </table>

    <table class="ratio-table" border="1" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <th colspan="4">Rasio Likuiditas</th>
        </tr>
        <tr>
            <td>1.</td>
            <td colspan="2">Kas dan Setara Kas</td>
            <td align="right">{{ number_format($kas_dan_setara_kas, 2) }}</td>
        </tr>
        <tr>
            <td>2.</td>
            <td colspan="2">Liabilitas Lancar</td>
            <td align="right">{{ number_format($liabilitas_lancar, 2) }}</td>
        </tr>
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="3">Rasio Likuiditas (%)</td>
            <td align="right">
                {{ $rasio_likuiditas !== null ? number_format($rasio_likuiditas, 2) . '%' : '—' }}
            </td>
        </tr>
        <tr>
            <th colspan="4">Rasio Solvabilitas</th>
        </tr>
        <tr>
            <td>1.</td>
            <td colspan="2">Total Aset</td>
            <td align="right">{{ number_format($totals['assets'], 2) }}</td>
        </tr>
        <tr>
            <td>2.</td>
            <td colspan="2">Total Liabilitas</td>
            <td align="right">{{ number_format($totals['liabilities'], 2) }}</td>
        </tr>
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="3">Rasio Solvabilitas (%)</td>
            <td align="right">
                {{ $rasio_solvabilitas !== null ? number_format($rasio_solvabilitas, 2) . '%' : '—' }}
            </td>
        </tr>
    </table>

    <p style="margin-top: 12px; font-size: 10px;">
        <i>Laba/Rugi tahun berjalan: {{ number_format($net_income, 2) }}</i>
    </p>
</body>
</html>