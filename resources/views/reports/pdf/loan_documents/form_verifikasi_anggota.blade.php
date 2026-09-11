@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
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

@foreach ($beneficiaries as $b)
    @if ($loop->iteration > 1)
        <div class="break"></div>
    @endif

    @php
        $identityItems = [
            ['label' => 'Nama Anggota', 'value' => $b['name']],
            ['label' => 'NIK', 'value' => $b['nik']],
            ['label' => 'Tempat, Tanggal Lahir', 'value' => ''],
            ['label' => 'Alamat', 'value' => $group['address']],
            ['label' => 'Pekerjaan Pokok Suami', 'value' => ''],
            ['label' => 'Pekerjaan Pokok Istri', 'value' => ''],
            ['label' => 'Jumlah Kredit yang Diminta', 'value' => 'Rp. ' . number_format((float) $b['proposed_amount'], 0, ',', '.')],
            ['label' => 'Jenis Usaha yang Akan Didanai', 'value' => ''],
        ];
        $infoKelompok = [
            'Apakah anggota ini aktif dalam pertemuan kelompok',
            'Apakah anggota ini aktif memberikan usul, pendapat, sadan dan sebagainya',
            'Apakah anggota ini menunjukkan sikap tenang dan terbuka',
            'Apakah anggota ini jujur, disiplin dan berusaha menepati janji',
            'Apakah anggota ini bersedia membayar iuran-iuran di kelompok',
            'Apakah anggota ini disiplin dalam membayar piutangnya',
            'Apakah anggota ini rajin menabung di kelompok',
            'Apakah bersedia menjaminkan harta/tabungan sebagai jaminan kredit yang diminta',
            'Apakah bersedia menandatangani perjanjian kredit berdua (suami/istri/orang tua)',
        ];
        $pendapatanPengeluaran = [
            [
                'Pendapatan rutin keluarga (suami & istri) per bulan',
                'Pendapatan dari hasil kebun, sawah, ladang',
                'Pendapatan lain-lain',
            ],
            [
                'Pengeluaran keluarga',
                'Pembelian alat/barang dagangan',
                'Pengeluaran kebutuhan makan/minum',
                'Pengeluaran sabun-cuci-mandi',
                'Pengeluaran untuk sekolah',
                'Pengeluaran untuk sosial',
                'Pengeluaran listrik, telpon, dll',
                'Pengeluaran lain-lain',
            ],
        ];
        $jaminan = ['Tabungan tanggung renteng', 'Nilai harta lain berupa ....................................'];
        $penilaian = [
            'Ratio pendapatan keluarga (bersih) per bulan dibagi angsuran per bulan',
            'Ratio tabungan di kelompok dibagi kredit yang diajukan',
        ];
    @endphp

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td class="b" colspan="3" align="center">
                <div style="font-size: 16px;">
                    PENILAIAN PERMOHONAN PIUTANG ANGGOTA KELOMPOK
                </div>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
        <tbody>
            <tr style="font-weight: bold;">
                <td width="3%" align="center">A.</td>
                <td colspan="6">IDENTITAS PEMINJAM (ANGGOTA)</td>
            </tr>

            @php
                $length = ceil(count($identityItems) / 2);
            @endphp

            <tr>
                <td>&nbsp;</td>
                <td colspan="6">
                    <table border="0" width="100%" cellspacing="0" cellpadding="0"
                        style="font-size: 11px; table-layout: fixed;">
                        @for ($i = 0; $i < $length; $i++)
                            @php
                                $label1 = $identityItems[$i]['label'];
                                $value1 = ': ' . $identityItems[$i]['value'];

                                $label2 = '';
                                $value2 = '';
                                if (isset($identityItems[$i + $length])) {
                                    $label2 = $identityItems[$i + $length]['label'];
                                    $value2 = ': ' . $identityItems[$i + $length]['value'];
                                }
                            @endphp

                            <tr>
                                <td width="21%">{{ $label1 }}</td>
                                <td width="25%">{{ $value1 }}</td>
                                <td width="29%">{{ $label2 }}</td>
                                <td width="25%">{{ $value2 }}</td>
                            </tr>
                        @endfor
                    </table>
                </td>
            </tr>

            <tr style="font-weight: bold;">
                <td class="t l b" align="center">B.</td>
                <td class="t b" colspan="4">INFORMASI DALAM KELOMPOK</td>
                <td class="t l b" width="7%" align="center">YA</td>
                <td class="t l b r" width="7%" align="center">TIDAK</td>
            </tr>

            @foreach ($infoKelompok as $val)
                <tr>
                    <td class="t l b">&nbsp;</td>
                    <td class="t b" align="center" width="3%">{{ $loop->iteration }}.</td>
                    <td class="t b" colspan="3">{{ $val }}</td>
                    <td class="t l b" align="center">&nbsp;</td>
                    <td class="t l b r" align="center">&nbsp;</td>
                </tr>
            @endforeach

            <tr style="font-weight: bold;">
                <td align="center">C.</td>
                <td colspan="6">INFORMASI PENDAPATAN & PENGELUARAN</td>
            </tr>

            @foreach ($pendapatanPengeluaran as $p)
                @php
                    $no = $loop->iteration;
                    $nomor = 0;
                @endphp
                @foreach ($p as $val)
                    @php
                        $number = '';
                        if ($no != $nomor) {
                            $nomor = $no;
                            $number = $no . '.';
                        }

                        $title = 'Pengeluaran';
                        if ($no == 1) {
                            $title = 'Pendapatan';
                        }
                    @endphp

                    <tr>
                        <td>&nbsp;</td>
                        <td align="center" width="3%">{{ $number }}</td>
                        <td colspan="2">{{ $val }}</td>
                        <td width="7%">Rp.</td>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                @endforeach

                <tr style="font-weight: bold;">
                    <td>&nbsp;</td>
                    <td align="center" width="3%">&nbsp;</td>
                    <td align="center" colspan="2">Jumlah {{ $title }}</td>
                    <td width="7%">Rp.</td>
                    <td colspan="2">&nbsp;</td>
                </tr>
            @endforeach

            <tr style="font-weight: bold;">
                <td align="center">D.</td>
                <td colspan="6">IDENTITAS JAMINAN</td>
            </tr>

            @foreach ($jaminan as $val)
                <tr>
                    <td>&nbsp;</td>
                    <td align="center" width="3%">{{ $loop->iteration }}.</td>
                    <td colspan="2">{{ $val }}</td>
                    <td width="7%">Rp.</td>
                    <td colspan="2">&nbsp;</td>
                </tr>
            @endforeach

            <tr style="font-weight: bold;">
                <td>&nbsp;</td>
                <td align="center" width="3%">&nbsp;</td>
                <td align="center" colspan="2">Total Nilai Jaminan</td>
                <td width="7%">Rp.</td>
                <td colspan="2">&nbsp;</td>
            </tr>

            <tr style="font-weight: bold;">
                <td align="center">E.</td>
                <td colspan="6">PENILAIAN</td>
            </tr>

            @foreach ($penilaian as $val)
                <tr>
                    <td>&nbsp;</td>
                    <td align="center" width="3%">{{ $loop->iteration }}.</td>
                    <td colspan="2">{{ $val }}</td>
                    <td width="7%">.....%</td>
                    <td colspan="2">&nbsp;</td>
                </tr>
            @endforeach

            <tr style="font-weight: bold;">
                <td align="center">E.</td>
                <td colspan="6">KESIMPULAN</td>
            </tr>

            <tr class="vt">
                <td>&nbsp;</td>
                <td colspan="2">
                    <div>
                        Anggota/pemanfaat ini LAYAK / TIDAK LAYAK untuk diberikan piutang sebesar:
                    </div>
                    <div>
                        ........................................................................
                    </div>
                    <div>
                        Catatan:
                        <br>
                        <br>
                    </div>
                    <div>
                        Coret yang tidak perlu
                    </div>
                </td>
                <td colspan="4">
                    <div>Diverifikasi oleh, Tim Verifikasi {{ $identity['legal_name'] }} Kecamatan
                        {{ $identity['district_name'] }}</div>
                    <table border="0" width="100%" cellspacing="0" cellpadding="0"
                        style="font-size: 11px; table-layout: fixed;">
                        <tr>
                            <td width="70" height="20">
                                <div>{{ '' }}</div>
                                <div>
                                    <b>{{ '' }}</b>
                                </div>
                            </td>
                            <td align="right" style="vertical-align: bottom;">
                                <b>____________________________________</b>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
@endforeach

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif