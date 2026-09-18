@php
    use Carbon\CarbonImmutable;

    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = $profile?->district_name ?? '';
    $address = $profile?->address ?? '';
    $phone = $profile?->phone ?? '';

    $group = $loan->borrower?->group;
    $groupName = strtoupper($group?->name ?? '-');
    $groupCode = $group?->code ?? '';
    $groupAddress = $group?->address ?? '';
    $village = $group?->village;
    $villageName = $village?->name ?? '';

    $loanNumber = $loan->loan_number ?? 'PINJ-' . $loan->id;
    $spkNo = $loan->spk_no ?? null;
    $disbursedAt = $loan->disbursed_at ? CarbonImmutable::parse($loan->disbursed_at) : null;
    $completedAt = $loan->completed_at ? CarbonImmutable::parse($loan->completed_at) : ($as_of ? CarbonImmutable::parse($as_of) : CarbonImmutable::now());

    $chair = $loan->committee->firstWhere('position', 'chair');
    $secretary = $loan->committee->firstWhere('position', 'secretary');
    $treasurer = $loan->committee->firstWhere('position', 'treasurer');

    $productCode = strtoupper($loan->product?->code ?? '');
    $productName = $loan->product?->name ?? '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Keterangan Lunas Kelompok #{{ $loan->id }}</title>
    <style>
        * { font-family: 'Times New Roman', Times, serif; }
        body { font-size: 12pt; line-height: 1.5; }
        .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px; margin-bottom: 24px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 2px 0; font-size: 10pt; }
        .judul { text-align: center; font-size: 13pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 24px 0 16px; }
        .tabel { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .tabel td { padding: 4px 8px; vertical-align: top; }
        .ttd { width: 100%; margin-top: 48px; }
        .ttd td { text-align: center; }
        .anggota-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 10pt; }
        .anggota-table th, .anggota-table td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        .anggota-table th { background: #f0f0f0; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $legalName }} {{ $district ? '· ' . strtoupper($district) : '' }}</h1>
        <p>{{ $address }}</p>
        @if ($phone)
            <p>Telp. {{ $phone }}</p>
        @endif
    </div>

    <div class="judul">
        Surat Keterangan Pelunasan Pinjaman Kelompok
    </div>

    <p style="text-align: justify;">
        Yang bertanda tangan di bawah ini, pengurus {{ $legalName }}, dengan ini menerangkan bahwa:
    </p>

    <table class="tabel">
        <tr>
            <td width="180">Nama Kelompok</td>
            <td width="10">:</td>
            <td><strong>{{ $groupName }}</strong></td>
        </tr>
        <tr>
            <td>Kode Kelompok</td>
            <td>:</td>
            <td>{{ $groupCode }}</td>
        </tr>
        <tr>
            <td>Desa</td>
            <td>:</td>
            <td>{{ $villageName }}</td>
        </tr>
        <tr>
            <td>Alamat Kelompok</td>
            <td>:</td>
            <td>{{ $groupAddress }}</td>
        </tr>
        <tr>
            <td>No. Pinjaman</td>
            <td>:</td>
            <td>{{ $loanNumber }}</td>
        </tr>
        @if ($spkNo)
            <tr>
                <td>No. SPK</td>
                <td>:</td>
                <td>{{ $spkNo }}</td>
            </tr>
        @endif
        <tr>
            <td>Produk Pinjaman</td>
            <td>:</td>
            <td>{{ $productName }} ({{ $productCode }})</td>
        </tr>
        <tr>
            <td>Tanggal Pencairan</td>
            <td>:</td>
            <td>{{ $disbursedAt?->translatedFormat('d F Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td>Plafon</td>
            <td>:</td>
            <td>Rp {{ number_format((float) $loan->principal_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Jangka</td>
            <td>:</td>
            <td>{{ $loan->term_months }} bulan</td>
        </tr>
    </table>

    @if ($loan->beneficiaries->isNotEmpty())
        <p>Daftar anggota penerima manfaat:</p>
        <table class="anggota-table">
            <thead>
                <tr>
                    <th width="30">No.</th>
                    <th>Nama Anggota</th>
                    <th width="120">NIK</th>
                    <th width="120">Alokasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($loan->beneficiaries as $i => $b)
                    @php
                        $person = $b->member?->person;
                    @endphp
                    <tr>
                        <td align="center">{{ $i + 1 }}</td>
                        <td>{{ $person?->full_name ?? '—' }}</td>
                        <td>{{ $person?->national_identity_number ?? '—' }}</td>
                        <td align="right">Rp {{ number_format((float) ($b->allocated_amount ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="text-align: justify;">
        Telah <strong>LUNAS</strong> dilunasi seluruhnya pada tanggal
        <strong>{{ $completedAt->translatedFormat('d F Y') }}</strong>.
        Dengan demikian, pinjaman atas nama kelompok tersebut telah ditutup secara resmi dan tidak
        memiliki tunggakan dalam bentuk apapun kepada {{ $legalName }}.
    </p>

    <p>
        Surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.
    </p>

    <table class="ttd">
        <tr>
            <td width="50%">
                Mengetahui,<br>
                Ketua Kelompok<br>
                <div style="height: 60px;"></div>
                <strong>{{ $chair?->member_name_snapshot ?? '________________' }}</strong>
            </td>
            <td width="50%">
                {{ $district ? strtoupper($district) . ', ' : '' }}{{ $completedAt->translatedFormat('d F Y') }}<br>
                {{ $legalName }}<br>
                <div style="height: 60px;"></div>
                <strong>Direktur / Pengurus</strong>
            </td>
        </tr>
    </table>
</body>
</html>
