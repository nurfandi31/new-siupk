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
                    DAFTAR PINJAMAN LUNAS
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
                <th class="t l b" width="10%">Loan ID / Nomor</th>
                <th class="t l b" width="6%">Jenis</th>
                <th class="t l b" width="18%">Peminjam / Kelompok</th>
                <th class="t l b" width="6%">NIK</th>
                <th class="t l b" width="10%">Desa</th>
                <th class="t l b" width="7%">Produk</th>
                <th class="t l b" width="10%">Tgl Cair</th>
                <th class="t l b" width="10%">Tgl Lunas</th>
                <th class="t l b" width="6%">Lama (hari)</th>
                <th class="t l b" width="6%">Angsur</th>
                <th class="t l b r" width="10%">Pokok</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $idx => $row)
                <tr>
                    <td class="l b" align="center">{{ $idx + 1 }}</td>
                    <td class="l b">
                        #{{ $row['loan_id'] }}
                        @if (!empty($row['loan_number']))
                            <br><span style="color: #718096;">{{ $row['loan_number'] }}</span>
                        @endif
                    </td>
                    <td class="l b" align="center">{{ $row['borrower_kind'] }}</td>
                    <td class="l b">
                        {{ $row['borrower_name'] }}
                        @if (!empty($row['borrower_code']))
                            <span style="color: #718096;">({{ $row['borrower_code'] }})</span>
                        @endif
                        @if (!empty($row['group_name']) && $row['borrower_kind'] === 'Individu')
                                <br><span style="color: #718096; font-size: 7px;">Kel: {{ $row['group_name'] }}</span>
                        @endif
                    </td>
                    <td class="l b" align="center">{{ $row['nik'] ?? '—' }}</td>
                    <td class="l b">{{ $row['village_name'] ?? '—' }}</td>
                    <td class="l b" align="center">{{ $row['product_code'] ?: '—' }}</td>
                    <td class="l b" align="center">
                        {{ $row['disbursed_at'] ? date('d/m/Y', strtotime($row['disbursed_at'])) : '—' }}
                    </td>
                    <td class="l b" align="center">
                        {{ $row['completed_at'] ? date('d/m/Y', strtotime($row['completed_at'])) : '—' }}
                    </td>
                    <td class="l b" align="center">{{ $row['loan_days'] !== null ? number_format($row['loan_days']) : '—' }}</td>
                    <td class="l b" align="center">{{ $row['installment_count'] }}</td>
                    <td class="l b r num">{{ number_format($row['principal_amount'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="12" align="center" style="padding: 12px;">
                        Tidak ada pinjaman lunas pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="10" align="left">TOTAL</th>
                <th class="t l b" align="center">{{ $totals['installments_total'] }}</th>
                <th class="t l b r num">{{ number_format($totals['principal_total'], 0, ',', '.') }}</th>
            </tr>
            <tr style="font-weight: normal;">
                <td class="l b" colspan="12" style="font-size: 7px; padding-top: 4px;">
                    Jumlah pinjaman: {{ $totals['count'] }} |
                    Kelompok: {{ $totals['group_count'] }} |
                    Individu: {{ $totals['member_count'] }} |
                    Total pembayaran: Rp {{ number_format($totals['paid_amount'], 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>
@endsection