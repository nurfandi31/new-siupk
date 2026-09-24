@php
    /** @var array<string, mixed> $identity */
    /** @var list<array<string, mixed>> $buckets */
    /** @var array<string, float|int> $totals */
    /** @var float $compliance_pct */
    /** @var string $borrower_scope */
    /** @var string $period_label */
    /** @var string $as_of */

    $generatedAt = $generated_at ?? null;

    $fmt = static function (float|int|null $v): string {
        $v = (float) ($v ?? 0);

        return $v < 0
            ? '('.number_format(abs($v), 2).')'
            : number_format($v, 2);
    };
    $pct = static function (float|int|null $v): string {
        return number_format((float) ($v ?? 0), 2).'%';
    };
@endphp
@extends('reports.pdf.layout', [
    'title' => 'PCPP — Penyisihan Cadangan Penghapusan Piutang',
    'identity' => $identity,
    'period' => ['period_label' => $period_label, 'as_of' => $as_of],
])

@section('content')
<style>
    html { margin-left: 30px; margin-right: 30px; }
</style>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td align="center">
            <div style="font-size: 18px; font-weight: bold;">
                PENYISIHAN CADANGAN PENGHAPUSAN PIUTANG (PCPP)
            </div>
            <div style="font-size: 14px; font-weight: bold;">
                PERIODE: {{ strtoupper($period_label) }}
            </div>
            <div style="font-size: 10px; color: #555;">
                Per {{ $as_of ?? '-' }}
                · Scope: {{ strtoupper($borrower_scope ?? 'all') }}
                · Dicetak: {{ $generatedAt ? \Carbon\Carbon::parse($generatedAt)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
    <tr><td height="6"></td></tr>
</table>

{{-- KPI ringkas --}}
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-bottom: 8px;">
    <tr style="background: rgb(245, 245, 245); font-weight: bold;">
        <td class="t l b" width="25%" align="center" height="22">Total Outstanding</td>
        <td class="t l b" width="25%" align="center">Total Cadangan Wajib</td>
        <td class="t l b" width="25%" align="center">Total Cadangan Dibentuk</td>
        <td class="t l b r" width="25%" align="center">Compliance Ratio</td>
    </tr>
    <tr>
        <td class="t l b" align="center" height="22"><b>{{ $fmt((float) ($totals['outstanding'] ?? 0)) }}</b></td>
        <td class="t l b" align="center"><b>{{ $fmt((float) ($totals['allowance_required'] ?? 0)) }}</b></td>
        <td class="t l b" align="center"><b>{{ $fmt((float) ($totals['allowance_formed'] ?? 0)) }}</b></td>
        <td class="t l b r" align="center">
            <b>{{ $pct($compliance_pct) }}</b>
        </td>
    </tr>
</table>

{{-- Tabel utama per kolektibilitas --}}
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9.5px; table-layout: fixed;">
    <thead>
        <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
            <th class="t l b" rowspan="2" width="4%">No</th>
            <th class="t l b" rowspan="2" width="20%">Golongan</th>
            <th class="t l b" rowspan="2" width="12%">Hari Keterlambatan</th>
            <th class="t l b" rowspan="2" width="8%">Jumlah Pinjaman</th>
            <th class="t l b" rowspan="2" width="13%">Outstanding</th>
            <th class="t l b" rowspan="2" width="7%">% Penyisihan</th>
            <th class="t l b" rowspan="2" width="12%">Cadangan Wajib</th>
            <th class="t l b" rowspan="2" width="12%">Cadangan Dibentuk</th>
            <th class="t l b r" rowspan="2" width="12%">Selisih</th>
        </tr>
    </thead>
    <tbody>
        @forelse($buckets as $idx => $b)
            @php
                $selisih = (float) ($b['selisih'] ?? 0);
                $selisihColor = $selisih < 0 ? '#c53030' : '#2f855a';
            @endphp
            <tr style="background: {{ $idx % 2 === 0 ? 'rgb(255, 255, 255)' : 'rgb(245, 245, 245)' }};">
                <td class="l b" align="center" height="18">{{ $idx + 1 }}</td>
                <td class="l b"><b>{{ $b['label'] ?? '' }}</b></td>
                <td class="l b" align="center">{{ $b['days_label'] ?? '' }}</td>
                <td class="l b" align="right">{{ (int) ($b['loan_count'] ?? 0) }}</td>
                <td class="l b" align="right">{{ $fmt((float) ($b['outstanding'] ?? 0)) }}</td>
                <td class="l b" align="right">{{ $pct((float) ($b['rate_pct'] ?? 0)) }}</td>
                <td class="l b" align="right">{{ $fmt((float) ($b['allowance_required'] ?? 0)) }}</td>
                <td class="l b" align="right">{{ $fmt((float) ($b['allowance_formed'] ?? 0)) }}</td>
                <td class="l b r" align="right" style="color: {{ $selisihColor }}; font-weight: bold;">
                    {{ $fmt($selisih) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="l b r" align="center" height="22">Belum ada data pinjaman aktif untuk periode ini.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td class="t l b" colspan="3" height="22" align="right" style="padding-right: 8px;">TOTAL</td>
            <td class="t l b" align="right">{{ (int) ($totals['loan_count'] ?? 0) }}</td>
            <td class="t l b" align="right">{{ $fmt((float) ($totals['outstanding'] ?? 0)) }}</td>
            <td class="t l b"></td>
            <td class="t l b" align="right">{{ $fmt((float) ($totals['allowance_required'] ?? 0)) }}</td>
            <td class="t l b" align="right">{{ $fmt((float) ($totals['allowance_formed'] ?? 0)) }}</td>
            <td class="t l b r" align="right">{{ $fmt((float) ($totals['selisih'] ?? 0)) }}</td>
        </tr>
    </tfoot>
</table>

<p style="font-size: 10px; margin-top: 10px; color: #555;">
    <b>Keterangan:</b> Tarif Penyisihan sesuai standar OJK:
    Lancar 0.5%, Dalam Perhatian Khusus 3%, Kurang Lancar 10%, Diragukan 50%, Macet 100%.
    Cadangan Dibentuk = saldo akun 1.1.04.* (Cadangan Kerugian Piutang) didistribusikan
    secara proporsional ke tiap golongan. Selisih negatif menunjukkan lembaga belum
    memenuhi CKPN sesuai standar OJK.
</p>

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
