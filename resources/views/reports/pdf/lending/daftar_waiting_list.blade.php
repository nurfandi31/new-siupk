@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 30px; margin-right: 30px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px; font-weight: bold;">{{ strtoupper($stage_title ?? 'DAFTAR WAITING LIST PINJAMAN') }}</div>
                <div style="font-size: 16px; font-weight: bold;">
                    PERIODE: {{ strtoupper($period_label) }}
                </div>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                <th class="t l b" width="4%">No</th>
                <th class="t l b" width="12%">Nomor Pinjaman</th>
                <th class="t l b" width="19%">Peminjam / Kelompok</th>
                <th class="t l b" width="12%">Desa</th>
                <th class="t l b" width="10%">Produk</th>
                <th class="t l b" width="6%">Jenis</th>
                <th class="t l b" width="13%">Pokok Pinjaman</th>
                <th class="t l b" width="10%">{{ $stage_date_label ?? 'Tgl Masuk Antrian' }}</th>
                <th class="t l b" width="9%">Kolektibilitas</th>
                <th class="t l b r" width="5%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="l b" align="center">{{ $row['no'] }}</td>
                    <td class="l b">{{ $row['loan_number'] ?: '—' }} <span style="color:#718096;">(#{{ $row['loan_id'] }})</span></td>
                    <td class="l b">
                        {{ $row['borrower_name'] }}
                        @if (! empty($row['borrower_code']))
                            <span style="color:#718096;">({{ $row['borrower_code'] }})</span>
                        @endif
                    </td>
                    <td class="l b">{{ $row['village_name'] }}</td>
                    <td class="l b">{{ $row['product_code'] }}</td>
                    <td class="l b" align="center">{{ $row['borrower_kind'] }}</td>
                    <td class="l b num">{{ number_format((float) $row['principal_amount'], 0, ',', '.') }}</td>
                    <td class="l b" align="center">{{ $row['stage_date_label'] }}</td>
                    <td class="l b" align="center">{{ $row['collector'] }}</td>
                    <td class="l b r" align="center">{{ $row['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="10" align="center" style="padding: 24px;">
                        Tidak ada pinjaman dalam antrian waiting list untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="6" align="left">TOTAL WAITING LIST ({{ $totals['count'] ?? 0 }})</th>
                <th class="t l b num">{{ number_format((float) ($totals['principal_amount'] ?? 0), 0, ',', '.') }}</th>
                <th class="t l b" colspan="3"></th>
            </tr>
        </tfoot>
    </table>

    <p style="font-size: 8px; color: #4a5568; margin-top: 8px;">
        Keterangan: {{ $stage_date_label ?? 'Tgl Masuk Antrian' }} dihitung berdasarkan kolom
        <code>approved_at</code> pada tabel loans. Urutan antrian mengikuti tanggal persetujuan;
        kolektibilitas memakai status collection berdasarkan angsuran yang jatuh tempo.
    </p>
@endsection
