@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerAddress = trim(($borrower?->address?->address_line ?? '').' '.($borrower?->village?->name ?? ''));
    $salutation = ((string) ($person?->gender ?? '')) === 'P' ? 'Ibu' : 'Bpk';

    $loanNumber = $loan_obj->loan_number ?? 'PINJ-' . $loan_obj->id;
    $disbursedAt = $loan_obj->disbursed_at ? CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y') : '—';
    $proposedAt = $loan_obj->proposed_at ? CarbonImmutable::parse($loan_obj->proposed_at)->translatedFormat('d F Y') : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $rate = (float) ($loan_obj->service_rate_total ?? $loan_obj->interest_rate ?? 0);
    $ratePerMonth = $term > 0 ? round($rate / $term, 2) : 0;
    $productName = $loan_obj->product?->name ?? 'Pinjaman Perorangan';

    $managerName = $profile?->manager_name ?? '________________';
    $managerTitle = $profile?->manager_title ?? 'Direktur';

    $today = CarbonImmutable::now();
    $todayLabel = $today->translatedFormat('d F Y');
    $docNo = 'SP2K-' . $loan_obj->id . '/' . $today->format('Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Pemberitahuan Persetujuan Kredit #{{ $loan_obj->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 11pt; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        .num { width: 5%; text-align: center; }
        .label { width: 35%; }
        .sep { width: 3%; text-align: center; }
        .val { width: 57%; }
        .justify { text-align: justify; }
        .header-meta { width: 55%; }
        .header-to { width: 45%; padding-left: 12px; }
        .header-table { margin-bottom: 12px; }
        .signature-block { margin-top: 36px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-meta">
                <table>
                    <tr><td style="width: 60px;">Nomor</td><td style="width: 8px; text-align: center;">:</td><td>{{ $docNo }}</td></tr>
                    <tr><td>Sifat</td><td style="text-align: center;">:</td><td>Penting dan Rahasia</td></tr>
                    <tr><td>Perihal</td><td style="text-align: center;">:</td><td><b>Surat Persetujuan Perjanjian Kredit (SP2K)</b></td></tr>
                    <tr><td>Tanggal</td><td style="text-align: center;">:</td><td>{{ $todayLabel }}</td></tr>
                </table>
            </td>
            <td class="header-to">
                <div>Kepada Yth.</div>
                <div><b>{{ $salutation }} {{ $borrowerName }}</b></div>
                <div>di</div>
                <div>&nbsp;&nbsp;{{ $borrowerAddress ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <div class="justify">
        Dengan ini diberitahukan bahwa sesuai dengan surat permohonan kredit Saudara tanggal {{ $proposedAt }}
        dan setelah diadakan verifikasi serta penilaian, maka {{ $district ?: $legalName }} menyetujui permohonan tersebut
        dengan ketentuan dan syarat-syarat sebagai berikut:
    </div>

    <div style="margin-top: 10px; font-weight: bold;">Fasilitas Kredit</div>

    <table>
        <tr>
            <td class="num">1.</td>
            <td class="label">Jumlah Plafon Kredit</td>
            <td class="sep">:</td>
            <td class="val">{{ $principalFmt }}</td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td class="label">Jangka Waktu Kredit</td>
            <td class="sep">:</td>
            <td class="val">{{ $term }} bulan</td>
        </tr>
        <tr>
            <td class="num">3.</td>
            <td class="label">Jenis Kredit</td>
            <td class="sep">:</td>
            <td class="val">{{ $productName }}</td>
        </tr>
        <tr>
            <td class="num">4.</td>
            <td class="label">Suku Jasa Kredit</td>
            <td class="sep">:</td>
            <td class="val">{{ number_format($ratePerMonth, 2, ',', '.') }} % per bulan</td>
        </tr>
        <tr>
            <td class="num">5.</td>
            <td class="label">Cara Penarikan</td>
            <td class="sep">:</td>
            <td class="val">Sekaligus</td>
        </tr>
        <tr>
            <td class="num">6.</td>
            <td class="label">Cara Pembayaran</td>
            <td class="sep">:</td>
            <td class="val">Pokok dan jasa diangsur sesuai tabel rencana angsuran sebagai bagian yang tidak terpisahkan dari Surat Perjanjian Kredit (SPK)</td>
        </tr>
        <tr>
            <td class="num">7.</td>
            <td class="label">Cara Pengikat Kredit</td>
            <td class="sep">:</td>
            <td class="val">Dibawah tanda tangan</td>
        </tr>
        <tr>
            <td class="num">8.</td>
            <td class="label">Syarat Lainnya</td>
            <td class="sep">:</td>
            <td class="val"></td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td colspan="3" style="padding-left: 12px;">a. Suami/Istri turut menandatangani SPK dan diikat dengan penanggung.</td>
        </tr>
        <tr>
            <td class="num">&nbsp;</td>
            <td colspan="3" style="padding-left: 12px;">b. Tidak memberi imbalan dalam bentuk uang, barang, fasilitas lainnya kepada petugas.</td>
        </tr>
    </table>

    <div class="justify" style="margin-top: 10px;">
        Sebagai tanda persetujuan Saudara, harap surat SP2K ini ditandatangani di atas materai Rp 10.000 dan
        diserahkan kembali kepada {{ $district ?: $legalName }} paling lambat dalam waktu 1 (satu) hari sebelum tanggal
        pencairan ({{ $disbursedAt }}).
    </div>

    <div class="signature-block">
        <table>
            <tr>
                <td style="width: 50%;">&nbsp;</td>
                <td style="width: 50%;">{{ $district ?: $legalName }}, {{ $todayLabel }}</td>
            </tr>
            <tr>
                <td style="text-align: center;">{{ $managerTitle }}</td>
                <td style="text-align: center;">Peminjam</td>
            </tr>
            <tr>
                <td style="height: 60px;">&nbsp;</td>
                <td style="height: 60px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">{{ strtoupper($managerName) }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $borrowerName }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
