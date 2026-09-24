@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px; font-weight: bold;">
                    DAFTAR KELOMPOK AKTIF
                </div>
                <div style="font-size: 16px; font-weight: bold;">
                    PERIODE: {{ strtoupper($period_label) }}
                </div>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                <th class="t l b" width="3%">No</th>
                <th class="t l b" width="10%">Kode Kelompok</th>
                <th class="t l b" width="20%">Nama Kelompok</th>
                <th class="t l b" width="14%">Desa</th>
                <th class="t l b" width="16%">Ketua</th>
                <th class="t l b" width="9%">Anggota Aktif</th>
                <th class="t l b" width="9%">Pinjaman Aktif</th>
                <th class="t l b" width="11%">Total Simpanan</th>
                <th class="t l b r" width="11%">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $idx => $row)
                <tr>
                    <td class="l b" align="center">{{ $idx + 1 }}</td>
                    <td class="l b">{{ $row['group_code'] }}</td>
                    <td class="l b">{{ $row['group_name'] }}</td>
                    <td class="l b">{{ $row['village_name'] ?? '—' }}</td>
                    <td class="l b">{{ $row['ketua_name'] ?? '—' }}</td>
                    <td class="l b" align="center">{{ $row['member_count'] }}</td>
                    <td class="l b" align="center">{{ $row['active_loan_count'] }}</td>
                    <td class="l b num">—</td>
                    <td class="l b r num">{{ number_format($row['outstanding_total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="9" align="center" style="padding: 12px;">
                        Tidak ada kelompok aktif pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="5" align="left">TOTAL</th>
                <th class="t l b" align="center">{{ $totals['member_total'] }}</th>
                <th class="t l b" align="center">{{ $totals['active_loan_total'] }}</th>
                <th class="t l b">—</th>
                <th class="t l b r num">{{ number_format($totals['outstanding_total'], 0, ',', '.') }}</th>
            </tr>
            <tr style="font-weight: normal;">
                <td class="l b" colspan="9" style="font-size: 7px; padding-top: 4px;">
                    Jumlah kelompok aktif: {{ $totals['count'] }}
                    &nbsp;(Modul simpanan belum tersedia — kolom Total Simpanan kosong)
                </td>
            </tr>
        </tfoot>
    </table>
@endsection