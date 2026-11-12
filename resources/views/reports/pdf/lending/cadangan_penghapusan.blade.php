@extends('reports.pdf.layout')

@section('content')
    @php
        $levelColumns = $levels ?? [];
        if (empty($levelColumns)) {
            $levelColumns = [
                ['level' => 1, 'nama' => 'Lancar', 'prosentase' => 0.5, 'bucket_key' => 'kolek1_lancar'],
                ['level' => 2, 'nama' => 'Diragukan', 'prosentase' => 50, 'bucket_key' => 'kolek2_diragukan'],
                ['level' => 3, 'nama' => 'Macet', 'prosentase' => 100, 'bucket_key' => 'kolek3_macet'],
            ];
        }
        $levelCount = count($levelColumns);
        $kolekWidth = $levelCount > 0 ? round(36 / $levelCount, 2) : 36;
        $ckpnWidth = $levelCount > 0 ? round(24 / $levelCount, 2) : 24;
        $formatPct = static function ($value): string {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        };
    @endphp

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
                    <th class="t l b" colspan="{{ $levelCount }}" width="36%">Klasifikasi Kolektibilitas</th>
                    <th class="t l b" colspan="{{ $levelCount }}" width="24%">Penyisihan Cadangan (CKPN)</th>
                    <th class="t l b r" rowspan="2" width="8%">Total CKPN</th>
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    @foreach ($levelColumns as $lvl)
                        <th class="t l b" width="{{ $kolekWidth }}%">{{ $lvl['nama'] }}</th>
                    @endforeach
                    @foreach ($levelColumns as $lvl)
                        <th class="t l b" width="{{ $ckpnWidth }}%">{{ $formatPct($lvl['prosentase']) }}% {{ $lvl['nama'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    <tr>
                        <td class="l b">{{ $v['village_name'] }}</td>
                        <td class="l b num">{{ number_format($v['saldo'] ?? 0, 0, ',', '.') }}</td>
                        @foreach ($levelColumns as $lvl)
                            <td class="l b num">{{ number_format((float) ($v[$lvl['bucket_key']] ?? 0), 0, ',', '.') }}</td>
                        @endforeach
                        @foreach ($levelColumns as $lvl)
                            <td class="l b num">{{ number_format((float) ($v["ckpn_{$lvl['level']}"] ?? 0), 0, ',', '.') }}</td>
                        @endforeach
                        <td class="l b r num" style="font-weight: bold;">{{ number_format((float) ($v['total_ckpn'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" align="left">TOTAL {{ $prod['product_code'] }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo'] ?? 0, 0, ',', '.') }}</th>
                    @foreach ($levelColumns as $lvl)
                        <th class="t l b num">{{ number_format((float) ($prod['totals'][$lvl['bucket_key']] ?? 0), 0, ',', '.') }}</th>
                    @endforeach
                    @foreach ($levelColumns as $lvl)
                        <th class="t l b num">{{ number_format((float) ($prod['totals']["ckpn_{$lvl['level']}"] ?? 0), 0, ',', '.') }}</th>
                    @endforeach
                    <th class="t l b r num">{{ number_format((float) ($prod['totals']['total_ckpn'] ?? 0), 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
