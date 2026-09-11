@extends('reports.pdf.layout', ['title' => 'Catatan Atas Laporan Keuangan (CALK)', 'identity' => $identity, 'period' => $period])

@section('content')
<style>
    ol, ul { margin-left: unset; }
    .pointA *:first-child { margin-top: 0; }
</style>
<table border="0" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>CATATAN ATAS LAPORAN KEUANGAN</b></div>
            <div style="font-size: 18px; text-transform: uppercase;"><b>{{ $identity['short_name'] ?? $identity['legal_name'] ?? config('app.name') }}</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
</table>

<ol style="list-style: upper-alpha;">
    <li>
        <div style="text-transform: uppercase;">Gambaran Umum</div>
        <div style="text-align: justify;">
            {{ $identity['short_name'] ?? $identity['legal_name'] ?? config('app.name') }} adalah Badan Usaha Milik Desa
            Bersama (Bumdesma Lkd) yang mengelola Dana Bergulir Masyarakat (DBM) melalui produk usahanya. Laporan
            Keuangan ini disusun dengan basis pencatatan akrual dan disajikan untuk periode
            {{ $period['period_label'] ?? '' }}.
        </div>
    </li>

    <li>
        <div style="text-transform: uppercase;">Ringkasan Posisi Keuangan</div>
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <thead>
                <tr style="background: rgb(74, 74, 74); color: #fff; font-weight: bold;">
                    <td height="20" align="center">Kode</td>
                    <td align="center">Nama Akun</td>
                    <td align="center" width="20%">Saldo</td>
                </tr>
            </thead>
            <tbody>
                @foreach($highlights as $idx => $h)
                    <tr style="background: {{ $idx % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                        <td align="center">{{ $idx + 1 }}</td>
                        <td>{{ $h['label'] }}</td>
                        <td align="right">
                            @if(($h['amount'] ?? 0) < 0)
                                ({{ number_format(abs($h['amount']), 2) }})
                            @else
                                {{ number_format($h['amount'], 2) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </li>

    <li>
        <div style="text-transform: uppercase;">Kebijakan Akuntansi</div>
        <ol style="list-style: lower-alpha;">
            @foreach($policies as $p)
                <li style="text-align: justify;">{{ $p }}</li>
            @endforeach
        </ol>
    </li>

    <li>
        <div style="text-transform: uppercase;">Lain Lain</div>
        <div style="text-align: justify;">
            {{ $notes !== '' ? $notes : 'Tidak ada catatan tambahan.' }}
        </div>
    </li>

    <li style="margin-top: 12px;">
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td align="justify">
                    <div style="text-transform: uppercase;">Penutup</div>
                    <div style="text-align: justify;">
                        Catatan atas Laporan Keuangan (CALK) ini merupakan bagian tidak terpisahkan dari Laporan Keuangan
                        {{ $identity['short_name'] ?? $identity['legal_name'] ?? config('app.name') }} untuk periode
                        {{ $period['period_label'] ?? '' }}. Selanjutnya CALK ini diharapkan dapat berguna bagi
                        pihak-pihak yang berkepentingan (stakeholders) serta memenuhi prinsip-prinsip transparansi,
                        akuntabilitas, pertanggungjawaban, independensi, dan fairness dalam pengelolaan keuangan.
                    </div>
                </td>
            </tr>
        </table>
    </li>
</ol>
@endsection
