@php
    $legalName = strtoupper($profile?->legal_name ?? $profile?->short_name ?? config('app.name'));
    $district = strtoupper($profile?->district_name ?? '');
    $regency = strtoupper($profile?->regency_name ?? '');
    $address = $profile?->address ?? '';

    $borrower = $loan_obj->borrower?->member;
    $person = $borrower?->person;
    $borrowerName = strtoupper($person?->full_name ?? '-');
    $borrowerNik = $person?->national_identity_number ?? '-';
    $villageName = $borrower?->village?->name ?? '';

    $spkNo = $loan_obj->spk_no ?? $loan_obj->loan_number ?? ('PINJ-' . $loan_obj->id);
    $disbursedAt = $loan_obj->disbursed_at
        ? \Carbon\CarbonImmutable::parse($loan_obj->disbursed_at)->translatedFormat('d F Y')
        : '—';
    $principal = (float) $loan_obj->principal_amount;
    $principalFmt = 'Rp ' . number_format($principal, 0, ',', '.');
    $term = (int) $loan_obj->term_months;
    $productName = $loan_obj->product?->name ?? 'Pinjaman Individu';
@endphp

<title>COVER PENCAIRAN INDIVIDU ({{ $borrowerName . ' - Loan ID. ' . $loan_obj->id }})</title>
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
        <h1 style="margin: 0px;">COVER PENCAIRAN</h1>
        <div style="margin: 0px; font-size: 24px;">
            {{ strtoupper('Pinjaman Individu ' . $productName) }}
        </div>
        <div style="margin: 0px;">
            Nomor SPK : {{ $spkNo }}
        </div>
    </header>

    <main>
        <div class="center">
            <div style="margin-top: 10px; font-size: 24px;">
                {{ $borrowerName }}
            </div>
            <div style="margin-top: 10px; font-size: 24px;">
                NIK : {{ $borrowerNik }}
            </div>

            <div style="font-size: 20px;">
                {{ $villageName }}
            </div>
        </div>

        <div class="bottom">
            <div style="font-weight: bold;">Alokasi {{ $principalFmt }}</div>
            <div style="font-weight: bold;">Tanggal Pencairan {{ $disbursedAt }}</div>
            <div style="font-weight: bold;">Tenor {{ $term }} Bulan</div>
        </div>
    </main>

    <footer>
        <table width="100%">
            <tr>
                <td align="center">
                    <div>{{ $legalName }}</div>
                    <div>
                        <b>{{ trim($district . ' ' . $regency) }}</b>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ $profile?->registration_number ? 'SK Kemenkumham RI No. '.$profile->registration_number : '' }}</i>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ $address }}</i>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ $profile?->email ?? '' }}</i>
                    </div>
                    <div style="font-size: 10px; color: grey;">
                        <i>{{ $profile?->phone ? 'Telp. '.$profile->phone : '' }}</i>
                    </div>
                </td>
            </tr>
        </table>
    </footer>
</body>