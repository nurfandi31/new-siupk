@php
    /** @var array<string, mixed> $identity */
    /** @var list<array<string, mixed>> $rows */
    /** @var list<array<string, mixed>> $parameters */
    $fmtMoney = static fn ($v): string => number_format((float) ($v ?? 0), 0, ',', '.');
    $fmtPct = static fn ($v): string => number_format((float) ($v ?? 0), 2, ',', '.').'%';
    $fmtScore = static fn ($v): string => rtrim(rtrim(number_format((float) ($v ?? 0), 2, ',', '.'), '0'), ',');
@endphp
@extends('reports.pdf.layout', [
    'title' => $title ?? 'Penilaian Tingkat Kesehatan LKM',
    'identity' => $identity,
    'period' => ['period_label' => $period_label ?? '', 'as_of' => $as_of ?? ''],
])

@section('content')
    <style>
        html { margin-left: 30px; margin-right: 30px; }
        .num { text-align: right; white-space: nowrap; }
        .matrix th, .matrix td { border: 1px solid #000; }
        .matrix thead th { background: rgb(232, 232, 232); font-weight: bold; text-align: center; }
        .param-table th, .param-table td { border: 1px solid #000; }
        .param-table th { background: rgb(232, 232, 232); font-weight: bold; text-align: left; }
        .muted { color: #555; font-size: 9px; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px; font-weight: bold;">{{ $title }}</div>
                <div style="font-size: 13px; font-weight: bold;">{{ $framework_label }}</div>
                <div style="font-size: 14px; font-weight: bold;">{{ strtoupper($period_label) }}</div>
                <div style="font-size: 10px; color: #555;">per {{ $as_of }}</div>
            </td>
        </tr>
        <tr><td height="6"></td></tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 8px;">
        <tr>
            <td width="25%" align="center" style="border: 1px solid #000; background: rgb(245,245,245); padding: 6px;">
                <div style="font-size: 9px;">{{ strtoupper($summary_label) }}</div>
                <div style="font-size: 16px; font-weight: bold;">{{ $fmtScore($composite_score) }} / {{ $composite_max }}</div>
            </td>
            <td width="25%" align="center" style="border: 1px solid #000; padding: 6px;">
                <div style="font-size: 9px;">PERINGKAT KOMPOSIT</div>
                <div style="font-size: 14px; font-weight: bold;">{{ $rating }}</div>
            </td>
            <td width="25%" align="center" style="border: 1px solid #000; padding: 6px;">
                <div style="font-size: 9px;">NPL NETO</div>
                <div style="font-size: 14px; font-weight: bold;">{{ $fmtPct($values['npl_neto'] ?? 0) }}</div>
            </td>
            <td width="25%" align="center" style="border: 1px solid #000; padding: 6px;">
                <div style="font-size: 9px;">COVERAGE PPAP</div>
                <div style="font-size: 14px; font-weight: bold;">{{ $fmtPct($values['coverage_ppap'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    @if (! empty($supervision_status))
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 8px;">
            <tr>
                <td width="28%" style="border: 1px solid #000; background: rgb(245,245,245); padding: 4px;">Status Pengawasan POJK 49/2024</td>
                <td width="72%" style="border: 1px solid #000; padding: 4px;">
                    <b>{{ $supervision_status }}</b>
                    @if (! empty($supervision_basis))
                        <div class="muted">{{ $supervision_basis }}</div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <table class="param-table" border="0" width="100%" cellspacing="0" cellpadding="4" style="font-size: 10px; margin-bottom: 8px;">
        <tr><th colspan="4">A. PARAMETER KEUANGAN UTAMA</th></tr>
        @foreach (array_chunk($parameters, 2) as $chunk)
            <tr>
                @foreach ($chunk as $p)
                    <td width="30%">{{ $p['label'] }}</td>
                    <td width="20%" class="num"><b>Rp {{ $fmtMoney($p['value']) }}</b></td>
                @endforeach
                @if (count($chunk) === 1)
                    <td width="30%"></td>
                    <td width="20%"></td>
                @endif
            </tr>
        @endforeach
    </table>

    <table class="matrix" border="0" width="100%" cellspacing="0" cellpadding="3" style="font-size: 8.5px; table-layout: fixed;">
        <thead>
            <tr>
                <th rowspan="2" width="3%">No</th>
                <th rowspan="2" width="11%">Aspek</th>
                <th rowspan="2" width="15%">Rasio</th>
                <th rowspan="2" width="24%">Parameter</th>
                <th colspan="4" width="35%">Klasifikasi Tingkat Kesehatan</th>
                <th rowspan="2" width="5%">Skor</th>
                <th rowspan="2" width="7%">Status</th>
            </tr>
            <tr>
                <th width="9%">Sehat</th>
                <th width="9%">Cukup Sehat</th>
                <th width="9%">Kurang Sehat</th>
                <th width="8%">Tidak Sehat</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td align="center">{{ $row['no'] }}</td>
                    <td>{{ $row['aspect'] }}</td>
                    <td>
                        <b>{{ $row['ratio'] }}</b>
                        @if ($row['result'] !== null)
                            <div style="margin-top: 3px; font-weight: bold;">{{ $fmtPct($row['result']) }}</div>
                        @endif
                    </td>
                    <td>
                        @foreach ($row['details'] as $detail)
                            <div style="display: flex; justify-content: space-between; gap: 4px;">
                                <span>{{ $detail['label'] }}</span>
                                <span class="num">{{ $detail['value'] === null ? '-' : $fmtMoney($detail['value']) }}</span>
                            </div>
                        @endforeach
                    </td>
                    @php
                        $criteria = $row['criteria'];
                        $groups = [
                            array_slice($criteria, 0, (int) ceil(count($criteria) / 4)),
                            array_slice($criteria, (int) ceil(count($criteria) / 4), (int) ceil(count($criteria) / 4)),
                            array_slice($criteria, (int) ceil(count($criteria) / 4) * 2, (int) ceil(count($criteria) / 4)),
                            array_slice($criteria, (int) ceil(count($criteria) / 4) * 3),
                        ];
                    @endphp
                    @foreach ($groups as $group)
                        <td>
                            @foreach ($group as $criterion)
                                <div>{{ $criterion }}</div>
                            @endforeach
                        </td>
                    @endforeach
                    <td align="center"><b>{{ $fmtScore($row['score']) }}</b></td>
                    <td align="center">{{ $row['status'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: rgb(210, 210, 210); font-weight: bold;">
                <td colspan="5" align="right">{{ $summary_label }}</td>
                <td colspan="3" align="center">{{ $fmtScore($composite_score) }} / {{ $composite_max }}</td>
                <td colspan="2" align="center">{{ $rating }}</td>
            </tr>
        </tfoot>
    </table>

    <p style="font-size: 8px; color: #555; margin-top: 8px;">
        Sumber: Neraca OJK, Laba Rugi OJK, Kolektibilitas, dan CKPN periode {{ $period_label }}.
        Dicetak {{ $generated_at ?? '-' }}.
    </p>
@endsection
