@extends('reports.pdf.layout', ['title' => 'Simulasi Pinjaman', 'identity' => $identity])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 12px;">
    <tr>
        <td align="center">
            <div style="font-size: 16px; font-weight: bold; text-transform: uppercase;">SIMULASI ANGSURAN PINJAMAN</div>
            <div style="font-size: 12px; color: #555; margin-top: 2px;">Tanggal Cetak: {{ date('d/m/Y H:i') }} WIB</div>
        </td>
    </tr>
</table>

@php
    $methodLabels = [
        'flat' => 'Flat / Tetap',
        'declining' => 'Efektif Menurun',
        'annuity' => 'Anuitas',
    ];
    $methodName = $methodLabels[$simulation['summary']['installment_method'] ?? 'flat'] ?? ucfirst($simulation['summary']['installment_method'] ?? 'Flat');
    $roundingVal = (int) ($simulation['summary']['rounding_step'] ?? 500);
    $roundingLabel = $roundingVal > 0 ? 'Rp ' . number_format($roundingVal, 0, ',', '.') : 'Tanpa Pembulatan (Desimal)';
    $rateMonthly = $simulation['summary']['interest_rate_monthly'] ?? round(($simulation['summary']['interest_rate_annual'] ?? 0) / 12, 2);
    $rateAnnual = $simulation['summary']['interest_rate_annual'] ?? ($rateMonthly * 12);
@endphp

<table border="0" width="100%" cellspacing="0" cellpadding="4" style="font-size: 11px; margin-bottom: 15px; border: 1px solid #ddd; background: #fafafa;">
    <tr>
        <td width="20%" style="font-weight: bold;">Peminjam / Kelompok</td>
        <td width="30%">: {{ $borrower_name ?? 'Calon Peminjam' }}</td>
        <td width="20%" style="font-weight: bold;">Total Pokok Pinjaman</td>
        <td width="30%" align="right" style="font-weight: bold;">: Rp {{ number_format($simulation['summary']['principal_amount'], 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Plafon Pinjaman</td>
        <td>: Rp {{ number_format($simulation['summary']['principal_amount'], 2, ',', '.') }}</td>
        <td style="font-weight: bold;">Total Jasa / Bunga</td>
        <td align="right" style="font-weight: bold;">: Rp {{ number_format($simulation['summary']['total_interest'], 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Jangka Waktu (Tenor)</td>
        <td>: {{ $simulation['summary']['term_months'] }} Bulan</td>
        <td style="font-weight: bold; color: #004d40;">Total Pembayaran</td>
        <td align="right" style="font-weight: bold; color: #004d40;">: Rp {{ number_format($simulation['summary']['total_payment'], 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Suku Bunga / Jasa</td>
        <td>: {{ number_format($rateMonthly, 2, ',', '.') }}% / bln ({{ number_format($rateAnnual, 2, ',', '.') }}% p.a.)</td>
        <td style="font-weight: bold;">Estimasi per Bulan</td>
        <td align="right" style="font-weight: bold;">: Rp {{ number_format($simulation['summary']['estimated_monthly_payment'], 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Sistem Bunga</td>
        <td>: {{ $methodName }}</td>
        <td style="font-weight: bold;">Metode Pembulatan</td>
        <td align="right">: {{ $roundingLabel }}</td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="3" style="font-size: 10px;">
    <thead>
        <tr style="background: #e0e0e0; font-weight: bold;">
            <th width="6%" class="t l b" align="center">Ke</th>
            <th width="18%" class="t l b" align="center">Jatuh Tempo</th>
            <th width="20%" class="t l b" align="right">Angsuran Pokok (Rp)</th>
            <th width="18%" class="t l b" align="right">Bunga / Jasa (Rp)</th>
            <th width="20%" class="t l b" align="right">Total Angsuran (Rp)</th>
            <th width="18%" class="t l b r" align="right">Sisa Pokok (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($simulation['schedule'] as $idx => $row)
            <tr style="background: {{ $idx % 2 === 0 ? '#fdfdfd' : '#f5f5f5' }};">
                <td class="t l b" align="center">{{ $row['number'] }}</td>
                <td class="t l b" align="center">{{ date('d/m/Y', strtotime($row['due_date'])) }}</td>
                <td class="t l b" align="right">{{ number_format($row['principal_due'], 2, ',', '.') }}</td>
                <td class="t l b" align="right">{{ number_format($row['interest_due'], 2, ',', '.') }}</td>
                <td class="t l b" align="right" style="font-weight: bold;">{{ number_format($row['total_due'], 2, ',', '.') }}</td>
                <td class="t l b r" align="right">{{ number_format($row['remaining_principal'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background: #e0e0e0; font-weight: bold;">
            <td colspan="2" class="t l b" align="center">TOTAL</td>
            <td class="t l b" align="right">Rp {{ number_format($simulation['summary']['principal_amount'], 2, ',', '.') }}</td>
            <td class="t l b" align="right">Rp {{ number_format($simulation['summary']['total_interest'], 2, ',', '.') }}</td>
            <td class="t l b" align="right">Rp {{ number_format($simulation['summary']['total_payment'], 2, ',', '.') }}</td>
            <td class="t l b r" align="right">Rp 0,00</td>
        </tr>
    </tfoot>
</table>

<div style="margin-top: 15px; font-size: 9px; color: #777; font-style: italic;">
    * Catatan: Tabel di atas adalah simulasi perhitungan estimasi jadwal pembayaran angsuran pinjaman. Nilai realisasi aktual akan disesuaikan dengan tanggal pencairan dan akad pinjaman resmi.
</div>
@endsection
