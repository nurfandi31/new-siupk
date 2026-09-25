@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px; font-weight: bold;">
                    DAFTAR PEMANFAAT AKTIF (KELOMPOK)
                </div>
                <div style="font-size: 16px; font-weight: bold;">
                    PERIODE: {{ strtoupper($period_label) }}
                </div>
            </td>
        </tr>
    </table>

    @forelse ($groups as $g)
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-top: 12px;">
            <tr>
                <td style="font-weight: bold;">
                    {{ $g['group_name'] ?? '—' }}
                    @if (! empty($g['group_code'])) ({{ $g['group_code'] }}) @endif
                    — Desa: {{ $g['village_name'] ?? '—' }}
                    ({{ count($g['members']) }} pemanfaat)
                </td>
            </tr>
        </table>
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8.5px; table-layout: fixed;">
            <thead>
                <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                    <th class="t l b" width="3%">No</th>
                    <th class="t l b" width="13%">NIK</th>
                    <th class="t l b" width="6%">No. Anggota</th>
                    <th class="t l b" width="20%">Nama</th>
                    <th class="t l b" width="10%">Tgl Cair</th>
                    <th class="t l b" width="13%">No. Pinjaman</th>
                    <th class="t l b" width="11%">Alokasi</th>
                    <th class="t l b" width="12%">Tunggakan Pokok</th>
                    <th class="t l b r" width="12%">Tunggakan Jasa</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($g['members'] as $idx => $m)
                    <tr>
                        <td class="l b" align="center">{{ $idx + 1 }}</td>
                        <td class="l b">{{ $m['nik'] ?? '—' }}</td>
                        <td class="l b">{{ $m['member_number'] ?? '—' }}</td>
                        <td class="l b">{{ $m['member_name'] }}</td>
                        <td class="l b" align="center">{{ ! empty($m['disbursed_at']) ? date('d/m/Y', strtotime($m['disbursed_at'])) : '—' }}</td>
                        <td class="l b">{{ $m['loan_number'] ?? '—' }}</td>
                        <td class="l b num">{{ number_format($m['allocated_amount'], 0, ',', '.') }}</td>
                        <td class="l b num">{{ number_format($m['tunggakan_pokok'], 0, ',', '.') }}</td>
                        <td class="l b r num">{{ number_format($m['tunggakan_jasa'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: rgb(232,232,232); font-weight: bold;">
                    <th class="t l b" colspan="6" align="left">Subtotal {{ $g['group_name'] ?? '' }}</th>
                    <th class="t l b num">{{ number_format($g['subtotal_alokasi'], 0, ',', '.') }}</th>
                    <th class="t l b num">{{ number_format($g['subtotal_tunggakan_pokok'], 0, ',', '.') }}</th>
                    <th class="t l b r num">{{ number_format($g['subtotal_tunggakan_jasa'], 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @empty
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-top: 12px;">
            <tr>
                <td align="center" style="padding: 12px;">Tidak ada kelompok aktif pada periode ini.</td>
            </tr>
        </table>
    @endforelse

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 20px;">
        <tr style="background: rgb(190,190,190); font-weight: bold;">
            <td style="padding: 6px;" width="20%">GRAND TOTAL</td>
            <td style="padding: 6px;" width="16%">Kelompok: {{ $totals['group_count'] }}</td>
            <td style="padding: 6px;" width="16%">Pemanfaat: {{ $totals['member_count'] }}</td>
            <td style="padding: 6px;" width="16%" align="right">Alokasi: {{ number_format($totals['alokasi_total'], 0, ',', '.') }}</td>
            <td style="padding: 6px;" width="16%" align="right">T.Pokok: {{ number_format($totals['tunggakan_pokok_total'], 0, ',', '.') }}</td>
            <td style="padding: 6px;" width="16%" align="right">T.Jasa: {{ number_format($totals['tunggakan_jasa_total'], 0, ',', '.') }}</td>
        </tr>
    </table>
@endsection
