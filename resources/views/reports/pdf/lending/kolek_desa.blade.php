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
        $levelWidth = $levelCount > 0 ? round(28 / $levelCount, 2) : 28;
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
                    @foreach ($levelColumns as $lvl)
                        <th class="t l b" width="{{ $levelWidth }}%">{{ $lvl['nama'] }}</th>
                    @endforeach
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b">Pokok</th>
                    <th class="t l b">Jasa</th>
                    @foreach ($levelColumns as $lvl)
                        <th class="t l b">Tingkat {{ $lvl['level'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    @php
                        $row = $v['subtotal'] ?? $v;
                        $pct = ($row['alokasi'] ?? 0) > 0 ? round((($row['saldo'] ?? 0) / $row['alokasi']) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td class="l b">{{ $v['village_name'] }}</td>
                        <td class="l b num">{{ number_format($row['alokasi'] ?? 0, 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($row['saldo'] ?? 0, 0, ',', '.') }}</td>
                        <td class="l b" align="center">{{ $pct }}%</td>
                        <td class="l b num">{{ number_format($row['tunggakan_pokok'] ?? 0, 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($row['tunggakan_jasa'] ?? 0, 0, ',', '.') }}</td>
                        @foreach ($levelColumns as $lvl)
                            <td class="l b num">{{ number_format((float) ($row[$lvl['bucket_key']] ?? 0), 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $prodPct = ($prod['totals']['alokasi'] ?? 0) > 0 ? round((($prod['totals']['saldo'] ?? 0) / $prod['totals']['alokasi']) * 100, 1) : 0;
                @endphp
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" align="left">TOTAL {{ $prod['product_code'] }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['alokasi'] ?? 0, 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['saldo'] ?? 0, 0, ',', '.') }}</th>
                    <th class="t l b" align="center">{{ $prodPct }}%</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_pokok'] ?? 0, 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($prod['totals']['tunggakan_jasa'] ?? 0, 0, ',', '.') }}</th>
                    @foreach ($levelColumns as $lvl)
                        <th class="t l b num">{{ number_format((float) ($prod['totals'][$lvl['bucket_key']] ?? 0), 0, ',', '.') }}</th>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
