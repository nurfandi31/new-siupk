@php
    /** @var array<string, mixed> $identity */
    /** @var list<array<string, mixed>> $buckets_meta */
    /** @var list<array<string, mixed>> $products */
    /** @var array<string, float|int> $grand_total */
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

    $bucketCount = max(1, count($buckets_meta));
@endphp
@extends('reports.pdf.layout', [
    'title' => 'Kolektibilitas OJK v2 (KBP2)',
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
                KOLEKTIBILITAS PINJAMAN — FORMAT OJK v2 (KBP2)
            </div>
            <div style="font-size: 14px; font-weight: bold;">
                PERIODE: {{ strtoupper($period_label) }}
            </div>
            <div style="font-size: 10px; color: #555;">
                Per {{ $as_of ?? '-' }} · Scope: {{ strtoupper($borrower_scope ?? 'all') }}
                · Dicetak: {{ $generatedAt ? \Carbon\Carbon::parse($generatedAt)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
    <tr><td height="6"></td></tr>
</table>

@forelse($products as $prodIdx => $prod)
    @if($prodIdx > 0)
        <div style="page-break-after: always; height: 0;">&nbsp;</div>
    @endif

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px; margin-bottom: 8px;">
        <tr>
            <td>
                <b>Produk:</b> {{ $prod['product_name'] ?? '' }}
                ({{ $prod['product_code'] ?? '' }})
                &nbsp;|&nbsp; <b>Outstanding:</b> {{ $fmt((float) ($prod['outstanding'] ?? 0)) }}
                &nbsp;|&nbsp; <b>Jumlah Pinjaman:</b> {{ (int) ($prod['loan_count'] ?? 0) }}
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                <th class="t l b" rowspan="2" width="20%">Golongan (Hari)</th>
                @foreach($buckets_meta as $bm)
                    <th class="t l b" colspan="2" width="{{ 80 / $bucketCount }}%">
                        {{ $bm['label'] ?? '' }}
                    </th>
                @endforeach
            </tr>
            <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                @foreach($buckets_meta as $bm)
                    <th class="t l b" width="{{ 40 / $bucketCount }}%">Pinjam</th>
                    <th class="t l b" width="{{ 40 / $bucketCount }}%">Outstanding</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr style="background: rgb(255, 255, 255);">
                <td class="l b" height="22"><b>Per Produk — Rekap</b></td>
                @foreach($prod['buckets'] ?? [] as $bk)
                    <td class="l b" align="right">{{ (int) ($bk['loan_count'] ?? 0) }}</td>
                    <td class="l b" align="right">{{ $fmt((float) ($bk['outstanding'] ?? 0)) }}</td>
                @endforeach
            </tr>
            <tr style="background: rgb(245, 245, 245);">
                <td class="l b" height="20"><b>Cadangan Wajib ({{ $pct(0) }})</b></td>
                @foreach($prod['buckets'] ?? [] as $bk)
                    <td class="l b" colspan="2" align="right">{{ $fmt((float) ($bk['allowance_required'] ?? 0)) }}</td>
                @endforeach
            </tr>
            <tr style="background: rgb(200, 200, 200); font-weight: bold;">
                <td class="t l b" height="20" colspan="{{ 1 + (2 * $bucketCount) }}" align="right" style="padding-right: 8px;">
                    TOTAL {{ $prod['product_code'] ?? '' }} — Cadangan Wajib: {{ $fmt((float) ($prod['allowance_required'] ?? 0)) }}
                    · Dibentuk: {{ $fmt((float) ($prod['allowance_formed'] ?? 0)) }}
                    · Selisih: {{ $fmt((float) ($prod['selisih'] ?? 0)) }}
                </td>
            </tr>
        </tbody>
    </table>
@empty
    <p style="font-size: 11px; text-align: center; color: #555;">Belum ada data pinjaman aktif untuk periode ini.</p>
@endforelse

@if(!empty($products))
    <div style="page-break-after: always; height: 0;">&nbsp;</div>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 12px; margin-bottom: 8px;">
        <tr>
            <td align="center"><b>GRAND TOTAL — SELURUH PRODUK</b></td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px;">
        <tr style="background: rgb(230, 230, 230); font-weight: bold;">
            <td class="t l b" width="40%" align="center" height="22">Total Pinjaman</td>
            <td class="t l b" width="20%" align="center">Total Outstanding</td>
            <td class="t l b" width="20%" align="center">Total Cadangan Wajib</td>
            <td class="t l b r" width="20%" align="center">Total Cadangan Dibentuk</td>
        </tr>
        <tr>
            <td class="t l b" align="center" height="22"><b>{{ (int) ($grand_total['loan_count'] ?? 0) }}</b></td>
            <td class="t l b" align="center"><b>{{ $fmt((float) ($grand_total['outstanding'] ?? 0)) }}</b></td>
            <td class="t l b" align="center"><b>{{ $fmt((float) ($grand_total['allowance_required'] ?? 0)) }}</b></td>
            <td class="t l b r" align="center"><b>{{ $fmt((float) ($grand_total['allowance_formed'] ?? 0)) }}</b></td>
        </tr>
        <tr>
            <td class="t l b" colspan="3" height="22" align="right" style="padding-right: 8px; font-weight: bold;">
                Selisih (Cadangan Dibentuk − Cadangan Wajib):
            </td>
            <td class="t l b r" align="center"><b>{{ $fmt((float) ($grand_total['selisih'] ?? 0)) }}</b></td>
        </tr>
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
