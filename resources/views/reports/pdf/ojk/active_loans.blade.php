@php
    /** @var array<string, mixed> $identity */
    /** @var list<array<string, mixed>> $rows */
    /** @var array<string, float|int> $totals */
@endphp
@extends('reports.pdf.layout', [
    'title' => 'DRP — Daftar Rincian Pinjaman Aktif',
    'identity' => array_merge($identity ?? [], [
        'district_name' => $identity['district_name'] ?? '',
        'regency_name' => $identity['regency_name'] ?? '',
        'address' => $identity['address'] ?? '',
        'phone' => $identity['phone'] ?? '',
        'registration_number' => $identity['registration_number'] ?? '',
    ]),
    'period' => [
        'period_label' => $period_label,
        'as_of' => $as_of ?? null,
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
                    DAFTAR RINCIAN PINJAMAN AKTIF (DRP)
                </div>
                <div style="font-size: 12px; font-weight: bold;">
                    PERIODE PENCAIRAN: {{ strtoupper($period_label) }}
                </div>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(220, 220, 220); font-weight: bold; text-align: center;">
                <th class="t l b" width="3%">No</th>
                <th class="t l b" width="9%">No. Kontrak</th>
                <th class="t l b" width="7%">Tgl Cair</th>
                <th class="t l b" width="14%">Peminjam</th>
                <th class="t l b" width="5%">Jenis</th>
                <th class="t l b" width="8%">Desa</th>
                <th class="t l b" width="8%">Pokok</th>
                <th class="t l b" width="8%">Sisa Pokok</th>
                <th class="t l b" width="6%">Angs ke-</th>
                <th class="t l b" width="7%">Jatuh Tempo</th>
                <th class="t l b" width="5%">Kol.</th>
                <th class="t l b" width="4%">Hari</th>
                <th class="t l b r" width="16%">Produk</th>
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
                    <td class="l b ctr">
                        {{ !empty($row['disbursed_at']) ? date('d/m/Y', strtotime($row['disbursed_at'])) : '—' }}
                    </td>
                    <td class="l b">
                        {{ $row['borrower_name'] }}
                        @if (!empty($row['borrower_code']))
                            <span style="color: #555;">({{ $row['borrower_code'] }})</span>
                        @endif
                        @if (!empty($row['nik']))
                            <br><span style="color: #888; font-size: 7px;">NIK: {{ $row['nik'] }}</span>
                        @endif
                    </td>
                    <td class="l b ctr">{{ $row['borrower_kind'] }}</td>
                    <td class="l b">{{ $row['village_name'] ?? '—' }}</td>
                    <td class="l b num">{{ number_format($row['principal_amount'], 0, ',', '.') }}</td>
                    <td class="l b num">{{ number_format($row['principal_remaining'], 0, ',', '.') }}</td>
                    <td class="l b ctr">{{ $row['installment_paid'] }}/{{ $row['tenor_months'] }}</td>
                    <td class="l b ctr">
                        {{ !empty($row['next_due_date']) ? date('d/m/Y', strtotime($row['next_due_date'])) : '—' }}
                    </td>
                    <td class="l b ctr" style="font-weight: bold;">
                        {{ $row['collectibility_code'] }}
                    </td>
                    <td class="l b ctr">
                        {{ $row['days_overdue'] > 0 ? $row['days_overdue'] : '—' }}
                    </td>
                    <td class="l b r">
                        <span style="font-weight: bold;">{{ $row['product_code'] }}</span>
                        <br><span style="color: #555; font-size: 7px;">{{ $row['product_name'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="13" align="center" style="padding: 14px;">
                        Tidak ada pinjaman aktif pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232,232,232); font-weight: bold;">
                <th class="t l b" colspan="6" align="left">TOTAL</th>
                <th class="t l b num">{{ number_format($totals['principal_total'] ?? 0, 0, ',', '.') }}</th>
                <th class="t l b num">{{ number_format($totals['principal_remaining_total'] ?? 0, 0, ',', '.') }}</th>
                <th class="t l b" colspan="5"></th>
                <th class="t l b r"></th>
            </tr>
            <tr style="font-weight: normal;">
                <td class="l b" colspan="13" style="font-size: 7px; padding-top: 4px;">
                    Jumlah pinjaman aktif: {{ $totals['count'] ?? 0 }} |
                    Kelompok: {{ $totals['group_count'] ?? 0 }} |
                    Individu: {{ $totals['member_count'] ?? 0 }} |
                    Sisa jasa: Rp {{ number_format($totals['interest_remaining_total'] ?? 0, 0, ',', '.') }} |
                    Tunggakan: {{ $totals['overdue_count'] ?? 0 }} (Rp {{ number_format($totals['overdue_amount_total'] ?? 0, 0, ',', '.') }})
                </td>
            </tr>
        </tfoot>
    </table>
@endsection
