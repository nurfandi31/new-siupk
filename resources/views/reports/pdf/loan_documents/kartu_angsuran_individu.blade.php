@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';
    $phone = $profile?->phone ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $borrowerPhone = $person?->phone ?? '-';

    $loanNumber = $loan_obj->loan_number ?? ('PINJ-' . $loan_obj->id);
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $ratePerMonth = $term > 0 ? round($rate / $term, 2) : 0;
    $productName = $loan_obj->product?->name ?? 'Pinjaman';
    $installments = $loan_obj->installments ?? collect();

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $barisAngsuran = (int) ceil(max($installments->count(), 1) / 2);

    $jumlahAngsuran = 0;
    foreach ($installments as $ra) {
        if ($jumlahAngsuran == 0) {
            $jumlahAngsuran = (float) (($ra->principal_amount ?? 0) + ($ra->interest_amount ?? 0));
        }
    }

    $barcode = $borrowerNik;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Angsuran Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin-bottom: 100px; }
        body { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 4px; }
        th { font-size: 12px; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
        .break { page-break-after: always; }
        li { text-align: justify; }
        .kop-cell { vertical-align: top; }
        .barcode { margin-top: 8px; }
    </style>
</head>
<body onload="window.print()">
    <main style="position: relative; font-size: 12px;">
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td rowspan="7" align="center" width="400" class="kop-cell">
                    <div style="font-size: 14px; font-weight: bold;">{{ $legalName }}</div>
                    <div>{{ $address }}</div>
                    <div>Telp. {{ $phone }}</div>
                    <div class="barcode">
                        <img src="data:image/png;base64,{{ $barcode ?? '' }}" width="150" alt="{{ $borrowerNik }}">
                    </div>
                    <div style="font-size: 14px;">{{ $borrowerNik }}</div>
                </td>
                <td width="150">Jenis Pinjaman</td>
                <td width="5" align="center">:</td>
                <td width="200">{{ $productName }}</td>
                <td width="150">Loan Id.</td>
                <td width="5" align="center">:</td>
                <td width="200">{{ $loan_obj->id }}</td>
            </tr>
            <tr>
                <td>Nama Peminjam</td>
                <td align="center">:</td>
                <td style="font-weight: bold;" colspan="4">{{ $borrowerName }}</td>
            </tr>
            <tr>
                <td>Alamat</td>
                <td align="center">:</td>
                <td colspan="4">{{ $borrowerAddress }}</td>
            </tr>
            <tr>
                <td>Telpon/SMS</td>
                <td align="center">:</td>
                <td>{{ $borrowerPhone }}</td>
                <td>Jumlah Angsuran</td>
                <td align="center">:</td>
                <td>{{ number_format($jumlahAngsuran, 0, ',', '.') }} / Bulanan</td>
            </tr>
            <tr>
                <td>Tgl Cair</td>
                <td align="center">:</td>
                <td>{{ $disbursedAt }}</td>
                <td>Jangka</td>
                <td align="center">:</td>
                <td>{{ $term }} Bulan</td>
            </tr>
            <tr>
                <td>Alokasi</td>
                <td align="center">:</td>
                <td>{{ $principalFmt }}</td>
                <td>Jasa</td>
                <td align="center">:</td>
                <td>{{ number_format($ratePerMonth, 2, ',', '.') }}%</td>
            </tr>
            <tr>
                <td>&nbsp;</td><td align="center">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td align="center">&nbsp;</td><td>&nbsp;</td>
            </tr>
            <tr>
                <td colspan="7" class="b t" style="font-weight: bold; font-size: 24px;" align="center">
                    KARTU ANGSURAN INDIVIDU
                </td>
            </tr>
        </table>

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
                @php
                    $i = $j - 1;
                    $left = $installments[$i] ?? null;
                    $right = $installments[$i + $barisAngsuran] ?? null;
                @endphp
                <tr>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                        {{ $left ? ($i + 1) : '' }}
                    </td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                        {{ $left && $left->due_date ? CarbonImmutable::parse($left->due_date)->format('d/m/Y') : '' }}
                    </td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="right">
                        {{ $left ? number_format((float) ($left->principal_amount ?? 0), 0, ',', '.') : '' }}
                    </td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }} r" align="right">
                        {{ $left ? number_format((float) ($left->interest_amount ?? 0), 0, ',', '.') : '' }}
                    </td>

                    <td>&nbsp;</td>

                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                        {{ $right ? ($i + $barisAngsuran + 1) : '' }}
                    </td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                        {{ $right && $right->due_date ? CarbonImmutable::parse($right->due_date)->format('d/m/Y') : '' }}
                    </td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="right">
                        {{ $right ? number_format((float) ($right->principal_amount ?? 0), 0, ',', '.') : '' }}
                    </td>
                    <td class="l {{ $j == $barisAngsuran ? 'b' : '' }} r" align="right">
                        {{ $right ? number_format((float) ($right->interest_amount ?? 0), 0, ',', '.') : '' }}
                    </td>
                </tr>
            @endfor
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td width="40" rowspan="5">&nbsp;</td>
                <td colspan="3" style="font-weight: bold;" height="30">&nbsp;</td>
                <td width="40" rowspan="5">&nbsp;</td>
            </tr>
            <tr>
                <td width="350" rowspan="3">
                    <div>Lembar 1 : Untuk Peminjam</div>
                    <div>Lembar 2 : Arsip Lembaga</div>
                </td>
                <td style="font-weight: bold; font-size: 12px;" width="350" align="center">
                    <div>{{ $managerTitle }} {{ $legalName }}</div>
                </td>
                <td style="font-weight: bold; font-size: 12px;" width="350" align="center">Peminjam</td>
            </tr>
            <tr>
                <td colspan="2" height="50"></td>
            </tr>
            <tr style="font-weight: bold; font-size: 12px; text-transform: uppercase;">
                <td width="350" align="center">
                    <div>{{ $managerName }}</div>
                </td>
                <td width="350" align="center">
                    {{ $borrowerName }}
                </td>
            </tr>
            <tr>
                <td colspan="3" style="font-weight: bold;" height="10">&nbsp;</td>
            </tr>
            <tr>
                <td colspan="5">
                    <ol>
                        <b>Perhatian:</b>
                        <li>Bayarlah angsuran tepat waktu sesuai dengan jadwal di atas.</li>
                        <li>Untuk memudahkan pelayanan, bawalah kartu ini dan slip pembayaran terakhir setiap melakukan angsuran.</li>
                        <li>Jagalah keutuhan kartu dan tidak melipatnya, jika hilang segera lapor {{ $legalName }}.</li>
                        <li>Jika lembar ini tidak mencukupi, cetak pada lembar baliknya dengan dibubuhi stempel {{ $legalName }}.</li>
                    </ol>
                </td>
            </tr>
        </table>
    </main>
    <div style="font-size: 9px; text-align: center; font-style: italic; padding-top: 10px;">
        Dokumen ini merupakan bagian tak terpisahkan dari SPK Nomor {{ $loan_obj->spk_no ?? $loanNumber }}
    </div>
</body>
</html>