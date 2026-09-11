@php
    use App\Domain\Accounting\Services\Reports\DocumentKindClassifier;

    $kind = $kind ?? DocumentKindClassifier::KIND_BM;
    $isCashIn = $kind === DocumentKindClassifier::KIND_BKM;
    $isCashOut = $kind === DocumentKindClassifier::KIND_BKK;
    $isMemorial = $kind === DocumentKindClassifier::KIND_BM;

    $identity = $identity ?? [];
    $entry = $entry ?? [];
    $title = $document['label'] ?? 'Bukti Kas';
    $documentNumber = $document_number ?? ($entry['journal_number'] ?: ($entry['id'] ?? ''));
    $documentDateLabel = $document_date_label ?? '-';
    $relation = $relation ?? ($entry['relation'] ?? '');
    $description = $description ?? ($entry['description'] ?? '');
    $amountLabel = $amount_label ?? '-';
    $debitLabel = $debit_label ?? '';
    $creditLabel = $credit_label ?? '';

    $legalName = strtoupper((string) ($identity['legal_name'] ?? config('app.name')));
    $district = (string) ($identity['district_name'] ?? '');
    $regency = (string) ($identity['regency_name'] ?? '');
    $registration = (string) ($identity['registration_number'] ?? '');
    $address = (string) ($identity['address'] ?? '');
    $phone = (string) ($identity['phone'] ?? '');
    $logoUrl = $identity['logo_url'] ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: 14cm 9cm landscape;
            margin: 0;
        }
        * {
            font-family: Arial, Helvetica, sans-serif;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            margin: 0;
            padding: 0;
            font-size: 9px;
            color: #000;
            background: #fff;
        }
        body {
            padding: 20px;
        }
        .box {
            width: 100%;
            border: 2px solid #000;
            padding-top: 16px;
            padding-bottom: 12px;
            padding-right: 22px;
            padding-left: 12px;
        }
        .box-header {
            width: 100%;
            border-bottom: 1px solid rgba(0, 0, 0, 0.5);
            padding-left: 16px;
            padding-right: 16px;
            padding-bottom: 8px;
        }
        .box-header td {
            vertical-align: middle;
        }
        .fw-bold { font-weight: bold; }
        .fs-8 { font-size: 8px; }
        .fs-10 { font-size: 10px; }
        .box-body {
            padding-top: 4px;
        }
        h1 {
            font-size: 28px;
            font-weight: bold;
            margin: 4px 0 6px 0;
            text-align: center;
        }
        .body-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .body-table td {
            padding: 2.5px 4px;
            vertical-align: top;
        }
        .keterangan {
            font-weight: normal;
        }
        .sign-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
            font-size: 10px;
        }
        .sign-table td {
            text-align: center;
            vertical-align: top;
            width: 33.33%;
        }
    </style>
</head>

<body>
<div class="box">
    <table class="box-header" cellspacing="0" cellpadding="0">
        <tr>
            @if (! empty($logoUrl))
                <td width="48" align="left">
                    <img src="{{ $logoUrl }}" width="40" height="40" style="object-fit: contain;">
                </td>
            @endif
            <td align="left">
                <div class="fw-bold fs-10">{{ $legalName }}</div>
                <div class="fw-bold fs-10">
                    {{ strtoupper(trim(($district !== '' ? 'Kec. '.$district : '').' '.($regency !== '' ? 'Kab. '.$regency : ''))) }}
                </div>
                <div class="fs-8">SK Kemenkumham RI No. {{ $registration ?: '-' }}</div>
                <div class="fs-8">{{ $address }}@if ($address !== '' && $phone !== ''), @endif Telp. {{ $phone ?: '-' }}</div>
            </td>
            <td width="130" align="right" style="vertical-align: top;">
                <table style="font-size: 9px; border-collapse: collapse;">
                    <tr>
                        <td align="left">Nomor</td>
                        <td width="8" align="center">:</td>
                        <td align="left">{{ $documentNumber }}/{{ $kind }}</td>
                    </tr>
                    <tr>
                        <td align="left">Tanggal</td>
                        <td width="8" align="center">:</td>
                        <td align="left">{{ $documentDateLabel }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="box-body">
        <h1>{{ strtoupper($title) }}</h1>

        <table class="body-table" cellspacing="0" cellpadding="0">
            @if ($isCashIn)
                <tr>
                    <td width="30%">Terima Dari</td>
                    <td width="2%">:</td>
                    <td width="68%" class="keterangan">{{ $relation ?: '--' }}</td>
                </tr>
            @elseif ($isCashOut)
                <tr>
                    <td width="30%">Dibayar Kepada</td>
                    <td width="2%">:</td>
                    <td width="68%" class="keterangan">{{ $relation ?: '--' }}</td>
                </tr>
            @endif
            <tr>
                <td width="30%">Keterangan</td>
                <td width="2%">:</td>
                <td width="68%" class="keterangan">{{ $description }}</td>
            </tr>
            <tr>
                <td width="30%">Jumlah</td>
                <td width="2%">:</td>
                <td width="68%" class="keterangan">{{ $amountLabel }}</td>
            </tr>
            <tr>
                <td width="30%">Kode Akun (D/K)</td>
                <td width="2%">&nbsp;</td>
                <td width="68%" class="keterangan">Debit {{ $debitLabel }}</td>
            </tr>
            <tr>
                <td width="30%">&nbsp;</td>
                <td width="2%">&nbsp;</td>
                <td width="68%" class="keterangan">Kredit {{ $creditLabel }}</td>
            </tr>
            @if ($isMemorial)
                <tr>
                    <td width="30%">&nbsp;</td>
                    <td width="2%">&nbsp;</td>
                    <td width="68%" class="keterangan">&nbsp;</td>
                </tr>
            @endif
        </table>

        <table class="sign-table" cellspacing="0" cellpadding="0">
            <tr>
                <td>Disetujui,</td>
                <td>Diverifikasi,</td>
                <td>Disiapkan Oleh :</td>
            </tr>
            <tr>
                <td>{{ $identity['approver_role'] ?? 'Ketua' }}</td>
                <td>{{ $identity['verifier_role'] ?? 'Sekretaris' }}</td>
                <td>&nbsp;</td>
            </tr>
            <tr><td colspan="3" style="height: 28px;">&nbsp;</td></tr>
            <tr>
                <td>{{ $identity['approver_name'] ?? '' }}</td>
                <td>{{ $identity['verifier_name'] ?? '' }}</td>
                <td>{{ $identity['preparer_name'] ?? '' }}</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
