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
                        DAFTAR KOLEKTIBILITAS PINJAMAN REKAP DESA — {{ strtoupper($prod['product_name']) }} ({{ $prod['product_code'] }})
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
                    <th class="t l b" rowspan="2" width="24%">Desa</th>
                    <th class="t l b" rowspan="2" width="11%">Alokasi</th>
                    <th class="t l b" rowspan="2" width="11%">Saldo Pokok</th>
                    <th class="t l b" rowspan="2" width="6%">%</th>
                    <th class="t l b" colspan="2" width="20%">Tunggakan</th>
                    <th class="t l b" width="10%">Lancar</th>
                    <th class="t l b" width="9%">Diragukan</th>
                    <th class="t l b r" width="9%">Macet</th>
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    <th class="t l b">(Tgk 1-3 Bln)</th>
                    <th class="t l b">(Tgk 4-5 Bln)</th>
                    <th class="t l b r">(Tgk 6+ Bln)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    @php
                        $pct = $v['alokasi'] > 0 ? round(($v['saldo'] / $v['alokasi']) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td class="l b">{{ $v['village_name'] }}</td>
                        <td class="l b num">{{ number_format($v['alokasi'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['saldo'], 0, ',', '.') }}</td>
                        <td class="l b" align="center">{{ $pct }}%</td>
                        <td class="l b num">{{ number_format($v['tunggakan_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['tunggakan_jasa'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['kolek1_lancar'], 0, ',', '.') }}</td>
                        <td class="l b num" style="{{ $v['kolek2_diragukan'] > 0 ? 'color: #d69e2e;' : '' }}">{{ number_format($v['kolek2_diragukan'], 0, ',', '.') }}</td>
                        <td class="l b r num" style="{{ $v['kolek3_macet'] > 0 ? 'color: #c53030;' : '' }}">{{ number_format($v['kolek3_macet'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $prodPct = $prod['totals']['alokasi'] > 0 ? round(($prod['totals']['saldo'] / $prod['totals']['alokasi']) * 100, 1) : 0;
                @endphp
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" align="left">TOTAL {{ $prod['product_code'] }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['alokasi'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo'], 0, ',', '.') }}</th>
                    <th class="t l b" align="center">{{ $prodPct }}%</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_jasa'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['kolek1_lancar'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['kolek2_diragukan'], 0, ',', '.') }}</th>
                    <th class="t l b r num">{{ number_format($prod['totals']['kolek3_macet'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
