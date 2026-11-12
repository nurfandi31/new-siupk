<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $title ?? 'Laporan Konsolidasi Kabupaten' }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px 94.48px; }
        body { font-size: 11px; color: #111; line-height: 1.3; }
        table { border-collapse: collapse; width: 100%; }
        table tr th, table tr td { padding: 2px 4px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
        .bg-gray { background-color: #f3f4f6; }
        .header-title { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .header-sub { font-size: 11px; color: #4b5563; margin-bottom: 15px; }
    </style>
</head>
@php
    $identity = $identity ?? [];
    $kabupatenName = strtoupper((string) ($regency_name ?? ($identity['regency_name'] ?? 'KABUPATEN')));
    $institutionName = strtoupper((string) ($identity['legal_name'] ?? 'BADAN KERJASAMA ANTAR DESA (BUMDESMA)'));
    $contactParts = array_filter([
        trim((string) ($identity['address'] ?? '')),
        trim((string) ($identity['phone'] ?? '')) !== '' ? 'Telp. '.trim((string) $identity['phone']) : '',
        trim((string) ($identity['email'] ?? '')),
        trim((string) ($identity['website'] ?? '')),
    ], static fn (string $value): bool => $value !== '');
    $logoUrl = $identity['logo_url'] ?? null;
@endphp
<body>
    <div style="border-bottom: 3px double #111; padding-bottom: 8px; margin-bottom: 12px;">
        <table style="width: 100%;">
            <tr>
                @if (! empty($logoUrl))
                    <td width="50" style="vertical-align: middle;">
                        <img src="{{ $logoUrl }}" width="40" alt="Logo">
                    </td>
                @endif
                <td>
                    <div style="font-size: 13px; font-weight: bold;">PEMERINTAH KABUPATEN {{ $kabupatenName }}</div>
                    <div style="font-size: 11px; font-weight: bold; color: #374151;">{{ $institutionName }}</div>
                    <div style="font-size: 10px; color: #374151;">SISTEM INFORMASI KEUANGAN GABUNGAN KECAMATAN / UPK DBM</div>
                    @if ($contactParts !== [])
                        <div style="font-size: 9px; color: #6b7280;">{{ implode(' · ', $contactParts) }}</div>
                    @endif
                </td>
                <td style="text-align: right; vertical-align: bottom;">
                    <div style="font-size: 10px; color: #6b7280;">Dicetak: {{ date('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <main>
        @yield('content')
    </main>
</body>
</html>
