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
    $village = $borrower?->village;
    $villageName = $village?->name ?? '';
    $guarantor = $borrower?->guarantor?->person;
    $guarantorName = $guarantor?->full_name ?? '';
    $guarantorNik = $guarantor?->national_identity_number ?? '';

    $loanNumber = $loan_obj->loan_number ?? 'PINJ-' . $loan_obj->id;
    $spkNo = $loan_obj->spk_no ?? '';
    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $term = (int) $loan_obj->term_months;
    $method = $loan_obj->installment_method ?? '';

    $collateral = is_array($loan_obj->collateral) ? $loan_obj->collateral : [];
    $collateralType = match ($collateral['type'] ?? null) {
        'tanah' => 'Surat Tanah',
        'bpkb' => 'BPKB Kendaraan',
        'sk' => 'SK Pegawai',
        'cash' => 'Simpanan',
        default => 'Lain-lain',
    };
    $collateralDetail = '';
    foreach (['nomor', 'atas_nama', 'luas', 'lokasi', 'kendaraan', 'nopol', 'tahun'] as $k) {
        if (! empty($collateral[$k])) {
            $collateralDetail .= $collateralDetail === '' ? '' : ' · ';
            $collateralDetail .= ucfirst(str_replace('_', ' ', $k)).': '.$collateral[$k];
        }
    }
    $collateralValue = (float) ($collateral['nilai'] ?? 0);

    $managerTitle = $profile?->manager_title ?? 'Direktur';
    $managerName = $profile?->manager_name ?? '________________';
    $secretaryTitle = $profile?->secretary_title ?? 'Sekretaris';
    $secretaryName = $profile?->secretary_name ?? '________________';
    $treasurerTitle = $profile?->treasurer_title ?? 'Bendahara';
    $treasurerName = $profile?->treasurer_name ?? '________________';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Perjanjian Kredit Individu #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 11pt; line-height: 1.4; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 16px; }
        .kop h1 { font-size: 13pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 9pt; }
        .judul { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; margin: 18px 0 12px; }
        .pasal { margin: 8px 0; text-align: justify; }
        .pasal-title { font-weight: bold; margin-top: 8px; }
        .tabel { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .tabel td { padding: 3px 6px; vertical-align: top; }
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· ' . $district : '' }}</h1>
        <p>{{ $address }}</p>
        @if ($phone)<p>Telp. {{ $phone }}</p>@endif
    </div>

    <div class="judul">SURAT PERJANJIAN KREDIT INDIVIDU<br>Nomor: {{ $spkNo ?: $loanNumber }}</div>

    <p style="text-align: justify;">
        Pada hari ini {{ $today->translatedFormat('l') }} tanggal {{ $todayLabel }}, bertempat di {{ $legalName }},
        kedua pihak yang bertanda tangan di bawah ini:
    </p>

    <table class="tabel">
        <tr><td colspan="3"><strong>I. PIHAK PERTAMA (Pemberi Kredit)</strong></td></tr>
        <tr><td width="160">Nama</td><td width="10">:</td><td>{{ $managerName }}</td></tr>
        <tr><td>Jabatan</td><td>:</td><td>{{ $managerTitle }} {{ $legalName }}</td></tr>
    </table>

    <table class="tabel">
        <tr><td colspan="3"><strong>II. PIHAK KEDUA (Penerima Kredit)</strong></td></tr>
        <tr><td width="160">Nama</td><td width="10">:</td><td><strong>{{ $borrowerName }}</strong></td></tr>
        <tr><td>NIK</td><td>:</td><td>{{ $borrowerNik }}</td></tr>
        <tr><td>Alamat</td><td>:</td><td>{{ $borrowerAddress }} {{ $villageName }}</td></tr>
        @if ($guarantorName)
            <tr><td>Penjamin</td><td>:</td><td>{{ $guarantorName }} (NIK: {{ $guarantorNik }})</td></tr>
        @endif
    </table>

    <p>Kedua pihak sepakat mengadakan Perjanjian Kredit dengan ketentuan sebagai berikut:</p>

    <div class="pasal">
        <div class="pasal-title">Pasal 1 — Plafon dan Tujuan</div>
        <p>Pihak Pertama setuju memberikan kredit kepada Pihak Kedua sebesar <strong>{{ $principalFmt }}</strong>
        ({{ \App\Support\IndonesianNumber::spelledOut($principal) }} rupiah) untuk keperluan produktif.</p>
    </div>

    <div class="pasal">
        <div class="pasal-title">Pasal 2 — Jangka Waktu</div>
        <p>Jangka waktu pinjaman adalah <strong>{{ $term }} bulan</strong>, dengan angsuran dibayar setiap bulan sejak tanggal pencairan {{ $disbursedAt }}.</p>
    </div>

    <div class="pasal">
        <div class="pasal-title">Pasal 3 — Jasa</div>
        <p>Jasa pinjaman adalah <strong>{{ number_format($rate, 2, ',', '.') }}%</strong> flat selama jangka waktu ({{ ucfirst($method) }}).</p>
    </div>

    @if (! empty($collateral))
        <div class="pasal">
            <div class="pasal-title">Pasal 4 — Jaminan</div>
            <p>Sebagai jaminan pinjaman, Pihak Kedua menyerahkan: <strong>{{ $collateralType }}</strong>{{ $collateralDetail ? ' — '.$collateralDetail : '' }}{{ $collateralValue > 0 ? ' dengan nilai jaminan Rp '.number_format($collateralValue, 0, ',', '.') : '' }}.</p>
        </div>
    @endif

    <div class="pasal">
        <div class="pasal-title">Pasal {{ empty($collateral) ? '4' : '5' }} — Denda Keterlambatan</div>
        <p>Apabila Pihak Kedua terlambat membayar angsuran, maka dikenakan denda sesuai ketentuan yang berlaku di {{ $legalName }}.</p>
    </div>

    <div class="pasal">
        <div class="pasal-title">Pasal {{ empty($collateral) ? '5' : '6' }} — Pelunasan Dipercepat</div>
        <p>Pelunasan dipercepat diperbolehkan tanpa penalti, dengan pemberitahuan tertulis terlebih dahulu kepada Pihak Pertama.</p>
    </div>

    <div class="pasal">
        <div class="pasal-title">Pasal {{ empty($collateral) ? '6' : '7' }} — Wanprestasi</div>
        <p>Apabila Pihak Kedua wanprestasi (cidera janji), Pihak Pertama berhak melakukan tindakan hukum sesuai peraturan yang berlaku setelah melakukan pemberitahuan dan peringatan terlebih dahulu.</p>
    </div>

    <div class="pasal">
        <div class="pasal-title">Pasal {{ empty($collateral) ? '7' : '8' }} — Penutup</div>
        <p>Surat perjanjian ini dibuat dan ditandatangani oleh kedua pihak dalam keadaan sadar tanpa paksaan dari pihak manapun, dan mengikat setelah ditandatangani.</p>
    </div>

    <table class="ttd">
        <tr>
            <td width="50%">
                Pihak Kedua<br><br><br><br><br>
                <strong>{{ $borrowerName }}</strong>
            </td>
            <td width="50%">
                {{ $district ? $district.', ' : '' }}{{ $todayLabel }}<br>
                Pihak Pertama<br><br><br><br><br>
                <strong>{{ $managerName }}</strong><br>
                {{ $managerTitle }}
            </td>
        </tr>
    </table>
</body>
</html>
