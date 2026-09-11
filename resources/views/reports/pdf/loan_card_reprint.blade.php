@php
    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $district = $identity['district_name'] ?? '';
    $phone = $identity['phone'] ?? '';
    $barisAngsuran = max(1, (int) ceil(count($rows) / 2));
    $rowCount = count($rows);
    $rowspan = $rowCount > 16 ? $rowCount + 3 : 19;
    $targetNumber = request()->query('ke') ? (int) request()->query('ke') : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Cetak Pada Kartu Angsuran #{{ $loan['id'] }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin-bottom: 100px; }
        ul, ol { margin-left: -10px; page-break-inside: auto !important; }
        table tr th, table tr td { padding: 2px 4px; font-size: 11px; }
        .invisible-text { opacity: 0; }
        .visible-text { opacity: 1; }
    </style>
</head>
<body onload="window.print()">
    <main style="position: relative; font-size: 12px;">
        <!-- Header area disembunyikan karena sudah ada cetakan bawaan pada kertas kartu -->
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; opacity: 0;">
            <tr>
                <td rowspan="7" align="center" width="400">
                    <div style="font-size: 14px; font-weight: bold;">{{ $legalName }}</div>
                    <div>Telp. {{ $phone }}</div>
                </td>
                <td width="150">Jenis Piutang</td>
                <td width="5">:</td>
                <td width="200">{{ strtoupper($loan['product_code'] ?? '-') }}</td>
                <td width="150">Loan Id.</td>
                <td width="5">:</td>
                <td width="200">{{ $loan['id'] }}</td>
            </tr>
            <tr>
                <td>Nama Kelompok</td>
                <td>:</td>
                <td colspan="4">{{ $loan['group_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Alamat</td>
                <td>:</td>
                <td colspan="4">{{ $loan['group_address'] ?? '-' }}</td>
            </tr>
            <tr><td colspan="6" height="50">&nbsp;</td></tr>
        </table>

        <!-- Tabel realisasi baris yang ingin dicetak ulang pada kartu fisik -->
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 140px;">
            @php $paidCount = 0; @endphp
            @foreach ($rows as $r)
                @if (($r['principal_paid'] + $r['interest_paid']) > 0)
                    @php
                        $paidCount++;
                        $nomor = $paidCount;
                        $isVisible = ($targetNumber === null || $targetNumber === (int) $r['installment_number']);
                    @endphp
                    <tr class="{{ $isVisible ? 'visible-text' : 'invisible-text' }}">
                        <td width="3%" align="center">{{ $nomor }}</td>
                        <td width="10%" align="center">{{ $r['due_date'] ? \Carbon\CarbonImmutable::parse($r['due_date'])->translatedFormat('d F Y') : '-' }}</td>
                        <td width="12%" align="right">{{ number_format($r['principal_paid'], 2) }}</td>
                        <td width="10%" align="right">{{ number_format($r['principal_remaining'], 2) }}</td>
                        <td width="12%" align="right">{{ number_format($r['interest_paid'], 2) }}</td>
                        <td width="10%" align="right">{{ number_format($r['interest_remaining'], 2) }}</td>
                        <td width="11%" align="right">{{ number_format($r['principal_remaining'], 2) }}</td>
                        <td width="11%" align="right">{{ number_format($r['interest_remaining'], 2) }}</td>
                        <td width="11%" align="center">TRX-{{ $r['installment_number'] }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    </main>
</body>
</html>