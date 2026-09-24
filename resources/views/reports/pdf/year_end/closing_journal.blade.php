@extends('reports.pdf.layout', ['title' => 'Jurnal Tutup Buku', 'identity' => $identity, 'period' => $period])

@section('content')
<style>
    .preview-banner { background: #fff3cd; color: #856404; padding: 6px; text-align: center; font-weight: bold; font-size: 11px; border: 1px solid #ffeeba; margin-bottom: 8px; }
    .entry-header { background: rgb(74, 74, 74); color: #fff; font-weight: bold; padding: 4px; }
</style>

<div class="preview-banner">
    ⚠️ PREVIEW / SIMULASI — Jurnal ini belum ter-posting.
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="8" align="center">
            <div style="font-size: 18px;"><b>JURNAL TUTUP BUKU</b></div>
            <div style="font-size: 16px;"><b>TAHUN BUKU {{ $year }}</b></div>
        </td>
    </tr>
    <tr><td colspan="8" height="3"></td></tr>
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td colspan="2">Total Pendapatan</td>
        <td align="right" colspan="2">{{ number_format($totals['revenue'], 2) }}</td>
        <td colspan="2">Total Beban</td>
        <td align="right" colspan="2">{{ number_format($totals['expense'], 2) }}</td>
    </tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td colspan="2">Surplus/(Defisit)</td>
        <td align="right" colspan="2">{{ number_format($totals['surplus'], 2) }}</td>
        <td colspan="2">Total Debit</td>
        <td align="right" colspan="2">{{ number_format($totals['debit'], 2) }}</td>
    </tr>
    <tr><td colspan="8" height="5"></td></tr>

    <tr style="background: rgb(74, 74, 74); color: #fff; font-weight: bold;">
        <td width="4%">No</td>
        <td width="10%">Tanggal</td>
        <td width="12%">Ref</td>
        <td width="10%">Kode</td>
        <td width="30%">Nama Akun</td>
        <td width="14%">Keterangan</td>
        <td width="10%" align="right">Debit</td>
        <td width="10%" align="right">Kredit</td>
    </tr>

    @php $i = 0; @endphp
    @foreach($rows as $row)
        @if($row['is_header'] ?? false)
            <tr style="background: rgb(167, 167, 167); font-weight: bold; color: #fff;">
                <td colspan="8" height="20" style="padding: 2px 4px;">
                    Entry #{{ $row['entry_no'] }} · {{ $row['reference'] }} — {{ $row['description'] }}
                </td>
            </tr>
        @else
            <tr style="background: {{ $i % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                <td align="center">{{ $row['no'] }}.</td>
                <td align="center">{{ $row['date'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td align="center">{{ $row['account_code'] }}</td>
                <td>{{ $row['account_name'] }}</td>
                <td>{{ $row['memo'] }}</td>
                <td align="right">{{ $row['debit'] ? number_format($row['debit'], 2) : '&nbsp;' }}</td>
                <td align="right">{{ $row['credit'] ? number_format($row['credit'], 2) : '&nbsp;' }}</td>
            </tr>
            @php $i++; @endphp
        @endif
    @endforeach

    <tr><td colspan="8" height="2"></td></tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td colspan="6" align="left">Total Jurnal Tutup Buku</td>
        <td align="right">{{ number_format($totals['debit'], 2) }}</td>
        <td align="right">{{ number_format($totals['credit'], 2) }}</td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
    <tr><td style="padding: 6px; background: #fff3cd; font-style: italic; color: #856404;">
        <strong>Catatan:</strong> Jurnal ini adalah PREVIEW/SIMULASI yang di-generate otomatis dari saldo
        akun nominal per 31 Desember {{ $year }}. Jurnal sebenarnya akan di-posting oleh modul Tutup Buku
        (PeriodClose) ketika administrator menjalankan proses tutup tahun.
    </td></tr>
</table>
@endsection