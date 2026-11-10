<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        .style6 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 16px; color: #000000; }
        .style9 { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000000; }
        .style10 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; color: #000000; }
        .style26 { font-family: Verdana, Arial, Helvetica, sans-serif; color: #000000; }
        .style27 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px; font-weight: bold; color: #000000; }
        .top { border-top: 1px solid #000000; }
        .bottom { border-bottom: 1px solid #000000; }
        .left { border-left: 1px solid #000000; }
        .right { border-right: 1px solid #000000; }
        .all { border: 1px solid #000000; }
        .align-justify { text-align: justify; }
        .align-center { text-align: center; }
        .align-right { text-align: right; }
    </style>
</head>
<body onload="window.print()">
@php
    $address = trim((string) ($account['member']['address'] ?? ''));
    $village = trim((string) ($account['member']['village'] ?? ''));
    $fullAddress = $village !== '' && ! str_contains($address, $village)
        ? trim($address.' '.$village)
        : $address;
@endphp
<table width="100%" border="0" align="center" cellpadding="3" cellspacing="0" style="margin-top:10px;">
    <tr>
        <td class="style9">&nbsp;<br>&nbsp;<br></td>
    </tr>
    <tr>
        <td colspan="2" class="style9">&nbsp;</td>
        <td width="10%" class="style9 align-right">&nbsp;</td>
        <td width="30%" class="style9">&nbsp;</td>
    </tr>
    <tr>
        <td class="style9">Rekening</td>
        <td class="style9">: {{ $account['account_number'] }}</td>
        <td width="10%" class="style9 align-right"><strong>CIF - {{ $account['row_id'] }}</strong></td>
    </tr>
    <tr>
        <td width="20%" class="style9">Nama Nasabah</td>
        <td colspan="2" class="style9">: {{ $account['member']['name'] }}</td>
    </tr>
    <tr>
        <td class="style9">Alamat</td>
        <td colspan="2" class="style9">: {{ $fullAddress }}</td>
    </tr>
    <tr>
        <td class="style9">Telpon</td>
        <td colspan="2" height="5" class="style9">: {{ $account['member']['phone'] }}</td>
    </tr>
    <tr>
        <td class="style9">Jenis Simpanan</td>
        <td colspan="2" class="style9">: {{ $account['product']['name'] }}</td>
    </tr>
</table>
</body>
</html>
