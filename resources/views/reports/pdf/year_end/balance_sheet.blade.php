@extends('reports.pdf.layout', ['title' => 'Neraca Tutup Buku', 'identity' => $identity, 'period' => $period])

@section('content')
<style>
    .preview-banner { background: #fff3cd; color: #856404; padding: 6px; text-align: center; font-weight: bold; font-size: 11px; border: 1px solid #ffeeba; margin-bottom: 8px; }
</style>

<div class="preview-banner">
    ⚠️ PREVIEW / SIMULASI — Posisi setelah jurnal tutup buku diposting.
</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>NERACA TUTUP BUKU</b></div>
            <div style="font-size: 16px;"><b>PER {{ strtoupper(\Carbon\Carbon::parse($as_of)->locale('id')->translatedFormat('d F Y')) }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="3"></td></tr>
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td width="50%">Total Aktiva</td>
        <td width="50%" align="right">{{ number_format($totals['assets'], 2) }}</td>
    </tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td>Total Pasiva (Liabilitas + Ekuitas)</td>
        <td align="right">{{ number_format($totals['liabilities_equity'], 2) }}</td>
    </tr>
    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
        <td>Selisih (harus 0)</td>
        <td align="right">{{ number_format($totals['assets'] - $totals['liabilities_equity'], 2) }}</td>
    </tr>
    <tr><td colspan="3" height="3"></td></tr>
    <tr style="background: #000; color: #fff;">
        <td>Kode</td>
        <td>Nama Akun</td>
        <td align="right">Saldo</td>
    </tr>
    <tr><td colspan="3" height="1"></td></tr>
    @foreach($sections as $l1)
        <tr style="background: rgb(74, 74, 74); color: #fff;">
            <td colspan="3" align="center" height="20"><b>{{ $l1['code'] }}. {{ $l1['name'] }}</b></td>
        </tr>
        @foreach($l1['children'] as $l2)
            <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                <td>{{ $l2['code'] }}</td>
                <td colspan="2">{{ $l2['name'] }}</td>
            </tr>
            @foreach($l2['children'] as $idx => $l3)
                <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                    <td>{{ $l3['code'] }}</td>
                    <td>{{ $l3['name'] }}</td>
                    <td align="right">
                        @if($l3['balance'] < 0)
                            ({{ number_format(abs($l3['balance']), 2) }})
                        @else
                            {{ number_format($l3['balance'], 2) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        @endforeach
        @php
            $sectionLabel = match ($l1['account_type'] ?? '') {
                'asset' => 'Jumlah Aset',
                'liability' => 'Jumlah Utang',
                'equity' => 'Jumlah Modal',
                default => 'Jumlah '.$l1['name'],
            };
        @endphp
        <tr>
            <td colspan="3" style="padding: 0px !important;">
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                        <td colspan="2">{{ $sectionLabel }}</td>
                        <td align="right">
                            @if(($l1['balance'] ?? 0) < 0)
                                ({{ number_format(abs($l1['balance']), 2) }})
                            @else
                                {{ number_format($l1['balance'] ?? 0, 2) }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td colspan="2">Jumlah Liabilitas + Ekuitas</td>
        <td align="right">{{ number_format($totals['liabilities_equity'], 2) }}</td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 6px;">
    <tr><td style="padding: 4px; background: rgb(232, 232, 232);">
        Saldo Laba Ditahan (termasuk laba tahun ini):
        <strong>{{ number_format($totals['retained_balance'], 2) }}</strong>
        · Laba Tahun Berjalan (sudah dipindah):
        <strong>{{ number_format($totals['net_income'], 2) }}</strong>
    </td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
    <tr><td style="padding: 6px; background: #fff3cd; font-style: italic; color: #856404;">
        <strong>Catatan:</strong> Neraca ini adalah PREVIEW/SIMULASI yang ditampilkan SETELAH jurnal tutup
        buku diposting. Akun nominal (pendapatan &amp; beban) sudah dinolkan dan laba tahun berjalan sudah
        direklas ke Laba Ditahan.
    </td></tr>
</table>
@endsection