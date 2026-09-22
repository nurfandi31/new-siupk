@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $borrowerAddress = trim(($borrower?->address?->address_line ?? '').' '.($borrower?->village?->name ?? ''));
    $borrowerPhone = $person?->phone ?? '-';
    $birthPlace = $person?->birth_place ?? '';
    $birthDate = $person?->birth_date ? CarbonImmutable::parse($person->birth_date)->translatedFormat('d F Y') : '';
    $gender = (string) ($person?->gender ?? '');
    $salutation = $gender === 'P' ? 'Ibu' : 'Bpk';

    $guarantor = $borrower?->guarantor?->person;
    $guarantorName = strtoupper($guarantor?->full_name ?? '-');
    $guarantorNik = $guarantor?->national_identity_number ?? '-';

    $loanNumber = $loan_obj->loan_number ?? 'PINJ-' . $loan_obj->id;
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $ratePerMonth = $term > 0 ? round($rate / $term, 2) : 0;
    $installment = (float) ($loan_obj->installment_amount ?? 0);
    $installmentFmt = 'Rp ' . number_format($installment, 0, ',', '.');

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
    $docNo = 'AKK-' . $loan_obj->id . '/' . $today->format('Y');
    $age = '';
    if ($person?->birth_date) {
        $age = (int) $person->birth_date->age;
        $age .= ' tahun';
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Analisa & Keputusan Kredit #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 11pt; line-height: 1.5; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 2px; }
        .subtitle { text-align: center; font-size: 11pt; margin-bottom: 12px; }
        .section { font-weight: bold; margin-top: 14px; margin-bottom: 4px; font-size: 11pt; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        .num { width: 5%; text-align: center; }
        .label { width: 35%; }
        .sep { width: 3%; text-align: center; }
        .val { width: 57%; }
        .ttd { margin-top: 36px; }
        .ttd-table { width: 100%; }
        .ttd-table td { text-align: center; padding: 0; }
        .signature-line { height: 60px; }
    </style>
</head>
<body>
    <div class="title">ANALISA DAN KEPUTUSAN KREDIT</div>
    <div class="subtitle">Pinjaman Perorangan {{ $district ?: $legalName }}</div>
    <div class="subtitle">Nomor : {{ $docNo }}</div>
    <div class="subtitle">KREDIT BARU</div>

    <div class="section">I. DATA POKOK PEMINJAM</div>
    <table>
        <tr>
            <td class="num">1.</td>
            <td class="label">Nama</td>
            <td class="sep">:</td>
            <td class="val"><b>{{ $borrowerName }}</b></td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td class="label">Tempat &amp; Tgl Lahir</td>
            <td class="sep">:</td>
            <td class="val">{{ $birthPlace }}{{ $birthPlace && $birthDate ? ', ' : '' }}{{ $birthDate ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">3.</td>
            <td class="label">Usia</td>
            <td class="sep">:</td>
            <td class="val">{{ $age ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">4.</td>
            <td class="label">Jenis Kelamin</td>
            <td class="sep">:</td>
            <td class="val">{{ $gender === 'P' ? 'Perempuan' : ($gender === 'L' ? 'Laki-laki' : '-') }}</td>
        </tr>
        <tr>
            <td class="num">5.</td>
            <td class="label">NIK</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerNik }}</td>
        </tr>
        <tr>
            <td class="num">6.</td>
            <td class="label">No HP</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerPhone }}</td>
        </tr>
        <tr>
            <td class="num">7.</td>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="val">{{ $borrowerAddress ?: '-' }}</td>
        </tr>
        <tr>
            <td class="num">8.</td>
            <td class="label">Penjamin</td>
            <td class="sep">:</td>
            <td class="val">{{ $guarantorName }}<br/>NIK: {{ $guarantorNik }}</td>
        </tr>
    </table>

    <div class="section">II. PERMOHONAN PINJAMAN</div>
    <table>
        <tr>
            <td class="num">1.</td>
            <td class="label">Plafon Diajukan</td>
            <td class="sep">:</td>
            <td class="val"><b>{{ $principalFmt }}</b></td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td class="label">Suku Jasa</td>
            <td class="sep">:</td>
            <td class="val">{{ number_format($rate, 2, ',', '.') }} % per tahun</td>
        </tr>
        <tr>
            <td class="num">3.</td>
            <td class="label">Angsuran per Bulan</td>
            <td class="sep">:</td>
            <td class="val">{{ $installmentFmt }}</td>
        </tr>
        <tr>
            <td class="num">4.</td>
            <td class="label">Tanggal Pencairan</td>
            <td class="sep">:</td>
            <td class="val">{{ $disbursedAt }}</td>
        </tr>
    </table>

    <div class="section">III. PERHITUNGAN KREDIT / RATE YANG DIGUNAKAN</div>
    <table>
        <tr>
            <td class="num">1.</td>
            <td class="label">Suku Bunga Flat Rate</td>
            <td class="sep">:</td>
            <td class="val">{{ number_format($ratePerMonth, 2, ',', '.') }} % per bulan</td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td class="label">Jangka Waktu</td>
            <td class="sep">:</td>
            <td class="val">{{ $term }} bulan</td>
        </tr>
        <tr>
            <td class="num">3.</td>
            <td class="label">Plafon Maksimum</td>
            <td class="sep">:</td>
            <td class="val">{{ $principalFmt }}</td>
        </tr>
    </table>

    <div class="section">IV. REKOMENDASI PEMBERIAN KREDIT</div>
    <p style="text-align: justify;">
        Berdasarkan hasil perhitungan tersebut di atas, kami simpulkan bahwa yang bersangkutan dapat dipertimbangkan
        permohonan kreditnya dengan syarat-syarat berikut:
    </p>
    <table>
        <tr>
            <td class="num">1.</td>
            <td class="label">Jenis Kredit</td>
            <td class="sep">:</td>
            <td class="val">{{ $loan_obj->product?->name ?? 'Pinjaman Perorangan' }}</td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td class="label">Besar Plafon Kredit</td>
            <td class="sep">:</td>
            <td class="val"><b>{{ $principalFmt }}</b></td>
        </tr>
        <tr>
            <td class="num">3.</td>
            <td class="label">Suku Bunga</td>
            <td class="sep">:</td>
            <td class="val">{{ number_format($rate, 2, ',', '.') }} % per tahun</td>
        </tr>
        <tr>
            <td class="num">4.</td>
            <td class="label">Jangka Waktu</td>
            <td class="sep">:</td>
            <td class="val">{{ $term }} bulan</td>
        </tr>
        <tr>
            <td class="num">5.</td>
            <td class="label">Angsuran per Bulan</td>
            <td class="sep">:</td>
            <td class="val">{{ $installmentFmt }}</td>
        </tr>
    </table>

    <div class="ttd">
        <table class="ttd-table">
            <tr>
                <td style="width: 50%;">&nbsp;</td>
                <td style="width: 50%;">{{ $district ?: $legalName }}, {{ $todayLabel }}</td>
            </tr>
            <tr>
                <td>{{ $managerTitle }}</td>
                <td>Peminjam</td>
            </tr>
            <tr>
                <td class="signature-line">&nbsp;</td>
                <td class="signature-line">&nbsp;</td>
            </tr>
            <tr>
                <td><b>{{ strtoupper($managerName) }}</b></td>
                <td><b>{{ $borrowerName }}</b></td>
            </tr>
        </table>
    </div>
</body>
</html>
