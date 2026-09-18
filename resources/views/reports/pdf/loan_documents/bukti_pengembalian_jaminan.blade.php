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

    $loanNumber = $loan_obj->loan_number ?? 'PINJ-' . $loan_obj->id;
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $completedAt = $loan_obj->completed_at ? CarbonImmutable::parse($loan_obj->completed_at)->translatedFormat('d F Y') : CarbonImmutable::now()->translatedFormat('d F Y');
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');

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

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';
    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
    $bpjNo = 'BPJ-' . $loan_obj->id . '/' . $today->format('Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Bukti Pengembalian Jaminan #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 11pt; line-height: 1.5; }
        .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 4px; margin-bottom: 24px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 1px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 13pt; font-weight: bold; text-decoration: underline; margin: 20px 0 14px; }
        .tabel { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .tabel td { padding: 4px 6px; vertical-align: top; }
        .ttd { width: 100%; margin-top: 48px; }
        .ttd td { text-align: center; padding: 4px; }
        .jaminan-box { border: 2px solid #000; padding: 12px; margin: 16px 0; background: #fafafa; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· '.$district : '' }}</h1>
        <p>{{ $address }}</p>
    </div>

    <div class="judul">BUKTI PENGEMBALIAN JAMINAN<br>Nomor: {{ $bpjNo }}</div>

    <p style="text-align: justify;">
        Yang bertanda tangan di bawah ini, {{ $managerTitle }} {{ $legalName }}, dengan ini menyatakan telah
        <strong>mengembalikan</strong> barang jaminan kepada peminjam yang telah melunasi pinjamannya:
    </p>

    <table class="tabel">
        <tr><td width="160">Nama Peminjam</td><td width="10">:</td><td><strong>{{ $borrowerName }}</strong></td></tr>
        <tr><td>NIK</td><td>:</td><td>{{ $borrowerNik }}</td></tr>
        <tr><td>Alamat</td><td>:</td><td>{{ $borrowerAddress }}</td></tr>
        <tr><td>No. Pinjaman</td><td>:</td><td>{{ $loanNumber }}</td></tr>
        <tr><td>Plafon</td><td>:</td><td>{{ $principalFmt }}</td></tr>
        <tr><td>Tanggal Pencairan</td><td>:</td><td>{{ $disbursedAt }}</td></tr>
        <tr><td>Tanggal Pelunasan</td><td>:</td><td>{{ $completedAt }}</td></tr>
    </table>

    <p>Barang jaminan yang dikembalikan berupa:</p>

    <div class="jaminan-box">
        <div style="font-size: 13pt; font-weight: bold; text-align: center; margin-bottom: 8px;">
            {{ $collateralType }}
        </div>
        @if ($collateralDetail)
            <div style="font-size: 10pt; line-height: 1.6;">{{ $collateralDetail }}</div>
        @else
            <div style="font-style: italic; color: #666;">(Detail jaminan belum diisi di sistem.)</div>
        @endif
    </div>

    <p style="text-align: justify;">
        Pengembalian jaminan dilakukan setelah peminjam melunasi seluruh kewajibannya (pokok dan jasa)
        pada tanggal {{ $completedAt }}. Dengan ditandatanganinya bukti ini, peminjam menyatakan bahwa
        jaminan telah diterima dalam keadaan lengkap dan {{ $legalName }} dibebaskan dari segala
        tanggung jawab保管 atas jaminan tersebut.
    </p>

    <p>Demikian Bukti Pengembalian Jaminan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

    <table class="ttd">
        <tr>
            <td width="50%">
                Yang Mengembalikan,<br>
                {{ $managerTitle }} {{ $legalName }}<br><br><br><br><br>
                <strong>{{ $managerName }}</strong>
            </td>
            <td width="50%">
                Yang Menerima Kembali,<br>
                Peminjam<br><br><br><br><br>
                <strong>{{ $borrowerName }}</strong>
            </td>
        </tr>
    </table>
</body>
</html>
