@php
    $letterheadVariant = $letterhead_variant ?? 'standard';
    $contentTop = $letterheadVariant === 'letter' ? 80 : 60;
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
        @include('reports.pdf.partials.letterhead', ['variant' => $letterheadVariant])
    </header>

    <main style="position: relative; top: {{ $contentTop }}px; font-size: 12px; padding-bottom: 38px;">
        @yield('content')
    </main>
</body>
</html>
