@php
    /** @var array<string, mixed> $identity */
    /** @var array<string, mixed> $period */
    /** @var array<string, mixed> $totals */
    /** @var list<array<string, mixed>> $interest_rows */
    /** @var list<array<string, mixed>> $expense_rows */
    /** @var float $default_rate */
    /** @var int $days_in_period */

    $generatedAt = $generated_at ?? null;
    $isMonthly = (bool) ($is_monthly ?? false);

    $fmt = static function (float|int|null $v): string {
        $v = (float) ($v ?? 0);

        return $v < 0
            ? '('.number_format(abs($v), 2).')'
            : number_format($v, 2);
    };
@endphp
@extends('reports.pdf.layout', [
    'title' => 'Daftar Bunga Simpanan',
    'identity' => $identity,
    'period' => $period,
])

@section('content')
<style>
    html { margin-left: 30px; margin-right: 30px; }
</style>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td align="center">
            <div style="font-size: 18px; font-weight: bold;">
                DAFTAR BUNGA SIMPANAN
            </div>
            <div style="font-size: 14px; font-weight: bold;">
                {{ strtoupper($period['period_label'] ?? '') }}
            </div>
            <div style="font-size: 10px; color: #555;">
                Per {{ $period['as_of'] ?? '-' }} · Tarif asumsi: {{ number_format((float) $default_rate * 100, 2) }}% p.a. · {{ $days_in_period }} hari periode
                · Dicetak: {{ $generatedAt ? \Carbon\Carbon::parse($generatedAt)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
    <tr><td height="6"></td></tr>
</table>

{{-- Tabel per akun simpanan --}}
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; table-layout: fixed;">
    <thead>
        <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
            <th class="t l b" rowspan="2" width="4%">No</th>
            <th class="t l b" rowspan="2" width="9%">Kode Akun</th>
            <th class="t l b" rowspan="2" width="22%">Nama Akun</th>
            <th class="t l b" rowspan="2" width="13%">Jenis Simpanan</th>
            <th class="t l b" rowspan="2" width="11%">Saldo Awal</th>
            <th class="t l b" rowspan="2" width="11%">Saldo Akhir</th>
            <th class="t l b" rowspan="2" width="11%">Saldo Rata-rata</th>
            <th class="t l b" rowspan="2" width="6%">Rate</th>
            <th class="t l b r" rowspan="2" width="13%">Bunga (estimasi)</th>
        </tr>
    </thead>
    <tbody>
        @if(empty($interest_rows))
            <tr><td colspan="9" align="center" class="l b r" height="22">Belum ada data simpanan untuk periode ini.</td></tr>
        @else
            @php
                $rowNo = 1;
                $currentKind = '';
            @endphp
            @foreach($interest_rows as $row)
                @php
                    $rowKind = (string) ($row['kind'] ?? 'lainnya');
                    if ($rowKind !== $currentKind) {
                        $currentKind = $rowKind;
                        echo '<tr style="background: rgb(180, 180, 180); font-weight: bold;"><td colspan="9" class="l b r" height="16">'.($row['kind_label'] ?? $rowKind).'</td></tr>';
                    }
                @endphp
                <tr>
                    <td class="l b" align="center" height="14">{{ $rowNo++ }}</td>
                    <td class="l b" align="center">{{ $row['code'] ?? '' }}</td>
                    <td class="l b">{{ $row['name'] ?? '' }}</td>
                    <td class="l b">{{ $row['kind_label'] ?? '' }}</td>
                    <td class="l b" align="right">{{ $fmt((float) ($row['opening_balance'] ?? 0)) }}</td>
                    <td class="l b" align="right">{{ $fmt((float) ($row['closing_balance'] ?? 0)) }}</td>
                    <td class="l b" align="right">{{ $fmt((float) ($row['average_balance'] ?? 0)) }}</td>
                    <td class="l b" align="right">{{ number_format((float) ($row['rate'] ?? 0) * 100, 2) }}%</td>
                    <td class="l b r" align="right"><b>{{ $fmt((float) ($row['interest_estimated'] ?? 0)) }}</b></td>
                </tr>
            @endforeach
        @endif
    </tbody>
    <tfoot>
        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td class="t l b" colspan="4" height="20" align="right" style="padding-right: 8px;">TOTAL</td>
            <td class="t l b" align="right">{{ $fmt((float) ($totals['opening_balance'] ?? 0)) }}</td>
            <td class="t l b" align="right">{{ $fmt((float) ($totals['closing_balance'] ?? 0)) }}</td>
            <td class="t l b" align="right">{{ $fmt((float) ($totals['average_balance'] ?? 0)) }}</td>
            <td class="t l b" align="right">{{ number_format((float) ($totals['average_rate'] ?? 0) * 100, 2) }}%</td>
            <td class="t l b r" align="right"><b>{{ $fmt((float) ($totals['interest_estimated'] ?? 0)) }}</b></td>
        </tr>
    </tfoot>
</table>

@if(!empty($expense_rows))
    <div style="page-break-after: always; height: 0;">&nbsp;</div>
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td align="center">
                <div style="font-size: 14px; font-weight: bold;">BEBAN BUNGA SIMPANAN (DARI JURNAL)</div>
            </td>
        </tr>
        <tr><td height="6"></td></tr>
    </table>
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                <th class="t l b" width="6%">No</th>
                <th class="t l b" width="14%">Kode Akun</th>
                <th class="t l b" width="40%">Nama Akun</th>
                <th class="t l b" width="20%">Mutasi Debit</th>
                <th class="t l b r" width="20%">Mutasi Kredit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expense_rows as $idx => $row)
                <tr>
                    <td class="l b" align="center" height="16">{{ $idx + 1 }}</td>
                    <td class="l b" align="center">{{ $row['code'] ?? '' }}</td>
                    <td class="l b">{{ $row['name'] ?? '' }}</td>
                    <td class="l b" align="right">{{ $fmt((float) ($row['period_debit'] ?? 0)) }}</td>
                    <td class="l b r" align="right">{{ $fmt((float) ($row['period_credit'] ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: rgb(200, 200, 200); font-weight: bold;">
                <td class="t l b" colspan="3" height="20" align="right" style="padding-right: 8px;">TOTAL</td>
                <td class="t l b" align="right">{{ $fmt((float) ($expense_total ?? 0)) }}</td>
                <td class="t l b r"></td>
            </tr>
        </tfoot>
    </table>
@endif

{{-- Tanda tangan --}}
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 24px;">
    <tr>
        @php
            $managerName = (string) ($identity['manager_name'] ?? '');
            $managerTitle = (string) ($identity['manager_title'] ?? '');
            $treasurerName = (string) ($identity['treasurer_name'] ?? '');
            $treasurerTitle = (string) ($identity['treasurer_title'] ?? '');
            if ($managerTitle === '') { $managerTitle = 'Ketua / Manager'; }
            if ($treasurerTitle === '') { $treasurerTitle = 'Bendahara'; }
        @endphp
        <td width="33%" align="center">Mengetahui,<br /><b>{{ $managerTitle }}</b></td>
        <td width="34%"></td>
        <td width="33%" align="center">Dibuat oleh,<br /><b>{{ $treasurerTitle }}</b></td>
    </tr>
    <tr><td colspan="3" height="48"></td></tr>
    <tr>
        <td align="center"><b><u>{{ $managerName !== '' ? $managerName : '...........................................' }}</u></b></td>
        <td></td>
        <td align="center"><b><u>{{ $treasurerName !== '' ? $treasurerName : '...........................................' }}</u></b></td>
    </tr>
</table>
@endsection
