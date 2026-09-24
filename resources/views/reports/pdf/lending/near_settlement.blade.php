@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 30px; margin-right: 30px; }
        .num { text-align: right; white-space: nowrap; }
        .ctr { text-align: center; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 6px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px; font-weight: bold;">
                    DAFTAR PINJAMAN MENDEKATI JATUH TEMPO
                </div>
                <div style="font-size: 14px; font-weight: bold;">
                    PER TANGGAL {{ \Carbon\Carbon::parse($as_of)->format('d/m/Y') }} — HORIZON {{ $horizon_days }} HARI
                </div>
                <div style="font-size: 12px; font-weight: normal; color: #4a5568;">
                    Jatuh tempo antara {{ \Carbon\Carbon::parse($as_of)->format('d/m/Y') }} s.d. {{ \Carbon\Carbon::parse($horizon_end)->format('d/m/Y') }} • Sisa tenor ≤ {{ $max_remaining_tenor }} angsuran
                </div>
            </td>
        </tr>
    </table>

    {{-- KPI summary --}}
    <table border="0" width="100%" cellspacing="0" cellpadding="4" style="font-size: 9.5px; margin-bottom: 10px; border: 1px solid #cbd5e0;">
        <tr style="background: rgb(237, 242, 247);">
            <td class="l b t" width="25%"><strong>Jumlah Pinjaman</strong></td>
            <td class="l b t" align="center" width="25%"><strong>{{ $totals['count'] }}</strong></td>
            <td class="l b t" width="25%"><strong>Total Sisa Pokok</strong></td>
            <td class="l b t r" align="right"><strong>Rp {{ number_format($totals['remaining_principal_total'], 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td class="l b"><strong>Total Pokok</strong></td>
            <td class="l b" align="right">Rp {{ number_format($totals['principal_total'], 0, ',', '.') }}</td>
            <td class="l b"><strong>Rata-rata Sisa Tenor</strong></td>
            <td class="l b r" align="center">{{ $totals['remaining_tenor_avg'] }} angsuran</td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8.5px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                <th class="t l b" width="3%">No</th>
                <th class="t l b" width="11%">No. Kontrak</th>
                <th class="t l b" width="6%">Subjek</th>
                <th class="t l b" width="20%">Peminjam / Kelompok</th>
                <th class="t l b" width="9%">Pokok</th>
                <th class="t l b" width="9%">Sisa Pokok</th>
                <th class="t l b" width="5%">Angs. ke-</th>
                <th class="t l b" width="5%">Sisa Tenor</th>
                <th class="t l b" width="11%">Tgl Jatuh Tempo</th>
                <th class="t l b" width="11%">Tgl Est. Lunas</th>
                <th class="t l b r" width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $idx => $row)
                @php
                    $statusColor = $row['is_overdue'] ? '#c53030' : ($row['days_to_next_due'] <= 7 ? '#d69e2e' : '#2f855a');
                @endphp
                <tr>
                    <td class="l b ctr">{{ $idx + 1 }}</td>
                    <td class="l b">{{ $row['loan_number'] }}</td>
                    <td class="l b ctr">{{ $row['borrower_kind'] }}</td>
                    <td class="l b">
                        <strong>{{ $row['borrower_name'] }}</strong><br>
                        <span style="color: #718096; font-size: 7px;">
                            {{ $row['borrower_identifier'] ?? '—' }}@if ($row['village_name']) • {{ $row['village_name'] }}@endif
                        </span>
                    </td>
                    <td class="l b num">{{ number_format($row['principal'], 0, ',', '.') }}</td>
                    <td class="l b num" style="font-weight: bold;">{{ number_format($row['remaining_principal'], 0, ',', '.') }}</td>
                    <td class="l b ctr">{{ $row['installment_number'] }}</td>
                    <td class="l b ctr" style="font-weight: bold;">{{ $row['remaining_tenor'] }}</td>
                    <td class="l b ctr" style="color: {{ $statusColor }}; font-weight: bold;">
                        {{ \Carbon\Carbon::parse($row['next_due_date'])->format('d/m/Y') }}<br>
                        <span style="font-size: 7px; font-weight: normal;">
                            {{ $row['is_overdue'] ? 'Overdue '.$row['days_to_next_due'].' hari' : $row['days_to_next_due'].' hari lagi' }}
                        </span>
                    </td>
                    <td class="l b ctr">
                        {{ $row['estimated_settlement_date'] ? \Carbon\Carbon::parse($row['estimated_settlement_date'])->format('d/m/Y') : '—' }}
                    </td>
                    <td class="l b r ctr" style="color: {{ $statusColor }}; font-weight: bold; font-size: 8px;">
                        {{ strtoupper($row['status']) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="11" align="center" style="padding: 16px; font-style: italic; color: #718096;">
                        Tidak ada pinjaman mendekati jatuh tempo pada horizon yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (count($rows) > 0)
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" colspan="4" align="left">TOTAL</th>
                    <th class="t l b num">{{ number_format($totals['principal_total'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($totals['remaining_principal_total'], 0, ',', '.') }}</th>
                    <th class="t l b r" colspan="5" align="center">{{ $totals['count'] }} pinjaman</th>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection