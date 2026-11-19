<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>COVER BUKU LAPORAN TAHUNAN</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        * { font-family: Arial, Helvetica, sans-serif; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; }
        .page {
            width: 180mm;
            height: 255mm;
            padding: 0;
            text-align: center;
        }
        .frame {
            width: 100%;
            height: 100%;
            border: 1px solid #000;
            box-sizing: border-box;
        }
        table.cover {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }
        table.cover td { text-align: center; }
        .title { font-size: 28px; font-weight: bold; letter-spacing: 1px; }
        .subtitle { font-size: 20px; margin-top: 6px; }
        .brand { font-size: 40px; font-weight: bold; color: #2b6cb0; }
        .footer { font-size: 12px; line-height: 1.6; }
        .muted { font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="page">
        <div class="frame">
            <table class="cover">
                <tr>
                    <td style="height: 20%; vertical-align: top; padding-top: 18mm;">
                        <div class="title">LAPORAN TAHUNAN</div>
                        <div class="subtitle">Tahun {{ $year }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="height: 50%; vertical-align: middle;">
                        @if (! empty($identity['logo_url']))
                            <img src="{{ $identity['logo_url'] }}" width="290" alt="Logo">
                        @else
                            <div class="brand">{{ $identity['short_name'] ?? $identity['legal_name'] ?? '' }}</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="height: 30%; vertical-align: bottom; padding-bottom: 16mm;">
                        <div class="footer">
                            <div style="font-weight: bold;">{{ strtoupper($identity['legal_name'] ?? '') }}</div>
                            <div><b>{{ strtoupper($identity['district_name'] ?? '') }}</b></div>
                            <div class="muted"><i>{{ $identity['registration_number'] ?? '' }}</i></div>
                            <div class="muted">
                                <i>{{ $identity['address'] ?? '' }}@if(! empty($identity['phone'])), Telp. {{ $identity['phone'] }}@endif</i>
                            </div>
                            <div class="muted"><i>{{ $identity['email'] ?? '' }}</i></div>
                            <div class="muted" style="margin-top: 8px;"><i>Tahun {{ $year }}</i></div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
