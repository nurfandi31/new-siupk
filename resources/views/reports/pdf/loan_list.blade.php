@extends('reports.pdf.layout', ['title' => 'Daftar Pinjaman', 'identity' => $identity])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="11" align="center">
            <div style="font-size: 16px; font-weight: bold; text-transform: uppercase;">DAFTAR PINJAMAN JAMBATAN DANA BERGULIR</div>
            <div style="font-size: 12px; margin-top: 2px;">
                Status: <b>{{ $status_label }}</b>
                @if(!empty($start_date) || !empty($end_date))
                    | Periode: <b>{{ $start_date ? date('d/m/Y', strtotime($start_date)) : 'Awal' }} s/d {{ $end_date ? date('d/m/Y', strtotime($end_date)) : 'Sekarang' }}</b>
                @else
                    | Periode: <b>Semua Tanggal</b>
                @endif
            </div>
        </td>
    </tr>
    <tr><td colspan="11" height="12"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="4" style="font-size: 10px;">
    <thead>
        <tr style="background: #e5e7eb; font-weight: bold; text-align: center;">
            <th class="t b l" width="24" height="22">No</th>
            <th class="t b l" width="85">No. Kode</th>
            <th class="t b l" align="left">Kelompok / Peminjam</th>
            <th class="t b l" align="left" width="90">Desa / Alamat</th>
            <th class="t b l" width="70">Pengajuan</th>
            <th class="t b l" align="right" width="90">Plafond Pokok</th>
            <th class="t b l" width="60">Jasa & Metode</th>
            <th class="t b l" width="40">Jangka</th>
            <th class="t b l" align="right" width="85">Pokok Dibayar</th>
            <th class="t b l" align="right" width="85">Sisa Pokok</th>
            <th class="t b l r" width="70">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($loans as $index => $item)
            @php
                $bg = $index % 2 === 1 ? '#f9fafb' : '#ffffff';
                $statusMap = [
                    'draft' => 'Proposal',
                    'proposed' => 'Proposal',
                    'verified' => 'Terverifikasi',
                    'approved' => 'Waiting',
                    'waiting' => 'Waiting',
                    'funded' => 'Pencairan',
                    'active' => 'Aktif',
                    'disbursed' => 'Aktif',
                    'completed' => 'Lunas',
                    'written_off' => 'Hapus Buku',
                    'rescheduled' => 'Reschedule',
                ];
                $statusText = $statusMap[$item['status']] ?? ucfirst($item['status']);
                $methodText = ucfirst($item['installment_method'] ?? 'flat');
            @endphp
            <tr style="background: {{ $bg }};">
                <td class="b l" align="center">{{ $index + 1 }}</td>
                <td class="b l" align="center">{{ $item['loan_number'] ?: ('#' . $item['row_id']) }}</td>
                <td class="b l">
                    <b>{{ $item['group_name'] }}</b>
                    @if(!empty($item['leader_name']))
                        <br><span style="color: #4b5563; font-size: 9px;">Ketua: {{ $item['leader_name'] }}</span>
                    @endif
                </td>
                <td class="b l">{{ $item['village_name'] ?: ($item['address'] ?: '-') }}</td>
                <td class="b l" align="center">{{ $item['proposed_at'] ? date('d/m/Y', strtotime($item['proposed_at'])) : '-' }}</td>
                <td class="b l" align="right">{{ number_format($item['principal_amount'], 0, ',', '.') }}</td>
                <td class="b l" align="center">{{ number_format($item['interest_rate'], 1) }}% ({{ $methodText }})</td>
                <td class="b l" align="center">{{ $item['term_months'] }} bln</td>
                <td class="b l" align="right">{{ number_format($item['principal_paid'], 0, ',', '.') }}</td>
                <td class="b l" align="right"><b>{{ number_format($item['principal_remaining'], 0, ',', '.') }}</b></td>
                <td class="b l r" align="center">
                    <span style="font-weight: bold; font-size: 9px;">{{ $statusText }}</span>
                </td>
            </tr>
        @empty
            <tr>
                <td class="b l r" colspan="11" align="center" style="padding: 20px 0; color: #6b7280;">
                    Tidak ada data pinjaman yang sesuai dengan kriteria filter.
                </td>
            </tr>
        @endforelse
    </tbody>
    @if(count($loans) > 0)
        <tfoot>
            <tr style="background: #d1d5db; font-weight: bold;">
                <td class="b l" colspan="5" align="center" height="22">TOTAL ({{ count($loans) }} Pinjaman)</td>
                <td class="b l" align="right">{{ number_format($totals['principal_amount'], 0, ',', '.') }}</td>
                <td class="b l" colspan="2"></td>
                <td class="b l" align="right">{{ number_format($totals['principal_paid'], 0, ',', '.') }}</td>
                <td class="b l" align="right">{{ number_format($totals['principal_remaining'], 0, ',', '.') }}</td>
                <td class="b l r"></td>
            </tr>
        </tfoot>
    @endif
</table>

@if(!empty($signatures))
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-top: 30px; page-break-inside: avoid;">
        <tr>
            <td width="25%" align="center">
                Mengetahui,<br><b>Ketua BUMDesma / LKD</b>
                <br><br><br><br><br>
                <b><u>{{ $signatures['manager'] ?? '..................................' }}</u></b>
            </td>
            <td width="25%" align="center">
                <br><b>Sekretaris</b>
                <br><br><br><br><br>
                <b><u>{{ $signatures['secretary'] ?? '..................................' }}</u></b>
            </td>
            <td width="25%" align="center">
                <br><b>Bendahara</b>
                <br><br><br><br><br>
                <b><u>{{ $signatures['treasurer'] ?? '..................................' }}</u></b>
            </td>
            <td width="25%" align="center">
                Dicetak Tanggal {{ date('d/m/Y') }}<br><b>Tim Verifikasi / Anotator</b>
                <br><br><br><br><br>
                <b><u>{{ $signatures['verifier'] ?? '..................................' }}</u></b>
            </td>
        </tr>
    </table>
@endif
@endsection
