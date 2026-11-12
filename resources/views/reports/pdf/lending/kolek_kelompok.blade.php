@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
        .num { text-align: right; white-space: nowrap; }
        .village-row td { background: rgb(242, 242, 242); font-weight: bold; }
        .subtotal-row td { background: rgb(248, 248, 248); font-weight: bold; }
    </style>

    @foreach ($products as $idx => $prod)
        @if ($idx > 0)
            <div class="break"></div>
        @endif

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
            <tr>
                <td align="center">
                    <div style="font-size: 18px; font-weight: bold;">
                        DAFTAR KOLEKTIBILITAS PINJAMAN KELOMPOK - {{ strtoupper($prod['product_name']) }} ({{ $prod['product_code'] }})
                    </div>
                    <div style="font-size: 16px; font-weight: bold;">
                        PERIODE: {{ strtoupper($period_label) }}
                    </div>
                </td>
            </tr>
        </table>

        @php
            $levelCount = max(1, count($levels ?? []));
            $levelWidth = 30 / $levelCount;
            $colCount = 7 + $levelCount;
        @endphp
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8.5px; table-layout: fixed;">
            <thead>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b" rowspan="2" width="13%">Desa</th>
                    <th class="t l b" rowspan="2" width="19%">Kelompok</th>
                    <th class="t l b" rowspan="2" width="8%">Alokasi</th>
                    <th class="t l b" rowspan="2" width="9%">Saldo Pokok</th>
                    <th class="t l b" colspan="2" width="14%">Tunggakan</th>
                    <th class="t l b" colspan="{{ $levelCount }}" width="30%">Saldo per Kolektibilitas</th>
                    <th class="t l b r" rowspan="2" width="7%">Kolek</th>
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    @foreach ($levels as $level)
                        <th class="t l b" width="{{ $levelWidth }}%">{{ $level['nama'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    <tr class="village-row">
                        <td class="t l b" colspan="{{ $colCount }}">DESA: {{ strtoupper($v['village_name']) }}</td>
                    </tr>
                    @foreach ($v['loans'] as $loan)
                        <tr>
                            <td class="l b">{{ $v['village_name'] }}</td>
                            <td class="l b">
                                {{ $loan['group_name'] }}
                                <span style="color: #666;">(#{{ $loan['loan_id'] }})</span>
                                <br>
                                <span style="color: #666;">{{ $loan['group_code'] ?: $loan['loan_number'] }}</span>
                            </td>
                            <td class="l b num">{{ number_format($loan['alokasi'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['saldo'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['tunggakan_pokok'], 0, ',', '.') }}</td>
                            <td class="l b num">{{ number_format($loan['tunggakan_jasa'], 0, ',', '.') }}</td>
                            @foreach ($levels as $level)
                                <td class="l b num">
                                    {{ ($loan['bucket_key'] ?? '') === $level['bucket_key'] ? number_format($loan['saldo'], 0, ',', '.') : '—' }}
                                </td>
                            @endforeach
                            <td class="l b r">{{ $loan['kolek_nama'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal-row">
                        <td class="l b" colspan="2">Subtotal {{ $v['village_name'] }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['alokasi'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['saldo'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['tunggakan_pokok'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($v['subtotal']['tunggakan_jasa'], 0, ',', '.') }}</td>
                        @foreach ($levels as $level)
                            <td class="l b num">{{ number_format((float) ($v['subtotal'][$level['bucket_key']] ?? 0), 0, ',', '.') }}</td>
                        @endforeach
                        <td class="l b r">{{ $v['subtotal']['peminjam_count'] }} pinjaman</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" align="left" colspan="2">TOTAL {{ $prod['product_code'] }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['alokasi'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_jasa'], 0, ',', '.') }}</th>
                    @foreach ($levels as $level)
                        <th class="t l b num">{{ number_format((float) ($prod['totals'][$level['bucket_key']] ?? 0), 0, ',', '.') }}</th>
                    @endforeach
                    <th class="t l b r">{{ $prod['totals']['peminjam_count'] }} pinjaman</th>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
