@extends('reports.pdf.layout', ['title' => 'Jurnal Transaksi', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="8" align="center">
            <div style="font-size: 18px;"><b>JURNAL TRANSAKSI</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="8" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <thead>
        <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
            <td height="15" align="center" width="4%">No</td>
            <td align="center" width="10%">Tanggal</td>
            <td align="center" width="8%">Ref ID.</td>
            <td align="center" width="8%">Kd. Rek</td>
            <td align="center" width="35%">Keterangan</td>
            <td align="center" width="15%">Debit</td>
            <td align="center" width="15%">Kredit</td>
            <td align="center" width="5%">Ins</td>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr style="background: {{ $loop->iteration % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td height="15" align="center">{{ $row['no'] }}.</td>
                <td align="center">{{ $row['date'] }}</td>
                <td align="left">{{ $row['journal_number'] }}</td>
                <td align="center">{{ $row['account_code'] }}</td>
                <td align="left">{{ $row['account_name'] }}{{ $row['description'] ? ' - '.$row['description'] : '' }}</td>
                <td align="right">{{ $row['debit'] ? number_format($row['debit'], 2) : '&nbsp;' }}</td>
                <td align="right">{{ $row['credit'] ? number_format($row['credit'], 2) : '&nbsp;' }}</td>
                <td align="center">&nbsp;</td>
            </tr>
        @endforeach

        <tr>
            <td colspan="8" style="padding: 0px !important;">
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                    <tr style="background: rgb(233, 233, 233); font-weight: bold; color: #000;">
                        <td height="15" align="center"><b>Total Transaksi</b></td>
                        <td align="right" width="15%">{{ number_format($totals['debit'], 2) }}</td>
                        <td align="right" width="15%">{{ number_format($totals['credit'], 2) }}</td>
                        <td align="center" width="5%">&nbsp;</td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
</table>
@endsection
