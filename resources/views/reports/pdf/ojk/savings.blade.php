@php
    /** @var array<string, mixed> $identity */
    /** @var array<string, mixed> $period */
    /** @var list<array<string, mixed>> $rows */
    /** @var array<string, array<string, mixed>> $byKind */
    /** @var array<string, float> $totals */

    $kindOrder = ['pokok', 'wajib', 'sukarela', 'berjangka', 'lainnya'];
    $generatedAt = $generated_at ?? null;
    $disclaimer = $disclaimer ?? 'Detail per anggota belum tersedia karena modul simpanan sedang dalam pengembangan.';

    $fmt = static function (float|int|null $v): string {
        $v = (float) ($v ?? 0);

        return $v < 0
            ? '('.number_format(abs($v), 2).')'
            : number_format($v, 2);
    };
@endphp
@extends('reports.pdf.layout', [
    'title' => 'DRT — Daftar Rincian Tabungan',
    'identity' => $identity,
    'period' => $period,
])

@section('content')
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td colspan="6" align="center">
                <div style="font-size: 16px;"><b>DAFTAR RINCIAN TABUNGAN (DRT)</b></div>
                <div style="font-size: 12px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
                <div style="font-size: 9px; color: #555;">
                    Per {{ $period['as_of'] ?? '-' }} · Dicetak: {{ $generatedAt ? \Carbon\Carbon::parse($generatedAt)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
        <tr><td colspan="6" height="4"></td></tr>
    </table>

    @if(!empty($disclaimer))
        <div style="font-size: 8px; color: #555; border: 1px solid #888; padding: 4px 6px; margin-bottom: 6px;">
            <b>Catatan:</b> {{ $disclaimer }}
        </div>
    @endif

    @if(empty($rows))
        <p style="text-align: center; font-size: 11px; color: #555;">Belum ada akun simpanan untuk periode ini.</p>
    @else
        {{-- Ringkasan per jenis simpanan --}}
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px;">
            <tr style="background: #000; color: #fff; font-weight: bold;">
                <td colspan="6" height="16" align="center">
                    RINGKASAN PER JENIS SIMPANAN
                </td>
            </tr>
            <tr style="background: rgb(230, 230, 230); font-weight: bold;">
                <td width="5%" height="20" align="center">No</td>
                <td width="35%">Jenis Simpanan</td>
                <td width="10%" align="center">Jumlah Akun</td>
                <td width="15%" align="right">Mutasi Debit</td>
                <td width="15%" align="right">Mutasi Kredit</td>
                <td width="20%" align="right">Saldo Akhir</td>
            </tr>
            @php $kindNo = 1; @endphp
            @foreach($kindOrder as $kind)
                @php $bucket = $byKind[$kind] ?? null; @endphp
                @continue(!$bucket || (int) ($bucket['count'] ?? 0) === 0)
                <tr>
                    <td height="18" align="center">{{ $kindNo++ }}</td>
                    <td>{{ $bucket['label'] }}</td>
                    <td align="center">{{ (int) $bucket['count'] }}</td>
                    <td align="right">{{ $fmt((float) $bucket['period_debit']) }}</td>
                    <td align="right">{{ $fmt((float) $bucket['period_credit']) }}</td>
                    <td align="right"><b>{{ $fmt((float) $bucket['closing_balance']) }}</b></td>
                </tr>
            @endforeach
            <tr style="background: rgb(200, 200, 200); font-weight: bold;">
                <td colspan="3" height="20" align="center">TOTAL</td>
                <td align="right">{{ $fmt((float) ($totals['period_debit'] ?? 0)) }}</td>
                <td align="right">{{ $fmt((float) ($totals['period_credit'] ?? 0)) }}</td>
                <td align="right"><b>{{ $fmt((float) ($totals['closing_balance'] ?? 0)) }}</b></td>
            </tr>
        </table>

        <div style="page-break-after: always; height: 0;">&nbsp;</div>

        {{-- Rincian per akun --}}
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px;">
            <tr>
                <td colspan="6" align="center">
                    <div style="font-size: 13px; font-weight: bold;">RINCIAN DAFTAR TABUNGAN</div>
                </td>
            </tr>
            <tr><td colspan="6" height="4"></td></tr>
            <tr style="background: #000; color: #fff; font-weight: bold;">
                <td width="3%" height="22" align="center">No</td>
                <td width="11%" align="center">Kode Akun</td>
                <td width="32%">Nama Rekening</td>
                <td width="18%">Jenis Simpanan</td>
                <td width="18%" align="right">Mutasi Debit/Kredit</td>
                <td width="18%" align="right">Saldo Akhir</td>
            </tr>
            @php $rowNo = 1; $currentKind = null; @endphp
            @foreach($rows as $row)
                @php $rowKind = (string) ($row['kind'] ?? 'lainnya'); @endphp
                @if($rowKind !== $currentKind)
                    @php $currentKind = $rowKind; @endphp
                    <tr style="background: rgb(180, 180, 180); font-weight: bold;">
                        <td colspan="6" height="16">
                            {{ $byKind[$rowKind]['label'] ?? 'Lainnya' }}
                        </td>
                    </tr>
                @endif
                @php
                    $closing = (float) ($row['closing_balance'] ?? 0);
                    $debit = (float) ($row['period_debit'] ?? 0);
                    $credit = (float) ($row['period_credit'] ?? 0);
                @endphp
                <tr>
                    <td align="center" height="16">{{ $rowNo++ }}</td>
                    <td align="center">{{ $row['code'] ?? '' }}</td>
                    <td>
                        {{ $row['name'] ?? '' }}
                        @if(!empty($row['parent_name']))
                            <div style="font-size: 8px; color: #555;">{{ $row['parent_code'] ?? '' }} · {{ $row['parent_name'] }}</div>
                        @endif
                    </td>
                    <td>{{ $row['kind_label'] ?? '' }}</td>
                    <td align="right">
                        <span style="color: #555;">D: {{ $fmt($debit) }}</span>
                        &nbsp;
                        <span style="color: #555;">K: {{ $fmt($credit) }}</span>
                    </td>
                    <td align="right" @if($closing < 0) style="color: #b00; font-weight: bold;" @endif>
                        <b>{{ $fmt($closing) }}</b>
                    </td>
                </tr>
            @endforeach
            <tr style="background: rgb(200, 200, 200); font-weight: bold;">
                <td colspan="4" align="center" height="20">TOTAL SELURUH TABUNGAN</td>
                <td align="right">
                    <span style="color: #555;">D: {{ $fmt((float) ($totals['period_debit'] ?? 0)) }}</span>
                    &nbsp;
                    <span style="color: #555;">K: {{ $fmt((float) ($totals['period_credit'] ?? 0)) }}</span>
                </td>
                <td align="right"><b>{{ $fmt((float) ($totals['closing_balance'] ?? 0)) }}</b></td>
            </tr>
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
    @endif
@endsection
