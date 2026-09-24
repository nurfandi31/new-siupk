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
                        DAFTAR KOLEKTIBILITAS MINGGUAN RINCIAN INDIVIDU — {{ strtoupper($prod['product_name']) }} ({{ $prod['product_code'] }})
                    </div>
                    <div style="font-size: 16px; font-weight: bold;">
                        MINGGU KE-{{ $week }} BULAN {{ strtoupper($period_label) }}
                    </div>
                    <div style="font-size: 13px; font-weight: bold; color: #2d3748;">
                        PERIODE: {{ \Carbon\Carbon::parse($week_start)->format('d/m/Y') }} s.d. {{ \Carbon\Carbon::parse($week_end)->format('d/m/Y') }}
                    </div>
                </td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8.5px; table-layout: fixed;">
            <thead>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b" rowspan="2" width="18%">Desa</th>
                    <th class="t l b" rowspan="2" width="22%">Peminjam / NIK</th>
                    <th class="t l b" rowspan="2" width="11%">Alokasi</th>
                    <th class="t l b" rowspan="2" width="11%">Saldo Pokok</th>
                    <th class="t l b" colspan="2" width="20%">Tunggakan</th>
                    <th class="t l b r" rowspan="2" width="18%">Kolek</th>
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    <tr style="font-weight: bold;">
                        <td class="l b" colspan="7">DESA: {{ strtoupper($v['village_name']) }}</td>
                    </tr>
                    @foreach ($v['loans'] as $loan)
                        @php
                            $kolekLabel = $loan['kolek'] === 1 ? 'Lancar' : ($loan['kolek'] === 2 ? 'Diragukan' : 'Macet');
                            $kolekColor = $loan['kolek'] === 1 ? '#2f855a' : ($loan['kolek'] === 2 ? '#d69e2e' : '#c53030');
                        @endphp
                        <tr>
                            <td class="l b">{{ $v['village_name'] }}</td>
                            <td class="l b">
                                {{ $loan['member_name'] }} (#{{ $loan['loan_id'] }})<br>
                                <span style="color: #718096; font-size: 7px;">NIK: {{ $loan['nik'] ?? '-' }}</span>
                            </td>
                            <td class="l b num">{{ number_format($loan['alokasi'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['saldo'], 0, ',', '.') }}</td>
                            <td class="l b num" style="{{ $loan['tunggakan_pokok'] > 0 ? 'color: #c53030;' : '' }}">{{ number_format($loan['tunggakan_pokok'], 0, ',', '.') }}</td>
                            <td class="l b num" style="{{ $loan['tunggakan_jasa'] > 0 ? 'color: #c53030;' : '' }}">{{ number_format($loan['tunggakan_jasa'], 0, ',', '.') }}</td>
                            <td class="l b r" align="center" style="color: {{ $kolekColor }}; font-weight: bold;">{{ $kolekLabel }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: rgb(242,242,242); font-weight: bold;">
                        <td class="l b" colspan="2">Subtotal {{ $v['village_name'] }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['alokasi'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['saldo'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['tunggakan_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['tunggakan_jasa'], 0, ',', '.') }}</td>
                        <td class="l b r" align="center">{{ $v['subtotal']['peminjam_count'] }} peminjam</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" colspan="2" align="left">TOTAL {{ $prod['product_code'] }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['alokasi'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_jasa'], 0, ',', '.') }}</th>
                    <th class="t l b r" align="center">{{ $prod['totals']['peminjam_count'] }} peminjam</th>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection