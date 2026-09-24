@extends('reports.pdf.layout', ['title' => 'Rencana vs Realisasi — Pinjaman Individu', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="12" align="center">
            <div style="font-size: 18px;"><b>RENCANA VS REALISASI ANGSURAN — PINJAMAN INDIVIDU</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="12" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-bottom: 14px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td height="20" align="center">Pinjaman</td>
        <td align="center">Angsuran</td>
        <td align="right">Rencana Pokok</td>
        <td align="right">Realisasi Pokok</td>
        <td align="right">Gap Pokok</td>
        <td align="right">Rencana Jasa</td>
        <td align="right">Realisasi Jasa</td>
        <td align="right">Gap Jasa</td>
        <td align="center">% Realisasi</td>
    </tr>
    <tr style="background: rgb(245, 245, 245);">
        <td height="20" align="center">{{ $totals['loan_count'] ?? 0 }}</td>
        <td align="center">{{ $totals['installment_count'] ?? 0 }}</td>
        <td align="right">{{ number_format($totals['plan_principal'] ?? 0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format($totals['actual_principal'] ?? 0, 0, ',', '.') }}</td>
        <td align="right" style="color: #c53030;">{{ number_format($totals['gap_principal'] ?? 0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format($totals['plan_interest'] ?? 0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format($totals['actual_interest'] ?? 0, 0, ',', '.') }}</td>
        <td align="right" style="color: #c53030;">{{ number_format($totals['gap_interest'] ?? 0, 0, ',', '.') }}</td>
        <td align="center" style="font-weight: bold;">{{ number_format($totals['pct_realization'] ?? 0, 2, ',', '.') }}%</td>
    </tr>
</table>

@foreach ($loans as $loan)
    @if (!$loop->first)
        <div class="break"></div>
    @endif
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-bottom: 8px;">
        <tr>
            <td>
                <div style="font-size: 13px; font-weight: bold;">
                    {{ $loan['member_name'] ?? '—' }}
                    <span style="color: #666; font-size: 10px;">
                        ({{ $loan['loan_number'] ?? '#'.$loan['loan_id'] }} · #{{ $loan['loan_id'] }} · {{ $loan['village_name'] ?? '—' }} · {{ $loan['product_code'] ?? '—' }})
                    </span>
                </div>
                <div style="font-size: 10px; color: #555;">
                    Alokasi {{ number_format($loan['principal_amount'] ?? 0, 0, ',', '.') }} · Cair {{ $loan['disbursed_at'] ? \Carbon\CarbonImmutable::parse($loan['disbursed_at'])->format('d/m/Y') : '—' }} ·
                    Realisasi {{ number_format($loan['totals']['pct_realization'] ?? 0, 2, ',', '.') }}% ·
                    @if (($loan['totals']['late_count'] ?? 0) > 0)
                        <span style="color: #c53030;">{{ $loan['totals']['late_count'] }} terlambat (rerata {{ number_format($loan['totals']['avg_delay_days'] ?? 0, 1, ',', '.') }} hari)</span>
                    @else
                        <span style="color: #2f855a;">Tidak ada keterlambatan</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8.5px;">
        <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
            <td height="20" align="center" width="4%">No</td>
            <td align="center" width="10%">Tgl Jatuh Tempo</td>
            <td align="center" width="10%">Tgl Bayar</td>
            <td align="center" width="6%">Angsuran</td>
            <td align="right" width="10%">Renc. Pokok</td>
            <td align="right" width="10%">Real. Pokok</td>
            <td align="right" width="10%">Selisih Pokok</td>
            <td align="right" width="10%">Renc. Jasa</td>
            <td align="right" width="10%">Real. Jasa</td>
            <td align="right" width="10%">Selisih Jasa</td>
            <td align="center" width="5%">Telat</td>
            <td align="center" width="13%">Status</td>
        </tr>
        @foreach ($loan['rows'] as $row)
            <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td align="center">{{ $loop->iteration }}</td>
                <td align="center">{{ \Carbon\CarbonImmutable::parse($row['due_date'])->format('d/m/Y') }}</td>
                <td align="center">{{ $row['paid_at'] ? \Carbon\CarbonImmutable::parse($row['paid_at'])->format('d/m/Y') : '—' }}</td>
                <td align="center">{{ $row['installment_number'] }}</td>
                <td align="right">{{ number_format($row['plan_principal'] ?? 0, 0, ',', '.') }}</td>
                <td align="right">{{ number_format($row['actual_principal'] ?? 0, 0, ',', '.') }}</td>
                <td align="right" style="{{ ($row['gap_principal'] ?? 0) > 0 ? 'color: #c53030;' : '' }}">{{ number_format($row['gap_principal'] ?? 0, 0, ',', '.') }}</td>
                <td align="right">{{ number_format($row['plan_interest'] ?? 0, 0, ',', '.') }}</td>
                <td align="right">{{ number_format($row['actual_interest'] ?? 0, 0, ',', '.') }}</td>
                <td align="right" style="{{ ($row['gap_interest'] ?? 0) > 0 ? 'color: #c53030;' : '' }}">{{ number_format($row['gap_interest'] ?? 0, 0, ',', '.') }}</td>
                <td align="center" style="{{ ($row['delay_days'] ?? 0) > 0 ? 'color: #c53030; font-weight: bold;' : '' }}">{{ $row['delay_days'] !== null ? $row['delay_days'] : '—' }}</td>
                <td align="center">{{ $row['realization_status'] ?? '—' }}</td>
            </tr>
        @endforeach
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="4" align="left">TOTAL {{ $loan['loan_number'] }}</td>
            <td align="right">{{ number_format($loan['totals']['plan_principal'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($loan['totals']['actual_principal'] ?? 0, 0, ',', '.') }}</td>
            <td align="right" style="color: #c53030;">{{ number_format($loan['totals']['gap_principal'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($loan['totals']['plan_interest'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($loan['totals']['actual_interest'] ?? 0, 0, ',', '.') }}</td>
            <td align="right" style="color: #c53030;">{{ number_format($loan['totals']['gap_interest'] ?? 0, 0, ',', '.') }}</td>
            <td colspan="2" align="center">{{ number_format($loan['totals']['pct_realization'] ?? 0, 2, ',', '.') }}% · Gap {{ number_format(($loan['totals']['gap_principal'] ?? 0) + ($loan['totals']['gap_interest'] ?? 0), 0, ',', '.') }}</td>
        </tr>
    </table>
@endforeach

@if (empty($loans))
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td align="center" style="padding: 30px; color: #666;">Tidak ada pinjaman individu dengan angsuran jatuh tempo pada periode ini.</td>
        </tr>
    </table>
@endif
@endsection
