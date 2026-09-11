@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $verificationDate = $tokens['{tgl_verifikasi}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $verificationNotes = $tokens['{keterangan_verifikasi}'] ?? '';
    $signatureHtml = $signature ?? '';
    $proposedAmount = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $termMonths = (int) $loan['term_months'];
    $verifiedYmd = $loan['verified_at'] ?? '';
    $proposalTotal = 0;
    $verifiedTotal = 0;
    $allocatedTotal = 0;
    $no = 0;
    $documentLabel = $documentTitle;
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

    table.p0 tr th,
    table.p0 tr td {
        padding: 0px !important;
    }

    table.p tr th,
    table.p tr td {
        padding: 4px 4px;
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

<title>{{ $documentLabel }}</title>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;">
                <b>{{ $documentLabel }}</b>
            </div>
            <div style="font-size: 16px;">
                <b>PINJAMAN KELOMPOK {{ strtoupper($loan['product_code']) }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="6">
            <b>IDENTITAS KELOMPOK :</b>
        </td>
    </tr>
    <tr>
        <td width="90">ID Kelompok</td>
        <td width="5" align="center">:</td>
        <td width="130">
            <b>{{ $group['code'] }}</b>
        </td>
        <td width="90">Tanggal Berdiri</td>
        <td width="5" align="center">:</td>
        <td width="130">
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Nama Kelompok </td>
        <td>:</td>
        <td>
            <b>{{ $group['name'] }}</b>
        </td>
        <td>Jenis Produk Piutang</td>
        <td>:</td>
        <td>
            <b>{{ $loan['product_code'] }}</b>
        </td>
    </tr>
    <tr>
        <td>Alamat Kelompok</td>
        <td>:</td>
        <td>
            <b>{{ $group['address'] }}</b>
        </td>
        <td>Jenis Usaha </td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>
            {{ '' }}
        </td>
        <td>:</td>
        <td>
            <b>{{ $group['village'] }}</b>
        </td>
        <td>Jenis Kegiatan</td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Kecamatan</td>
        <td>:</td>
        <td>
            <b>{{ $identity['district_name'] }}</b>
        </td>
        <td>Tingkat Kelompok </td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Telpon</td>
        <td>:</td>
        <td>
            <b>{{ $identity['phone'] }}</b>
        </td>
        <td>Fungsi Kelompok </td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Nama Ketua</td>
        <td>:</td>
        <td>
            <b>{{ $committeeChair }}</b>
        </td>
        <td>Last Update</td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td>Nama Sekretaris</td>
        <td>:</td>
        <td>
            <b>{{ $committeeSecretary }}</b>
        </td>
        <td>Petugas/PJ</td>
        <td>:</td>
        <td>
            <b>{{ '' }}</b>
        </td>
    </tr>
    <tr>
        <td colspan="6">&nbsp;</td>
    </tr>
</table>

<div>
    <b>DATA PIUTANG KELOMPOK :</b>
</div>
<table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(232,232,232)">
        <th width="10" height="15" align="center">ID.Reg. #{{ $loan['id'] }}</th>
        <th width="30" align="center">Tanggal</th>
        <th width="30" align="center">Alokasi</th>
        <th width="30" align="center">Jasa</th>
        <th width="30" align="center">Jangka</th>
        <th width="30" align="center">Sistem</th>

    </tr>
    <tr>
        <td align="center"><b>Data Proposal</b></td>
        <td>{{ $proposalDate }}</td>
        <td align="right">{{ $proposedAmount }}</td>
        <td align="center">
            {{ number_format((float) $tokens['{jasa_persen}'] / max(1, $termMonths), 2) }}%/{{ '' }}
        </td>
        <td align="center">{{ $termMonths }} bulan</td>
        <td align="center">{{ '' }}</td>
    </tr>
    <tr>
        <td align="center">Data Verifikasi</td>
        <td>{{ $verificationDate }}</td>
        <td align="right">{{ $proposedAmount }}</td>
        <td align="center">
            {{ number_format((float) $tokens['{jasa_persen}'] / max(1, $termMonths), 2) }}%/{{ '' }}
        </td>
        <td align="center">{{ $termMonths }} bulan</td>
        <td align="center">{{ '' }}</td>
    </tr>
    <tr>
        <td colspan="6" height="20">
            Catatan Verifikasi :
            <div>{{ $verificationNotes }}</div>
        </td>
    </tr>
</table>

<div style="margin-top: 12px;">
    <b>DATA PIUTANG ANGGOTA :</b>
</div>
<table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <th width="5%" align="center">No</th>
        <th width="20%" align="center">Nama Anggota</th>
        <th width="15%" align="center">Pinj. Lalu</th>
        <th width="15%" align="center">Proposal (Rp.)</th>
        <th width="15%" align="center">Rekom TV</th>
        <th width="15%" align="center">Rekom TP</th>
        <th align="center">Catatan</th>
    </tr>

    @foreach ($beneficiaries as $b)
        @php
            $proposedAmountBenef = (float) $b['proposed_amount'];
            $verifiedAmountBenef = (float) $b['verified_amount'];
            $allocatedAmountBenef = (float) $b['allocated_amount'];

            $proposalTotal += $proposedAmountBenef;
            $verifiedTotal += $verifiedAmountBenef;
            $allocatedTotal += $allocatedAmountBenef;

            $previousLoan = 0;
            $no = $loop->iteration;
        @endphp
        <tr>
            <td align="center">{{ $no }}</td>
            <td>{{ $b['name'] }}</td>
            <td align="right">{{ number_format((float) $previousLoan, 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) $proposedAmountBenef, 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) $verifiedAmountBenef, 0, ',', '.') }}</td>
            <td align="right">{{ number_format((float) $allocatedAmountBenef, 0, ',', '.') }}</td>
            <td>{{ '' }}</td>
        </tr>
    @endforeach

    <tr>
        <td align="center" colspan="2">
            <b>JUMLAH</b>
        </td>
        <td align="right">{{ number_format(0, 0, ',', '.') }}</td>
        <td align="right">{{ number_format((float) $proposalTotal, 0, ',', '.') }}</td>
        <td align="right">{{ number_format((float) $verifiedTotal, 0, ',', '.') }}</td>
        <td align="right">{{ number_format((float) $allocatedTotal, 0, ',', '.') }}</td>
        <td align="right">&nbsp;</td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td style="padding: 0px !important;">
            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                style="font-size: 11px;">
                <tr>
                    <td colspan="2">&nbsp;</td>
                </tr>
                <tr>
                    <td width="50%" align="justify" style="vertical-align: text-top;">
                        <div>Verified Sign:</div>
                        <div>
                            {{ '' }} {{ $identity['legal_name'] }} {{ $identity['district_name'] }}
                            {{ $identity['district_name'] }}
                            menyatakan dengan sebenar-benarnya sesuai
                            dengan hasil survey lapangan bahwa kelompok dengan identitas tersebut di atas
                            <b>ADA/TIDAK
                                ADA</b>
                            keberadaannya dan dapat dipertanggungjawabkan sesuai dengan peraturan yang berlaku.
                            Serta
                            <b>LAYAK/TIDAK
                                LAYAK</b> untuk diberikan piutang sesuai dengan hasil rekomendasi Verifikasi di
                            atas.
                            Form ini
                            digunakan sebagai dasar Verified pada SI UPK.
                        </div>
                    </td>
                    <td width="50%" align="justify" style="vertical-align: top;">
                        <div>
                            Diverifikasi oleh, {{ '' }} {{ $identity['legal_name'] }}
                            {{ $identity['district_name'] }}
                            {{ $identity['district_name'] }}
                        </div>
                        <div style="margin-top: 12px;">
                            <table border="0" width="100%" cellspacing="0" cellpadding="0"
                                style="font-size: 11px;">
                                <tr>
                                    <td width="70" height="20">
                                        <div>{{ '' }}</div>
                                        <div>
                                            <b>{{ '' }}</b>
                                        </div>
                                    </td>
                                    <td align="right" style="vertical-align: bottom;">
                                        _____________________________________
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            @if ($signatureHtml)
                {!! $signatureHtml !!}
            @else
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0"
                    style="font-size: 11px;">
                    <tr>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center">
                            <div>Mengetahui</div>
                            <div>{{ '' }}
                                {{ $group['village'] }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td height="30">&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center">
                            {{ '' }}
                        </td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>