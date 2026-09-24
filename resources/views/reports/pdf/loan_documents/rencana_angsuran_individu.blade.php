@php
    use Carbon\CarbonImmutable;
    use App\Support\IndonesianNumber;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $villageName = $borrower?->village?->name ?? '';

    $loanNumber = $loan_obj->loan_number ?? ('PINJ-' . $loan_obj->id);

    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $verifiedAt = $loan_obj->verified_at ? CarbonImmutable::parse($loan_obj->verified_at)->translatedFormat('d F Y') : null;
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : null;

    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $ratePerMonth = $term > 0 ? round($rate / $term, 2) : 0;
    $productName = $loan_obj->product?->name ?? 'Pinjaman';
    $installments = $loan_obj->installments ?? collect();

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $saldoPokok = $principal;
    $saldoJasa = round(($saldoPokok * $rate) / 100, 0);
    $sumPokok = 0;
    $sumJasa = 0;
    $jumlahAngsuran = 0;

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Rencana Angsuran Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body { font-size: 11px; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 16pt; font-weight: bold; margin: 16px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
        th { font-weight: normal; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">RENCANA ANGSURAN PINJAMAN INDIVIDU {{ strtoupper($productName) }}</div>

    <table>
        <tr>
            <td width="120">Nama Peminjam</td>
            <td width="5" align="center">:</td>
            <td><b>{{ $borrowerName }}</b></td>
            <td width="120">Jangka Waktu</td>
            <td width="5" align="center">:</td>
            <td><b>{{ $term }} Bulan</b></td>
        </tr>
        <tr>
            <td>No Register</td>
            <td align="center">:</td>
            <td><b>{{ $loanNumber }}</b></td>
            <td>Sistem Angsuran</td>
            <td align="center">:</td>
            <td><b>Bulanan ({{ $term }} Kali)</b></td>
        </tr>
        <tr>
            <td>Tanggal Proposal</td>
            <td align="center">:</td>
            <td><b>{{ $proposedAt }}</b></td>
            <td>Jenis Jasa</td>
            <td align="center">:</td>
            <td><b>{{ $rate }}% per tahun</b></td>
        </tr>
        <tr>
            <td>Alokasi Pinjaman</td>
            <td align="center">:</td>
            <td><b>{{ $principalFmt }}</b></td>
            <td>Prosentase Jasa</td>
            <td align="center">:</td>
            <td><b>{{ number_format($ratePerMonth, 2, ',', '.') }}% per bulan</b></td>
        </tr>
        <tr>
            <td colspan="6">&nbsp;</td>
        </tr>
    </table>

    <table style="table-layout: fixed;">
        <tr style="background: rgb(232, 232, 232);">
            <th class="l t b" height="20" width="5%" align="center">Ke</th>
            <th class="l t b" width="13%" align="center">Tanggal</th>
            <th class="l t b" width="13%" align="center">Pokok</th>
            <th class="l t b" width="13%" align="center">Jasa</th>
            <th class="l t b" width="15%" align="center">Jumlah</th>
            <th class="l t b" width="15%" align="center">Total Target</th>
            <th class="l t b" width="13%" align="center">Saldo Pokok</th>
            <th class="l t b r" width="13%" align="center">Saldo Jasa</th>
        </tr>
        @foreach ($installments as $idx => $ra)
            @php
                $wajibPokok = (float) ($ra->principal_amount ?? 0);
                $wajibJasa = (float) ($ra->interest_amount ?? 0);
                $wajibAngsur = $wajibPokok + $wajibJasa;
                $jumlahAngsuran += $wajibAngsur;
                $saldoPokok -= $wajibPokok;
                $saldoJasa -= $wajibJasa;
                $sumPokok += $wajibPokok;
                $sumJasa += $wajibJasa;
                $tglJatuhTempo = $ra->due_date
                    ? CarbonImmutable::parse($ra->due_date)->format('d/m/Y')
                    : '';
            @endphp
            <tr>
                <td class="l" align="center">{{ $idx + 1 }}</td>
                <td class="l" align="center">{{ $tglJatuhTempo }}</td>
                <td class="l" align="right">{{ number_format($wajibPokok, 0, ',', '.') }}</td>
                <td class="l" align="right">{{ number_format($wajibJasa, 0, ',', '.') }}</td>
                <td class="l" align="right">{{ number_format($wajibAngsur, 0, ',', '.') }}</td>
                <td class="l" align="right">{{ number_format($jumlahAngsuran, 0, ',', '.') }}</td>
                <td class="l" align="right">{{ number_format($saldoPokok, 0, ',', '.') }}</td>
                <td class="l r" align="right">{{ number_format($saldoJasa, 0, ',', '.') }}</td>
            </tr>
        @endforeach

        @if ($installments->isEmpty())
            @for ($i = 1; $i <= max($term, 1); $i++)
                <tr>
                    <td class="l" align="center">{{ $i }}</td>
                    <td class="l" align="center">—</td>
                    <td class="l" align="right">—</td>
                    <td class="l" align="right">—</td>
                    <td class="l" align="right">—</td>
                    <td class="l" align="right">—</td>
                    <td class="l" align="right">—</td>
                    <td class="l r" align="right">—</td>
                </tr>
            @endfor
        @endif

        <tr style="font-weight: bold;">
            <td class="l t b" colspan="2" height="20" align="center">JUMLAH</td>
            <td class="l t b" align="right">{{ number_format($sumPokok, 0, ',', '.') }}</td>
            <td class="l t b" align="right">{{ number_format($sumJasa, 0, ',', '.') }}</td>
            <td class="l t b" align="right">{{ number_format($jumlahAngsuran, 0, ',', '.') }}</td>
            <td class="l t b" align="right">{{ number_format($jumlahAngsuran, 0, ',', '.') }}</td>
            <td class="l t b" align="right">{{ number_format($saldoPokok, 0, ',', '.') }}</td>
            <td class="l t b r" align="right">{{ number_format($saldoJasa, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="ttd" style="margin-top: 36px;">
        <tr>
            <td width="50%">&nbsp;</td>
            <td width="50%" align="center">{{ $district ? $district.', ' : '' }}{{ $proposedAt }}</td>
        </tr>
        <tr>
            <td align="center">{{ $managerTitle }} {{ $legalName }}<br><br><br><br><br>
                <b>{{ $managerName }}</b>
            </td>
            <td align="center">Pemanfaat<br><br><br><br><br>
                <b>{{ $borrowerName }}</b>
            </td>
        </tr>
    </table>
</body>
</html>