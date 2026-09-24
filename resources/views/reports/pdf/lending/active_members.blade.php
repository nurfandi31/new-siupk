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
                    DAFTAR PEMANFAAT AKTIF
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
                <th class="t l b" width="3%">No</th>
                <th class="t l b" width="11%">NIK</th>
                <th class="t l b" width="6%">No. Anggota</th>
                <th class="t l b" width="17%">Nama Anggota</th>
                <th class="t l b" width="11%">Desa</th>
                <th class="t l b" width="14%">Kelompok</th>
                <th class="t l b" width="7%">Status</th>
                <th class="t l b" width="6%">Pinj. Aktif</th>
                <th class="t l b" width="7%">Simpanan</th>
                <th class="t l b" width="10%">Outstanding</th>
                <th class="t l b" width="9%">Tunggakan Pokok</th>
                <th class="t l b r" width="9%">Tunggakan Jasa</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $idx => $row)
                <tr>
                    <td class="l b" align="center">{{ $idx + 1 }}</td>
                    <td class="l b">{{ $row['nik'] ?? '—' }}</td>
                    <td class="l b">{{ $row['member_number'] ?? '—' }}</td>
                    <td class="l b">{{ $row['member_name'] }}</td>
                    <td class="l b">{{ $row['village_name'] ?? '—' }}</td>
                    <td class="l b">{{ $row['group_name'] ?? '—' }}</td>
                    <td class="l b" align="center">{{ ucfirst($row['status']) }}</td>
                    <td class="l b" align="center">{{ $row['loan_count'] }}</td>
                    <td class="l b num">—</td>
                    <td class="l b num">{{ number_format($row['outstanding_total'], 0, ',', '.') }}</td>
                    <td class="l b num" style="{{ $row['tunggakan_pokok'] > 0 ? 'color: #c53030;' : '' }}">
                        {{ number_format($row['tunggakan_pokok'], 0, ',', '.') }}
                    </td>
                    <td class="l b r num" style="{{ $row['tunggakan_jasa'] > 0 ? 'color: #c53030;' : '' }}">
                        {{ number_format($row['tunggakan_jasa'], 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="12" align="center" style="padding: 12px;">
                        Tidak ada pemanfaat aktif pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="7" align="left">TOTAL</th>
                <th class="t l b" align="center">{{ $totals['loan_total'] }}</th>
                <th class="t l b">—</th>
                <th class="t l b num">{{ number_format($totals['outstanding_total'], 0, ',', '.') }}</th>
                <th class="t l b num">{{ number_format($totals['tunggakan_pokok_total'], 0, ',', '.') }}</th>
                <th class="t l b r num">{{ number_format($totals['tunggakan_jasa_total'], 0, ',', '.') }}</th>
            </tr>
            <tr style="font-weight: normal;">
                <td class="l b" colspan="12" style="font-size: 7px; padding-top: 4px;">
                    Jumlah pemanfaat aktif: {{ $totals['count'] }}
                    &nbsp;(Modul simpanan belum tersedia — kolom Simpanan kosong)
                </td>
            </tr>
        </tfoot>
    </table>
@endsection