@php
    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $district = $identity['district_name'] ?? '';
    $regency = $identity['regency_name'] ?? '';
    $registration = $identity['registration_number'] ?? '';
    $address = $identity['address'] ?? '';
    $phone = $identity['phone'] ?? '';
    $logoUrl = $identity['logo_url'] ?? null;

    $barisAngsuran = max(1, (int) ceil(count($rows) / 2));
    $rowCount = count($rows);
    $rowspan = $rowCount > 16 ? $rowCount + 3 : 19;
    $chairPerson = '';
    foreach ($committee ?? [] as $c) {
        if (stripos($c['position'] ?? '', 'ketua') !== false) {
            $chairPerson = $c['name'] ?? '';
            break;
        }
    }
    if ($chairPerson === '' && !empty($committee[0]['name'])) {
        $chairPerson = $committee[0]['name'];
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Kartu Angsuran #{{ $loan['id'] }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin-bottom: 100px; }
        ul, ol { margin-left: -10px; page-break-inside: auto !important; }
        header { position: fixed; top: -10px; left: 0px; right: 0px; }
        table tr th, table tr td { padding: 2px 4px; }
        table tr th { font-size: 12px; }
        .break { page-break-after: always; }
        li { text-align: justify; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
    </style>
</head>
<body onload="window.print()">
    <main style="position: relative; font-size: 12px;">
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td rowspan="7" align="center" width="400">
                    <div style="font-size: 14px; font-weight: bold;">
                        {{ $legalName }} {{ $district }}
                    </div>
                    <div>{{ $address }}</div>
                    <div>Telp. {{ $phone }}</div>
                    <div style="margin-top: 8px;">
                        @if (! empty($logoUrl))
                            <img src="{{ $logoUrl }}" height="40" alt="{{ $loan['loan_number'] ?? $loan['id'] }}">
                        @endif
                    </div>
                    <div style="font-size: 14px;">{{ $loan['loan_number'] ?? ('PINJ-' . $loan['id']) }}</div>
                </td>
                <td width="150">Jenis Piutang</td>
                <td width="5" align="center">:</td>
                <td width="200">{{ strtoupper($loan['product_code'] ?? $loan['product_name'] ?? '-') }}</td>
                <td width="150">Loan Id.</td>
                <td width="5" align="center">:</td>
                <td width="200">{{ $loan['id'] }}</td>
            </tr>
            <tr>
                <td>Nama Kelompok</td>
                <td align="center">:</td>
                <td style="font-weight: bold;" colspan="4">{{ $loan['group_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Alamat</td>
                <td align="center">:</td>
                <td colspan="4">{{ $loan['group_address'] ?? $loan['village_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Telpon/SMS</td>
                <td align="center">:</td>
                <td>{{ $phone ?: '-' }}</td>
                <td>Anggota</td>
                <td align="center">:</td>
                <td>{{ $loan['beneficiaries_count'] ?? 0 }}</td>
            </tr>
            <tr>
                <td>Tgl Cair</td>
                <td align="center">:</td>
                <td>{{ $loan['disbursed_at'] ? \Carbon\CarbonImmutable::parse($loan['disbursed_at'])->translatedFormat('d F Y') : '-' }}</td>
                <td>Jangka</td>
                <td align="center">:</td>
                <td>{{ $loan['term_months'] }} Bulan</td>
            </tr>
            <tr>
                <td>Alokasi</td>
                <td align="center">:</td>
                <td>{{ number_format($loan['principal_amount'], 2) }}</td>
                <td>Jasa</td>
                <td align="center">:</td>
                <td>{{ number_format($loan['interest_rate'], 2) }}%</td>
            </tr>
            <tr>
                <td>Angsuran</td>
                <td align="center">:</td>
                @php
                    $firstRow = $rows[0] ?? null;
                    $wajib = $firstRow ? ($firstRow['principal_due'] + $firstRow['interest_due']) : 0;
                @endphp
                <td>{{ number_format($wajib, 2) }} / Bulan</td>
                <td colspan="3">
                    Angsuran pada tanggal {{ $firstRow && $firstRow['due_date'] ? \Carbon\CarbonImmutable::parse($firstRow['due_date'])->format('d') : '-' }}
                </td>
            </tr>
            <tr>
                <td colspan="7" class="b t" style="font-weight: bold; font-size: 24px;" align="center">
                    KARTU ANGSURAN
                </td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td width="5%">&nbsp;</td>
                <td colspan="9" style="font-weight: bold;" height="30">TABEL KEWAJIBAN PEMBAYARAN ANGSURAN</td>
                <td width="5%">&nbsp;</td>
            </tr>
            <tr style="font-weight: bold;">
                <th rowspan="{{ $barisAngsuran + 1 }}">&nbsp;</th>
                <th height="30" class="l t b" align="center">Ke</th>
                <th class="l t b" align="center">Tanggal</th>
                <th class="l t b" align="center">Pokok</th>
                <th class="l t b r" align="center">Jasa</th>
                <th>&nbsp;</th>
                <th class="l t b" align="center">Ke</th>
                <th class="l t b" align="center">Tanggal</th>
                <th class="l t b" align="center">Pokok</th>
                <th class="l t b r" align="center">Jasa</th>
                <th rowspan="{{ $barisAngsuran + 1 }}">&nbsp;</th>
            </tr>
            @for ($j = 1; $j <= $barisAngsuran; $j++)
                @php
                    $i = $j - 1;
                    $left = $rows[$i] ?? null;
                    $right = $rows[$i + $barisAngsuran] ?? null;
                    $isLast = ($j === $barisAngsuran);
                    $bClass = $isLast ? 'b' : '';
                @endphp
                <tr>
                    <td class="l {{ $bClass }}" align="center">{{ $left ? $left['installment_number'] : '' }}</td>
                    <td class="l {{ $bClass }}" align="center">{{ $left && $left['due_date'] ? \Carbon\CarbonImmutable::parse($left['due_date'])->translatedFormat('d F Y') : '' }}</td>
                    <td class="l {{ $bClass }}" align="right">{{ $left ? number_format($left['principal_due'], 2) : '' }}</td>
                    <td class="l {{ $bClass }} r" align="right">{{ $left ? number_format($left['interest_due'], 2) : '' }}</td>
                    <td>&nbsp;</td>
                    @if ($right)
                        <td class="l {{ $bClass }}" align="center">{{ $right['installment_number'] }}</td>
                        <td class="l {{ $bClass }}" align="center">{{ $right['due_date'] ? \Carbon\CarbonImmutable::parse($right['due_date'])->translatedFormat('d F Y') : '' }}</td>
                        <td class="l {{ $bClass }}" align="right">{{ number_format($right['principal_due'], 2) }}</td>
                        <td class="l {{ $bClass }} r" align="right">{{ number_format($right['interest_due'], 2) }}</td>
                    @else
                        <td class="l {{ $bClass }}" align="center"></td>
                        <td class="l {{ $bClass }}" align="center"></td>
                        <td class="l {{ $bClass }}" align="right"></td>
                        <td class="l {{ $bClass }} r" align="right"></td>
                    @endif
                </tr>
            @endfor
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td width="5%" rowspan="{{ $rowspan }}">&nbsp;</td>
                <td width="90%" colspan="9" style="font-weight: bold;" height="30">
                    REALISASI PEMBAYARAN ANGSURAN
                </td>
                <td width="5%" rowspan="{{ $rowspan }}">&nbsp;</td>
            </tr>
            <tr>
                <th width="3%" class="l t b" rowspan="2">No</th>
                <th width="10%" class="l t b" rowspan="2">Tanggal</th>
                <th width="22%" class="l t" colspan="2">Pokok</th>
                <th width="22%" class="l t" colspan="2">Jasa</th>
                <th width="22%" class="l t" colspan="2">Saldo Piutang</th>
                <th width="11%" class="l t r b" rowspan="2">Sign</th>
            </tr>
            <tr>
                <th width="12%" class="l b t">Dibayar</th>
                <th width="10%" class="l b t">Tunggakan</th>
                <th width="12%" class="l b t">Dibayar</th>
                <th width="10%" class="l b t">Tunggakan</th>
                <th width="11%" class="l b t">Pokok</th>
                <th width="11%" class="l b t">Jasa</th>
            </tr>

            @php $paidCount = 0; @endphp
            @foreach ($rows as $r)
                @if (($r['principal_paid'] + $r['interest_paid']) > 0)
                    @php
                        $paidCount++;
                        $nomor = $paidCount;
                        $bClass = ($nomor + 3 == $rowspan) ? 'b' : '';
                    @endphp
                    <tr>
                        <td class="l {{ $bClass }}" align="center">{{ $nomor }}</td>
                        <td class="l {{ $bClass }}" align="center">{{ $r['due_date'] ? \Carbon\CarbonImmutable::parse($r['due_date'])->translatedFormat('d F Y') : '-' }}</td>
                        <td class="l {{ $bClass }}" align="right">{{ number_format($r['principal_paid'], 2) }}</td>
                        <td class="l {{ $bClass }}" align="right">{{ number_format($r['principal_remaining'], 2) }}</td>
                        <td class="l {{ $bClass }}" align="right">{{ number_format($r['interest_paid'], 2) }}</td>
                        <td class="l {{ $bClass }}" align="right">{{ number_format($r['interest_remaining'], 2) }}</td>
                        <td class="l {{ $bClass }}" align="right">{{ number_format($r['principal_remaining'], 2) }}</td>
                        <td class="l {{ $bClass }}" align="right">{{ number_format($r['interest_remaining'], 2) }}</td>
                        <td class="l {{ $bClass }} r" align="center">TRX-{{ $r['installment_number'] }}</td>
                    </tr>
                @endif
            @endforeach

            @if ($paidCount < 16)
                @for ($i = 1; $i <= 16 - $paidCount; $i++)
                    @php $bClass = ($i == 16 - $paidCount) ? 'b' : ''; @endphp
                    <tr>
                        <td class="l {{ $bClass }}" align="center">&nbsp;</td>
                        <td class="l {{ $bClass }}" align="center">&nbsp;</td>
                        <td class="l {{ $bClass }}" align="right">&nbsp;</td>
                        <td class="l {{ $bClass }}" align="right">&nbsp;</td>
                        <td class="l {{ $bClass }}" align="right">&nbsp;</td>
                        <td class="l {{ $bClass }}" align="right">&nbsp;</td>
                        <td class="l {{ $bClass }}" align="right">&nbsp;</td>
                        <td class="l {{ $bClass }}" align="right">&nbsp;</td>
                        <td class="l {{ $bClass }} r" align="center">&nbsp;</td>
                    </tr>
                @endfor
            @endif
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td width="5%" rowspan="5">&nbsp;</td>
                <td colspan="3" style="font-weight: bold;" height="30">&nbsp;</td>
                <td width="5%" rowspan="5">&nbsp;</td>
            </tr>
            <tr>
                <td width="350" rowspan="3">
                    <div>Lembar 1 : Untuk Kelompok</div>
                    <div>Lembar 2 : Arsip Lembaga</div>
                </td>
                <td style="font-weight: bold; font-size: 12px;" width="350" align="center">
                    <div>Direktur</div>
                </td>
                <td style="font-weight: bold; font-size: 12px;" width="350" align="center">Ketua Kelompok</td>
            </tr>
            <tr>
                <td colspan="2" height="50"></td>
            </tr>
            <tr style="font-weight: bold; font-size: 12px; text-transform: uppercase;">
                <td width="350" align="center">
                    <div>{{ $identity['legal_name'] ?? 'PIMPINAN' }}</div>
                </td>
                <td width="350" align="center">
                    {{ $chairPerson ?: ($loan['group_name'] ?? 'KETUA') }}
                </td>
            </tr>
            <tr>
                <td colspan="3" style="font-weight: bold;" height="10">&nbsp;</td>
            </tr>
            <tr>
                <td colspan="4">
                    <ol>
                        <b>Perhatian:</b>
                        <li>Bayarlah angsuran tepat waktu sesuai dengan jadwal diatas</li>
                        <li>Untuk memudahkan pelayanan, bawalah kartu ini dan slip pembayaran terakhir setiap melakukan angsuran</li>
                        <li>Jagalah keutuhan kartu dan tidak melipatnya, jika hilang segera lapor {{ $legalName }}</li>
                        <li>Jika lembar ini tidak mencukupi, cetak pada lembar baliknya dengan dibubuhi stempel {{ $legalName }}</li>
                    </ol>
                </td>
                <td>&nbsp;</td>
            </tr>
        </table>
    </main>
</body>
</html>