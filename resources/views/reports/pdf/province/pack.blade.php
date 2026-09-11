@php
    $province = strtoupper($province_name ?? 'PROVINSI');
    $periodLabel = $pack['period']['period_label'] ?? "Tahun {$year}";
    $bs = $pack['balance_sheet'] ?? [];
    $is = $pack['income_statement'] ?? [];
    $cf = $pack['cash_flow'] ?? [];
    $eq = $pack['equity_changes'] ?? [];
    $calk = $pack['calk'] ?? [];

    $fmt = fn($val) => number_format((float)$val, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Laporan Keuangan Konsolidasi {{ $province }} - {{ $periodLabel }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; box-sizing: border-box; }
        html, body { margin: 0; padding: 75.59px 94.48px; font-size: 12px; color: #111; }
        .break { page-break-after: always; clear: both; }
        .cover { text-align: center; padding-top: 120px; }
        .cover h1 { font-size: 20px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .cover h2 { font-size: 16px; font-weight: normal; margin-bottom: 25px; color: #333; }
        .cover h3 { font-size: 14px; font-weight: bold; margin-top: 40px; text-transform: uppercase; color: #000; }
        .header-box { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 20px; text-align: center; }
        .header-box h2 { margin: 0; font-size: 15px; text-transform: uppercase; }
        .header-box p { margin: 3px 0 0 0; font-size: 11px; color: #444; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        table.data-table th, table.data-table td { border: 1px solid #000; padding: 2px 4px; font-size: 10px; }
        table.data-table th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .section-title { font-size: 12px; font-weight: bold; margin-top: 15px; margin-bottom: 5px; text-transform: uppercase; background: #eaeaea; padding: 4px 6px; }
        .summary-box { border: 1px solid #444; background: #fafafa; padding: 10px; margin-top: 15px; }
        .signature-table { width: 100%; margin-top: 40px; border-collapse: collapse; }
        .signature-table td { border: none; text-align: center; vertical-align: top; padding: 10px; }
    </style>
</head>
<body>

    <!-- 1. COVER PAGE -->
    <div class="cover break">
        <div style="font-size: 14px; font-weight: bold; letter-spacing: 1px; color: #555;">SISTEM INFORMASI LEMBAGA KEUANGAN DESA (siupk)</div>
        <h1>LAPORAN KEUANGAN KONSOLIDASI</h1>
        <h2>TINGKAT PROVINSI {{ $province }}</h2>
        
        <div style="margin: 60px 0;">
            <div style="font-size: 13px; color: #444;">Periode Pelaporan:</div>
            <div style="font-size: 18px; font-weight: bold; margin-top: 5px;">{{ $periodLabel }}</div>
        </div>

        <h3>TIM SUPERVISI PROVINSI {{ $province }}</h3>
        <p style="font-size: 11px; color: #666; margin-top: 100px;">Dokumen ini dihasilkan secara otomatis oleh Sistem siupk Platform</p>
    </div>

    <!-- 2. NERACA KONSOLIDASI -->
    <div class="break">
        <div class="header-box">
            <h2>LAPORAN NERACA KONSOLIDASI</h2>
            <p>PROVINSI {{ $province }} — PERIODE {{ $periodLabel }}</p>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="15%">Kode Akun</th>
                    <th width="55%">Nama Akun / Komponen</th>
                    <th width="30%">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bold" style="background-color: #f9f9f9;">
                    <td colspan="3">ASET</td>
                </tr>
                @foreach($bs['assets']['rows'] ?? [] as $row)
                    <tr>
                        <td class="{{ $row['level'] === 1 ? 'bold' : '' }}">{{ $row['code'] }}</td>
                        <td style="padding-left: {{ ($row['level'] - 1) * 15 + 7 }}px;" class="{{ $row['level'] === 1 ? 'bold' : '' }}">{{ $row['name'] }}</td>
                        <td class="text-right {{ $row['level'] === 1 ? 'bold' : '' }}">{{ $fmt($row['balance']) }}</td>
                    </tr>
                @endforeach
                <tr class="bold" style="background-color: #eef6ff;">
                    <td colspan="2" class="text-right">TOTAL ASET</td>
                    <td class="text-right">{{ $fmt($bs['assets']['total'] ?? 0) }}</td>
                </tr>

                <tr class="bold" style="background-color: #f9f9f9;">
                    <td colspan="3">KEWAJIBAN & EKUITAS</td>
                </tr>
                @foreach($bs['liabilities']['rows'] ?? [] as $row)
                    <tr>
                        <td class="{{ $row['level'] === 1 ? 'bold' : '' }}">{{ $row['code'] }}</td>
                        <td style="padding-left: {{ ($row['level'] - 1) * 15 + 7 }}px;" class="{{ $row['level'] === 1 ? 'bold' : '' }}">{{ $row['name'] }}</td>
                        <td class="text-right {{ $row['level'] === 1 ? 'bold' : '' }}">{{ $fmt($row['balance']) }}</td>
                    </tr>
                @endforeach
                <tr class="bold" style="background-color: #f4f4f4;">
                    <td colspan="2" class="text-right">TOTAL KEWAJIBAN</td>
                    <td class="text-right">{{ $fmt($bs['liabilities']['total'] ?? 0) }}</td>
                </tr>

                @foreach($bs['equity']['rows'] ?? [] as $row)
                    <tr>
                        <td class="{{ $row['level'] === 1 ? 'bold' : '' }}">{{ $row['code'] }}</td>
                        <td style="padding-left: {{ ($row['level'] - 1) * 15 + 7 }}px;" class="{{ $row['level'] === 1 ? 'bold' : '' }}">{{ $row['name'] }}</td>
                        <td class="text-right {{ $row['level'] === 1 ? 'bold' : '' }}">{{ $fmt($row['balance']) }}</td>
                    </tr>
                @endforeach
                <tr class="bold" style="background-color: #f4f4f4;">
                    <td colspan="2" class="text-right">TOTAL EKUITAS</td>
                    <td class="text-right">{{ $fmt($bs['equity']['total'] ?? 0) }}</td>
                </tr>

                <tr class="bold" style="background-color: #eef6ff;">
                    <td colspan="2" class="text-right">TOTAL KEWAJIBAN & EKUITAS</td>
                    <td class="text-right">{{ $fmt($bs['total_liabilities_and_equity'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box text-center bold">
            Status Keseimbangan: {{ ($bs['is_balanced'] ?? false) ? 'SEIMBANG (BALANCED)' : 'TIDAK SEIMBANG (Selisih: Rp ' . $fmt($bs['difference'] ?? 0) . ')' }}
        </div>
    </div>

    <!-- 3. LABA RUGI KONSOLIDASI -->
    <div class="break">
        <div class="header-box">
            <h2>LAPORAN LABA RUGI KONSOLIDASI</h2>
            <p>PROVINSI {{ $province }} — PERIODE {{ $periodLabel }}</p>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="15%">Kode Akun</th>
                    <th width="55%">Uraian Pendapatan & Beban</th>
                    <th width="30%">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bold" style="background-color: #f9f9f9;"><td colspan="3">PENDAPATAN OPERASIONAL</td></tr>
                @foreach($is['revenue_ops']['rows'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['code'] }}</td>
                        <td style="padding-left: {{ ($row['level'] - 1) * 15 + 7 }}px;">{{ $row['name'] }}</td>
                        <td class="text-right">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="bold" style="background-color: #f4f4f4;"><td colspan="2" class="text-right">SUBTOTAL PENDAPATAN OPERASIONAL</td><td class="text-right">{{ $fmt($is['revenue_ops']['total'] ?? 0) }}</td></tr>

                <tr class="bold" style="background-color: #f9f9f9;"><td colspan="3">BEBAN OPERASIONAL</td></tr>
                @foreach($is['expense_ops']['rows'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['code'] }}</td>
                        <td style="padding-left: {{ ($row['level'] - 1) * 15 + 7 }}px;">{{ $row['name'] }}</td>
                        <td class="text-right">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="bold" style="background-color: #f4f4f4;"><td colspan="2" class="text-right">SUBTOTAL BEBAN OPERASIONAL</td><td class="text-right">{{ $fmt($is['expense_ops']['total'] ?? 0) }}</td></tr>

                <tr class="bold" style="background-color: #eef6ff;"><td colspan="2" class="text-right">LABA OPERASIONAL</td><td class="text-right">{{ $fmt($is['summary']['operating_profit']['ytd'] ?? 0) }}</td></tr>

                <tr class="bold" style="background-color: #f9f9f9;"><td colspan="3">PENDAPATAN / (BEBAN) NON OPERASIONAL</td></tr>
                @foreach($is['revenue_non']['rows'] ?? [] as $row)
                    <tr><td>{{ $row['code'] }}</td><td style="padding-left: 15px;">{{ $row['name'] }}</td><td class="text-right">{{ $fmt($row['amount']) }}</td></tr>
                @endforeach
                @foreach($is['expense_non']['rows'] ?? [] as $row)
                    <tr><td>{{ $row['code'] }}</td><td style="padding-left: 15px;">{{ $row['name'] }}</td><td class="text-right">({{ $fmt($row['amount']) }})</td></tr>
                @endforeach

                <tr class="bold" style="background-color: #eef6ff;"><td colspan="2" class="text-right">LABA SEBELUM PAJAK</td><td class="text-right">{{ $fmt($is['summary']['before_tax']['ytd'] ?? 0) }}</td></tr>

                @if(!empty($is['tax']['rows']))
                    @foreach($is['tax']['rows'] as $row)
                        <tr><td>{{ $row['code'] }}</td><td style="padding-left: 15px;">{{ $row['name'] }}</td><td class="text-right">({{ $fmt($row['amount']) }})</td></tr>
                    @endforeach
                @endif

                <tr class="bold" style="background-color: #d9ebd9; font-size: 11px;"><td colspan="2" class="text-right">LABA BERSIH (NET PROFIT)</td><td class="text-right">{{ $fmt($is['summary']['after_tax']['ytd'] ?? 0) }}</td></tr>
            </tbody>
        </table>
    </div>

    <!-- 4. ARUS KAS KONSOLIDASI -->
    <div class="break">
        <div class="header-box">
            <h2>LAPORAN ARUS KAS KONSOLIDASI</h2>
            <p>PROVINSI {{ $province }} — PERIODE {{ $periodLabel }}</p>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="70%">Aktivitas Arus Kas</th>
                    <th width="30%">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Arus Kas dari Aktivitas Operasional</td>
                    <td class="text-right">{{ $fmt($cf['operating_activities'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Arus Kas dari Aktivitas Investasi</td>
                    <td class="text-right">{{ $fmt($cf['investing_activities'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Arus Kas dari Aktivitas Pendanaan / Pembiayaan</td>
                    <td class="text-right">{{ $fmt($cf['financing_activities'] ?? 0) }}</td>
                </tr>
                <tr class="bold" style="background-color: #f4f4f4;">
                    <td>KENAIKAN / (PENURUNAN) BERSIH KAS & BANK</td>
                    <td class="text-right">{{ $fmt($cf['net_cash_change'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Saldo Kas & Bank Awal Periode</td>
                    <td class="text-right">{{ $fmt($cf['opening_cash'] ?? 0) }}</td>
                </tr>
                <tr class="bold" style="background-color: #d9ebd9;">
                    <td>SALDO KAS & BANK AKHIR PERIODE</td>
                    <td class="text-right">{{ $fmt($cf['ending_cash'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 5. PERUBAHAN EKUITAS KONSOLIDASI -->
    <div class="break">
        <div class="header-box">
            <h2>LAPORAN PERUBAHAN EKUITAS KONSOLIDASI</h2>
            <p>PROVINSI {{ $province }} — PERIODE {{ $periodLabel }}</p>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="70%">Komponen Ekuitas</th>
                    <th width="30%">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ekuitas Awal Periode</td>
                    <td class="text-right">{{ $fmt($eq['opening_equity'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Laba Bersih Tahun / Periode Berjalan</td>
                    <td class="text-right">{{ $fmt($eq['net_income'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Penambahan Modal / Penyertaan Modal Desa</td>
                    <td class="text-right">{{ $fmt($eq['additions'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Penyaluran Alokasi Hasil Usaha / Prive</td>
                    <td class="text-right">({{ $fmt($eq['withdrawals'] ?? 0) }})</td>
                </tr>
                <tr class="bold" style="background-color: #d9ebd9;">
                    <td>TOTAL EKUITAS AKHIR PERIODE</td>
                    <td class="text-right">{{ $fmt($eq['ending_equity'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 6. CALK (CATATAN ATAS LAPORAN KEUANGAN) -->
    <div>
        <div class="header-box">
            <h2>CATATAN ATAS LAPORAN KEUANGAN (CALK)</h2>
            <p>PROVINSI {{ $province }} — PERIODE {{ $periodLabel }}</p>
        </div>

        <div class="section-title">1. Gambaran Umum Entitas</div>
        <p>{{ $calk['general_notes'] ?? 'Laporan konsolidasi menyajikan kinerja keuangan gabungan seluruh unit kerja.' }}</p>

        <div class="section-title">2. Kebijakan Akuntansi</div>
        <p>{{ $calk['accounting_policies'] ?? 'Disajikan sesuai SAK ETAP.' }}</p>

        <div class="section-title">3. Cakupan Konsolidasi</div>
        <p>Laporan konsolidasi ini mencakup <b>{{ $calk['tenants_count'] ?? 0 }}</b> Unit Pengelola / BUMDesma di bawah koordinasi Provinsi {{ $province }}.</p>

        <table class="signature-table">
            <tr>
                <td width="50%">
                    <p>Disetujui Oleh,<br><b>Ketua Tim Supervisor Provinsi</b></p>
                    <br><br><br>
                    <p><b>( _______________________ )</b></p>
                </td>
                <td width="50%">
                    <p>Disusun Oleh,<br><b>Analis Keuangan Provinsi</b></p>
                    <br><br><br>
                    <p><b>( _______________________ )</b></p>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
