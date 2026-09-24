<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Profil Kelembagaan OJK</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 60px; margin-left: 75px; margin-right: 75px; }
        body { font-size: 11px; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 4px; }
        h2 { font-size: 13px; margin: 14px 0 6px; border-bottom: 1px solid #000; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.kv td.label { width: 35%; vertical-align: top; padding: 2px 4px; }
        table.kv td.value { vertical-align: top; padding: 2px 4px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 3px 5px; }
        table.grid th { background: #e6e6e6; text-align: left; }
        .center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .footer-area { margin-top: 24px; }
        .signature-box { width: 33%; text-align: center; display: inline-block; vertical-align: top; padding: 0 4px; }
    </style>
</head>
<body>
    @php
        $id = $identity;
        $legalName = strtoupper($id['legal_name'] ?? '');
        $district = $id['district_name'] ?? '';
        $regency = $id['regency_name'] ?? '';
        $province = $id['province_name'] ?? '';
        $periodLabel = strtoupper($period_label ?? ($period['period_label'] ?? ''));
        $dateFormatted = $date_formatted ?? '';
    @endphp

    <h1>PROFIL LEMBAGA</h1>
    <p class="center" style="margin-bottom: 4px;">
        <strong>{{ $legalName }}</strong>
    </p>
    <p class="center" style="margin-bottom: 4px;">
        {{ $district !== '' ? strtoupper($district) : '' }}{{ $regency !== '' ? ' · ' . strtoupper($regency) : '' }}{{ $province !== '' ? ' · ' . strtoupper($province) : '' }}
    </p>
    <p class="center" style="margin-bottom: 14px;">
        Untuk Periode Yang Berakhir Pada Tanggal {{ $dateFormatted }}
    </p>

    <h2>1. Identitas Lembaga</h2>
    <table class="kv">
        <tr>
            <td class="label">1. Nama Lembaga</td>
            <td class="value">: {{ $id['legal_name'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">2. Nomor Lembaga / Sandi</td>
            <td class="value">: {{ $id['registration_number'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">3. NPWP</td>
            <td class="value">: {{ $id['tax_number'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">4. No. Izin Operasional / NIB</td>
            <td class="value">: {{ $id['registration_number'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">5. Tanggal Mulai Operasi</td>
            <td class="value">: {{ $id['operational_start_date'] ? \Carbon\CarbonImmutable::parse($id['operational_start_date'])->locale('id')->translatedFormat('d F Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">6. Jenis LJK</td>
            <td class="value">: BUMDesma Lembaga Keuangan Desa</td>
        </tr>
    </table>

    <h2>2. Alamat Lengkap</h2>
    <table class="kv">
        <tr>
            <td class="label">a. Alamat</td>
            <td class="value">: {{ $id['address'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">b. Kecamatan</td>
            <td class="value">: {{ $district !== '' ? $district : '—' }}</td>
        </tr>
        <tr>
            <td class="label">c. Kabupaten / Kota</td>
            <td class="value">: {{ $regency !== '' ? $regency : '—' }}</td>
        </tr>
        <tr>
            <td class="label">d. Provinsi</td>
            <td class="value">: {{ $province !== '' ? $province : '—' }}</td>
        </tr>
        <tr>
            <td class="label">e. Telepon</td>
            <td class="value">: {{ $id['phone'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">f. Email</td>
            <td class="value">: {{ $id['email'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">g. Website</td>
            <td class="value">: {{ $id['website'] ?? '—' }}</td>
        </tr>
    </table>

    <h2>3. Susunan Pengurus</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 8%;" class="center">No</th>
                <th>Nama</th>
                <th style="width: 30%;">Jabatan</th>
            </tr>
        </thead>
        <tbody>
            @if (empty($pengurus) || count($pengurus) === 0)
                <tr>
                    <td colspan="3" class="center">— Belum ada data pengurus —</td>
                </tr>
            @else
                @foreach ($pengurus as $idx => $p)
                    <tr>
                        <td class="center">{{ $idx + 1 }}</td>
                        <td>{{ $p['name'] }}</td>
                        <td>{{ $p['role'] }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <h2>4. Wilayah Kerja & Unit Desa</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 8%;" class="center">No</th>
                <th style="width: 15%;">Kode</th>
                <th>Nama Unit</th>
                <th style="width: 15%;">Tipe</th>
                <th>Alamat</th>
            </tr>
        </thead>
        <tbody>
            @if (empty($units) || count($units) === 0)
                <tr>
                    <td colspan="5" class="center">— Belum ada data unit desa —</td>
                </tr>
            @else
                @foreach ($units as $idx => $u)
                    <tr>
                        <td class="center">{{ $idx + 1 }}</td>
                        <td>{{ $u['code'] }}</td>
                        <td>{{ $u['name'] }}</td>
                        <td>{{ $u['type'] ?: '—' }}</td>
                        <td>{{ $u['address'] ?: '—' }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <div class="footer-area">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; width: 50%;"></td>
                <td style="border: none; width: 50%;" class="center">
                    {{ $district !== '' ? $district . ', ' : '' }}{{ $dateFormatted }}
                </td>
            </tr>
            <tr>
                <td style="border: none;"></td>
                <td style="border: none;" class="center">
                    <strong>{{ $legalName }}</strong>
                </td>
            </tr>
            <tr>
                <td style="border: none; height: 60px;"></td>
                <td style="border: none;"></td>
            </tr>
            <tr>
                <td style="border: none;"></td>
                <td style="border: none;" class="center">
                    <strong><u>{{ $id['manager_name'] ?? '' }}</u></strong><br>
                    <span>{{ $id['manager_title'] ?? 'Ketua' }}</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>