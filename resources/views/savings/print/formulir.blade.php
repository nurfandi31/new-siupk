<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        .style6 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 14px; }
        .style9 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; }
        .style10 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 9px; }
        .all { border: 1px solid #000000; }
        .top { border-top: 1px solid #000000; }
        .bottom { border-bottom: 1px solid #000000; }
        .left { border-left: 1px solid #000000; }
        .right { border-right: 1px solid #000000; }
        .style27 { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; font-weight: bold; }
        .align-justify { text-align: justify; }
        .align-center { text-align: center; }
        .align-right { text-align: right; }
    </style>
</head>
<body @if (! request()->boolean('preview')) onload="window.print()" @endif>
@php
    $member = $account['member'];
    $gender = $member['gender'] ?? '';
    $birth = trim(($member['birth_place'] ?? '').', '.($member['birth_date'] ?? ''), ', ');
    $isInstitution = (int) ($account['authority_type'] ?? 1) === 2;
    $jenisRekening = (string) ($account['product']['name'] ?? '');
    $mark = ($pdf ?? false) ? 'X' : '✓';
@endphp

<table width="97%" border="0" align="center" cellpadding="3" cellspacing="0">
    <tr>
        <td height="20" colspan="2" width="65%" class="bottom">
            <span class="style9">&nbsp;</span>
        </td>
        <td height="20" colspan="2" class="bottom">
            <div align="right" class="style9">CIF : </div>
            <div align="right" class="style9">No Rekening : </div>
        </td>
        <td height="20" colspan="2" class="bottom" width="15%">
            <div align="left" class="style9 bottom">&nbsp;</div>
            <div align="left" class="style9 bottom">&nbsp;</div>
        </td>
    </tr>
</table>

<table width="97%" border="0" align="center" cellpadding="3" cellspacing="0">
    <tr>
        <td height="35" colspan="6" class="bottom"><p align="center" class="style6"><b>FORMULIR SIMPANAN NASABAH</b></p></td>
    </tr>
    <tr>
        <td height="0" class="align-justify style9"><div align="right">1.</div></td>
        <td height="0" class="align-justify style9">N I K</td>
        <td height="0" colspan="4" class="style27 bottom">: {{ $member['nik'] ?? '' }}</td>
    </tr>
    <tr>
        <td width="4%" class="align-justify style9"><div align="right">2.</div></td>
        <td width="25%" class="align-justify style9">Nama Lengkap</td>
        <td colspan="4" class="style27 bottom">: {{ $member['name'] ?? '' }}</td>
    </tr>
    <tr>
        <td width="4%" class="align-justify style9"><div align="right">3.</div></td>
        <td width="25%" class="align-justify style9">Nama Alias</td>
        <td colspan="3" width="40%" class="style27 bottom">:&nbsp;</td>
        <td class="style27">
            &nbsp; &nbsp; [{{ $gender === 'L' ? $mark : ' ' }}] Laki Laki
            &nbsp; &nbsp; [{{ $gender === 'P' ? $mark : ' ' }}] Perempuan
        </td>
    </tr>
    <tr>
        <td height="0" class="align-justify style9"><div align="right">4.</div></td>
        <td height="0" class="align-justify style9">Tempat, Tanggal Lahir</td>
        <td height="0" colspan="4" class="style27 bottom">: {{ $birth }}</td>
    </tr>
    <tr>
        <td height="0" class="align-justify style9"><div align="right">5.</div></td>
        <td height="0" class="align-justify style9">Alamat</td>
        <td height="0" colspan="4" class="style27 bottom">: {{ $member['address'] ?? '' }}</td>
    </tr>
    <tr>
        <td height="0" class="align-justify style9"><div align="right">6.</div></td>
        <td height="0" class="align-justify style9">No. Handphone</td>
        <td height="0" colspan="4" class="style27 bottom">: {{ $member['phone'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">7.</div></td>
        <td class="align-justify style9">Agama</td>
        <td colspan="4" class="style27 bottom">:&nbsp;</td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">8.</div></td>
        <td class="align-justify style9">Status Pernikahan</td>
        <td colspan="4" class="style27">
            : &nbsp; &nbsp; [ ] Lajang &nbsp; &nbsp; [ ] Menikah &nbsp; &nbsp; [ ] Duda/Janda
        </td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">9.</div></td>
        <td class="align-justify style9">Pendidikan Terakhir</td>
        <td colspan="4" class="style27">
            : &nbsp; &nbsp; [ ] Tidak ada &nbsp; &nbsp; [ ] SD &nbsp; &nbsp; [ ] SLTP &nbsp; &nbsp; [ ] SLTA
            &nbsp; &nbsp; [ ] D3 &nbsp; &nbsp; [ ] S1 &nbsp; &nbsp; [ ] S2 &nbsp; &nbsp; [ ] S3 &nbsp; &nbsp; [ ] Lainnya
        </td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">10.</div></td>
        <td class="align-justify style9">Pekerjaan</td>
        <td colspan="4" class="style27 bottom">: {{ $member['occupation'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">11.</div></td>
        <td class="align-justify style9">Alamat Pekerjaan</td>
        <td colspan="4" class="style27 bottom">: {{ $member['address'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">12.</div></td>
        <td class="align-justify style9">NPWP</td>
        <td colspan="4" class="style27 bottom">:&nbsp;</td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">13.</div></td>
        <td class="align-justify style9">Penghasilan per Bulan</td>
        <td colspan="4" class="style27">
            : &nbsp; &nbsp; [ ] &lt; 5 jt &nbsp; &nbsp; [ ] 5 - 15 jt &nbsp; &nbsp; [ ] 15 - 25 jt &nbsp; &nbsp; [ ] &gt; 25 jt
        </td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">14.</div></td>
        <td class="align-justify style9">Sumber Dana</td>
        <td colspan="4" class="style27">
            : &nbsp; &nbsp; [ ] Gaji/Upah &nbsp; &nbsp; [ ] Usaha &nbsp; &nbsp; [ ] Lainnya
        </td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">15.</div></td>
        <td class="align-justify style9">Jenis Rekening</td>
        <td colspan="4" class="style27 bottom">:
            &nbsp; &nbsp; [{{ $jenisRekening === 'Simantera' ? $mark : ' ' }}] Simantera
            &nbsp; &nbsp; [{{ $jenisRekening === 'Simantera Qurban' ? $mark : ' ' }}] Simantera Qurban
            &nbsp; &nbsp; [{{ $jenisRekening === 'Simantera Hari raya' ? $mark : ' ' }}] Simantera Hari raya
        </td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">16.</div></td>
        <td class="align-justify style9">Status Permohonan</td>
        <td colspan="4" class="style27">
            : &nbsp; &nbsp; [{{ $isInstitution ? ' ' : $mark }}] Pribadi
            &nbsp; &nbsp; [{{ $isInstitution ? $mark : ' ' }}] Kuasa
        </td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right"></div></td>
        <td colspan="4" class="style27"><span class="style27 bottom">Isikan Jika Status Permohonan Kuasa</span>
            <table width="97%" border="0" align="center" cellpadding="3" cellspacing="0">
                <tr>
                    <td width="4%" class="align-justify style9"><div align="right">1.</div></td>
                    <td width="25%" class="align-justify style9">Nama Lembaga</td>
                    <td colspan="4" class="style27 bottom">: {{ $account['institution_name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td width="4%" class="align-justify style9"><div align="right">2.</div></td>
                    <td width="25%" class="align-justify style9">Jabatan</td>
                    <td colspan="4" class="style27 bottom">: {{ $account['institution_position'] ?? '' }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">17.</div></td>
        <td class="align-justify style9">Pengampu</td>
        <td colspan="4" class="style27 bottom">: {{ $account['guardian_name'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">18.</div></td>
        <td class="align-justify style9">Hubungan</td>
        <td colspan="4" class="style27 bottom">: {{ $account['relationship'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="align-justify style9"><div align="right">19.</div></td>
        <td class="align-justify style9">Nama Gadis Ibu Kandung</td>
        <td colspan="4" class="style27 bottom">:&nbsp;</td>
    </tr>
</table>
<br>
<table width="97%" border="0" class="style9 top" align="center" cellpadding="3" cellspacing="0">
    <tr>
        <td width="45%" class="style9 align-justify" colspan="2">
            <p><span class="style27">PERNYATAAN</span><br>Bersama ini saya menyatakan bahwa<br>
                1. Semua data isian diatas adalah benar.<br>
                2. Menyetujui serta tunduk pada ketentuan dan syarat umum yang berlaku pada pembukaan rekening di {{ $organization['legal_name'] }}.<br>
                3. Dana yang saya setorkan dan pergunakan tidak berasal dari / untuk tujuan <i>money laundering</i> atau pencucian uang.
            </p>
        </td>
    </tr>
    <tr>
        <td height="24" class="style9">
            <p align="center">Petugas Penerima</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <p align="center"><b>{{ $penerima }}</b></p>
        </td>
        <td height="24" class="style9">
            <p align="center">Pemohon</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <p align="center"><b>{{ $member['name'] ?? '' }}</b></p>
        </td>
    </tr>
    <tr>
        <td colspan="3">&nbsp;<p>&nbsp;</p><p>&nbsp;</p></td>
    </tr>
    <tr>
        <td class="style10 top align-right" colspan="3">Dicetak Oleh: {{ $pencetak }}; pada: {{ now()->format('Y-m-d H:i:s') }}</td>
    </tr>
</table>
</body>
</html>
