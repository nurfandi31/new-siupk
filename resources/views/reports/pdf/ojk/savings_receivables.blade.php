@php
    /** @var array<string, mixed> $identity */
    /** @var array<string, mixed> $period */
    /** @var array<string, mixed> $passiva */
    /** @var array<string, mixed> $aktiva */
    /** @var array<string, float> $totals */
    /** @var array<string, float> $ratio */

    $generatedAt = $generated_at ?? null;
    $passivaBuckets = $passiva['by_kind'] ?? [];
    $aktivaBuckets = $aktiva['by_kind'] ?? [];

    $fmt = static function (float|int|null $v): string {
        $v = (float) ($v ?? 0);

        return $v < 0
            ? '('.number_format(abs($v), 2).')'
            : number_format($v, 2);
    };
@endphp
@extends('reports.pdf.layout', [
    'title' => 'Simpanan & Piutang (SMPN)',
    'identity' => $identity,
    'period' => $period,
])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td align="center">
            <div style="font-size: 18px; font-weight: bold;">
                SIMPANAN &amp; PIUTANG (SMPN)
            </div>
            <div style="font-size: 14px; font-weight: bold;">
                {{ strtoupper($period['period_label'] ?? '') }}
            </div>
            <div style="font-size: 10px; color: #555;">
                Per {{ $period['as_of'] ?? '-' }} · Dicetak: {{ $generatedAt ? \Carbon\Carbon::parse($generatedAt)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
    <tr><td height="6"></td></tr>
</table>

{{-- KPI ringkas --}}
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-bottom: 8px;">
    <tr style="background: rgb(245, 245, 245); font-weight: bold;">
        <td class="t l b" width="33%" align="center" height="22">Total Simpanan (Pasiva)</td>
        <td class="t l b" width="33%" align="center">Total Piutang (Aktiva)</td>
        <td class="t l b r" width="34%" align="center">Rasio Piutang / Simpanan</td>
    </tr>
    <tr>
        <td class="t l b" align="center" height="22"><b>{{ $fmt($totals['savings'] ?? 0) }}</b></td>
        <td class="t l b" align="center"><b>{{ $fmt($totals['receivables'] ?? 0) }}</b></td>
        <td class="t l b r" align="center">
            <b>{{ number_format((float) ($ratio['piutang_to_simpanan'] ?? 0), 2) }}%</b>
        </td>
    </tr>
</table>

{{-- Tabel 2 sisi (Pasiva vs Aktiva) --}}
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; table-layout: fixed;">
    <colgroup>
        <col width="50%" />
        <col width="50%" />
    </colgroup>
    <thead>
        <tr style="background: #000; color: #fff; font-weight: bold;">
            <td class="t l b" align="center" height="20">SISI PASIVA — SIMPANAN</td>
            <td class="t l b r" align="center">SISI AKTIVA — PIUTANG</td>
        </tr>
    </thead>
    <tbody>
        @php
            $pasivaOrder = ['pokok', 'wajib', 'sukarela', 'berjangka', 'lainnya'];
            $aktivaOrder = ['pokok', 'bunga', 'lain'];
            $rendered = 0;
        @endphp
        @foreach($pasivaOrder as $idx => $kind)
            @php
                $pb = $passivaBuckets[$kind] ?? null;
                $ab = $aktivaOrder[$idx] ?? null;
                $aktivaB = $ab !== null ? ($aktivaBuckets[$ab] ?? null) : null;
            @endphp
            <tr style="background: {{ $rendered % 2 === 0 ? 'rgb(255, 255, 255)' : 'rgb(245, 245, 245)' }};">
                <td class="l b" height="18" style="padding-left: 8px;">
                    {{ $pb['label'] ?? '-' }}
                    <span style="float: right;">{{ $fmt((float) ($pb['closing_balance'] ?? 0)) }}</span>
                </td>
                <td class="l b r" style="padding-left: 8px;">
                    {{ $aktivaB['label'] ?? '' }}
                    @if($aktivaB !== null)
                        <span style="float: right;">{{ $fmt((float) ($aktivaB['closing_balance'] ?? 0)) }}</span>
                    @endif
                </td>
            </tr>
            @php $rendered++; @endphp
        @endforeach
        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td class="t l b" height="22" align="right" style="padding-right: 10px;">
                TOTAL SIMPANAN: {{ $fmt((float) ($totals['savings'] ?? 0)) }}
            </td>
            <td class="t l b r" align="right" style="padding-right: 10px;">
                TOTAL PIUTANG: {{ $fmt((float) ($totals['receivables'] ?? 0)) }}
            </td>
        </tr>
        <tr style="background: rgb(230, 230, 230); font-weight: bold;">
            <td colspan="2" class="t l b r" align="center" height="22">
                Selisih (Simpanan − Piutang): {{ $fmt((float) ($totals['selisih'] ?? 0)) }}
                &nbsp;|&nbsp; Rasio Piutang Pokok / Simpanan: {{ number_format((float) ($ratio['pokok_to_simpanan'] ?? 0), 2) }}%
            </td>
        </tr>
    </tbody>
</table>

{{-- Tanda tangan pengurus --}}
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
