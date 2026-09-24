@extends('reports.pdf.layout', ['title' => 'Laba Rugi Tutup Buku', 'identity' => $identity, 'period' => $period])

@section('content')
<style>
    .preview-banner { background: #fff3cd; color: #856404; padding: 6px; text-align: center; font-weight: bold; font-size: 11px; border: 1px solid #ffeeba; margin-bottom: 8px; }
</style>

<div class="preview-banner">
    ⚠️ PREVIEW / SIMULASI — Ringkasan akhir tahun sebelum tutup buku.
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="2" align="center">
            <div style="font-size: 18px;"><b>LAPORAN LABA RUGI TUTUP BUKU</b></div>
            <div style="font-size: 16px;"><b>TAHUN {{ $year }}</b></div>
        </td>
    </tr>
    <tr><td colspan="2" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold; font-size: 12px;">
        <td align="center" width="70%" height="16">Rekening</td>
        <td align="center" width="30%">Tahun {{ $year }}</td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tbody>
    @foreach($groups as $group)
        <tr style="background: rgb(150, 150, 150); font-weight: bold;">
            <td colspan="2" height="14" style="padding: 2px 4px;">{{ $group['code'] }}. {{ $group['name'] }}</td>
        </tr>
        @foreach($group['children'] as $idx => $row)
            <tr style="background: {{ $idx % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                <td align="left" style="padding: 1px 4px 1px 16px;">{{ $row['code'] }}. {{ $row['name'] }}</td>
                <td align="right">{{ number_format($row['ytd'], 2) }}</td>
            </tr>
        @endforeach
        <tr style="background: rgb(150, 150, 150); font-weight: bold;">
            <td align="left" height="14" style="padding: 2px 4px;">Jumlah {{ $group['code'] }}. {{ $group['name'] }}</td>
            <td align="right">{{ number_format($group['ytd'], 2) }}</td>
        </tr>
    @endforeach

    <tr><td colspan="2" height="2"></td></tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td align="left">A. Laba Rugi OPERASIONAL (Pendapatan Ops − Beban Ops)</td>
        <td align="right">{{ number_format($summary['operating'], 2) }}</td>
    </tr>

    <tr><td colspan="2" height="2"></td></tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td align="left">B. Laba Rugi NON OPERASIONAL</td>
        <td align="right">{{ number_format($summary['non_operating'], 2) }}</td>
    </tr>

    <tr><td colspan="2" height="2"></td></tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td align="left">C. Laba Rugi Sebelum Taksiran Pajak (A + B)</td>
        <td align="right">{{ number_format($summary['before_tax'], 2) }}</td>
    </tr>

    <tr><td colspan="2" height="2"></td></tr>
    <tr style="background: rgb(150, 150, 150); font-weight: bold;">
        <td colspan="2" height="14">Beban Pajak</td>
    </tr>
    <tr style="background: rgb(230, 230, 230);">
        <td align="left">Taksiran Pajak</td>
        <td align="right">{{ number_format($summary['tax'], 2) }}</td>
    </tr>

    <tr><td colspan="2" height="2"></td></tr>
    <tr>
        <td colspan="2" style="padding: 0px !important;">
            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                <tr style="background: rgb(150, 150, 150); color: #fff; font-weight: bold;">
                    <td width="70%" align="left">D. Laba (Rugi) Bersih</td>
                    <td width="30%" align="right">{{ number_format($summary['after_tax'], 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>

    <tr><td colspan="2" height="5"></td></tr>
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td>KPI: Total Pendapatan</td>
        <td align="right">{{ number_format($kpi['total_revenue'], 2) }}</td>
    </tr>
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td>KPI: Total Beban</td>
        <td align="right">{{ number_format($kpi['total_expense'], 2) }}</td>
    </tr>
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td>KPI: Margin</td>
        <td align="right">{{ number_format($kpi['margin_pct'], 2) }}%</td>
    </tr>
    </tbody>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
    <tr><td style="padding: 6px; background: #fff3cd; font-style: italic; color: #856404;">
        <strong>Catatan:</strong> Laba Rugi tutup buku menampilkan ringkasan akhir tahun. Setelah jurnal
        tutup buku diposting, akun nominal (pendapatan &amp; beban) bersaldo 0 dan saldonya pindah ke
        Ikhtisar Laba Rugi lalu ke Laba Ditahan.
    </td></tr>
</table>
@endsection