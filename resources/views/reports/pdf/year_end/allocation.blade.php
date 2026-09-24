@extends('reports.pdf.layout', ['title' => 'Alokasi Laba Tutup Buku', 'identity' => $identity, 'period' => $period])

@section('content')
<style>
    .preview-banner { background: #fff3cd; color: #856404; padding: 6px; text-align: center; font-weight: bold; font-size: 11px; border: 1px solid #ffeeba; margin-bottom: 8px; }
</style>

<div class="preview-banner">
    ⚠️ PREVIEW / SIMULASI — Laporan ini bukan jurnal yang sudah ter-posting.
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="5" align="center">
            <div style="font-size: 18px;"><b>ALOKASI LABA TUTUP BUKU</b></div>
            <div style="font-size: 16px;"><b>TAHUN BUKU {{ $year }}</b></div>
        </td>
    </tr>
    <tr><td colspan="5" height="3"></td></tr>
    <tr style="background: rgb(74, 74, 74); color: #fff;">
        <td width="5%">No</td>
        <td width="35%">Uraian</td>
        <td width="15%" align="right">Persentase (%)</td>
        <td width="22%" align="right">Nominal (Rp)</td>
        <td width="23%">Keterangan</td>
    </tr>
    <tr><td colspan="5" height="1"></td></tr>
    @foreach($lines as $idx => $line)
        <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
            <td align="center">{{ $line['no'] }}.</td>
            <td>{{ $line['label'] }}{{ $line['key'] === 'ditahan' ? ' (sisa)' : '' }}</td>
            <td align="right">{{ number_format($line['percentage'], 2) }}%</td>
            <td align="right">{{ number_format($line['amount'], 2) }}</td>
            <td>{{ $line['note'] }}</td>
        </tr>
    @endforeach

    <tr><td colspan="5" height="2"></td></tr>
    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
        <td colspan="2" align="left">Total Surplus</td>
        <td align="right">100.00%</td>
        <td align="right">{{ number_format($surplus, 2) }}</td>
        <td></td>
    </tr>

    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td colspan="2" align="left">Total Dialokasikan</td>
        <td align="right">{{ number_format($totals['pct_allocated'], 2) }}%</td>
        <td align="right">{{ number_format($totals['allocated'], 2) }}</td>
        <td></td>
    </tr>

    <tr style="background: {{ abs($totals['remaining']) < 0.01 ? 'rgb(190, 230, 190)' : 'rgb(245, 200, 200)' }}; font-weight: bold;">
        <td colspan="2" align="left">Sisa (belum dialokasikan)</td>
        <td align="right">{{ number_format(100 - $totals['pct_allocated'], 2) }}%</td>
        <td align="right">{{ number_format($totals['remaining'], 2) }}</td>
        <td></td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
    <tr><td colspan="2" style="padding: 4px; background: rgb(232, 232, 232); font-weight: bold;">
        Akun Tujuan (referensi jurnal alokasi)
    </td></tr>
    @foreach($account_targets as $key => $acc)
        <tr>
            <td width="30%" style="padding: 2px 4px;">{{ ucfirst(str_replace('_', ' ', $key)) }}:</td>
            <td style="padding: 2px 4px;">{{ $acc['code'] }} · {{ $acc['name'] ?: '(tidak tersedia)' }}</td>
        </tr>
    @endforeach
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
    <tr><td style="padding: 6px; background: #fff3cd; font-style: italic; color: #856404;">
        <strong>Catatan:</strong> Laporan ini adalah PREVIEW/SIMULASI alokasi laba. Jurnal alokasi sebenarnya
        diproses oleh modul Tutup Buku (PeriodClose) setelah pengesahan RAT. Pos "Laba Ditahan (sisa)"
        dihitung otomatis sebagai sisa surplus yang belum dialokasikan ke pos lain.
    </td></tr>
</table>

@if(!empty($notes))
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
        <tr><td style="padding: 6px; background: rgb(232, 232, 232); font-weight: bold;">Catatan Manajemen</td></tr>
        <tr><td style="padding: 6px; text-align: justify;">{{ $notes }}</td></tr>
    </table>
@endif
@endsection