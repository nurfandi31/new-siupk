@php
    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $address = $identity['address'] ?? '';
    $phone = $identity['phone'] ?? '';
    $logoUrl = $identity['logo_url'] ?? null;

    $barisAngsuran = max(1, (int) ceil(count($rows) / 2));
    $rowCount = count($rows);
    $rowspan = $rowCount > 16 ? $rowCount + 3 : 19;

    $borrower = $borrower ?? [];
    $borrowerName = strtoupper($borrower['name'] ?? '-');
    $borrowerCode = $borrower['member_code'] ?? '-';
    $borrowerAddress = $borrower['address'] ?? '-';
    $borrowerPhone = $borrower['phone'] ?? '-';
    $borrowerNik = $borrower['identity_number'] ?? '-';
    $villageName = $borrower['village_name'] ?? '-';

    $productName = strtoupper($loan['product_code'] ?? $loan['product_name'] ?? '-');
    $spkNo = $loan['spk_no'] ?? null;

    $totalPlan = ($totals['plan_principal'] ?? 0) + ($totals['plan_interest'] ?? 0);
    $totalPaid = ($totals['paid_principal'] ?? 0) + ($totals['paid_interest'] ?? 0);
    $totalRemaining = ($totals['remaining_principal'] ?? 0) + ($totals['remaining_interest'] ?? 0);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Kartu Angsuran Individu #{{ $loan['id'] }}</title>
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
        .hidden { opacity: 0; visibility: hidden; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            background: #047857;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .header-title {
            font-size: 14px;
            font-weight: bold;
            background: #047857;
            color: #fff;
            padding: 6px;
            text-align: center;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <main style="position: relative; font-size: 12px;">
        {{-- KOP / Identitas Lembaga --}}
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td rowspan="7" align="center" width="380">
                    <div style="font-size: 14px; font-weight: bold;">
                        {{ $legalName }}
                    </div>
                    <div>{{ $address }}</div>
                    <div>Telp. {{ $phone }}</div>
                    <div style="margin-top: 6px;">
                        @if (! empty($logoUrl))
                            <img src="{{ $logoUrl }}" height="40" alt="{{ $loan['loan_number'] ?? $loan['id'] }}">
                        @endif
                    </div>
                    <div style="font-size: 11px; margin-top: 4px;">NIK: {{ $borrowerNik }}</div>
                </td>
                <td width="140">Jenis Pinjaman</td>
                <td width="5" align="center">:</td>
                <td width="180">{{ $productName }}</td>
                <td width="140">Loan Id.</td>
                <td width="5" align="center">:</td>
                <td width="180">{{ $loan['id'] }}</td>
            </tr>
            <tr>
                <td>Nama Peminjam</td>
                <td align="center">:</td>
                <td style="font-weight: bold;" colspan="4">{{ $borrowerName }}</td>
            </tr>
            <tr>
                <td>No. Anggota</td>
                <td align="center">:</td>
                <td colspan="4">{{ $borrowerCode }}</td>
            </tr>
            <tr>
                <td>Alamat</td>
                <td align="center">:</td>
                <td colspan="4">{{ $borrowerAddress }} {{ $villageName }}</td>
            </tr>
            <tr>
                <td>Telpon/SMS</td>
                <td align="center">:</td>
                <td colspan="4">{{ $borrowerPhone }}</td>
            </tr>
            <tr>
                <td>Tgl Cair</td>
                <td align="center">:</td>
                <td>{{ $loan['disbursed_at'] ? \Carbon\CarbonImmutable::parse($loan['disbursed_at'])->translatedFormat('d F Y') : '—' }}</td>
                <td>Jangka</td>
                <td align="center">:</td>
                <td>{{ $loan['term_months'] }} {{ ($loan['principal_frequency'] ?? '') === 'weekly' ? 'Minggu' : 'Bulan' }}</td>
            </tr>
            <tr>
                <td>Alokasi</td>
                <td align="center">:</td>
                <td>{{ number_format((float) ($loan['principal_amount'] ?? 0), 0, ',', '.') }}</td>
                <td>Jasa</td>
                <td align="center">:</td>
                <td>{{ number_format((float) ($loan['interest_rate'] ?? 0), 2, ',', '.') }}%</td>
            </tr>
            <tr>
                <td colspan="7" class="header-title">
                    KARTU ANGSURAN · PINJAMAN INDIVIDU
                </td>
            </tr>
        </table>

        {{-- Tabel Kewajiban Angsuran --}}
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td width="40">&nbsp;</td>
                <td colspan="9" style="font-weight: bold;" height="30">TABEL KEWAJIBAN PEMBAYARAN ANGSURAN</td>
                <td width="40">&nbsp;</td>
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
                @php $i = $j - 1; @endphp
                <tr>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">{{ $rows[$i]['installment_number'] ?? '' }}</td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">{{ !empty($rows[$i]['due_date']) ? \Carbon\CarbonImmutable::parse($rows[$i]['due_date'])->translatedFormat('d/m/Y') : '' }}</td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="right">{{ number_format((float) ($rows[$i]['principal_due'] ?? 0), 0, ',', '.') }}</td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }} r" align="right">{{ number_format((float) ($rows[$i]['interest_due'] ?? 0), 0, ',', '.') }}</td>

                    <td>&nbsp;</td>

                    @if (isset($rows[$i + $barisAngsuran]))
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">{{ $rows[$i + $barisAngsuran]['installment_number'] }}</td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">{{ !empty($rows[$i + $barisAngsuran]['due_date']) ? \Carbon\CarbonImmutable::parse($rows[$i + $barisAngsuran]['due_date'])->translatedFormat('d/m/Y') : '' }}</td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="right">{{ number_format((float) ($rows[$i + $barisAngsuran]['principal_due'] ?? 0), 0, ',', '.') }}</td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }} r" align="right">{{ number_format((float) ($rows[$i + $barisAngsuran]['interest_due'] ?? 0), 0, ',', '.') }}</td>
                    @else
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}"></td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}"></td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}"></td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }} r"></td>
                    @endif
                </tr>
            @endfor
        </table>

        {{-- Ringkasan Total --}}
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
            <tr>
                <td width="40">&nbsp;</td>
                <td colspan="4" style="font-weight: bold;">RINGKASAN ANGSURAN</td>
                <td width="40">&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <th class="l t b" align="left" width="180">Keterangan</th>
                <th class="l t b" align="right" width="120">Pokok</th>
                <th class="l t b" align="right" width="120">Jasa</th>
                <th class="l t b r" align="right" width="120">Total</th>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td class="l">Rencana</td>
                <td class="l" align="right">{{ number_format($totals['plan_principal'] ?? 0, 0, ',', '.') }}</td>
                <td class="l" align="right">{{ number_format($totals['plan_interest'] ?? 0, 0, ',', '.') }}</td>
                <td class="l r" align="right">{{ number_format($totalPlan, 0, ',', '.') }}</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td class="l">Sudah Dibayar</td>
                <td class="l" align="right">{{ number_format($totals['paid_principal'] ?? 0, 0, ',', '.') }}</td>
                <td class="l" align="right">{{ number_format($totals['paid_interest'] ?? 0, 0, ',', '.') }}</td>
                <td class="l r" align="right">{{ number_format($totalPaid, 0, ',', '.') }}</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td class="l b">Sisa</td>
                <td class="l b" align="right">{{ number_format($totals['remaining_principal'] ?? 0, 0, ',', '.') }}</td>
                <td class="l b" align="right">{{ number_format($totals['remaining_interest'] ?? 0, 0, ',', '.') }}</td>
                <td class="l b r" align="right">{{ number_format($totalRemaining, 0, ',', '.') }}</td>
                <td>&nbsp;</td>
            </tr>
        </table>

        {{-- TTD --}}
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 24px;">
            <tr>
                <td width="40">&nbsp;</td>
                <td width="200">
                    <div>Lembar 1 : Untuk Peminjam</div>
                    <div>Lembar 2 : Arsip Lembaga</div>
                </td>
                <td width="40">&nbsp;</td>
                <td width="200" align="center">
                    <div style="font-weight: bold;">Peminjam</div>
                    <div style="height: 60px;"></div>
                    <div style="font-weight: bold; text-transform: uppercase;">
                        {{ $borrowerName }}
                    </div>
                </td>
                <td width="40">&nbsp;</td>
            </tr>
        </table>

        @if (! empty($spkNo))
            <div style="font-size: 9px; text-align: center; font-style: italic; padding-top: 8px;">
                Dokumen ini merupakan bagian tak terpisahkan dari SPK Nomor {{ $spkNo }}
            </div>
        @endif

        <div style="font-size: 10px; margin-top: 12px;">
            <ol>
                <strong>Perhatian:</strong>
                <li>Bayarlah angsuran tepat waktu sesuai dengan jadwal di atas.</li>
                <li>Untuk memudahkan pelayanan, bawalah kartu ini dan slip pembayaran terakhir setiap melakukan angsuran.</li>
                <li>Jagalah keutuhan kartu dan tidak melipatnya. Jika hilang, segera laporkan ke {{ $legalName }}.</li>
                <li>Jika lembar ini tidak mencukupi, cetak pada lembar baliknya dengan dibubuhi stempel {{ $legalName }}.</li>
            </ol>
        </div>
    </main>
</body>
</html>
