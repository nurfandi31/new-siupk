@php
    use App\Support\IndonesianDate;
    use App\Support\IndonesianNumber;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $alokasi = number_format((float) $loan['principal_amount'], 0, ',', '.');
    $beneficiaryCount = count($beneficiaries);
    $minus = 0;
    $disbursedYmd = $loan['disbursed_at'] ?? '';
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
                <b>BERITA ACARA PENCAIRAN</b>
            </div>
            <div style="font-size: 16px;">
                <b>PIUTANG KELOMPOK {{ $loan['product_code'] }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<div style="text-align: justify;">
    Pada hari ini {{ IndonesianDate::dayName($disbursedYmd) }} tanggal
    {{ IndonesianNumber::spelledOut((float) IndonesianDate::day($disbursedYmd)) }} bulan {{ IndonesianDate::monthName($disbursedYmd) }} tahun
    {{ IndonesianNumber::spelledOut((float) IndonesianDate::year($disbursedYmd)) }}, telah diadakan pencairan dana perguliran
    {{ $identity['legal_name'] }} {{ $identity['district_name'] }} kepada Kelompok
    {{ $group['name'] }} {{ '' }}
    {{ $group['village'] }} {{ $identity['district_name'] }} bertempat di
    {{ '' }},
    sebesar Rp.
    {{ $alokasi }} ({{ '' }} Rupiah), sesuai dengan
    Register Piutang pada Data Base Piutang Nomor nomor : {{ $group['code'] }} dan Surat Perjanjian
    Kredit (SPK) nomor: {{ $loan['loan_number'] }}.
</div>

<div style="text-align: justify;">
    Adapun rincian piutang dan data kelompok (Profil Kelompok) adalah sebagai berikut :
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td width="10" align="center">1.</td>
            <td width="100">{{ '' }}</td>
            <td width="10" align="center">:</td>
            <td>
                <b>{{ $group['village'] }}</b>
            </td>

            <td width="10" align="center">9.</td>
            <td width="100">Tingkat Kelompok</td>
            <td width="10" align="center">:</td>
            <td>
                <b>{{ '' }}</b>
            </td>
        </tr>
        <tr>
            <td align="center">2.</td>
            <td>Nama Kelompok</td>
            <td align="center">:</td>
            <td>
                <b>{{ $group['name'] }}</b>
            </td>

            <td align="center">10.</td>
            <td>Fungsi Kelompok</td>
            <td align="center">:</td>
            <td>
                <b>{{ '' }}</b>
            </td>
        </tr>
        <tr>
            <td align="center">3.</td>
            <td>Alamat Kelompok</td>
            <td align="center">:</td>
            <td>
                <b>{{ $group['address'] }}</b>
            </td>

            <td align="center">11.</td>
            <td>Nama Ketua</td>
            <td align="center">:</td>
            <td>
                <b>{{ $committeeChair }}</b>
            </td>
        </tr>
        <tr>
            <td align="center">4.</td>
            <td>Tanggal Berdiri</td>
            <td align="center">:</td>
            <td>
                <b>{{ '' }}</b>
            </td>

            <td align="center">12.</td>
            <td>Nomor Kontak</td>
            <td align="center">:</td>
            <td>
                <b>{{ $identity['phone'] }}</b>
            </td>
        </tr>
        <tr>
            <td align="center">5.</td>
            <td>Jumlah Pemanfaat</td>
            <td align="center">:</td>
            <td>
                <b>{{ $beneficiaryCount }} Orang</b>
            </td>

            <td align="center">13.</td>
            <td>Tanggal Pencairan</td>
            <td align="center">:</td>
            <td>
                <b>{{ $disbursementDate }}</b>
            </td>
        </tr>
        <tr>
            <td align="center">6.</td>
            <td>Jenis Piutang</td>
            <td align="center">:</td>
            <td>
                <b>{{ $loan['product_code'] }} Orang</b>
            </td>

            <td align="center">14.</td>
            <td>Alokasi Piutang</td>
            <td align="center">:</td>
            <td>
                <b>{{ $alokasi }}</b>
            </td>
        </tr>
        <tr>
            <td align="center">7.</td>
            <td>Jenis Usaha</td>
            <td align="center">:</td>
            <td>
                <b>{{ '' }}</b>
            </td>

            <td align="center">15.</td>
            <td>Jangka Sistem</td>
            <td align="center">:</td>
            <td>
                <b>{{ $loan['term_months'] }} / {{ '' }}</b>
            </td>
        </tr>
        <tr>
            <td align="center">8.</td>
            <td>Jenis Kegiatan</td>
            <td align="center">:</td>
            <td>
                <b>{{ '' }}</b>
            </td>

            <td align="center">16.</td>
            <td>Prosentase Jasa</td>
            <td align="center">:</td>
            <td>
                <b>{{ number_format((float) $tokens['{jasa_persen}'] / max(1, $loan['term_months']), 2) }}%</b>
            </td>
        </tr>
    </table>
    Untuk bertindak mewakili Kelompok dalam perjanjian kredit dengan {{ $identity['legal_name'] }}
    {{ $identity['district_name'] }} sesuai
    dengan registrasi piutang nomor {{ $group['code'] }} dan data piutang sebagai berikut :

    <table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
        <tr style="background: rgb(232, 232, 232)">
            <th width="3%" height="20">No</th>
            <th width="17%">Nik</th>
            <th width="20%">Nama Anggota</th>
            <th width="15%">Nomor HP</th>
            <th width="30%">Alamat</th>
            <th width="15%">Alokasi</th>
        </tr>

        @foreach ($beneficiaries as $b)
            @php
                if ((float) $b['allocated_amount'] == 0) {
                    $minus += 1;
                    continue;
                }

                $no = $loop->iteration - $minus;
            @endphp
            <tr>
                <td align="center">{{ $no }}</td>
                <td align="center">{{ $b['nik'] }}</td>
                <td>{{ $b['name'] }}</td>
                <td align="center">{{ '' }}</td>
                <td>{{ $group['address'] }}</td>
                <td align="right">{{ number_format((float) $b['allocated_amount'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td style="padding: 0px !important;">
                <div>
                    Catatan Pencairan :
                </div>

                <p style="margin-top: 24px;">
                    Demikian, berita acara ini dibuat sekaligus sebagai bukti pencairan dana piutang di atas.
                </p>

                @if ($signatureHtml)
                    {!! $signatureHtml !!}
                @else
                    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                        <tr>
                            <td width="50%">&nbsp;</td>
                            <td width="25%">&nbsp;</td>
                            <td width="25%">&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td align="center" colspan="2">
                                {{ $identity['district_name'] }}, {{ $disbursementDate }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" height="40">&nbsp;</td>
                        </tr>
                        <tr>
                            <td align="center" style="font-weight: bold;">
                                {{ '' }}
                            </td>
                            <td colspan="2" align="center" style="font-weight: bold;">{{ $committeeChair }}
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                {{ '' }}
                            </td>
                            <td colspan="2" align="center">Ketua Kelompok</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>