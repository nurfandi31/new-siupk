@extends('reports.pdf.layout')

@section('content')
    <style>
        .num { text-align: right; white-space: nowrap; }
    </style>

    @foreach ($categories as $idx => $cat)
        @if ($idx > 0)
            <div class="break"></div>
        @endif

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
            <tr>
                <td align="center">
                    <div style="font-size: 14px; font-weight: bold;">
                        DAFTAR REKAPITULASI INVENTARIS / ASET TETAP — {{ strtoupper($cat['category_name']) }}
                    </div>
                    <div style="font-size: 12px; font-weight: bold; color: #444;">
                        PERIODE: {{ strtoupper($period_label) }}
                    </div>
                </td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8px; table-layout: fixed;">
            <thead>
                <tr style="background: rgb(232,232,232); font-weight: bold; text-align: center;">
                    <th class="t l b" rowspan="2" width="3%">No</th>
                    <th class="t l b" rowspan="2" width="7%">Tgl Beli</th>
                    <th class="t l b" rowspan="2" width="18%">Nama Barang</th>
                    <th class="t l b" rowspan="2" width="7%">Kode/ID</th>
                    <th class="t l b" rowspan="2" width="6%">Kondisi</th>
                    <th class="t l b" rowspan="2" width="4%">Unit</th>
                    <th class="t l b" rowspan="2" width="8%">Harga Satuan</th>
                    <th class="t l b" rowspan="2" width="9%">Harga Perolehan</th>
                    <th class="t l b" rowspan="2" width="5%">Umur (Bln)</th>
                    <th class="t l b" rowspan="2" width="8%">Susut/Bln</th>
                    <th class="t l b" colspan="2" width="12%">Tahun Ini</th>
                    <th class="t l b" colspan="2" width="12%">s.d. Tahun Ini</th>
                    <th class="t l b r" rowspan="2" width="9%">Nilai Buku</th>
                </tr>
                <tr style="background: rgb(242,242,242); font-weight: bold; text-align: center;">
                    <th class="t l b">Bln</th>
                    <th class="t l b">Biaya</th>
                    <th class="t l b">Bln</th>
                    <th class="t l b">Akumulasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cat['assets'] as $a)
                    <tr>
                        <td class="l b" align="center">{{ $a['no'] }}</td>
                        <td class="l b" align="center">{{ $a['purchased_at'] }}</td>
                        <td class="l b" style="font-weight: bold;">{{ $a['name'] }}</td>
                        <td class="l b" align="center">{{ $a['asset_code'] }}</td>
                        <td class="l b" align="center">{{ ucfirst($a['condition']) }}</td>
                        <td class="l b" align="center">{{ $a['unit'] }}</td>
                        <td class="l b num">{{ number_format($a['unit_cost'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($a['acquisition'], 0, ',', '.') }}</td>
                        <td class="l b" align="center">{{ $a['useful_life_months'] ?: '—' }}</td>
                        <td class="l b num">{{ number_format($a['monthly_depreciation'], 0, ',', '.') }}</td>
                        <td class="l b" align="center">{{ $a['months_this_year'] }}</td>
                        <td class="l b num">{{ number_format($a['depreciation_year'], 0, ',', '.') }}</td>
                        <td class="l b" align="center">{{ $a['useful_life_months'] > 0 ? min($a['useful_life_months'], $a['months_this_year']) : '—' }}</td>
                        <td class="l b num">{{ number_format($a['accumulated_depreciation'], 0, ',', '.') }}</td>
                        <td class="l b r num" style="font-weight: bold; color: #2b6cb0;">{{ number_format($a['book_value'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" colspan="5" align="left">TOTAL {{ $cat['category_name'] }}</th>
                    <th class="t l b" align="center">{{ $cat['totals']['unit'] }}</th>
                    <th class="t l b"></th>
                    <th class="t l b num">{{ number_format($cat['totals']['acquisition'], 0, ',', '.') }}</th>
                    <th class="t l b" colspan="3"></th>
                    <th class="t l b num">{{ number_format($cat['totals']['depreciation_year'], 0, ',', '.') }}</th>
                    <th class="t l b"></th>
                    <th class="t l b num">{{ number_format($cat['totals']['depreciation_accumulated'], 0, ',', '.') }}</th>
                    <th class="t l b r num">{{ number_format($cat['totals']['book_value'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection