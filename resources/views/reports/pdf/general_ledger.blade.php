@extends('reports.pdf.layout', [
    'title' => 'Buku Besar '.($account['name'] ?? ''),
    'identity' => $identity,
    'period' => $period,
])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="8" align="center">
            <div style="font-size: 18px;"><b>BUKU BESAR {{ strtoupper($account['name'] ?? '') }}</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="8" height="5"></td></tr>
</table>

<div style="width: 100%; text-align: right; font-size: 11px;">Kode Akun : {{ $account['code'] ?? '' }}</div>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <thead>
        <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
            <td height="15" align="center" width="4%">No</td>
            <td align="center" width="10%">Tanggal</td>
            <td align="center" width="8%">Ref ID.</td>
            <td align="center">Keterangan</td>
            <td align="center" width="13%">Debit</td>
            <td align="center" width="13%">Kredit</td>
            <td align="center" width="13%">Saldo</td>
            <td align="center" width="5%">Ins</td>
        </tr>
    </thead>
    <tbody>
        @if($opening)
            <tr style="background: rgb(230, 230, 230);">
                <td align="center"></td>
                <td align="center">{{ $opening['year']['date'] }}</td>
                <td align="center"></td>
                <td>{{ $opening['year']['label'] }}</td>
                <td align="right">{{ number_format($opening['year']['debit'], 2) }}</td>
                <td align="right">{{ number_format($opening['year']['credit'], 2) }}</td>
                <td align="right">
                    @if(($opening['year']['balance'] ?? 0) < 0)
                        ({{ number_format(abs($opening['year']['balance']), 2) }})
                    @else
                        {{ number_format($opening['year']['balance'], 2) }}
                    @endif
                </td>
                <td align="center"></td>
            </tr>
            <tr style="background: rgb(255, 255, 255);">
                <td align="center"></td>
                <td align="center">{{ $opening['prior']['date'] }}</td>
                <td align="center"></td>
                <td>{{ $opening['prior']['label'] }}</td>
                <td align="right">{{ number_format($opening['prior']['debit'], 2) }}</td>
                <td align="right">{{ number_format($opening['prior']['credit'], 2) }}</td>
                <td align="right">
                    @if(($opening['prior']['balance'] ?? 0) < 0)
                        ({{ number_format(abs($opening['prior']['balance']), 2) }})
                    @else
                        {{ number_format($opening['prior']['balance'], 2) }}
                    @endif
                </td>
                <td align="center"></td>
            </tr>
        @endif
        @foreach($rows as $row)
            <tr style="background: {{ $loop->iteration % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td align="center">{{ $row['no'] }}</td>
                <td align="center">{{ $row['date'] }}</td>
                <td align="center">{{ $row['journal_number'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td align="right">{{ $row['debit'] ? number_format($row['debit'], 2) : '&nbsp;' }}</td>
                <td align="right">{{ $row['credit'] ? number_format($row['credit'], 2) : '&nbsp;' }}</td>
                <td align="right">
                    @if(($row['balance'] ?? 0) < 0)
                        ({{ number_format(abs($row['balance']), 2) }})
                    @else
                        {{ number_format($row['balance'], 2) }}
                    @endif
                </td>
                <td align="center">&nbsp;</td>
            </tr>
        @endforeach

        <tr>
            <td colspan="8" style="padding: 0px !important;">
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                    <tr style="background: rgb(233, 233, 233);">
                        <td height="12"><b>{{ $totals['period']['label'] ?? 'Total Transaksi Periode' }}</b></td>
                        <td align="right" width="13%"><b>{{ number_format($totals['period']['debit'] ?? $totals['debit'], 2) }}</b></td>
                        <td align="right" width="13%"><b>{{ number_format($totals['period']['credit'] ?? $totals['credit'], 2) }}</b></td>
                        <td align="center" rowspan="3" width="13%">
                            @if(($totals['ytd']['balance'] ?? $totals['closing_balance'] ?? 0) < 0)
                                <b>({{ number_format(abs($totals['ytd']['balance'] ?? $totals['closing_balance'] ?? 0), 2) }})</b>
                            @else
                                <b>{{ number_format($totals['ytd']['balance'] ?? $totals['closing_balance'] ?? 0, 2) }}</b>
                            @endif
                        </td>
                    </tr>
                    <tr style="background: rgb(255, 255, 255);">
                        <td height="12"><b>{{ $totals['ytd']['label'] ?? 'Total Transaksi s/d Periode' }}</b></td>
                        <td align="right"><b>{{ number_format($totals['ytd']['debit'] ?? 0, 2) }}</b></td>
                        <td align="right"><b>{{ number_format($totals['ytd']['credit'] ?? 0, 2) }}</b></td>
                    </tr>
                    <tr style="background: rgb(233, 233, 233);">
                        <td height="12"><b>{{ $totals['cumulative']['label'] ?? 'Total Transaksi Kumulatif Tahun' }}</b></td>
                        <td align="right"><b>{{ number_format($totals['cumulative']['debit'] ?? 0, 2) }}</b></td>
                        <td align="right"><b>{{ number_format($totals['cumulative']['credit'] ?? 0, 2) }}</b></td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
</table>
@endsection
