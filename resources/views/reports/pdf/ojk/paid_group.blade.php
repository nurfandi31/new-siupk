@php
    /** @var array<string, mixed> $identity */
    /** @var list<array<string, mixed>> $rows */
    /** @var array<string, float|int> $totals */
@endphp
@extends('reports.pdf.layout', [
    'title' => 'DRPL — Rincian Pinjaman Lunas Kelompok',
    'identity' => array_merge($identity ?? [], [
        'district_name' => $identity['district_name'] ?? '',
        'regency_name' => $identity['regency_name'] ?? '',
        'address' => $identity['address'] ?? '',
        'phone' => $identity['phone'] ?? '',
        'registration_number' => $identity['registration_number'] ?? '',
    ]),
    'period' => [
        'period_label' => $period_label,
        'as_of' => null,
    ],
])

@section('content')
    <style>
        html { margin-left: 30px; margin-right: 30px; }
        .num { text-align: right; white-space: nowrap; }
        .ctr { text-align: center; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 8px;">
        <tr>
            <td align="center">
                <div style="font-size: 16px; font-weight: bold;">
                    RINCIAN PINJAMAN LUNAS KELOMPOK (DRPL)
                </div>
                <div style="font-size: 12px; font-weight: bold;">
                    PERIODE PELUNASAN: {{ strtoupper($period_label) }}
                </div>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(220, 220, 220); font-weight: bold; text-align: center;">
                <th class="t l b" width="3%">No</th>
                <th class="t l b" width="8%">No. Kontrak</th>
                <th class="t l b" width="16%">Kelompok</th>
                <th class="t l b" width="9%">Desa</th>
                <th class="t l b" width="8%">Pokok</th>
                <th class="t l b" width="9%">Total Dibayar</th>
                <th class="t l b" width="6%">Tgl Cair</th>
                <th class="t l b" width="6%">Tgl Lunas</th>
                <th class="t l b" width="5%">Lama (bln)</th>
                <th class="t l b" width="5%">Suku (%)</th>
                <th class="t l b" width="8%">Bagi Hasil</th>
                <th class="t l b" width="6%">Tunggakan</th>
                <th class="t l b" width="4%">Kol.</th>
                <th class="t l b r" width="7%">Produk</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $idx => $row)
                <tr>
                    <td class="l b ctr">{{ $idx + 1 }}</td>
                    <td class="l b">
                        #{{ $row['loan_id'] }}
                        @if (!empty($row['loan_number']))
                            <br><span style="color: #555;">{{ $row['loan_number'] }}</span>
                        @endif
                    </td>
                    <td class="l b">
                        {{ $row['group_name'] }}
                        @if (!empty($row['group_code']))
                            <br><span style="color: #555; font-size: 7px;">{{ $row['group_code'] }}</span>
                        @endif
                    </td>
                    <td class="l b">{{ $row['village_name'] ?? '—' }}</td>
                    <td class="l b num">{{ number_format($row['principal_amount'], 0, ',', '.') }}</td>
                    <td class="l b num">{{ number_format($row['total_paid'], 0, ',', '.') }}</td>
                    <td class="l b ctr">
                        {{ !empty($row['disbursed_at']) ? date('d/m/Y', strtotime($row['disbursed_at'])) : '—' }}
                    </td>
                    <td class="l b ctr">
                        {{ !empty($row['completed_at']) ? date('d/m/Y', strtotime($row['completed_at'])) : '—' }}
                    </td>
                    <td class="l b ctr">{{ $row['loan_months'] ?? '—' }}</td>
                    <td class="l b ctr">{{ number_format($row['interest_rate'], 2) }}</td>
                    <td class="l b num">{{ number_format($row['interest_paid'], 0, ',', '.') }}</td>
                    <td class="l b num">{{ number_format((float) ($row['arrears_amount'] ?? 0), 0, ',', '.') }}</td>
                    <td class="l b ctr">{{ $row['collectibility_label'] ?? 'Lancar' }}</td>
                    <td class="l b r">
                        <span style="font-weight: bold;">{{ $row['product_code'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="14" align="center" style="padding: 14px;">
                        Tidak ada pinjaman kelompok lunas pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="4" align="left">TOTAL</th>
                <th class="t l b num">{{ number_format($totals['principal_total'] ?? 0, 0, ',', '.') }}</th>
                <th class="t l b num">{{ number_format($totals['paid_amount_total'] ?? 0, 0, ',', '.') }}</th>
                <th class="t l b" colspan="4"></th>
                <th class="t l b num">{{ number_format($totals['interest_total'] ?? 0, 0, ',', '.') }}</th>
                <th class="t l b num">0</th>
                <th class="t l b ctr">L</th>
                <th class="t l b r"></th>
            </tr>
            <tr style="font-weight: normal;">
                <td class="l b" colspan="14" style="font-size: 7px; padding-top: 4px;">
                    Jumlah pinjaman kelompok lunas: {{ $totals['count'] ?? 0 }} |
                    Total pokok: Rp {{ number_format($totals['principal_total'] ?? 0, 0, ',', '.') }} |
                    Total pembayaran: Rp {{ number_format($totals['paid_amount_total'] ?? 0, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>
@endsection
