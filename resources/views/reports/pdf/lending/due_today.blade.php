@extends('reports.pdf.layout', ['title' => 'Tagihan Jatuh Tempo', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="12" align="center">
            <div style="font-size: 18px;"><b>TAGIHAN JATUH TEMPO</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="12" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 14px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td height="20" align="center">Total Angsuran</td>
        <td align="right">Pokok</td>
        <td align="right">Bunga</td>
        <td align="right">Denda</td>
        <td align="right">Total Tagihan</td>
    </tr>
    <tr style="background: rgb(245, 245, 245);">
        <td height="20" align="center">{{ $totals['count'] ?? 0 }} ({{ $totals['loan_count'] ?? 0 }} pinjaman)</td>
        <td align="right">{{ number_format($totals['principal_due'] ?? 0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format($totals['interest_due'] ?? 0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format($totals['penalty_due'] ?? 0, 0, ',', '.') }}</td>
        <td align="right" style="font-weight: bold;">{{ number_format($totals['total_due'] ?? 0, 0, ',', '.') }}</td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px;">
    <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
        <td height="22" align="center" width="4%">No</td>
        <td width="9%">Pinjaman</td>
        <td width="18%">Peminjam / Kelompok</td>
        <td width="14%">Desa</td>
        <td width="8%">Produk</td>
        <td align="center" width="5%">Ke-</td>
        <td align="center" width="8%">Jatuh Tempo</td>
        <td align="center" width="5%">Hari</td>
        <td align="right" width="9%">Pokok</td>
        <td align="right" width="9%">Bunga</td>
        <td align="right" width="6%">Denda</td>
        <td align="right" width="11%">Total</td>
    </tr>
    @forelse($rows as $row)
        <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td align="center">{{ $loop->iteration }}</td>
            <td>
                <b>#{{ $row['loan_id'] }}</b><br />
                <span style="color: #666; font-size: 8px;">{{ $row['loan_number'] }}</span>
            </td>
            <td>
                {{ $row['borrower_name'] }}
                @if (!empty($row['borrower_code']))
                    <br /><span style="color: #666; font-size: 8px;">({{ $row['borrower_code'] }})</span>
                @endif
            </td>
            <td>{{ $row['village_name'] }}</td>
            <td>
                {{ $row['product_code'] }}<br />
                <span style="color: #666; font-size: 8px;">{{ $row['product_name'] }}</span>
            </td>
            <td align="center">{{ $row['installment_number'] }}</td>
            <td align="center">{{ \Carbon\CarbonImmutable::parse($row['due_date'])->format('d/m/Y') }}</td>
            <td align="center" style="{{ ($row['days_overdue'] ?? 0) > 0 ? 'color: #c53030; font-weight: bold;' : '' }}">{{ $row['days_overdue'] }}</td>
            <td align="right">{{ number_format($row['principal_due'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($row['interest_due'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($row['penalty_due'] ?? 0, 0, ',', '.') }}</td>
            <td align="right" style="font-weight: bold;">{{ number_format($row['total_due'] ?? 0, 0, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="12" align="center" style="color: #666; padding: 20px;">Tidak ada tagihan jatuh tempo pada tanggal ini.</td>
        </tr>
    @endforelse
    @if (count($rows) > 0)
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td colspan="8" align="left">JUMLAH ({{ $totals['count'] ?? 0 }} angsuran · {{ $totals['loan_count'] ?? 0 }} pinjaman)</td>
            <td align="right">{{ number_format($totals['principal_due'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($totals['interest_due'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($totals['penalty_due'] ?? 0, 0, ',', '.') }}</td>
            <td align="right">{{ number_format($totals['total_due'] ?? 0, 0, ',', '.') }}</td>
        </tr>
    @endif
</table>
@endsection
