@extends('reports.pdf.layout', ['title' => 'CALK Tutup Buku', 'identity' => $identity, 'period' => $period])

@section('content')
<style>
    ol, ul { margin-left: unset; }
    .pointA *:first-child { margin-top: 0; }
    .preview-banner { background: #fff3cd; color: #856404; padding: 6px; text-align: center; font-weight: bold; font-size: 11px; border: 1px solid #ffeeba; margin-bottom: 8px; }
</style>

<div class="preview-banner">
    ⚠️ PREVIEW / SIMULASI — Bagian dari pelaporan tutup buku akhir tahun.
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>CATATAN ATAS LAPORAN KEUANGAN</b></div>
            <div style="font-size: 18px; text-transform: uppercase;"><b>{{ $identity['short_name'] ?? $identity['legal_name'] ?? config('app.name') }}</b></div>
            <div style="font-size: 14px;"><b>CALK TUTUP BUKU · TAHUN {{ $year }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
</table>

<ol style="list-style: upper-alpha;">
    @foreach($chapters as $chapter)
        <li style="margin-top: 8px;">
            <div style="text-transform: uppercase; font-weight: bold;">{{ $chapter['title'] }}</div>
            <div style="text-align: justify; margin-top: 4px;">
                {{ $chapter['body'] }}
            </div>
        </li>
    @endforeach
</ol>

<h3 style="margin-top: 12px; font-size: 13px;">KPI Ringkasan Tutup Buku</h3>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <thead>
        <tr style="background: rgb(74, 74, 74); color: #fff; font-weight: bold;">
            <td height="20" align="center">No</td>
            <td align="center">Indikator</td>
            <td align="center" width="25%">Nilai</td>
        </tr>
    </thead>
    <tbody>
        @foreach($highlights as $idx => $h)
            <tr style="background: {{ $idx % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                <td align="center">{{ $idx + 1 }}</td>
                <td>{{ $h['label'] }}</td>
                <td align="right">
                    @if(($h['amount'] ?? 0) < 0)
                        ({{ number_format(abs($h['amount']), 2) }})
                    @else
                        {{ number_format($h['amount'], 2) }}
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<h3 style="margin-top: 12px; font-size: 13px;">Ringkasan Jurnal Tutup Buku</h3>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(232, 232, 232);">
        <td width="40%">Jumlah entry jurnal</td>
        <td>: <strong>{{ $closing_journal_summary['entries'] }}</strong></td>
    </tr>
    <tr style="background: rgb(245, 245, 245);">
        <td>Total Pendapatan ditutup</td>
        <td>: <strong>{{ number_format($closing_journal_summary['revenue'], 2) }}</strong></td>
    </tr>
    <tr style="background: rgb(232, 232, 232);">
        <td>Total Beban ditutup</td>
        <td>: <strong>{{ number_format($closing_journal_summary['expense'], 2) }}</strong></td>
    </tr>
    <tr style="background: rgb(245, 245, 245);">
        <td>Surplus/(Defisit)</td>
        <td>: <strong>{{ number_format($closing_journal_summary['surplus'], 2) }}</strong></td>
    </tr>
    <tr style="background: rgb(200, 200, 200);">
        <td>Status keseimbangan</td>
        <td>: <strong>{{ $closing_journal_summary['balanced'] ? 'Debit = Kredit (seimbang)' : 'Tidak seimbang' }}</strong></td>
    </tr>
</table>

<h3 style="margin-top: 12px; font-size: 13px;">Ringkasan Alokasi Laba</h3>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <thead>
        <tr style="background: rgb(74, 74, 74); color: #fff; font-weight: bold;">
            <td align="center">Pos</td>
            <td align="center" width="15%">Persentase</td>
            <td align="center" width="25%">Nominal</td>
        </tr>
    </thead>
    <tbody>
        <tr style="background: rgb(232, 232, 232);">
            <td><strong>Total Surplus</strong></td>
            <td align="center">100.00%</td>
            <td align="right"><strong>{{ number_format($allocation_summary['surplus'], 2) }}</strong></td>
        </tr>
        @foreach($allocation_summary['lines'] as $idx => $line)
            <tr style="background: {{ $idx % 2 == 0 ? 'rgb(245, 245, 245)' : 'rgb(255, 255, 255)' }};">
                <td>{{ $line['label'] }}{{ $line['key'] === 'ditahan' ? ' (sisa)' : '' }}</td>
                <td align="center">{{ number_format($line['percentage'], 2) }}%</td>
                <td align="right">{{ number_format($line['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td>Total Dialokasikan</td>
            <td align="center">{{ number_format($allocation_summary['totals']['pct_allocated'], 2) }}%</td>
            <td align="right">{{ number_format($allocation_summary['totals']['allocated'], 2) }}</td>
        </tr>
    </tbody>
</table>

<h3 style="margin-top: 12px; font-size: 13px;">Kebijakan Akuntansi (Ringkas)</h3>
<ol style="list-style: lower-alpha; font-size: 11px;">
    @foreach($policies as $p)
        <li style="text-align: justify;">{{ $p }}</li>
    @endforeach
</ol>

<div style="margin-top: 12px; padding: 6px; background: #fff3cd; font-style: italic; color: #856404; font-size: 11px;">
    <strong>Catatan:</strong> CALK ini adalah bagian dari pelaporan tutup buku akhir tahun. Pastikan setiap
    bab direview oleh manajemen/direksi sebelum penerbitan resmi. Setelah tutup buku benar-benar dijalankan
    (PeriodClose), catatan akan direfleksikan ke laporan final.
</div>
@endsection