@extends('reports.pdf.regency.layout', ['title' => 'Buku Besar Konsolidasi - ' . ($regency_name ?? 'Kabupaten')])

@section('content')
<div class="text-center" style="margin-bottom: 12px;">
    <div class="header-title">BUKU BESAR KONSOLIDASI KABUPATEN</div>
    <div class="header-sub">
        Akun: <b>{{ $report['account']['code'] }} - {{ $report['account']['name'] }}</b><br>
        Periode: {{ $report['period']['period_label'] ?? '' }}
        @if (!empty($report['is_consolidated']))
            · Gabungan Seluruh Kecamatan
        @endif
    </div>
</div>

<table class="t l r b" style="font-size: 10px;">
    <thead>
        <tr class="bg-gray">
            <th class="b r" style="width: 70px;">Tanggal</th>
            <th class="b r" style="width: 90px;">Kecamatan</th>
            <th class="b r" style="width: 80px;">No Bukti</th>
            <th class="b r" style="text-align: left;">Keterangan</th>
            <th class="b r text-right" style="width: 80px;">Debit (Rp)</th>
            <th class="b r text-right" style="width: 80px;">Kredit (Rp)</th>
            <th class="b text-right" style="width: 85px;">Saldo (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="font-bold" style="background-color: #fafafa;">
            <td class="b r text-center">—</td>
            <td class="b r">—</td>
            <td class="b r">—</td>
            <td class="b r"><i>SALDO AWAL</i></td>
            <td class="b r text-right">—</td>
            <td class="b r text-right">—</td>
            <td class="b text-right">{{ number_format($report['opening_balance'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        @forelse ($report['entries'] as $entry)
            <tr>
                <td class="b r text-center">{{ date('d/m/Y', strtotime($entry['date'])) }}</td>
                <td class="b r">{{ $entry['kecamatan_name'] }}</td>
                <td class="b r">{{ $entry['voucher_number'] }}</td>
                <td class="b r">{{ $entry['description'] }}</td>
                <td class="b r text-right">{{ $entry['debit'] > 0 ? number_format($entry['debit'], 2, ',', '.') : '—' }}</td>
                <td class="b r text-right">{{ $entry['credit'] > 0 ? number_format($entry['credit'], 2, ',', '.') : '—' }}</td>
                <td class="b text-right">{{ number_format($entry['balance'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td class="b r text-center" colspan="7" style="padding: 12px; color: #6b7280;">Tidak ada mutasi transaksi pada periode ini.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="bg-gray font-bold">
            <td class="t b r" colspan="4">TOTAL MUTASI & SALDO AKHIR</td>
            <td class="t b r text-right">{{ number_format($report['total_debit'] ?? 0, 2, ',', '.') }}</td>
            <td class="t b r text-right">{{ number_format($report['total_credit'] ?? 0, 2, ',', '.') }}</td>
            <td class="t b text-right">{{ number_format($report['closing_balance'] ?? 0, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endsection
