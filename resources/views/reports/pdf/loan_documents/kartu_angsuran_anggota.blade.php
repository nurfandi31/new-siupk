@php
    use App\Support\IndonesianDate;

    $committeeChair = $tokens['{nama_ketua}'] ?? '';
    $committeeSecretary = $tokens['{nama_sekretaris}'] ?? '';
    $committeeTreasurer = $tokens['{nama_bendahara}'] ?? '';
    $disbursementDate = $tokens['{tgl_cair}'] ?? '';
    $documentTitle = $document['label'] ?? '';
    $signatureHtml = $signature ?? '';
    $installments = $installments ?? [];
    $barisAngsuran = max(1, (int) ceil(count($installments) / 2));
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ str_replace('_', ' ', $documentTitle) }}</title>
    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
        }

        html {
            margin-bottom: 100px;
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

        table tr th {
            font-size: 12px;
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
</head>

<body>
    @foreach ($beneficiaries as $b)
        @php
            $no = $loop->iteration;
            $wajibPokok = 0;
            $wajibJasa = 0;
            if (!empty($installments[0])) {
                $wajibPokok = (float) $installments[0]['principal_due'];
                $wajibJasa = (float) $installments[0]['interest_due'];
            }
            $angsuran = $wajibPokok + $wajibJasa;
        @endphp

        @if ($no > 1)
            <div class="break"></div>
        @endif
        <main style="position: relative; font-size: 12px;">
            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px;">
                <tr>
                    <td rowspan="7" align="center" width="400">
                        <div style="font-size: 14px; font-weight: bold;">
                            {{ $identity['legal_name'] }} {{ $identity['district_name'] }}
                        </div>
                        <div>
                            {{ $identity['address'] }}
                        </div>
                        <div>
                            Telp. {{ $identity['phone'] }}
                        </div>
                        <div style="margin-top: 8px;">
                            <img width="150" src="data:image/png;base64," alt="{{ $group['code'] }}">
                        </div>
                        <div style="font-size: 14px;">{{ $group['code'] }}</div>
                    </td>
                    <td width="150">Jenis Piutang</td>
                    <td width="5" align="center">:</td>
                    <td width="200">{{ $loan['product_code'] }}</td>
                    <td width="150">Loan Id.</td>
                    <td width="5" align="center">:</td>
                    <td width="200">{{ $loan['id'] }}</td>
                </tr>
                <tr>
                    <td>Nama Kelompok</td>
                    <td align="center">:</td>
                    <td style="font-weight: bold;" colspan="4">{{ $group['name'] }}</td>
                </tr>
                <tr>
                    <td>Alamat</td>
                    <td align="center">:</td>
                    <td colspan="4">{{ $group['address'] }}</td>
                </tr>
                <tr>
                    <td>Telpon/SMS</td>
                    <td align="center">:</td>
                    <td>{{ '' }}</td>
                    <td>Pemanfaat</td>
                    <td align="center">:</td>
                    <td style="font-weight: bold;">{{ $b['name'] }}</td>
                </tr>
                <tr>
                    <td>Tgl Cair</td>
                    <td align="center">:</td>
                    <td>{{ $disbursementDate }}</td>
                    <td>Jangka</td>
                    <td align="center">:</td>
                    <td>{{ $loan['term_months'] }} {{ 'Bulan' }}</td>
                </tr>
                <tr>
                    <td>Alokasi</td>
                    <td align="center">:</td>
                    <td>{{ number_format((float) $b['allocated_amount'], 0, ',', '.') }}</td>
                    <td>Jasa</td>
                    <td align="center">:</td>
                    <td>{{ number_format((float) $tokens['{jasa_persen}'] / max(1, $loan['term_months']), 2) . '%' }}</td>
                </tr>
                <tr>
                    <td>Angsuran</td>
                    <td align="center">:</td>

                    <td style="display: inline-block;">
                        {{ number_format((float) $angsuran, 0, ',', '.') }} /
                        {{ '' }}
                    </td>
                    <td colspan="3">
                        Angsuran pada tanggal {{ '' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="7" class="b t" style="font-weight: bold; font-size: 24px;" align="center">
                        KARTU ANGSURAN ANGGOTA
                    </td>
                </tr>
            </table>

            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                <tr>
                    <td width="5%">&nbsp;</td>
                    <td colspan="9" style="font-weight: bold;" height="30">TABEL KEWAJIBAN PEMBAYARAN ANGSURAN
                    </td>
                    <td width="5%">&nbsp;</td>
                </tr>

                <tr style="font-weight: bold;">
                    <th rowspan="{{ $barisAngsuran + 1 }}">&nbsp;</th>
                    <th height="30" class="l t b" align="center">Ke</th>
                    <th class="l t b" align="center">Tanggal</th>
                    <th class="l t b" align="center">Pokok</th>
                    <th class="l t b r" align="center">Jasa</th>

                    <th>&nbsp;</th>

                    <th class="l t b" align="center">Ke</th>
                    <th class="l t b" align="center">Tanggal</th>
                    <th class="l t b" align="center">Pokok</th>
                    <th class="l t b r" align="center">Jasa</th>
                    <th rowspan="{{ $barisAngsuran + 1 }}">&nbsp;</th>
                </tr>

                @for ($j = 1; $j <= $barisAngsuran; $j++)
                    @php
                        $i = $j - 1;
                        $raLeft = $installments[$i] ?? null;
                    @endphp
                    <tr>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                            {{ $raLeft['number'] ?? '' }}
                        </td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                            {{ $raLeft['due_date_label'] ?? '' }}
                        </td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="right">
                            {{ $raLeft ? number_format((float) $raLeft['principal_due'], 0, ',', '.') : '' }}
                        </td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }} r" align="right">
                            {{ $raLeft ? number_format((float) $raLeft['interest_due'], 0, ',', '.') : '' }}
                        </td>

                        <td>&nbsp;</td>

                        @php
                            $raRight = $installments[$i + $barisAngsuran] ?? null;
                        @endphp

                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                            {{ $raRight['number'] ?? '' }}
                        </td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="center">
                            {{ $raRight['due_date_label'] ?? '' }}
                        </td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }}" align="right">
                            {{ $raRight ? number_format((float) $raRight['principal_due'], 0, ',', '.') : '' }}
                        </td>
                        <td class="l {{ $j == $barisAngsuran ? 'b' : '' }} r" align="right">
                            {{ $raRight ? number_format((float) $raRight['interest_due'], 0, ',', '.') : '' }}
                        </td>
                    </tr>
                @endfor

            </table>

            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                <tr>
                    <td width="5%" rowspan="19">&nbsp;</td>
                    <td width="90%" colspan="9" style="font-weight: bold;" height="30">REALISASI PEMBAYARAN
                        ANGSURAN</td>
                    <td width="5%" rowspan="19">&nbsp;</td>
                </tr>
                <tr>
                    <th width="3%" class="l t b" rowspan="2">No</th>
                    <th width="10%" class="l t b" rowspan="2">Tanggal</th>
                    <th width="22%" class="l t" colspan="2">Pokok</th>
                    <th width="22%" class="l t" colspan="2">Jasa</th>
                    <th width="22%" class="l t" colspan="2">Saldo Piutang</th>
                    <th width="11%" class="l t r b" rowspan="2">Sign</th>
                </tr>
                <tr>
                    <th width="12%" class="l b t">Dibayar</th>
                    <th width="10%" class="l b t">Tunggakan</th>
                    <th width="12%" class="l b t">Dibayar</th>
                    <th width="10%" class="l b t">Tunggakan</th>
                    <th width="11%" class="l b t">Pokok</th>
                    <th width="11%" class="l b t">Jasa</th>
                </tr>

                @for ($i = 1; $i <= 16; $i++)
                    <tr>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="center">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="center">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="right">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="right">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="right">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="right">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="right">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }}" align="right">&nbsp;</td>
                        <td class="l {{ $i == 16 ? 'b' : '' }} r" align="center">&nbsp;</td>
                    </tr>
                @endfor
            </table>

            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                <tr>
                    <td width="5%" rowspan="5">&nbsp;</td>
                    <td colspan="3" style="font-weight: bold;" height="30">&nbsp;</td>
                    <td width="5%" rowspan="5">&nbsp;</td>
                </tr>
                <tr>
                    <td width="350" rowspan="3">
                        <div>Lembar 1 : Untuk Kelompok</div>
                        <div>Lembar 2 : Arsip Lembaga</div>
                    </td>
                    <td style="font-weight: bold; font-size: 12px;" width="350" align="center">Ketua Kelompok</td>
                    <td style="font-weight: bold; font-size: 12px;" width="350" align="center">
                        <div>Anggota Pemanfaat</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" height="50"></td>
                </tr>
                <tr style="font-weight: bold; font-size: 12px; text-transform: uppercase;">
                    <td width="350" align="center">
                        {{ $committeeChair }}
                    </td>
                    <td width="350" align="center">
                        <div>{{ $b['name'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="font-weight: bold;" height="10">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="4">
                        <ol>
                            <b>Perhatian:</b>
                            <li>Bayarlah angsuran tepat waktu sesuai dengan jadwal diatas</li>
                            <li>Untuk memudahkan pelayanan, bawalah kartu ini dan slip pembayaran terakhir setiap
                                melakukan
                                angsuran</li>
                            <li>Jagalah keutuhan kartu dan tidak melipatnya, jika hilang segera lapor
                                {{ $identity['legal_name'] }}</li>
                            <li>Jika lembar ini tidak mencukupi, cetak pada lembar baliknya dengan dibubuhi stempel
                                {{ $identity['legal_name'] }}</li>
                        </ol>
                    </td>
                    <td>
                        <div style="display: flex; height: 100%; justify-content: center; align-items: center;">
                            {{ '' }}
                        </div>
                    </td>
                </tr>
            </table>
        </main>
    @endforeach

    @if (!empty($signatureHtml))
        {!! $signatureHtml !!}
    @endif
</body>

</html>