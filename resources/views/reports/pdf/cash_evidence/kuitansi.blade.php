@php
    use App\Domain\Accounting\Services\Reports\DocumentKindClassifier;

    $identity = $identity ?? [];
    $entry = $entry ?? [];
    $kind = $kind ?? DocumentKindClassifier::KIND_BM;
    $isCashOut = $kind === DocumentKindClassifier::KIND_BKK;

    $legalName = strtoupper((string) ($identity['legal_name'] ?? config('app.name')));
    $district = (string) ($identity['district_name'] ?? '');
    $regency = (string) ($identity['regency_name'] ?? '');
    $registration = (string) ($identity['registration_number'] ?? '');
    $address = (string) ($identity['address'] ?? '');
    $phone = (string) ($identity['phone'] ?? '');
    $logoUrl = $identity['logo_url'] ?? null;

    $locationLine = trim(($district !== '' ? 'Kec. '.$district : '').' '.($regency !== '' ? 'Kab. '.$regency : ''));
    $documentNumber = $document_number ?? ($entry['journal_number'] ?: ($entry['id'] ?? ''));
    $dateLabel = $document_date_label ?? '-';
    $receivedFrom = $received_from ?? ($relation ?? '--');
    $paidBy = $paid_by ?? '--';
    $receivedBy = $received_by ?? '--';
    $purpose = $purpose ?? ($entry['description'] ?? '');
    $amountLabel = $amount_label ?? '-';
    $amountTerbilang = $amount_terbilang ?? '-';
    $terbilangClass = strlen($amountTerbilang) > 30 ? 'terbilang-small' : '';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>K U I T A N S I</title>
    <style>
        @page {
            size: 20cm 10cm landscape;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff;
        }
        body {
            padding: 20px;
        }
        .container {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .box {
            border: 2px solid #000;
            padding-top: 16px;
            padding-bottom: 12px;
            padding-right: 22px;
            padding-left: 12px;
        }
        .box-header {
            padding-left: 16px;
            padding-right: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.5);
        }
        .box-header td {
            vertical-align: middle;
        }
        .box-body {
            padding-top: 0;
            padding-left: 24px;
            padding-right: 24px;
            font-size: 14px;
        }
        .fw-bold { font-weight: bold; }
        .fs-8 { font-size: 8px; }
        .fs-10 { font-size: 10px; }
        .fs-12 { font-size: 12px; }
        .fs-14 { font-size: 14px; }
        .align-center { text-align: center; }
        .align-right { text-align: right; }
        .keterangan {
            padding: 6px 6px 4px;
            vertical-align: top;
        }
        .border-b {
            border-bottom: 1px dashed rgba(0, 0, 0, 0.4);
        }
        .jajargenjang {
            background-color: rgb(204, 204, 204);
            -ms-transform: skew(-20deg);
            -webkit-transform: skew(-20deg);
            transform: skew(-20deg);
            text-align: center;
        }
        .jajargenjang > span {
            display: inline-block;
            -ms-transform: skew(20deg);
            -webkit-transform: skew(20deg);
            transform: skew(20deg);
        }
        .title {
            font-family: Cochin, Georgia, Times, 'Times New Roman', serif;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 4px 0 8px;
        }
        .terbilang-line {
            margin: 4px 0 6px;
            padding: 4px 8px;
            font-weight: 600;
            font-size: 13px;
        }
        .terbilang-small {
            font-size: 9px;
        }
        .signature-table {
            width: 100%;
            margin-top: 8px;
            font-size: 12px;
        }
        .signature-table td {
            text-align: center;
            vertical-align: top;
        }
        .printed-at {
            margin-top: 4px;
            font-size: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="box">
        <table class="box-header" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                @if (! empty($logoUrl))
                    <td width="58" align="left">
                        <img src="{{ $logoUrl }}" width="50" height="50" style="object-fit: contain;">
                    </td>
                @endif
                <td align="left">
                    <div class="fw-bold">{{ $legalName }}</div>
                    <div class="fw-bold">{{ strtoupper($locationLine) }}</div>
                    <div class="fs-10">SK Kemenkumham RI No. {{ $registration ?: '-' }}</div>
                    <div class="fs-10">{{ $address }}@if ($address !== '' && $phone !== ''), @endif Telp. {{ $phone ?: '-' }}</div>
                </td>
                <td width="150" align="right" valign="top" class="fw-bold">
                    Nomor : {{ $documentNumber }}/{{ $kind }}
                </td>
            </tr>
        </table>

        <div class="box-body">
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td colspan="3" class="title">K U I T A N S I</td>
                </tr>
                <tr>
                    <td width="30%" class="keterangan">Telah Terima Dari</td>
                    <td width="2%" class="keterangan">:</td>
                    <td class="keterangan">{{ $receivedFrom }}</td>
                </tr>
                <tr>
                    <td class="keterangan">Uang Sebanyak</td>
                    <td class="keterangan">:</td>
                    <td class="keterangan">
                        <div class="jajargenjang terbilang-line {{ $terbilangClass }}">
                            <span>{{ $amountTerbilang }}</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="keterangan">Untuk Pembayaran</td>
                    <td class="keterangan">:</td>
                    <td class="keterangan border-b">{{ $purpose }}</td>
                </tr>
                <tr>
                    <td class="keterangan">&nbsp;</td>
                    <td class="keterangan">&nbsp;</td>
                    <td class="keterangan border-b">&nbsp;</td>
                </tr>
            </table>

            <table width="100%" class="signature-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td width="50%" align="center">
                        <div style="font-style: italic;">
                            Terbilang Rp.
                            <span class="jajargenjang" style="display: inline-block; padding: 1px 8px;">{{ $amountLabel }}</span>
                        </div>
                    </td>
                    <td align="center">
                        {{ $district !== '' ? $district : ($identity['short_name'] ?? '') }}, {{ $dateLabel }}
                    </td>
                </tr>
                <tr>
                    <td width="50%" align="center">Dibayar Oleh</td>
                    <td width="50%" align="center">Diterima Oleh</td>
                </tr>
                <tr>
                    <td align="center" height="34">&nbsp;</td>
                    <td align="center">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center">{{ $paidBy }}</td>
                    <td align="center">{{ $receivedBy }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="printed-at">Dicetak pada {{ now()->format('Y-m-d H:i:s') }}</div>
</div>
</body>
</html>
