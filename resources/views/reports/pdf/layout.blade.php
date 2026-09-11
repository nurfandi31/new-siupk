@php
    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $district = $identity['district_name'] ?? '';
    $regency = $identity['regency_name'] ?? '';
    $locationLine = trim(($district !== '' ? 'Kec. ' . $district : '') . ' ' . ($regency !== '' ? 'Kab. ' . $regency : ''));
    $registration = $identity['registration_number'] ?? '';
    $address = $identity['address'] ?? '';
    $phone = $identity['phone'] ?? '';
    $email = $identity['email'] ?? '';
    $logoUrl = $identity['logo_url'] ?? null;

    $infoLine = $address;
    if ($address !== '' && $phone !== '') {
        $infoLine .= ', Telp. ' . $phone;
    } elseif ($phone !== '') {
        $infoLine = 'Telp. ' . $phone;
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $title ?? 'Laporan' }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75px; margin-left: 94px; }
        body { font-size: 12px; }
        header {
            position: fixed;
            top: -10px;
            left: 0;
            right: 0;
        }
        table tr th, table tr td { padding: 2px 4px; }
        ul, ol { margin-left: -10px; page-break-inside: auto !important; }
        table tr td table tr td { padding: 0 !important; }
        .break { page-break-after: always; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
    </style>
</head>
<body>
    <header>
        <table width="100%" style="border-bottom: 1px solid grey;">
            <tr>
                @if (! empty($logoUrl))
                    <td width="30">
                        <img src="{{ $logoUrl }}" width="40" alt="Logo">
                    </td>
                @endif
                <td>
                    <div style="font-size: 12px;">{{ $legalName }}</div>
                    <div style="font-size: 12px;">
                        <b>{{ strtoupper($locationLine) }}</b>
                    </div>
                </td>
            </tr>
        </table>
        <table width="100%" style="position: relative; top: -10px;">
            <tr>
                <td>
                    <span style="font-size: 8px; color: grey;">
                        <i>{{ $registration !== '' ? 'SK Kemenkumham RI No. ' . $registration : '' }}</i>
                    </span>
                </td>
                <td align="right">
                    <span style="font-size: 8px; color: grey;">
                        <i>{{ $infoLine }}</i>
                    </span>
                </td>
            </tr>
        </table>
    </header>

    <main style="position: relative; top: 60px; font-size: 12px; padding-bottom: 38px;">
        @yield('content')
    </main>
</body>
</html>
