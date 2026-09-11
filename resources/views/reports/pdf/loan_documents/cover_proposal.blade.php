@php
    $proposalDate = $tokens['{tgl_proposal}'] ?? '';
    $proposalAmount = $tokens['{alokasi}'] ?? '';
    $termMonths = (int) $loan['term_months'];
    $documentLabel = $document['label'] ?? '';
    $logoUrl = $identity['logo_url'] ?? '';
    $infoDate = $today_label ?? '';
@endphp

<title>COVER PROPOSAL ({{ $group['name'] . ' - Loan ID. ' . $loan['id'] }})</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<style>
    * {
        font-family: Arial, Helvetica, sans-serif;
    }

    html {
        margin: 75.59px;
        margin-left: 94.48px;
    }

    body {
        width: 100%;
        height: fit-content;
        border: 1px solid #000;
        position: relative;
    }

    header {
        position: relative;
        top: 60px;
        text-align: center;
    }

    footer {
        position: absolute;
        bottom: 0px;
        width: 100%;
        border-top: 1px solid #000;
    }

    .center {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }

    .bottom {
        position: absolute;
        bottom: 12%;
        width: 100%;
        text-align: center;
    }
</style>

<body>
    <header>
        <h1 style="margin: 0px;">{{ strtoupper($documentLabel) }}</h1>
        <div style="margin: 0px; font-size: 24px;">
            PIUTANG KELOMPOK {{ strtoupper($loan['product_code']) }}
        </div>
    </header>

    <main>
        <div class="center">
            <img src="{{ $logoUrl }}" width="290" alt="{{ $logoUrl }}">
            <div style="margin-top: 10px; font-size: 24px;">
                Kelompok {{ $group['name'] }}
            </div>
            <div style="font-size: 20px;">
                {{ $group['village'] }}
            </div>
        </div>

        <div class="bottom">
            <div style="font-weight: bold;">Pengajuan {{ $proposalAmount }}</div>
            <div style="font-weight: bold;">Tanggal Proposal {{ $proposalDate }}</div>
            <div style="font-weight: bold;">Tenor {{ $termMonths }} Bulan</div>
        </div>
    </main>

    <footer>
        <table width="100%">
            <tr>
                <td align="center">
                    <div>{{ strtoupper($identity['legal_name']) }}</div>
                    <div>
                        <b>{{ strtoupper(trim(($identity['district_name'] ?? '') . ' ' . ($identity['regency_name'] ?? ''))) }}</b>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ 'SK Kemenkumham RI No.' . ($identity['registration_number'] ?? '-') }}</i>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ ($identity['address'] ?? '') . (!empty($identity['phone']) ? ', Telp. ' . $identity['phone'] : '') }}</i>
                    </div>
                    @if (!empty($identity['email']))
                        <div style="font-size: 10px; color: grey;">
                            <i>{{ $identity['email'] }}</i>
                        </div>
                    @endif
                    <div style="font-size: 10px; color: grey; margin-top: 10px;">
                        <i>Tahun {{ date('Y') }}</i>
                    </div>
                </td>
            </tr>
        </table>
    </footer>
</body>
