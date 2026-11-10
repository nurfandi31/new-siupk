<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        .style9 { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000000; }
        .style10 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; color: #000000; }
        .align-center { text-align: center; }
    </style>
</head>
<body onload="window.print()">
@php
    // SIUPK memakai offset baris tetap agar hasil cetak pas pada buku simpanan.
    $i = max(0, (int) $transactionCount - 1);
    $a = $i % 24;
    $br = ($a <= 10) ? $a : $a + 2;
@endphp
<table width="100%" border="0" align="center" cellpadding="5" cellspacing="0">
    <tr>
        <th width="10%" class="style10"><br><br><br><br><br></th>
    </tr>
    @for($no = 1; $no <= $br; $no++)
        <tr>
            <th width="10%" class="style9">&nbsp;</th>
        </tr>
    @endfor
    <tr>
        <td width="12%" class="style9 align-center">{{ $transaction['transaction_date'] }}</td>
        <td width="5%" class="style9 align-center">{{ $kode }}</td>
        <td width="16%" class="style9 align-center">{{ number_format($debit, 2, '.', ',') }}</td>
        <td width="16%" class="style9 align-center">{{ number_format($kredit, 2, '.', ',') }}</td>
        <td width="16%" class="style9 align-center">{{ number_format($saldo, 2, '.', ',') }}</td>
        <td width="10%" class="style9 align-center">{{ strtoupper($user) }}-{{ $transaction['row_id'] }}</td>
        <td width="25%" class="style9 align-center">&nbsp;</td>
    </tr>
</table>
</body>
</html>
