<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        .style9 { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000000; }
    </style>
</head>
<body onload="window.print()">
<br><br><br>
<table width="97%" border="0" align="center" cellpadding="5" cellspacing="0">
    <tr>
        <th width="5%" class="style9">&nbsp;</th>
        <td width="80%" class="style9">
            {{ $transaction['row_id'] }}.{{ $account['account_number'] }} {{ $transaction['transaction_date'] }}-{{ strtoupper($user) }}<br>
            CIF-{{ $account['row_id'] }}-{{ $kode }}<br>
            Rp. {{ number_format($transaction['amount'], 2, '.', ',') }}<br><br>
        </td>
    </tr>
</table>
</body>
</html>
