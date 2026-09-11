@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $verificationDate = $tokens['{tgl_verifikasi}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $alokasi = (float) $loan['principal_amount'];
    $alokasiPinjaman = $alokasi;
    $saldoPokok = $alokasi;
    $jasaPersen = (float) $tokens['{jasa_persen}'];
    $saldoJasa = $saldoPokok * ($jasaPersen / 100);
    $jumlahAngsuran = 0;
    $sumPokok = 0;
    $sumJasa = 0;
    $termMonths = (int) $loan['term_months'];
    $installments = $installments ?? [];
@endphp

<style>
    * {
        font-family: Arial, Helvetica, sans-serif;
    }

    html {
        margin: 75.59px;
        margin-left: 94.48px;
    }

    ul,
    ol {
        margin-left: -10px;
        page-break-inside: auto !important;
    }

    header {
        position: fixed;
        top: -10px;
        left: 0px;
        right: 0px;
    }

    table tr th,
    table tr td {
        padding: 2px 4px;
    }

    table.p tr th,
    table.p tr td {
        padding: 4px 4px;
    }

    table.p0 tr th,
    table.p0 tr td {
        padding: 0px !important;
    }

    .break {
        page-break-after: always;
    }

    li {
        text-align: justify;
    }

    .l {
        border-left: 1px solid #000;
    }

    .t {
        border-top: 1px solid #000;
    }

    .r {
        border-right: 1px solid #000;
    }

    .b {
        border-bottom: 1px solid #000;
    }
</style>

<title>{{ $documentTitle }}</title>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;">
                <b>RENCANA ANGSURAN PIUTANG {{ strtoupper($loan['product_code']) }}</b>
            </div>
            <div style="font-size: 16px; text-decoration: underline;">
                <b>
                    {{ 'KELOMPOK' }}
                    {{ strtoupper($group['name']) }}
                    {{ strtoupper('' . $group['village']) }}
                </b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="90">Loan ID.</td>
        <td width="5" align="center">:</td>
        <td>
            <b>{{ $group['name'] }} &mdash; {{ $loan['id'] }}</b>
        </td>
        <td width="90">Jangka waktu</td>
        <td width="5" align="center">:</td>
        <td>
            <b>{{ $termMonths }} Bulan</b>
        </td>
    </tr>
    <tr>
        <td>No. SPK</td>
        <td align="center">:</td>
        <td>
            <b>{{ $loan['loan_number'] }}</b>
        </td>
        <td>Sistem Angsuran</td>
        <td align="center">:</td>
        <td>
            <b>{{ '' }} {{ round($termMonths / max(1, 1)) }} Kali</b>
        </td>
    </tr>
    <tr>
        <td>{{ 'Tanggal Proposal' }}</td>
        <td align="center">:</td>
        <td>
            <b>{{ $proposalDate }}</b>
        </td>
        <td>Jenis Jasa</td>
        <td align="center">:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Alokasi Piutang</td>
        <td align="center">:</td>
        <td>
            <b>Rp. {{ number_format((float) $alokasiPinjaman, 0, ',', '.') }}</b>
        </td>
        <td>Prosentase Jasa</td>
        <td align="center">:</td>
        <td>
            <b>{{ round($jasaPersen / max(1, $termMonths), 2) }}% per bulan</b>
        </td>
    </tr>
    <tr>
        <td colspan="6">&nbsp;</td>
    </tr>
</table>

<table border="0" width="100%" align="center"cellspacing="0" cellpadding="0"
    style="font-size: 11px; table-layout: fixed;">
    <tr style="background: rgb(232, 232, 232)">
        <th class="l t b" height="20" width="5%" align="center">Ke</th>
        <th class="l t b" width="13%" align="center">Tanggal</th>
        <th class="l t b" width="13%" align="center">Pokok</th>
        <th class="l t b" width="13%" align="center">Jasa</th>
        <th class="l t b" width="15%" align="center">Jumlah</th>
        <th class="l t b" width="15%" align="center">Total Target</th>
        <th class="l t b" width="13%" align="center">Saldo Pokok</th>
        <th class="l t b r" width="13%" align="center">Saldo Jasa</th>
    </tr>
    @foreach ($installments as $ra)
        @if (($ra['number'] ?? 0) == 0)
            @continue
        @endif
        @php
            $wajibPokok = (float) $ra['principal_due'];
            $wajibJasa = (float) $ra['interest_due'];
            $wajibAngsur = $wajibPokok + $wajibJasa;
            $jumlahAngsuran += $wajibAngsur;
            $saldoPokok -= $wajibPokok;
            $saldoJasa -= $wajibJasa;

            $sumPokok += $wajibPokok;
            $sumJasa += $wajibJasa;

            $b = '';
            if ($ra['number'] == $termMonths) {
                $b = 'b';
            }
        @endphp
        <tr>
            <td class="l {{ $b }}" align="center">{{ $ra['number'] }}</td>
            <td class="l {{ $b }}" align="center">{{ $ra['due_date_label'] }}</td>
            <td class="l {{ $b }}" align="right">{{ number_format((float) $wajibPokok, 0, ',', '.') }}</td>
            <td class="l {{ $b }}" align="right">{{ number_format((float) $wajibJasa, 0, ',', '.') }}</td>
            <td class="l {{ $b }}" align="right">{{ number_format((float) $wajibAngsur, 0, ',', '.') }}</td>
            <td class="l {{ $b }}" align="right">{{ number_format((float) $jumlahAngsuran, 0, ',', '.') }}</td>
            <td class="l {{ $b }}" align="right">{{ number_format((float) $saldoPokok, 0, ',', '.') }}</td>
            <td class="l {{ $b }} r" align="right">{{ number_format((float) $saldoJasa, 0, ',', '.') }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="8" style="padding: 0px !important;">
            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                style="font-size: 11px; table-layout: fixed;">
                <tr style="font-weight: bold;">
                    <td class="l t b" width="18%" height="15" align="center" colspan="2">Jumlah</td>
                    <td class="l t b" width="13%" align="right">{{ number_format((float) $sumPokok, 0, ',', '.') }}</td>
                    <td class="l t b" width="13%" align="right">
                        {{ number_format((float) $sumJasa, 0, ',', '.') }}
                    </td>
                    <td class="l t b" width="15%" align="right">{{ number_format((float) $jumlahAngsuran, 0, ',', '.') }}</td>
                    <td class="l t b" width="15%" align="right">{{ number_format((float) $jumlahAngsuran, 0, ',', '.') }}</td>
                    <td class="l t b" width="13%" align="right">{{ number_format((float) $saldoPokok, 0, ',', '.') }}</td>
                    <td class="l t b r" width="13%" align="right">{{ number_format((float) $saldoJasa, 0, ',', '.') }}</td>
                </tr>
            </table>

            @if (!empty($signatureHtml))
                {!! $signatureHtml !!}
            @else
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 11px;">
                    <tr>
                        <td align="center" colspan="5">&nbsp;</td>
                        <td align="center" colspan="3">
                            {{ $identity['district_name'] }}, {{ $proposalDate }}
                        </td>
                    </tr>
                    <tr>
                        <td align="center" colspan="5">
                            {{ '' }}
                        </td>
                        <td align="center" colspan="3">
                            {{ 'Ketua Kelompok' }}
                            {{ $group['name'] }}
                        </td>
                    </tr>
                    <tr>
                        <td align="center" colspan="8" height="40">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" colspan="5">
                            <b>{{ '' }}</b>
                        </td>
                        <td align="center" colspan="3">
                            <b>{{ $committeeChair }}</b>
                        </td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>