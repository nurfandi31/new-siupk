@php
    $identity = $identity ?? [];
    $entry = $entry ?? [];
    $paper = (string) ($paper ?? '80');
    $legalName = strtoupper((string) ($identity['legal_name'] ?? config('app.name')));
    $district = (string) ($identity['district_name'] ?? '');
    $address = (string) ($identity['address'] ?? '');
    $phone = (string) ($identity['phone'] ?? '');
    $registration = (string) ($identity['registration_number'] ?? '');
    $documentNumber = $document_number ?? ($entry['journal_number'] ?: ($entry['id'] ?? ''));
    $dateLabel = $document_date_label ?? '-';
    $receivedFrom = $received_from ?? '--';
    $paidBy = $paid_by ?? '--';
    $receivedBy = $received_by ?? '--';
    $purpose = $purpose ?? ($entry['description'] ?? '');
    $amountLabel = $amount_label ?? '-';
    $amountTerbilang = $amount_terbilang ?? '-';
    $is58 = $paper === '58';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>K U I T A N S I</title>
    <style>
        @page {
            size: {{ $is58 ? '58mm 68mm' : '80mm 90mm' }};
            margin: 2mm;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
        body { font-size: {{ $is58 ? '7px' : '8px' }}; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 1px 0; vertical-align: top; }
        .center { text-align: center; }
        .right { text-align: right; }
        .line { border-bottom: 1px dashed #000; }
        .title { font-size: {{ $is58 ? '10px' : '12px' }}; font-weight: bold; letter-spacing: 1px; }
        .muted { color: #333; }
        .terbilang { font-size: {{ $is58 ? '6px' : '7px' }}; font-weight: bold; text-transform: uppercase; }
        .sign { margin-top: 8px; }
        .small { font-size: {{ $is58 ? '6px' : '7px' }}; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td class="center line" colspan="3">
                <div><b>{{ $legalName }}</b></div>
                @if ($address !== '')<div>{{ $address }}</div>@endif
                @if ($phone !== '')<div>Telp. {{ $phone }}</div>@endif
                @if ($registration !== '')<div>SK No. {{ $registration }}</div>@endif
            </td>
        </tr>
        <tr><td class="center line" colspan="3"><span class="title">K U I T A N S I</span></td></tr>
        <tr>
            <td width="22%">No</td><td width="3%">:</td><td>{{ $documentNumber }}/{{ $kind ?? '' }}</td>
        </tr>
        <tr>
            <td>Tanggal</td><td>:</td><td>{{ $dateLabel }}</td>
        </tr>
        <tr><td colspan="3" height="4"></td></tr>
        <tr>
            <td>Telah Terima Dari</td><td>:</td><td>{{ $receivedFrom }}</td>
        </tr>
        <tr>
            <td>Uang Sejumlah</td><td>:</td><td>{{ $amountLabel }}</td>
        </tr>
        <tr>
            <td>Keperluan</td><td>:</td><td>{{ $purpose }}</td>
        </tr>
        <tr><td colspan="3" height="4"></td></tr>
        <tr><td colspan="3">Terbilang :</td></tr>
        <tr><td class="terbilang" colspan="3">{{ $amountTerbilang }}</td></tr>
        <tr><td colspan="3" height="6"></td></tr>
        <tr>
            <td class="center" colspan="2">Dibayar Oleh</td>
            <td class="center">Diterima Oleh</td>
        </tr>
        <tr><td colspan="3" height="24"></td></tr>
        <tr class="sign">
            <td class="center" colspan="2"><b>{{ $paidBy }}</b></td>
            <td class="center"><b>{{ $receivedBy }}</b></td>
        </tr>
        <tr><td colspan="3" height="3"></td></tr>
        <tr>
            <td class="center small muted" colspan="3">
                {{ $district !== '' ? $district.', ' : '' }}{{ $dateLabel }}
            </td>
        </tr>
    </table>
</body>
</html>
