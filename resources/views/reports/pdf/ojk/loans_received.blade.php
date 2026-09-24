@php
    $id = $identity;
    $legalName = strtoupper($id['legal_name'] ?? '');
    $periodLabel = strtoupper($period_label ?? '');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>DRPY — Rincian Pinjaman Diterima</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 50px; margin-left: 60px; margin-right: 60px; }
        body { font-size: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .title { text-align: center; }
        .title .big { font-size: 14px; font-weight: bold; }
        .title .med { font-size: 12px; font-weight: bold; }
        .title .sub { font-size: 11px; }
        th, td { border: 1px solid #000; padding: 3px 5px; }
        th { background: rgb(74, 74, 74); color: #fff; font-weight: bold; text-align: center; }
        .zebra-odd { background: rgb(230, 230, 230); }
        .zebra-even { background: rgb(255, 255, 255); }
        tfoot td { background: rgb(167, 167, 167); font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .info-row td { border: none; padding: 1px 4px; }
    </style>
</head>
<body>
    <table border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td colspan="11" class="center">
                <div class="title">
                    <div style="font-size: 13px;"><b>{{ $legalName }}</b></div>
                    <div class="big">DAFTAR RINCIAN PINJAMAN YANG DITERIMA (DRPY)</div>
                    <div class="med">Periode {{ $periodLabel }}</div>
                </div>
            </td>
        </tr>
        <tr><td colspan="11" height="6"></td></tr>
        <tr class="info-row">
            <td style="width: 18%;">Nama Lembaga</td>
            <td colspan="10">: {{ $id['legal_name'] ?? '' }}</td>
        </tr>
        <tr class="info-row">
            <td>Periode Laporan</td>
            <td colspan="10">: {{ $periodLabel }}</td>
        </tr>
        <tr class="info-row">
            <td>Status Filter</td>
            <td colspan="10">: {{ strtoupper($status ?? 'all') }} · Kreditur: {{ strtoupper($creditor_type ?? 'all') }}</td>
        </tr>
        <tr><td colspan="11" height="6"></td></tr>
    </table>

    <table border="1" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 18%;">Kreditur / Jenis</th>
                <th style="width: 12%;">No. Kontrak</th>
                <th style="width: 9%;">Tgl Kontrak</th>
                <th style="width: 11%;" class="right">Pokok</th>
                <th style="width: 11%;" class="right">Sisa Pokok</th>
                <th style="width: 9%;">Bunga</th>
                <th style="width: 8%;">Tenor</th>
                <th style="width: 9%;">Jatuh Tempo</th>
                <th style="width: 9%;">Status</th>
                <th style="width: 10%;">Tujuan</th>
            </tr>
        </thead>
        <tbody>
            @if (empty($rows) || count($rows) === 0)
                <tr>
                    <td colspan="11" class="center">— Belum ada data pinjaman diterima —</td>
                </tr>
            @else
                @foreach ($rows as $idx => $r)
                    @php $bg = $idx % 2 === 0 ? 'zebra-even' : 'zebra-odd'; @endphp
                    <tr class="{{ $bg }}">
                        <td class="center">{{ $idx + 1 }}</td>
                        <td>
                            <b>{{ $r['creditor_name'] }}</b>
                            <div style="font-size: 9px; color: #555;">{{ $r['creditor_type_label'] ?? '' }}</div>
                        </td>
                        <td>{{ $r['contract_number'] }}</td>
                        <td class="center">
                            {{ $r['contract_date'] ? \Carbon\CarbonImmutable::parse($r['contract_date'])->locale('id')->translatedFormat('d-m-Y') : '—' }}
                        </td>
                        <td class="right">{{ number_format($r['principal_amount'], 2) }}</td>
                        <td class="right">{{ number_format($r['principal_remaining'], 2) }}</td>
                        <td class="center">{{ number_format($r['interest_rate'], 2) }}% / {{ $r['interest_type'] }}</td>
                        <td class="center">{{ $r['tenor_remaining_months'] ?? 0 }}/{{ $r['tenor_months'] }} bln</td>
                        <td class="center">
                            {{ $r['due_date'] ? \Carbon\CarbonImmutable::parse($r['due_date'])->locale('id')->translatedFormat('d-m-Y') : '—' }}
                        </td>
                        <td class="center">{{ $r['status_label'] }}</td>
                        <td>{{ $r['purpose'] ?? '—' }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="center">TOTAL</td>
                <td class="right">{{ number_format($totals['principal_total'], 2) }}</td>
                <td class="right">{{ number_format($totals['principal_remaining_total'], 2) }}</td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top: 8px; font-size: 9px;">
        <i>Keterangan: DRPY adalah laporan rincian pinjaman dari pihak ketiga yang diterima oleh
        {{ $legalName }}. Disusun untuk memenuhi kewajiban pelaporan OJK. Posisi per {{ $as_of }}.</i>
    </p>
</body>
</html>