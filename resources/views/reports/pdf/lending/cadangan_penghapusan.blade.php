@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    @foreach ($products as $idx => $prod)
        @if ($idx > 0)
            <div class="break"></div>
        @endif

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
            <tr>
                <td align="center">
                    <div style="font-size: 18px; font-weight: bold;">
                        CADANGAN KERUGIAN PENURUNAN NILAI (CKPN) — {{ strtoupper($prod['product_name']) }} ({{ $prod['product_code'] }})
                    </div>
                    <div style="font-size: 16px; font-weight: bold;">
                        PERIODE: {{ strtoupper($period_label) }}
                    </div>
                </td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8.5px; table-layout: fixed;">
            <thead>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b" rowspan="2" width="22%">Desa</th>
                    <th class="t l b" rowspan="2" width="10%">Saldo Pokok</th>
                    <th class="t l b" colspan="3" width="36%">Klasifikasi Kolektibilitas</th>
                    <th class="t l b" colspan="3" width="24%">Penyisihan Cadangan (CKPN)</th>
                    <th class="t l b r" rowspan="2" width="8%">Total CKPN</th>
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b">Lancar</th>
                    <th class="t l b">Diragukan</th>
                    <th class="t l b">Macet</th>
                    <th class="t l b">0.5% Lancar</th>
                    <th class="t l b">50% Diragukan</th>
                    <th class="t l b">100% Macet</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    <tr>
                        <td class="l b">{{ $v['village_name'] }}</td>
                        <td class="l b num">{{ number_format($v['saldo'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['kolek1_lancar'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['kolek2_diragukan'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['kolek3_macet'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['ckpn_lancar'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['ckpn_diragukan'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['ckpn_macet'], 0, ',', '.') }}</td>
                        <td class="l b r num" style="font-weight: bold;">{{ number_format($v['total_ckpn'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" align="left">TOTAL {{ $prod['product_code'] }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['kolek1_lancar'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['kolek2_diragukan'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['kolek3_macet'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['ckpn_lancar'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['ckpn_diragukan'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['ckpn_macet'], 0, ',', '.') }}</th>
                    <th class="t l b r num">{{ number_format($prod['totals']['total_ckpn'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
