@php
    /** @var array<string, mixed> $identity */
    /** @var array<string, mixed> $period */
    /** @var list<array<string, mixed>> $rows */
    /** @var array<string, mixed> $totals */

    $fmt = static function (float|int|null $v): string {
        $v = (float) ($v ?? 0);

        return $v < 0
            ? '('.number_format(abs($v), 2).')'
            : number_format($v, 2);
    };

    $pct = static function (float|int|null $v): string {
        $v = (float) ($v ?? 0);

        return number_format($v, 2).'%';
    };

    $isYtd = (bool) ($period['is_ytd'] ?? false);
    $periodLabel = (string) ($period['label'] ?? '');
    $rangeLabel = (string) ($period['range_label'] ?? '');
    $generatedAt = $generated_at ?? null;

    $grouped = [
        'revenue' => ['label' => 'PENDAPATAN', 'rows' => [], 'totals' => null],
        'expense' => ['label' => 'BEBAN', 'rows' => [], 'totals' => null],
    ];
    foreach ($rows as $r) {
        $t = (string) ($r['account_type'] ?? '');
        if (isset($grouped[$t])) {
            $grouped[$t]['rows'][] = $r;
        }
    }
    $grouped['revenue']['totals'] = $totals['revenue'] ?? null;
    $grouped['expense']['totals'] = $totals['expense'] ?? null;
@endphp
@extends('reports.pdf.layout', [
    'title' => 'E-Budgeting '.($periodLabel !== '' ? $periodLabel : ''),
    'identity' => $identity,
    'period' => ['period_label' => $rangeLabel !== '' ? $rangeLabel : $periodLabel],
])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px;">
    <tr>
        <td colspan="9" align="center">
            <div style="font-size: 18px;"><b>E-BUDGETING (RENCANA vs REALISASI)</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($periodLabel) }}</b></div>
            @if($rangeLabel !== '' && $rangeLabel !== $periodLabel)
                <div style="font-size: 11px; color: #555;"><i>{{ $rangeLabel }}</i></div>
            @endif
            <div style="font-size: 9px; color: #555;">
                Dicetak: {{ $generatedAt ? \Carbon\Carbon::parse($generatedAt)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
    <tr><td colspan="9" height="6"></td></tr>
</table>

@if(empty($rows))
    <p style="text-align: center; font-size: 11px; color: #555;">
        Belum ada akun anggaran untuk periode ini. Pastikan rencana anggaran sudah diinput di modul Budgeting.
    </p>
@else
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9.5px;">
        <thead>
            <tr style="background: #000; color: #fff; font-weight: bold;">
                <td rowspan="2" width="3%" align="center" height="22">No</td>
                <td rowspan="2" width="8%" align="center">Kode</td>
                <td rowspan="2" width="22%">Nama Akun</td>
                <td colspan="4" align="center">{{ $isYtd ? 'Sampai Triwulan Berjalan' : 'Triwulan Ini' }}</td>
                <td colspan="4" align="center">Year-to-Date (s/d Triwulan)</td>
            </tr>
            <tr style="background: rgb(74, 74, 74); color: #fff; font-weight: bold;">
                <td width="8%" align="right">Anggaran</td>
                <td width="8%" align="right">Realisasi</td>
                <td width="7%" align="right">Selisih</td>
                <td width="6%" align="right">%</td>
                <td width="8%" align="right">Anggaran</td>
                <td width="8%" align="right">Realisasi</td>
                <td width="7%" align="right">Selisih</td>
                <td width="6%" align="right">%</td>
            </tr>
        </thead>
        <tbody>
            @php $rowNo = 1; @endphp
            @foreach($grouped as $bucket)
                @if(count($bucket['rows']) > 0)
                    <tr style="background: rgb(150, 150, 150); color: #fff; font-weight: bold;">
                        <td colspan="9" height="16">{{ $bucket['label'] }}</td>
                    </tr>
                    @foreach($bucket['rows'] as $r)
                        <tr style="background: {{ $rowNo % 2 == 0 ? 'rgb(245, 245, 245)' : 'rgb(255, 255, 255)' }};">
                            <td align="center" height="14">{{ $rowNo++ }}</td>
                            <td align="center">{{ $r['code'] ?? '' }}</td>
                            <td>{{ $r['name'] ?? '' }}</td>
                            <td align="right">{{ $fmt($r['budget_quarter'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($r['realized_quarter'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($r['variance'] ?? 0) }}</td>
                            <td align="right">{{ $pct($r['realized_pct'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($r['budget_ytd'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($r['realized_ytd'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($r['variance_ytd'] ?? 0) }}</td>
                            <td align="right">{{ $pct($r['realized_pct_ytd'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    @if($bucket['totals'] !== null)
                        <tr style="background: rgb(220, 220, 220); font-weight: bold;">
                            <td colspan="3" height="16">TOTAL {{ $bucket['label'] }}</td>
                            <td align="right">{{ $fmt($bucket['totals']['budget_quarter'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($bucket['totals']['realized_quarter'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($bucket['totals']['variance'] ?? 0) }}</td>
                            <td align="right">{{ $pct($bucket['totals']['realized_pct'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($bucket['totals']['budget_ytd'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($bucket['totals']['realized_ytd'] ?? 0) }}</td>
                            <td align="right">{{ $fmt($bucket['totals']['variance_ytd'] ?? 0) }}</td>
                            <td align="right">{{ $pct($bucket['totals']['realized_pct_ytd'] ?? 0) }}</td>
                        </tr>
                    @endif
                @endif
            @endforeach

            @if(($totals['net'] ?? null) !== null)
                <tr style="background: rgb(180, 180, 180); color: #fff; font-weight: bold;">
                    <td colspan="3" height="18">SURPLUS / (DEFISIT) ANGGARAN</td>
                    <td align="right">{{ $fmt($totals['net']['budget_quarter'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($totals['net']['realized_quarter'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($totals['net']['variance'] ?? 0) }}</td>
                    <td align="right">{{ $pct($totals['net']['realized_pct'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($totals['net']['budget_ytd'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($totals['net']['realized_ytd'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($totals['net']['variance_ytd'] ?? 0) }}</td>
                    <td align="right">{{ $pct($totals['net']['realized_pct_ytd'] ?? 0) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p style="font-size: 9px; color: #555; margin-top: 8px;">
        Catatan: Anggaran bersumber dari modul Budgeting (status {{ ($budget['status'] ?? 'draft') }}).
        Realisasi dihitung dari jurnal posted (sign normal-balance).
    </p>
@endif
@endsection
