@php
    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $district = $identity['district_name'] ?? '';
    $regency = $identity['regency_name'] ?? '';
    $locationLine = trim(($district !== '' ? 'Kec. ' . $district : '') . ' ' . ($regency !== '' ? 'Kab. ' . $regency : ''));
    $registration = $identity['registration_number'] ?? '';
    $address = $identity['address'] ?? '';
    $phone = $identity['phone'] ?? '';
    $logoUrl = $identity['logo_url'] ?? null;

    $infoLine = $address;
    if ($address !== '' && $phone !== '') {
        $infoLine .= ', Telp. ' . $phone;
    } elseif ($phone !== '') {
        $infoLine = 'Telp. ' . $phone;
    }

    $schedule = $schedule ?? [];
    $installmentNumber = (int) ($schedule['installment_number'] ?? 1);
    $installmentTotal = (int) ($schedule['installment_total'] ?? 0);
    $loanAmount = (float) ($schedule['loan_amount'] ?? 0);
    $loanInterest = (float) ($schedule['loan_interest'] ?? 0);
    $targetPrincipal = (float) ($schedule['target_principal'] ?? 0);
    $targetInterest = (float) ($schedule['target_interest'] ?? 0);
    $nextPrincipal = (float) ($schedule['next_principal'] ?? 0);
    $nextInterest = (float) ($schedule['next_interest'] ?? 0);
    $tunggakanPrincipal = (float) ($schedule['tunggakan_principal'] ?? 0);
    $tunggakanInterest = (float) ($schedule['tunggakan_interest'] ?? 0);
    $saldoPrincipal = (float) ($schedule['saldo_principal'] ?? 0);
    $saldoInterest = (float) ($schedule['saldo_interest'] ?? 0);
    $totalTagihan = (float) ($schedule['total_tagihan'] ?? 0);
    $terbilang = (string) ($schedule['terbilang'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Bukti Angsuran #{{ $entry['id'] }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75px; margin-left: 94px; }
        body { font-size: 11px; color: #111; }
        table { border-collapse: collapse; }
        table tr th, table tr td { padding: 2px 4px; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
    </style>
</head>
<body>
    <table width="100%" style="border-bottom: 1px solid grey;">
        <tr>
            @if (! empty($logoUrl))
                <td width="30">
                    <img src="{{ $logoUrl }}" width="40" alt="Logo">
                </td>
            @endif
            <td>
                <div style="font-size: 12px;">{{ $legalName }}</div>
                <div style="font-size: 12px;">
                    <b>{{ strtoupper($locationLine) }}</b>
                </div>
            </td>
        </tr>
    </table>
    <table width="100%" style="position: relative; top: -10px;">
        <tr>
            <td>
                <span style="font-size: 8px; color: grey;">
                    <i>{{ $registration !== '' ? 'SK Kemenkumham RI No. ' . $registration : '' }}</i>
                </span>
            </td>
            <td align="right">
                <span style="font-size: 8px; color: grey;">
                    <i>{{ $infoLine }}</i>
                </span>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td colspan="2" align="center">
                <div style="font-size: 18px;"><b>BUKTI PENERIMAAN ANGSURAN</b></div>
                <div style="font-size: 12px;">
                    No. {{ $entry['journal_number'] ?: $entry['id'] }}
                    &middot; {{ \Carbon\CarbonImmutable::parse($entry['transaction_date'])->format('d/m/Y') }}
                </div>
            </td>
        </tr>
        <tr><td colspan="2" height="10"></td></tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; border: 1px solid #999; margin-bottom: 10px;">
        <tr>
            <td width="25%" style="padding: 4px 6px; color: #555;">Pinjaman</td>
            <td style="padding: 4px 6px;">
                @if($loan)
                    #{{ $loan['id'] }}
                    @if($loan['loan_number']) &middot; {{ $loan['loan_number'] }}@endif
                    @if($loan['product_code']) &middot; {{ strtoupper($loan['product_code']) }}@endif
                @else
                    &mdash;
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Kelompok</td>
            <td style="padding: 4px 6px;">{{ $loan['group_name'] ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Penyetor</td>
            <td style="padding: 4px 6px;">{{ $payer['name'] ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Keterangan</td>
            <td style="padding: 4px 6px;">{{ $entry['description'] ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Angsuran ke</td>
            <td style="padding: 4px 6px;">{{ $installmentNumber }} dari {{ $installmentTotal > 0 ? $installmentTotal : '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Alokasi pinjaman</td>
            <td style="padding: 4px 6px;">Pokok {{ number_format($loanAmount, 2) }} &middot; Jasa {{ number_format($loanInterest, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Target pengembalian</td>
            <td style="padding: 4px 6px;">Pokok {{ number_format($targetPrincipal, 2) }} &middot; Jasa {{ number_format($targetInterest, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Saldo pinjaman</td>
            <td style="padding: 4px 6px;">Pokok {{ number_format($saldoPrincipal, 2) }} &middot; Jasa {{ number_format($saldoInterest, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Tunggakan s.d. bulan ini</td>
            <td style="padding: 4px 6px;">Pokok {{ number_format($tunggakanPrincipal, 2) }} &middot; Jasa {{ number_format($tunggakanInterest, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Tagihan bulan depan</td>
            <td style="padding: 4px 6px;">Pokok {{ number_format($nextPrincipal, 2) }} &middot; Jasa {{ number_format($nextInterest, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Total tagihan (M+1)</td>
            <td style="padding: 4px 6px;"><b>{{ number_format($totalTagihan, 2) }}</b></td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr style="background: rgb(232, 232, 232); font-weight: bold;">
            <td height="20">Rincian</td>
            <td align="right">Nominal</td>
        </tr>
        <tr style="background: rgb(230, 230, 230);">
            <td>Pokok</td>
            <td align="right">{{ number_format($amounts['principal'], 2) }}</td>
        </tr>
        <tr style="background: rgba(255, 255, 255);">
            <td>Jasa</td>
            <td align="right">{{ number_format($amounts['interest'], 2) }}</td>
        </tr>
        <tr style="background: rgb(230, 230, 230);">
            <td>Denda</td>
            <td align="right">{{ number_format($amounts['penalty'], 2) }}</td>
        </tr>
        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
            <td>Total diterima</td>
            <td align="right">Rp {{ number_format($amounts['total'], 2) }}</td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 14px;">
        <tr>
            <td style="font-size: 10px; color: #666; padding-bottom: 4px;">Jurnal:</td>
        </tr>
    </table>
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
            <td height="20">Akun</td>
            <td align="right">Debit</td>
            <td align="right">Kredit</td>
        </tr>
        @foreach($lines as $line)
            <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td>{{ $line['account_code'] }} &middot; {{ $line['account_name'] }}</td>
                <td align="right">{{ $line['debit'] > 0 ? number_format($line['debit'], 2) : '' }}</td>
                <td align="right">{{ $line['credit'] > 0 ? number_format($line['credit'], 2) : '' }}</td>
            </tr>
        @endforeach
    </table>

    @if ($terbilang !== '')
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 10px;">
            <tr>
                <td style="padding: 2px 4px; color: #555;">Terbilang</td>
                <td style="padding: 2px 4px; font-weight: bold; text-transform: uppercase;">{{ $terbilang }}</td>
            </tr>
        </table>
    @endif

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 28px;">
        <tr>
            <td width="50%" align="center">Penyetor</td>
            <td width="50%" align="center">Petugas</td>
        </tr>
        <tr>
            <td align="center" height="60"></td>
            <td align="center" height="60"></td>
        </tr>
        <tr>
            <td align="center">( {{ $payer['name'] ?? '……………' }} )</td>
            <td align="center">( …………… )</td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; color: #666; margin-top: 16px;">
        <tr>
            <td align="center">
                Dicetak {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                &middot; ID jurnal {{ $entry['id'] }}
                <br>
                Lembar 1 untuk Kelompok, lembar 2 Arsip UPK.
                <br>
                Bawalah kartu angsuran dan slip ini pada saat mengangsur bulan depan.
            </td>
        </tr>
    </table>
</body>
</html>