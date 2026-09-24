@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = $borrower?->address?->address_line ?? '';
    $borrowerGender = $person?->gender === 'P' ? 'P' : 'L';
    $villageName = $borrower?->village?->name ?? '';

    $loanNumber = $loan_obj->loan_number ?? ('PINJ-' . $loan_obj->id);
    $spkNo = $loan_obj->spk_no ?? $loanNumber;
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $ratePerMonth = $term > 0 ? round($rate / $term, 2) : 0;
    $productName = $loan_obj->product?->name ?? 'Pinjaman';

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $signature = $signature ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Tanda Terima Dana Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 18pt; font-weight: bold; margin: 16px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
        th { font-weight: normal; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">TANDA TERIMA<br>PINJAMAN INDIVIDU {{ strtoupper($productName) }}</div>

    <table>
        <tr>
            <td width="120">Nama Pemanfaat</td>
            <td width="5" align="right">:</td>
            <td>{{ $borrowerName }} - {{ $loanNumber }}</td>
            <td width="120">Alokasi Pinjaman</td>
            <td width="5" align="right">:</td>
            <td>{{ $principalFmt }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td align="right">:</td>
            <td>{{ $borrowerAddress }}</td>
            <td>Sistem Angsuran</td>
            <td align="right">:</td>
            <td>Bulanan</td>
        </tr>
        <tr>
            <td>Tanggal Pencairan</td>
            <td align="right">:</td>
            <td>{{ $disbursedAt }}</td>
            <td>Prosentase Jasa</td>
            <td align="right">:</td>
            <td>{{ $rate }}% / {{ $term }} bulan</td>
        </tr>
        <tr>
            <td>Nomor SPK</td>
            <td align="right">:</td>
            <td>{{ $spkNo }}</td>
            <td>Pinjaman Ke-</td>
            <td align="right">:</td>
            <td>1</td>
        </tr>
    </table>

    <table style="table-layout: fixed;">
        <tr style="background: rgb(232, 232, 232);">
            <th class="t l b" width="3%" height="20">No</th>
            <th class="t l b" width="18%">NIK</th>
            <th class="t l b" width="22%">Nama Pemanfaat</th>
            <th class="t l b" width="3%">JK</th>
            <th class="t l b" width="26%">Alamat</th>
            <th class="t l b" width="14%">Pengajuan</th>
            <th class="t l b r" width="14%">Ttd</th>
        </tr>
        <tr>
            <td class="t l b" height="20" align="center">1</td>
            <td class="t l b">{{ $borrowerNik }}</td>
            <td class="t l b">{{ $borrowerName }}</td>
            <td class="t l b" align="center">{{ $borrowerGender }}</td>
            <td class="t l b">{{ $borrowerAddress }}</td>
            <td class="t l b" align="right">{{ number_format($principal, 0, ',', '.') }}</td>
            <td class="t l b r" align="center">1.</td>
        </tr>
        <tr>
            <td colspan="7" style="padding: 0px !important;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="font-weight: bold;">
                        <td class="t l b" height="20" width="72%" align="center">JUMLAH</td>
                        <td class="t l b" align="right" width="14%">{{ number_format($principal, 0, ',', '.') }}</td>
                        <td class="t l b r" width="14%">&nbsp;</td>
                    </tr>
                </table>

                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td width="60%">&nbsp;</td>
                        <td width="60">Diterima Di</td>
                        <td width="2">:</td>
                        <td>{{ $legalName }}</td>
                    </tr>
                    <tr>
                        <td width="60%">&nbsp;</td>
                        <td width="60">Pada Tanggal</td>
                        <td width="2">:</td>
                        <td>{{ $disbursedAt }}</td>
                    </tr>
                </table>

                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td colspan="2" height="10">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" width="50%">Mengetahui,</td>
                        <td align="center" width="50%">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center">{{ $managerTitle }} {{ $legalName }}</td>
                        <td align="center">Penerima</td>
                    </tr>
                    <tr>
                        <td align="center" colspan="2" height="40">&nbsp;</td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td align="center">{{ $managerName }}</td>
                        <td align="center">{{ $borrowerName }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" align="center">{!! $signature ?? '' !!}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>