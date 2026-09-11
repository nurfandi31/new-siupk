@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $minus = 0;
    $dataPemanfaat = [];
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
                <b>SURAT KUASA</b>
            </div>
            <div style="font-size: 16px; text-decoration: underline;">
                PENANDATANGANAN SPK
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="4" align="justify">
            Yang bertanda tangan di bawah ini, kami para anggota Kelompok {{ $group['name'] }} alamat
            {{ $group['address'] }} {{ '' }}
            {{ $group['village'] }} {{ $identity['district_name'] }} {{ $identity['district_name'] }} {{ $identity['regency_name'] }} :
        </td>
    </tr>
    <tr style="background: rgb(232, 232, 232)">
        <th class="b l t" width="10" height="20">No</th>
        <th class="b l t" width="140">Nama Anggota</th>
        <th class="b l t" width="80">Nik</th>
        <th class="b l t r">Alamat</th>
    </tr>

    @foreach ($beneficiaries as $b)
        @php
            if ((float) $b['allocated_amount'] == 0) {
                $minus += 1;
                continue;
            }

            $no = $loop->iteration - $minus;
            $dataPemanfaat[] = $b;
        @endphp

        <tr>
            <td height="15" class="l b" align="center">{{ $no }}</td>
            <td class="l b">{{ $b['name'] }}</td>
            <td class="l b" align="center">{{ $b['nik'] }}</td>
            <td class="l b r">
                {{ $group['address'] }} {{ '' }}
                {{ $group['village'] }}
            </td>
        </tr>
    @endforeach
    <tr>
        <td colspan="4">
            Memberikan kuasa sepenuhnya kepada pengurus kelompok :
        </td>
    </tr>
    <tr style="background: rgb(232, 232, 232)">
        <th class="b l t" width="10" height="20">No</th>
        <th class="b l t" width="140">Nama Anggota</th>
        <th class="b l t" width="80">Jabatan</th>
        <th class="b l t r">Alamat</th>
    </tr>
    <tr>
        <td height="15" class="l b" align="center">1</td>
        <td class="l b">{{ $committeeChair }}</td>
        <td class="l b">Ketua</td>
        <td class="l b r">
            {{ $group['address'] }}
        </td>
    </tr>
    <tr>
        <td height="15" class="l b" align="center">2</td>
        <td class="l b">{{ $committeeSecretary }}</td>
        <td class="l b">Sekretaris</td>
        <td class="l b r">
            {{ $group['address'] }}
        </td>
    </tr>
    <tr>
        <td height="15" class="l b" align="center">3</td>
        <td class="l b">{{ $committeeTreasurer }}</td>
        <td class="l b">Bendahara</td>
        <td class="l b r">
            {{ $group['address'] }}
        </td>
    </tr>
    <tr>
        <td colspan="4">
            <div style="text-align: justify;">
                Untuk menandatangani Surat Perjanjian Kredit (SPK) dan seluruh dokumen perjanjian sebagai bagian yang
                tidak terpisahkan dari Surat Perjanjian Kredit (SPK)
                kepada {{ $identity['legal_name'] }} {{ $identity['district_name'] }} {{ $identity['district_name'] }}.
            </div>

            <div style="text-align: justify;">
                Berkaitan dengan pemberian kuasa ini, kami seluruh anggota kelompok
                {{ $group['name'] }}
                menyatakan bersedia menanggung segala resiko dan tanggungjawab yang muncul sebagai akibat
                ditandatanganinya Surat perjanjian Kredit (SPK) tersebut.
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="4">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="3">&nbsp;</td>
        <td align="center">
            {{ $identity['district_name'] }}, {{ $disbursementDate }}
        </td>
    </tr>
    <tr>
        <td colspan="3">&nbsp;</td>
        <td align="center">
            Anggota Kelompok selaku pemberi kuasa :
        </td>
    </tr>
</table>

@php
    $batasPemanfaat = max(1, (int) ceil(count($dataPemanfaat) / 2));
@endphp

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td style="padding: 0px !important;">
            <table border="0" width="100%" cellspacing="0" cellpadding="0"
                style="font-size: 11px; margin-top: 8px;" class="p0">
                @for ($i = 1; $i <= $batasPemanfaat; $i++)
                    @php
                        $j = $i - 1;
                    @endphp
                    <tr>
                        <td width="25%" height="20">
                            @if (isset($dataPemanfaat[$j]))
                                {{ $i }}. {{ $dataPemanfaat[$j]['name'] }}
                            @endif
                        </td>
                        <td class="vb" width="25%">
                            @if (isset($dataPemanfaat[$j]))
                                ..............................................
                            @endif
                        </td>
                        <td width="25%">
                            @if (isset($dataPemanfaat[$j + $batasPemanfaat]))
                                {{ $i + $batasPemanfaat }}.
                                {{ $dataPemanfaat[$j + $batasPemanfaat]['name'] }}
                            @endif
                        </td>
                        <td class="vb" width="25%">
                            @if (isset($dataPemanfaat[$j + $batasPemanfaat]))
                                ..............................................
                            @endif
                        </td>
                    </tr>
                @endfor
            </table>

            <table border="0" width="100%" cellspacing="0" cellpadding="0"
                style="font-size: 11px; margin-top: 8px;" class="p0">
                <tr>
                    <td colspan="3" align="center">
                        Pengurus kelompok Selaku Penerima Kuasa :
                    </td>
                </tr>
                <tr>
                    <td colspan="3" height="50"></td>
                </tr>
                <tr>
                    <td align="center" width="33%">
                        {{ $committeeChair }}
                    </td>
                    <td align="center" width="33%">
                        {{ $committeeSecretary }}
                    </td>
                    <td align="center" width="33%">
                        {{ $committeeTreasurer }}
                    </td>
                </tr>
                <tr>
                    <td align="center">Ketua</td>
                    <td align="center">Sekretaris</td>
                    <td align="center">Bendahara</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif