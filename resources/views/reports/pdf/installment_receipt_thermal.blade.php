@php
    $identity = $identity ?? [];
    $entry = $entry ?? [];
    $loan = $loan ?? null;
    $payer = $payer ?? null;
    $amounts = $amounts ?? [];
    $schedule = $schedule ?? [];
    $paper = (string) ($paper ?? '80');
    $is58 = $paper === '58';
    $legalName = strtoupper((string) ($identity['legal_name'] ?? config('app.name')));
    $address = (string) ($identity['address'] ?? '');
    $phone = (string) ($identity['phone'] ?? '');
    $dateLabel = $entry['transaction_date']
        ? \Carbon\CarbonImmutable::parse($entry['transaction_date'])->format('d/m/Y')
        : '-';
    $number = $entry['journal_number'] ?: ($entry['id'] ?? '');
    $groupId = $loan['group_name'] ?? '—';
    $groupIdLabel = $loan['group_address'] ?? '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BUKTI ANGSURAN</title>
    <style>
        @page { size: {{ $is58 ? '58mm 68mm' : '80mm 90mm' }}; margin: 2mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
        body { font-size: {{ $is58 ? '6px' : '7px' }}; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 1px 0; vertical-align: top; }
        .center { text-align: center; }
        .right { text-align: right; }
        .line { border-bottom: 1px dashed #000; }
        .title { font-size: {{ $is58 ? '9px' : '11px' }}; font-weight: bold; }
        .small { font-size: {{ $is58 ? '5px' : '6px' }}; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td class="center line" colspan="2">
                <div class="bold">{{ $legalName }}</div>
                @if ($address !== '')<div>{{ $address }}</div>@endif
                @if ($phone !== '')<div>Telp. {{ $phone }}</div>@endif
            </td>
        </tr>
        <tr><td class="center line" colspan="2"><span class="title">BUKTI ANGSURAN</span></td></tr>
        <tr><td>No.</td><td>: {{ $number }}</td></tr>
        <tr><td>Tanggal</td><td>: {{ $dateLabel }}</td></tr>
        <tr><td>Kelompok</td><td>: {{ $groupId }}</td></tr>
        @if ($groupIdLabel !== '')
            <tr><td>Alamat</td><td>: {{ $groupIdLabel }}</td></tr>
        @endif
        <tr><td>Penyetor</td><td>: {{ $payer['name'] ?? '—' }}</td></tr>
        <tr><td>Angsuran ke</td><td>: {{ $schedule['installment_number'] ?? 1 }} dari {{ $schedule['installment_total'] ?? 0 }}</td></tr>
        <tr><td colspan="2" height="3"></td></tr>
        <tr class="bold"><td>Uraian</td><td class="right">Jumlah</td></tr>
        <tr><td>Pokok</td><td class="right">{{ number_format((float) ($amounts['principal'] ?? 0), 2) }}</td></tr>
        <tr><td>Jasa</td><td class="right">{{ number_format((float) ($amounts['interest'] ?? 0), 2) }}</td></tr>
        <tr><td>Denda</td><td class="right">{{ number_format((float) ($amounts['penalty'] ?? 0), 2) }}</td></tr>
        <tr class="bold"><td>Jumlah bayar</td><td class="right">{{ number_format((float) ($amounts['total'] ?? 0), 2) }}</td></tr>
        <tr><td colspan="2" height="3"></td></tr>
        <tr><td>Saldo pokok</td><td class="right">{{ number_format((float) ($schedule['saldo_principal'] ?? 0), 2) }}</td></tr>
        <tr><td>Saldo jasa</td><td class="right">{{ number_format((float) ($schedule['saldo_interest'] ?? 0), 2) }}</td></tr>
        <tr><td>Tunggakan</td><td class="right">{{ number_format((float) ($schedule['tunggakan_principal'] ?? 0) + (float) ($schedule['tunggakan_interest'] ?? 0), 2) }}</td></tr>
        <tr><td>Tagihan M+1</td><td class="right">{{ number_format((float) ($schedule['total_tagihan'] ?? 0), 2) }}</td></tr>
        <tr><td colspan="2" height="3"></td></tr>
        <tr><td colspan="2" class="small">Terbilang: <span class="bold">{{ strtoupper((string) ($schedule['terbilang'] ?? '')) }}</span></td></tr>
        <tr><td colspan="2" height="10"></td></tr>
        <tr>
            <td class="center">Penyetor</td>
            <td class="center">Petugas</td>
        </tr>
        <tr><td colspan="2" height="22"></td></tr>
        <tr>
            <td class="center">( {{ $payer['name'] ?? '…………' }} )</td>
            <td class="center">( ………… )</td>
        </tr>
        <tr><td colspan="2" class="center small">Lembar 1 Kelompok · Lembar 2 Arsip UPK</td></tr>
    </table>
</body>
</html>
