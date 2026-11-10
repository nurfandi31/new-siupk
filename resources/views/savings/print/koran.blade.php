<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        .style6 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 16px; color: #000000; }
        .style9 { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000000; }
        .style10 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; color: #000000; }
        .style27 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px; font-weight: bold; color: #000000; }
        .top { border-top: 1px solid #000000; }
        .bottom { border-bottom: 1px solid #000000; }
        .left { border-left: 1px solid #000000; }
        .right { border-right: 1px solid #000000; }
        .all { border: 1px solid #000000; }
        .align-justify { text-align: justify; }
        .align-center { text-align: center; padding-left: 5px; padding-right: 5px; }
        .align-right { text-align: right; padding-left: 5px; padding-right: 5px; }
        .align-left { text-align: left; padding-left: 5px; padding-right: 5px; }
    </style>
</head>
<body onload="window.print()">
@php
    $member = $account['member'];
    $isProgram = (string) ($account['product']['code'] ?? '') === '3';
@endphp
<table width="97%" border="0" align="center" cellpadding="3" cellspacing="0">
    <tr>
        <td height="20" colspan="2" class="bottom">
            <div class="style9 text-align-left">
                {{ $organization['legal_name'] }}@if($organization['district_name']) Kecamatan {{ $organization['district_name'] }}@endif<br>
                {{ $organization['address'] }}
                @if($organization['phone']) <span>&#9742; {{ $organization['phone'] }}</span> @endif
            </div>
        </td>
        <td height="20" colspan="2" class="">
            <div align="right" class="style9">Dokumen Simpanan<br>
                CIF - {{ $account['row_id'] }}
            </div>
        </td>
    </tr>
    <tr>
        <td height="50" colspan="4" class="">
            <p align="center" class="style6">REKENING KORAN {{ strtoupper($account['product']['name']) }}<br>{{ $statement['period_label'] }}</p>
        </td>
    </tr>
    <tr>
        <td width="19%" height="20" class="style9">Nomor Rekening</td>
        <td width="32%" height="20"><span class="style9">:</span><span class="style27"> {{ strtoupper($account['account_number']) }}</span></td>
    </tr>
    <tr>
        <td height="20" class="style9">CIF / NIK</td>
        <td height="20"><span class="style9">:</span><span class="style27"> {{ $account['row_id'] }} / {{ $member['nik'] ?? '' }}</span></td>
    </tr>
    <tr>
        <td width="19%" height="20" class="style9">Nama Kreditur</td>
        <td width="42%" height="20"><span class="style9">:</span><span class="style27"> {{ $member['name'] }}</span></td>
    </tr>
    <tr>
        <td height="20" class="style9">Tanggal Buka</td>
        <td height="20"><span class="style9">:</span><span class="style27"> {{ $account['opened_at'] }}</span></td>
    </tr>
    @if($isProgram)
    <tr>
        <td height="20" class="style9">Jenis Program</td>
        <td height="20"><span class="style9">:</span><span class="style27"> {{ $account['notes'] }}</span></td>
    </tr>
    @endif
    <tr>
        <td height="20" class="style9">Alamat</td>
        <td height="20"><span class="style9">:</span><span class="style27"> {{ $member['address'] }}</span></td>
    </tr>
    <tr>
        <td height="20" class="style9">Telpon</td>
        <td height="20"><span class="style9">:</span><span class="style27"> {{ $member['phone'] }}</span></td>
    </tr>
</table>
<table width="96%" border="0" align="center" cellpadding="3" cellspacing="0">
    <tr>
        <th width="4%" height="30" class="align-center">NO</th>
        <th width="8%" class="align-center">TANGGAL</th>
        <th width="6%" class="align-center">REF</th>
        <th width="40%" class="align-center">KETERANGAN</th>
        <th width="10%" class="align-center">DEBIT</th>
        <th width="10%" class="align-center">KREDIT</th>
        <th width="10%" class="align-center">SALDO</th>
        <th width="2%" class="align-center">P</th>
    </tr>
    @if (($statement['all_time'] ?? false) && ($statement['month'] ?? 0) > 0)
    <tr>
        <th colspan="9" class="text-center"><strong>mohon untuk memilih tahun juga</strong></th>
    </tr>
    @elseif (! ($statement['all_time'] ?? false))
    <tr>
        <th colspan="3">&nbsp;</th>
        <th colspan="3" class="text-left">
            <strong>
                @if ($statement['month'] > 0)
                    Saldo Sebelum {{ $statement['period_label'] }}
                @else
                    Saldo Sebelum {{ $statement['year'] }}
                @endif
            </strong>
        </th>
        <th class="style9 align-right">{{ number_format($statement['opening_balance'], 2, '.', ',') }}</th>
        <th colspan="2">&nbsp;</th>
    </tr>
    @endif
    @forelse($statement['transactions'] as $index => $trx)
        <tr>
            <td width="4%" height="30" class="style9">{{ $index + 1 }}</td>
            <td width="15%" class="style9 align-center">{{ $trx['transaction_date'] }}</td>
            <td width="6%" class="style9 align-center">{{ $trx['row_id'] }}</td>
            <td width="35%" class="style9">{{ $trx['description'] }}</td>
            <td width="10%" class="style9 align-right">{{ $trx['direction'] === 'out' ? number_format($trx['amount'], 2, '.', ',') : '0.00' }}</td>
            <td width="10%" class="style9 align-right">{{ $trx['direction'] === 'in' ? number_format($trx['amount'], 2, '.', ',') : '0.00' }}</td>
            <td width="10%" class="style9 align-right">{{ number_format($trx['balance_after'], 2, '.', ',') }}</td>
            <td width="2%" class="style9 align-center">{{ $trx['created_by_id'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="9" class="text-center">Tidak ada transaksi di periode ini</td>
        </tr>
    @endforelse
    <tr>
        <td colspan="6" class="text-center"><b>TOTAL SALDO</b></td>
        <td class="style9 align-right"><b>{{ number_format($statement['closing_balance'], 2, '.', ',') }}</b></td>
        <td colspan="2">&nbsp;</td>
    </tr>
</table>
</body>
</html>
