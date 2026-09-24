@php
    /** @var array<string, mixed> $identity */
    /** @var array<string, mixed> $invoice */
    /** @var array<string, mixed>|null $payer */
    /** @var list<array<string, mixed>> $items */
    /** @var array<string, float> $totals */
    /** @var array<string, mixed> $payment */
    /** @var string|null $notes */

    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $shortName = strtoupper($identity['short_name'] ?? $legalName);
    $address = $identity['address'] ?? '';
    $phone = $identity['phone'] ?? '';
    $email = $identity['email'] ?? '';
    $registration = $identity['registration_number'] ?? '';
    $taxNumber = $identity['tax_number'] ?? '';
    $logoUrl = $identity['logo_url'] ?? null;

    $infoLine = $address;
    if ($address !== '' && $phone !== '') {
        $infoLine .= ', Telp. ' . $phone;
    } elseif ($phone !== '') {
        $infoLine = 'Telp. ' . $phone;
    }

    $fmt = static function (float|int|null $v): string {
        $v = (float) ($v ?? 0);

        return $v < 0
            ? '('.number_format(abs($v), 2).')'
            : number_format($v, 2);
    };

    $terbilang = static function (float $n): string {
        // Indonesian number-to-words — pragmatic version (rupiah)
        $n = round($n, 0);
        if ($n <= 0) return 'nol rupiah';

        $units = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $say = static function (int $n) use (&$say, $units): string {
            if ($n < 12) return $units[$n];
            if ($n < 20) return $units[$n - 10].' belas';
            if ($n < 100) return $units[intdiv($n, 10)].' puluh'.($n % 10 ? ' '.$units[$n % 10] : '');
            if ($n < 200) return 'seratus '.($n - 100 > 0 ? $say($n - 100) : '');
            if ($n < 1000) return $units[intdiv($n, 100)].' ratus'.($n % 100 ? ' '.$say($n % 100) : '');
            if ($n < 2000) return 'seribu '.($n - 1000 > 0 ? $say($n - 1000) : '');
            if ($n < 1_000_000) return $say(intdiv($n, 1000)).' ribu'.($n % 1000 ? ' '.$say($n % 1000) : '');
            if ($n < 1_000_000_000) return $say(intdiv($n, 1_000_000)).' juta'.($n % 1_000_000 ? ' '.$say($n % 1_000_000) : '');
            return $say(intdiv($n, 1_000_000_000)).' miliar'.($n % 1_000_000_000 ? ' '.$say($n % 1_000_000_000) : '');
        };

        return trim($say((int) $n)).' rupiah';
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Invoice {{ $invoice['number'] ?? '' }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 50px; }
        body { font-size: 11px; color: #111; }
        table { border-collapse: collapse; }
        table tr th, table tr td { padding: 4px 6px; vertical-align: top; }
        .title { font-size: 28px; font-weight: bold; color: rgb(21, 85, 92); letter-spacing: 2px; }
        .subtitle { font-size: 11px; font-style: italic; color: #555; }
        .heading-bar { background: rgb(21, 85, 92); color: #fff; font-weight: bold; padding: 4px 8px; }
        .table-items th { background: rgb(232, 232, 232); font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000; }
        .table-items td { border-bottom: 1px dotted #999; }
        .total-bar { background: rgb(21, 85, 92); color: #fff; font-weight: bold; }
        .signature { border-top: 1px solid #000; min-height: 60px; }
        .info-row { font-size: 10px; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
    </style>
</head>
<body>
    <table border="0" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="58%" valign="top">
                <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        @if (! empty($logoUrl))
                            <td width="80" valign="top" style="padding-right: 8px;">
                                <img src="{{ $logoUrl }}" width="80" alt="Logo" />
                            </td>
                        @endif
                        <td valign="top">
                            <div style="font-size: 16px; font-weight: bold;">{{ $legalName }}</div>
                            <div class="info-row">{{ $infoLine }}</div>
                            @if($email !== '')
                                <div class="info-row">{{ $email }}</div>
                            @endif
                            @if($registration !== '')
                                <div class="info-row">SK Kemenkumham RI No. {{ $registration }}</div>
                            @endif
                            @if($taxNumber !== '')
                                <div class="info-row">NPWP: {{ $taxNumber }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td width="42%" valign="top" align="right">
                <div class="title">INVOICE</div>
                <div class="subtitle">No. {{ $invoice['number'] ?? '' }}</div>
                <table border="0" cellspacing="0" cellpadding="0" style="margin-top: 10px; font-size: 11px;">
                    <tr>
                        <td>Tanggal</td>
                        <td>: <b>{{ \Carbon\Carbon::parse($invoice['date'] ?? '')->format('d/m/Y') }}</b></td>
                    </tr>
                    <tr>
                        <td>Jatuh Tempo</td>
                        <td>: <b>{{ \Carbon\Carbon::parse($invoice['due_date'] ?? '')->format('d/m/Y') }}</b></td>
                    </tr>
                    @if(!empty($invoice['reference']))
                        <tr>
                            <td>Referensi</td>
                            <td>: {{ $invoice['reference'] }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div style="height: 8px;"></div>

    <table border="0" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="58%" valign="top">
                <div class="heading-bar">KEPADA YTH.</div>
                <table border="0" cellspacing="0" cellpadding="0" style="margin-top: 6px;">
                    <tr>
                        <td><b>{{ $payer['name'] ?? '—' }}</b></td>
                    </tr>
                    @if(!empty($payer['identity_number']))
                        <tr>
                            <td class="info-row">{{ $payer['identity_number'] }}</td>
                        </tr>
                    @endif
                    @if(!empty($payer['address']))
                        <tr>
                            <td class="info-row">{{ $payer['address'] }}</td>
                        </tr>
                    @endif
                    @if(!empty($payer['phone']))
                        <tr>
                            <td class="info-row">{{ $payer['phone'] }}</td>
                        </tr>
                    @endif
                </table>
            </td>
            <td width="42%" valign="top">
                <div class="heading-bar">PERIHAL</div>
                <div style="margin-top: 6px;">{{ $invoice['subject'] ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <div style="height: 10px;"></div>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" class="table-items">
        <thead>
            <tr>
                <th width="5%" align="center">No</th>
                <th align="left">Uraian</th>
                <th width="10%" align="center">Qty</th>
                <th width="20%" align="right">Harga</th>
                <th width="22%" align="right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse($items as $item)
                <tr>
                    <td align="center" height="20">{{ $no++ }}</td>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td align="center">{{ rtrim(rtrim(number_format((float) ($item['qty'] ?? 0), 2), '0'), '.') }}</td>
                    <td align="right">{{ $fmt($item['unit_price'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($item['amount'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" align="center" height="22" style="color: #555;">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @php
        $grand = (float) ($totals['grand_total'] ?? 0);
        $ppn = (float) ($totals['ppn'] ?? 0);
        $subtotal = (float) ($totals['subtotal'] ?? 0);
        $paid = (float) ($totals['paid'] ?? 0);
        $due = (float) ($totals['due'] ?? $grand);
    @endphp

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="margin-top: 6px;">
        <tr>
            <td width="60%" valign="top" style="padding-right: 12px;">
                <table border="0" cellspacing="0" cellpadding="0" style="font-size: 10px;">
                    <tr>
                        <td><b>Terbilang:</b></td>
                    </tr>
                    <tr>
                        <td style="font-style: italic; text-transform: capitalize;">"{{ $terbilang($grand) }}"</td>
                    </tr>
                </table>
            </td>
            <td width="40%" valign="top">
                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                    <tr>
                        <td>Subtotal</td>
                        <td align="right">{{ $fmt($subtotal) }}</td>
                    </tr>
                    @if($ppn > 0)
                        <tr>
                            <td>PPN</td>
                            <td align="right">{{ $fmt($ppn) }}</td>
                        </tr>
                    @endif
                    <tr class="total-bar">
                        <td height="22" style="padding: 4px 8px;">TOTAL</td>
                        <td align="right" style="padding: 4px 8px;">Rp {{ $fmt($grand) }}</td>
                    </tr>
                    @if($paid > 0)
                        <tr>
                            <td>Sudah Dibayar</td>
                            <td align="right">{{ $fmt($paid) }}</td>
                        </tr>
                        <tr>
                            <td><b>Sisa Tagihan</b></td>
                            <td align="right"><b>{{ $fmt($due) }}</b></td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div style="height: 10px;"></div>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px;">
        <tr>
            <td valign="top" width="55%">
                <div class="heading-bar">CARA PEMBAYARAN</div>
                <table border="0" cellspacing="0" cellpadding="2" style="margin-top: 6px;">
                    <tr>
                        <td valign="top">Metode</td>
                        <td valign="top">: {{ $payment['method'] ?? '—' }}</td>
                    </tr>
                    @if(!empty($payment['bank_account']))
                        <tr>
                            <td valign="top">Rekening</td>
                            <td valign="top">: {{ $payment['bank_account'] }}</td>
                        </tr>
                    @endif
                    @if(!empty($payment['instructions']))
                        <tr>
                            <td valign="top">Instruksi</td>
                            <td valign="top">: {{ $payment['instructions'] }}</td>
                        </tr>
                    @endif
                    @if($notes !== null && $notes !== '')
                        <tr>
                            <td valign="top">Catatan</td>
                            <td valign="top">: {{ $notes }}</td>
                        </tr>
                    @endif
                </table>
            </td>
            <td valign="top" width="45%" align="center">
                <div style="font-size: 10px;">{{ \Carbon\Carbon::parse($invoice['date'] ?? '')->translatedFormat('d F Y') }}</div>
                <div style="font-size: 10px; font-weight: bold; margin-top: 2px;">{{ $shortName }}</div>
                <div style="height: 56px;"></div>
                <div class="signature" style="display: inline-block; min-width: 220px; padding: 4px 12px 0;">
                    <b><u>{{ $identity['treasurer_name'] ?? 'Bendahara' }}</u></b>
                </div>
                <div style="font-size: 9px; color: #555; margin-top: 2px;">
                    {{ $identity['treasurer_title'] ?? 'Bendahara' }}
                </div>
            </td>
        </tr>
    </table>

    <div style="height: 12px;"></div>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; color: #555;">
        <tr>
            <td align="center" style="border-top: 1px solid #000; padding-top: 4px;">
                Invoice ini diterbitkan otomatis oleh Sistem Informasi BUMDesma — SIUPKNext.
                Keaslian dokumen dapat dikonfirmasi melalui kantor layanan.
            </td>
        </tr>
    </table>
</body>
</html>
