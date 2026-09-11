<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>COVER BUKU LAPORAN TAHUNAN</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        body {
            width: 100%;
            height: fit-content;
            border: 1px solid #000;
            padding: 0;
            text-align: center;
            position: relative;
        }
        header { position: relative; top: 60px; text-align: center; }
        footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid #000;
        }
        img {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body>
    <header>
        <h1 style="margin: 0px;">{{ strtoupper($period_label) }}</h1>
        <div style="margin: 0px; font-size: 24px;">{{ strtoupper($year) }}</div>
    </header>

    <main>
        @if (! empty($identity['logo_url']))
            <img src="{{ $identity['logo_url'] }}" width="290" alt="Logo">
        @else
            <div style="font-size: 40px; font-weight: bold; color: #2b6cb0;">{{ $identity['short_name'] ?? 'BUMDESMA' }}</div>
        @endif
    </main>

    <footer>
        <table width="100%">
            <tr>
                <td align="center">
                    <div>{{ strtoupper($identity['legal_name']) }}</div>
                    <div><b>{{ strtoupper($identity['district_name']) }}</b></div>
                    <div style="font-size: 10px; color: grey;"><i>{{ $identity['registration_number'] }}</i></div>
                    <div style="font-size: 10px; color: grey;"><i>{{ $identity['address'] }}@if($identity['phone']), Telp. {{ $identity['phone'] }}@endif</i></div>
                    <div style="font-size: 10px; color: grey;"><i>{{ $identity['email'] }}</i></div>
                    <div style="font-size: 10px; color: grey; margin-top: 10px;"><i>Tahun {{ $year }}</i></div>
                </td>
            </tr>
        </table>
    </footer>
</body>
</html>