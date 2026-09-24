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
                    DAFTAR PINJAMAN DIHAPUSBUKUKAN — KELOMPOK
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
                <th class="t l b" width="13%">Nomor Pinjaman</th>
                <th class="t l b" width="17%">Kelompok</th>
                <th class="t l b" width="10%">Desa</th>
                <th class="t l b" width="10%">Pokok</th>
                <th class="t l b" width="10%">Sisa Pokok</th>
                <th class="t l b" width="10%">Cadangan CKPN</th>
                <th class="t l b" width="10%">Nilai Bersih</th>
                <th class="t l b" width="8%">Tgl Hapus</th>
                <th class="t l b r" width="9%">Alasan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="l b" align="center">{{ $row['no'] }}</td>
                    <td class="l b">
                        {{ $row['loan_number'] ?: '—' }}
                        <span style="color:#718096;">(#{{ $row['loan_id'] }})</span>
                    </td>
                    <td class="l b">
                        {{ $row['group_name'] ?: '—' }}
                        @if (! empty($row['group_code']))
                            <span style="color:#718096;">({{ $row['group_code'] }})</span>
                        @endif
                    </td>
                    <td class="l b">{{ $row['village_name'] ?? '—' }}</td>
                    <td class="l b num">{{ number_format((float) $row['principal_amount'], 0, ',', '.') }}</td>
                    <td class="l b num">{{ number_format((float) $row['sisa_pokok'], 0, ',', '.') }}</td>
                    <td class="l b num" style="color: #c53030;">
                        {{ number_format((float) $row['ckpn'], 0, ',', '.') }}
                    </td>
                    <td class="l b num">{{ number_format((float) $row['nilai_bersih'], 0, ',', '.') }}</td>
                    <td class="l b" align="center">{{ $row['written_off_at_label'] }}</td>
                    <td class="l b r">{{ $row['reason'] ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="10" align="center" style="padding: 18px;">
                        Tidak ada pinjaman kelompok yang dihapusbukukan pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="4" align="left">TOTAL ({{ $totals['count'] ?? 0 }} pinjaman)</th>
                <th class="t l b num">{{ number_format((float) ($totals['principal_total'] ?? 0), 0, ',', '.') }}</th>
                <th class="t l b num">{{ number_format((float) ($totals['sisa_pokok_total'] ?? 0), 0, ',', '.') }}</th>
                <th class="t l b num">{{ number_format((float) ($totals['ckpn_total'] ?? 0), 0, ',', '.') }}</th>
                <th class="t l b num">{{ number_format((float) ($totals['nilai_bersih_total'] ?? 0), 0, ',', '.') }}</th>
                <th class="t l b" colspan="2"></th>
            </tr>
        </tfoot>
    </table>

    <p style="font-size: 7.5px; color: #4a5568; margin-top: 10px;">
        Catatan: Cadangan CKPN dihitung sebagai 100% dari sisa pokok saat pinjaman dihapusbukukan
        (konsisten dengan kolektibilitas macet pada modul Cadangan Penghapusan). Nilai bersih adalah
        sisa pokok dikurangi CKPN. Tanggal hapus buku mengacu pada kolom
        <code>loan_write_offs.written_off_at</code>.
    </p>
@endsection
