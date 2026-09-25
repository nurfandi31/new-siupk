@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 30px; margin-right: 30px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px; font-weight: bold;">
                    DAFTAR PINJAMAN DIHAPUSBUKUKAN — ANGGOTA KELOMPOK
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
                <th class="t l b" width="3%">No</th>
                <th class="t l b" width="10%">Tgl Hapus</th>
                <th class="t l b" width="11%">NIK</th>
                <th class="t l b" width="6%">No. Anggota</th>
                <th class="t l b" width="17%">Nama Anggota</th>
                <th class="t l b" width="13%">Kelompok</th>
                <th class="t l b" width="10%">Desa</th>
                <th class="t l b" width="9%">No. Pinjaman</th>
                <th class="t l b" width="9%">Alokasi</th>
                <th class="t l b r" width="12%">Sisa Pokok</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $idx => $row)
                <tr>
                    <td class="l b" align="center">{{ $idx + 1 }}</td>
                    <td class="l b" align="center">{{ ! empty($row['written_off_at']) ? date('d/m/Y', strtotime($row['written_off_at'])) : '—' }}</td>
                    <td class="l b">{{ $row['nik'] ?? '—' }}</td>
                    <td class="l b">{{ $row['member_number'] ?? '—' }}</td>
                    <td class="l b">{{ $row['member_name'] }}</td>
                    <td class="l b">
                        {{ $row['group_name'] ?? '—' }}
                        @if (! empty($row['group_code']))
                            <span style="color:#718096;">({{ $row['group_code'] }})</span>
                        @endif
                    </td>
                    <td class="l b">{{ $row['village_name'] ?? '—' }}</td>
                    <td class="l b">{{ $row['loan_number'] ?? '—' }}</td>
                    <td class="l b num">{{ number_format($row['allocated_amount'], 0, ',', '.') }}</td>
                    <td class="l b r num">{{ number_format($row['principal_balance'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="10" align="center" style="padding: 12px;">
                        Tidak ada pinjaman anggota kelompok yang dihapusbukukan pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="7" align="left">TOTAL</th>
                <th class="t l b" align="center">{{ $totals['count'] }} anggota</th>
                <th class="t l b">—</th>
                <th class="t l b r num">{{ number_format($totals['saldo_total'], 0, ',', '.') }}</th>
            </tr>
        </tfoot>
    </table>
@endsection
