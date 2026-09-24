<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Cover Laporan OJK</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75px; margin-left: 94px; }
        body { font-size: 12px; }
        .frame {
            border: 2px solid #000;
            padding: 24px;
            text-align: center;
            height: 800px;
            position: relative;
        }
        .logo {
            width: 90px;
            height: 90px;
            margin: 12px auto 24px;
            display: block;
            object-fit: contain;
        }
        .logo-fallback {
            width: 90px;
            height: 90px;
            margin: 12px auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #2b6cb0;
            color: #2b6cb0;
            font-size: 36px;
            font-weight: bold;
        }
        .top-mark { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 2px; }
        .sub-mark { font-size: 10px; color: #777; text-transform: uppercase; letter-spacing: 2px; margin-top: 4px; }
        .legal-name { font-size: 22px; font-weight: bold; text-transform: uppercase; margin-top: 36px; }
        .district { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
        .address { font-size: 11px; color: #444; margin-top: 6px; max-width: 70%; margin-left: auto; margin-right: auto; }
        .title-band {
            margin-top: 56px;
            padding-top: 16px;
            padding-bottom: 16px;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        .title { font-size: 22px; font-weight: bold; text-transform: uppercase; }
        .period { font-size: 16px; font-weight: bold; margin-top: 6px; }
        .footer-text { font-size: 10px; color: #555; margin-top: 28px; max-width: 70%; margin-left: auto; margin-right: auto; }
        .pengurus {
            position: absolute;
            left: 24px;
            right: 24px;
            bottom: 24px;
            font-size: 11px;
        }
        .pengurus-title {
            text-transform: uppercase;
            font-weight: bold;
            text-align: left;
            margin-bottom: 4px;
        }
        .pengurus-list { text-align: left; }
        .pengurus-row { margin: 2px 0; }
    </style>
</head>
<body>
    <div class="frame">
        @php
            $legal = strtoupper($identity['legal_name'] ?? '');
            $short = $identity['short_name'] ?? '';
            $district = $identity['district_name'] ?? '';
            $regency = $identity['regency_name'] ?? '';
            $periodLabel = strtoupper($period_label ?? ($period['period_label'] ?? ''));
            $logoUrl = $identity['logo_url'] ?? null;
            $initial = strtoupper(substr((string) ($short ?: $legal), 0, 1));
        @endphp

        <p class="top-mark">Otoritas Jasa Keuangan Republik Indonesia</p>
        <p class="sub-mark">Laporan Pelaporan Keuangan Lembaga</p>

        @if (! empty($logoUrl))
            <img src="{{ $logoUrl }}" class="logo" alt="Logo">
        @else
            <div class="logo-fallback">{{ $initial !== '' ? $initial : 'L' }}</div>
        @endif

        <div class="legal-name">{{ $legal }}</div>
        @if ($district !== '')
            <div class="district">{{ strtoupper($district) }}{{ $regency !== '' ? ' · ' . strtoupper($regency) : '' }}</div>
        @endif
        @if (! empty($identity['address']))
            <div class="address">{{ $identity['address'] }}</div>
        @endif

        <div class="title-band">
            <div class="title">Laporan Keuangan</div>
            <div class="period">Periode {{ $periodLabel }}</div>
        </div>

        <p class="footer-text">
            Disampaikan kepada Otoritas Jasa Keuangan Republik Indonesia untuk memenuhi
            ketentuan pelaporan keuangan lembaga jasa keuangan.
        </p>

        @if (! empty($identity['manager_name']) || ! empty($identity['secretary_name']) || ! empty($identity['treasurer_name']))
            <div class="pengurus">
                <div class="pengurus-title">Disusun Oleh:</div>
                <div class="pengurus-list">
                    @if (! empty($identity['manager_name']))
                        <div class="pengurus-row">{{ $identity['manager_title'] ?? 'Ketua' }}: {{ $identity['manager_name'] }}</div>
                    @endif
                    @if (! empty($identity['secretary_name']))
                        <div class="pengurus-row">{{ $identity['secretary_title'] ?? 'Sekretaris' }}: {{ $identity['secretary_name'] }}</div>
                    @endif
                    @if (! empty($identity['treasurer_name']))
                        <div class="pengurus-row">{{ $identity['treasurer_title'] ?? 'Bendahara' }}: {{ $identity['treasurer_name'] }}</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</body>
</html>