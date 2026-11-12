@php
    $identity = $identity ?? [];
    $variant = $variant ?? 'standard';

    $legalName = strtoupper((string) ($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name')));
    $district = trim((string) ($identity['district_name'] ?? ''));
    $regency = trim((string) ($identity['regency_name'] ?? ''));
    $province = trim((string) ($identity['province_name'] ?? ''));
    $registration = trim((string) ($identity['registration_number'] ?? ''));
    $taxNumber = trim((string) ($identity['tax_number'] ?? ''));
    $address = trim((string) ($identity['address'] ?? ''));
    $phone = trim((string) ($identity['phone'] ?? ''));
    $email = trim((string) ($identity['email'] ?? ''));
    $website = trim((string) ($identity['website'] ?? ''));
    $logoUrl = $identity['logo_url'] ?? ($logo_url ?? null);

    $locationLine = trim(
        ($district !== '' ? 'Kec. '.$district : '')
        .' '.($regency !== '' ? 'Kab. '.$regency : '')
        .' '.($province !== '' ? 'Prov. '.$province : '')
    );

    $contactParts = [];
    if ($address !== '') {
        $contactParts[] = $address;
    }
    if ($phone !== '') {
        $contactParts[] = 'Telp. '.$phone;
    }
    if ($email !== '') {
        $contactParts[] = $email;
    }
    if ($website !== '') {
        $contactParts[] = $website;
    }
    $contactLine = implode(' · ', $contactParts);
    $registrationLine = $registration !== '' ? 'SK Kemenkumham RI No. '.$registration : '';
@endphp

@if ($variant === 'letter')
    <table width="100%" style="border-bottom: 4px double #000;">
        <tr>
            @if (! empty($logoUrl))
                <td width="70" align="center" style="vertical-align: middle;">
                    <img src="{{ $logoUrl }}" width="70" alt="Logo">
                </td>
            @endif
            <td align="center">
                <div style="font-size: 14px;">{{ $legalName }}</div>
                <div style="font-size: 12px;">
                    <b>{{ strtoupper($locationLine) }}</b>
                </div>
                @if ($registrationLine !== '')
                    <div style="font-size: 9px; color: grey;">
                        <i>{{ $registrationLine }}</i>
                    </div>
                @endif
                @if ($contactLine !== '')
                    <div style="font-size: 9px; color: grey;">
                        <i>{{ $contactLine }}</i>
                    </div>
                @endif
                @if ($taxNumber !== '')
                    <div style="font-size: 9px; color: grey;">
                        <i>NPWP: {{ $taxNumber }}</i>
                    </div>
                @endif
            </td>
        </tr>
    </table>
@else
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
                    <i>{{ $registrationLine }}</i>
                </span>
            </td>
            <td align="right">
                <span style="font-size: 8px; color: grey;">
                    <i>{{ implode(' · ', array_filter([$address, $phone !== '' ? 'Telp. '.$phone : ''])) }}</i>
                </span>
            </td>
        </tr>
    </table>
@endif
