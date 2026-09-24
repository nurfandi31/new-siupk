@extends('reports.pdf.layout', ['title' => 'Daftar Tunggakan', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="13" align="center">
            <div style="font-size: 18px;"><b>DAFTAR TUNGGAKAN</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="13" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-bottom: 14px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td height="20" align="center">Pinjaman Menunggak</td>
        <td align="right">Total Tunggakan</td>
        <td align="right">Pokok Tunggakan</td>
        <td align="right">Bunga Tunggakan</td>
        <td align="center">Rata-rata Telat</td>
        <td align="center">Maks Telat</td>
    </tr>
    <tr style="background: rgb(245, 245, 245);">
        <td height="20" align="center">{{ $totals['loan_count'] ?? 0 }}</td>
        <td align="right" style="font-weight: bold; color: #c53030;">{{ number_format($totals['overdue_amount'] ?? 0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format($totals['overdue_principal'] ?? 0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format($totals['overdue_interest'] ?? 0, 0, ',', '.') }}</td>
        <td align="center">{{ number_format($totals['avg_days_overdue'] ?? 0, 1, ',', '.') }} hari</td>
        <td align="center">{{ $totals['max_days_overdue'] ?? 0 }} hari</td>
    </tr>
</table>

@if (!empty($aging))
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-bottom: 14px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td height="20">Bucket Aging</td>
        <td align="center">Jumlah</td>
        <td align="right">Sisa Pokok</td>
        <td align="right">Total Tunggakan</td>
    </tr>
    @foreach ($aging as $bucket)
        <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(245, 245, 245)' : 'rgb(255, 255, 255)' }};">
            <td>{{ $bucket['label'] }}</td>
            <td align="center">{{ $bucket['count'] }}</td>
            <td align="right">{{ number_format($bucket['principal'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($bucket['overdue'] ?? 0, 0, ',', '.') }}</td>
        </tr>
    @endforeach
</table>
@endif

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8.5px;">
    <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
        <td height="22" align="center" width="3%">No</td>
        <td width="9%">Pinjaman</td>
        <td width="14%">Peminjam / Kelompok</td>
        <td width="12%">Desa</td>
        <td width="8%">Produk</td>
        <td align="right" width="9%">Pokok Pinjaman</td>
        <td align="right" width="8%">Sisa Pokok</td>
        <td align="right" width="8%">Tungg. Pokok</td>
        <td align="right" width="8%">Tungg. Bunga</td>
        <td align="right" width="9%">Total Tunggakan</td>
        <td align="center" width="4%"># Ang</td>
        <td align="center" width="4%">Hari</td>
        <td align="center" width="10%">Kolektibilitas</td>
    </tr>
    @forelse($rows as $row)
        <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td align="center">{{ $loop->iteration }}</td>
            <td>
                <b>#{{ $row['loan_id'] }}</b><br />
                <span style="color: #666; font-size: 7px;">{{ $row['loan_number'] }}</span>
            </td>
            <td>
                {{ $row['borrower_name'] }}
                @if (!empty($row['borrower_code']))
                    <br /><span style="color: #666; font-size: 7px;">({{ $row['borrower_code'] }})</span>
                @endif
            </td>
            <td>{{ $row['village_name'] }}</td>
            <td>
                {{ $row['product_code'] }}<br />
                <span style="color: #666; font-size: 7px;">{{ $row['product_name'] }}</span>
            </td>
            <td align="right">{{ number_format($row['principal_disbursed'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($row['principal_remaining'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($row['overdue_principal'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($row['overdue_interest'] ?? 0, 0, ',', '.') }}</td>
            <td align="right" style="font-weight: bold; color: #c53030;">{{ number_format($row['overdue_amount'] ?? 0, 0, ',', '.') }}</td>
            <td align="center">{{ $row['overdue_installment_count'] ?? 0 }}</td>
            <td align="center" style="{{ ($row['days_overdue'] ?? 0) > 90 ? 'color: #c53030; font-weight: bold;' : '' }}">{{ $row['days_overdue'] ?? 0 }}</td>
            <td align="center">{{ $row['collectibility'] ?? '' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="13" align="center" style="color: #666; padding: 20px;">Tidak ada pinjaman menunggak pada posisi tanggal ini.</td>
        </tr>
    @endforelse
    @if (count($rows) > 0)
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="5" align="left">JUMLAH ({{ $totals['loan_count'] ?? 0 }} pinjaman)</td>
            <td align="right">{{ number_format($totals['principal_disbursed'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($totals['principal_remaining'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($totals['overdue_principal'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($totals['overdue_interest'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($totals['overdue_amount'] ?? 0, 0, ',', '.') }}</td>
            <td align="center">{{ $totals['overdue_installment_count'] ?? 0 }}</td>
            <td colspan="2" align="center">maks {{ $totals['max_days_overdue'] ?? 0 }} hari</td>
        </tr>
    @endif
</table>
@endsection
