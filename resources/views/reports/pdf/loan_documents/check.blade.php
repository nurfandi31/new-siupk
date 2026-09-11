@php
    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $documentTitle = $document['label'] ?? '';

    $data = [
        'Cover/Sampul',
        'Surat Permohonan Pinjaman',
        'Surat Rekomendasi Kredit',
        'Profil Kelompok',
        'Susunan Pengurus',
        'Daftar Anggota Kelompok',
        'Daftar Pemanfaat',
        'Surat Pernyataan Tanggung Renteng',
        'FC KTP Pemanfaat dan Penjamin',
    ];

    $data[] = 'Surat Pernyataan Peminjam';
    $data[] = 'BA Musyawarah';
    $data[] = 'Form Verifikasi';
    $data[] = 'Daftar Hadir Verifikasi';
    $data[] = 'Form Verifikasi Anggota';
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
    <tr class="b">
        <td colspan="3" align="center">
            <div style="font-size: 20px;">
                <b>CHECK LIST</b>
            </div>
            <div style="font-size: 18px;">
                KELENGKAPAN PROPOSAL {{ strtoupper($loan['product_code']) }}
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>

</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td width="100">Kode Kelompok</td>
        <td width="5">:</td>
        <td style="font-weight: bold;">{{ $group['code'] }}</td>

        <td width="80">&nbsp;</td>

        <td width="100">Tanggal</td>
        <td width="5">:</td>
        <td style="font-weight: bold;">{{ $proposalDate }}</td>
    </tr>

    <tr>
        <td>Nama Kelompok</td>
        <td width="5">:</td>
        <td style="font-weight: bold;">{{ $group['name'] }}</td>

        <td>&nbsp;</td>

        <td>Ketua</td>
        <td width="5">:</td>
        <td style="font-weight: bold;">{{ $committeeChair }}</td>
    </tr>

    <tr>
        <td>Desa/Kelurahan</td>
        <td width="5">:</td>
        <td style="font-weight: bold;">{{ $group['village'] }}</td>

        <td>&nbsp;</td>

        <td>Telpon</td>
        <td width="5">:</td>
        <td style="font-weight: bold;">{{ $identity['phone'] }}</td>
    </tr>
</table>

<table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
    <tr style="background: rgb(232,232,232);">
        <th rowspan="2" width="10">No</th>
        <th rowspan="2">Nama Dokumen</th>
        <th colspan="3">Status</th>
        <th rowspan="2" width="150">Catatan</th>
    </tr>
    <tr style="background: rgb(232,232,232);">
        <th width="30">C</th>
        <th width="30">K</th>
        <th width="30">TA</th>
    </tr>

    @php
        $nomor = 0;
    @endphp
    @foreach ($data as $v)
        @php
            $nomor++;
        @endphp
        <tr>
            <td align="center">{{ $nomor }}</td>
            <td>{{ $v }}</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="6">
            <b>Catatan :</b>
            <br>
            <br>
            <br>
            <br>
            <br>
        </td>
    </tr>
</table>
<div style="font-size: 8px; margin-bottom: 16px;">Keterangan: C = Cukup | K = Kurang | TA = Tidak Ada</div>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 12px;">
    <tr>
        <td width="60%"></td>
        <td width="40%" align="center">Diperika tanggal, ___________________</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center">Diperiksa Oleh</td>
    </tr>
    <tr>
        <td colspan="2" height="40">&nbsp;</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="center"><b>_____________________</b></td>
    </tr>
</table>