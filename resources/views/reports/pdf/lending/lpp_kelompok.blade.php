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
                        DAFTAR PERKEMBANGAN PIUTANG (LPP) RINCIAN KELOMPOK — {{ strtoupper($prod['product_name']) }} ({{ $prod['product_code'] }})
                    </div>
                    <div style="font-size: 16px; font-weight: bold;">
                        PERIODE: {{ strtoupper($period_label) }}
                    </div>
                </td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8px; table-layout: fixed;">
            <thead>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b" rowspan="2" width="18%">Kelompok / Loan ID</th>
                    <th class="t l b" rowspan="2" width="7%">Pencairan</th>
                    <th class="t l b" rowspan="2" width="4%">Pmf</th>
                    <th class="t l b" rowspan="2" width="8%">Alokasi</th>
                    <th class="t l b" colspan="2" width="13%">Target</th>
                    <th class="t l b" colspan="2" width="13%">Real s.d. Lalu</th>
                    <th class="t l b" colspan="2" width="13%">Real Bulan Ini</th>
                    <th class="t l b" colspan="2" width="13%">Real Kumulatif</th>
                    <th class="t l b" colspan="2" width="13%">Saldo</th>
                    <th class="t l b r" colspan="2" width="13%">Tunggakan</th>
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    <th class="t l b">Pokok</th>
                    <th class="t l b r">Jasa</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    <tr style="font-weight: bold;">
                        <td class="l b" colspan="17">DESA: {{ strtoupper($v['village_name']) }}</td>
                    </tr>
                    @foreach ($v['loans'] as $loan)
                        <tr>
                            <td class="l b">{{ $loan['group_name'] }} <span style="color: #718096;">(#{{ $loan['loan_id'] }})</span></td>
                            <td class="l b" align="center">{{ $loan['disbursed_at'] ? date('d/m/Y', strtotime($loan['disbursed_at'])) : '-' }}</td>
                            <td class="l b" align="center">{{ $loan['pemanfaat_count'] }}</td>
                            <td class="l b num">{{ number_format($loan['alokasi'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['target_pokok'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['target_jasa'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['real_lalu_pokok'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['real_lalu_jasa'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['real_ini_pokok'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['real_ini_jasa'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['real_kumulatif_pokok'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['real_kumulatif_jasa'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['saldo_pokok'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['saldo_jasa'], 0, ',', '.') }}</td>
                            <td class="l b num" style="{{ $loan['tunggakan_pokok'] > 0 ? 'color: #c53030;' : '' }}">{{ number_format($loan['tunggakan_pokok'], 0, ',', '.') }}</td>
                            <td class="l b r num" style="{{ $loan['tunggakan_jasa'] > 0 ? 'color: #c53030;' : '' }}">{{ number_format($loan['tunggakan_jasa'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: rgb(242,242,242); font-weight: bold;">
                        <td class="l b" colspan="2">Subtotal {{ $v['village_name'] }}</td>
                        <td class="l b" align="center">{{ $v['subtotal']['pemanfaat_count'] }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['alokasi'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['target_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['target_jasa'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['real_lalu_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['real_lalu_jasa'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['real_ini_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['real_ini_jasa'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['real_kumulatif_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['real_kumulatif_jasa'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['saldo_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['saldo_jasa'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['tunggakan_pokok'], 0, ',', '.') }}</td>
                        <td class="l b r num">{{ number_format($v['subtotal']['tunggakan_jasa'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" colspan="2" align="left">TOTAL {{ $prod['product_code'] }}</th>
                    <th class="t l b" align="center">{{ $prod['totals']['pemanfaat_count'] }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['alokasi'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['target_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['target_jasa'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['real_lalu_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['real_lalu_jasa'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['real_ini_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['real_ini_jasa'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['real_kumulatif_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['real_kumulatif_jasa'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo_jasa'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b r num">{{ number_format($prod['totals']['tunggakan_jasa'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
