@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
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

<table border="0" width="100%" cellspacing="0" cellpadding="0">
    @foreach ($beneficiaries as $b)
        <tr>
            <td style="padding-top: 6px;">
                <div style="padding: 12px; padding-bottom: 0px; border: 1px solid #000;">
                    <table border="0" width="100%" class="p" style="font-size: 11px;">
                        <tr>
                            <td colspan="3" align="center" style="text-transform: uppercase; font-size: 14px;">
                                <b>K u i t a n s i</b>
                            </td>
                        </tr>
                        <tr>
                            <td width="90">Telah Diterima Dari</td>
                            <td width="10" align="center">:</td>
                            <td class="b">
                                <b>{{ '' }}</b>
                            </td>
                        </tr>
                        <tr>
                            <td>Uang Sebanyak</td>
                            <td align="center">:</td>
                            <td class="b">
                                <b>{{ '' }} Rupiah</b>
                            </td>
                        </tr>
                        <tr>
                            <td>Untuk Pembayaran</td>
                            <td align="center">:</td>
                            <td class="b">
                                <b>Pencairan Pemanfaat Kelompok {{ $group['name'] }}</b>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">&nbsp;</td>
                            <td class="b">
                                <b>
                                    a.n. {{ $b['name'] }} NIK. {{ $b['nik'] }}
                                </b>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3"></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="t b" align="center">
                                Rp. {{ number_format((float) $b['allocated_amount'], 0, ',', '.') }}
                            </td>
                            <td>&nbsp;</td>
                        </tr>
                    </table>

                    <table border="0" width="100%" style="font-size: 11px;">
                        <tr>
                            <td width="60%">&nbsp;</td>
                            <td width="40%" align="center">
                                {{ $identity['district_name'] }}, {{ $tokens['{tgl_cair}'] ?? '' }}
                            </td>
                        </tr>
                        <tr>
                            <td width="60%">&nbsp;</td>
                            <td width="40%" align="center">
                                Diterima Oleh
                            </td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td align="center">
                                Anggota Pemanfaat
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" height="30">&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td align="center">
                                <b>{{ $b['name'] }}</b>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    @endforeach
</table>

@if (!empty($signatureHtml))
    {!! $signatureHtml !!}
@endif