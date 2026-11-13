@php
    $identity = $identity ?? [];
    $entry = $entry ?? [];
    $loan = $loan ?? null;
    $payer = $payer ?? null;
    $amounts = $amounts ?? [];
    $schedule = $schedule ?? [];
    $legalName = strtoupper((string) ($identity['legal_name'] ?? config('app.name')));
    $address = (string) ($identity['address'] ?? '');
    $phone = (string) ($identity['phone'] ?? '');
    $dateLabel = $entry['transaction_date']
        ? \Carbon\CarbonImmutable::parse($entry['transaction_date'])->format('d/m/Y')
        : '-';
    $number = $entry['journal_number'] ?: ($entry['id'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>STRUK ANGSURAN</title>
    <style>
        @page { size: A5 landscape; margin: 8mm; }
        * { font-family: 'Courier New', Courier, monospace; }
        html, body { margin: 0; padding: 0; color: #000; }
        body { font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 3px; vertical-align: top; }
        .b { border: 1px solid #000; }
        .line { border-bottom: 1px solid #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .small { font-size: 7px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="6" class="center line">
                <div class="bold">{{ $legalName }}</div>
                <div>{{ $address }}@if ($address !== '' && $phone !== '') · @endif{{ $phone }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="6" class="center line"><span class="bold" style="font-size: 11px;">BUKTI ANGSURAN</span></td>
        </tr>
        <tr>
            <td width="16%">Kode Transaksi</td><td width="18%">: {{ $number }}</td>
            <td width="16%">Tanggal</td><td width="18%">: {{ $dateLabel }}</td>
            <td width="14%">Angsuran ke</td><td>: {{ $schedule['installment_number'] ?? 1 }} / {{ $schedule['installment_total'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Kelompok</td><td colspan="3">: {{ $loan['group_name'] ?? '—' }}</td>
            <td>Alokasi Pokok</td><td class="right">{{ number_format((float) ($schedule['loan_amount'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Alamat</td><td colspan="3">: {{ $loan['group_address'] ?? '—' }}</td>
            <td>Alokasi Jasa</td><td class="right">{{ number_format((float) ($schedule['loan_interest'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Penyetor</td><td colspan="3">: {{ $payer['name'] ?? '—' }}</td>
            <td>Target Pokok</td><td class="right">{{ number_format((float) ($schedule['target_principal'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Pinjaman</td><td colspan="3">: #{{ $loan['id'] ?? '—' }} {{ $loan['loan_number'] ?? '' }} {{ $loan['product_code'] ?? '' }}</td>
            <td>Target Jasa</td><td class="right">{{ number_format((float) ($schedule['target_interest'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Realisasi Pokok</td><td class="right">{{ number_format((float) ($amounts['principal'] ?? 0), 2) }}</td>
            <td>Realisasi Jasa</td><td class="right">{{ number_format((float) ($amounts['interest'] ?? 0), 2) }}</td>
            <td>Saldo Pokok</td><td class="right">{{ number_format((float) ($schedule['saldo_principal'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Denda</td><td class="right">{{ number_format((float) ($amounts['penalty'] ?? 0), 2) }}</td>
            <td class="bold">Jumlah Bayar</td><td class="right bold">{{ number_format((float) ($amounts['total'] ?? 0), 2) }}</td>
            <td>Saldo Jasa</td><td class="right">{{ number_format((float) ($schedule['saldo_interest'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="4" class="small">
                Terbilang: <span class="bold">{{ strtoupper((string) ($schedule['terbilang'] ?? '')) }}</span>
            </td>
            <td>Tunggakan</td><td class="right">{{ number_format((float) ($schedule['tunggakan_principal'] ?? 0) + (float) ($schedule['tunggakan_interest'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="4" class="small">Lembar 1 untuk Kelompok, lembar 2 Arsip UPK.</td>
            <td>Tagihan M+1</td><td class="right">{{ number_format((float) ($schedule['total_tagihan'] ?? 0), 2) }}</td>
        </tr>
        <tr><td colspan="6" height="18"></td></tr>
        <tr>
            <td colspan="2" class="center">Diterima Oleh</td>
            <td colspan="2" class="center">Penyetor</td>
            <td colspan="2" class="center">Petugas</td>
        </tr>
        <tr><td colspan="6" height="30"></td></tr>
        <tr>
            <td colspan="2" class="center line">{{ $payer['name'] ?? '…………' }}</td>
            <td colspan="2" class="center line">{{ $payer['name'] ?? '…………' }}</td>
            <td colspan="2" class="center line">…………</td>
        </tr>
    </table>
</body>
</html>
