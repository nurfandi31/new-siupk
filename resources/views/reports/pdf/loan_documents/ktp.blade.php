@php
    $documentTitle = $document['label'] ?? '';
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

    .identity-card {
        width: 85.6mm;
        height: 54mm;
        page-break-inside: avoid;
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
            <div style="font-size: 18px; text-decoration: underline">
                <b>FC KTP PEMANFAAT DAN PENJAMIN</b>
            </div>
            <div style="font-size: 16px;">
                <b>KELOMPOK {{ strtoupper($group['name']) }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

@foreach ($beneficiaries as $b)
    <div class="identity-card">
        <table width="100%" height="100%" cellspacing="0" cellpadding="4" style="table-layout:fixed;border:1px solid #000;">
            <tr>
                <td width="46%" align="center" valign="middle" style="border-right:1px solid #000;">
                    @if (! empty($b['identity_photo_data']))
                        <img src="{{ $b['identity_photo_data'] }}" alt="Foto KTP {{ $b['name'] }}" style="max-width:100%;max-height:100%;object-fit:contain;" />
                    @else
                        <div style="height:100%;padding:6px;border:1px dashed #666;color:#666;">
                            <b>FOTO KTP BELUM DIUNGGAH</b>
                            <br />
                            Unggah di profil anggota
                        </div>
                    @endif
                </td>
                <td valign="top">
                    <b>{{ $b['name'] }}</b>
                    <br />
                    NIK: {{ $b['nik'] ?: '—' }}
                    <br />
                    {{ $group['address'] ?: '—' }}
                </td>
            </tr>
        </table>
    </div>

    @unless ($loop->last)
        <div style="page-break-after:always;"></div>
    @endunless
@endforeach
