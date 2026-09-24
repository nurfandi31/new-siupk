<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports\YearEnd;

use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Services\TenantSettingService;
use Carbon\CarbonImmutable;

/**
 * CALK khusus Tutup Buku — Catatan Atas Laporan Keuangan
 * untuk pelaporan akhir tahun buku.
 *
 * Berbeda dari CALK bulanan/tahunan biasa yang fokus ringkasan
 * + catatan manajemen, CALK tutup buku mencakup:
 *   Bab I  : Umum (profil, dasar, periode tutup buku)
 *   Bab II : Ikhtisar Kebijakan Akuntansi
 *   Bab III: Penjelasan Pos Neraca (fokus perubahan signifikan)
 *   Bab IV : Penjelasan Laba Rugi
 *   Bab V  : Alokasi Laba
 *   Bab VI : Peristiwa Penting Setelah Tanggal Neraca
 *
 * Catatan disimpan di tenant_settings (key dapat di-edit via form).
 */
final readonly class YearEndCalkService
{
    public const NOTES_KEY = 'year_end.calk.notes';

    public function __construct(
        private AccountBalanceQuery $balances,
        private AllocationService $allocation,
        private ClosingJournalService $closingJournal,
        private YearEndBalanceSheetService $yearEndBalanceSheet,
        private YearEndIncomeStatementService $yearEndIncomeStatement,
        private TenantSettingService $settings,
    ) {}

    /**
     * Bangun CALK tutup buku.
     *
     * @return array<string, mixed>
     */
    public function build(int $year): array
    {
        $period = $this->balances->resolvePeriod($year, null);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();

        $allocation = $this->allocation->build($year);
        $closingJournal = $this->closingJournal->build($year);
        $yearEndBalance = $this->yearEndBalanceSheet->build($year);
        $yearEndIncome = $this->yearEndIncomeStatement->build($year);

        $profile = OrganizationProfile::query()->first();

        $storedNotes = $this->settings->get(self::NOTES_KEY, []);
        if (! is_array($storedNotes)) {
            $storedNotes = [];
        }

        // Default chapters
        $chapters = $this->defaultChapters($storedNotes, $year);

        // Highlight KPI
        $highlights = [
            [
                'key' => 'total_surplus',
                'label' => 'Surplus/(Defisit) Tahun Buku',
                'amount' => round((float) $yearEndIncome['summary']['after_tax'], 2),
            ],
            [
                'key' => 'total_asset',
                'label' => 'Total Aset (Neraca Tutup Buku)',
                'amount' => round((float) $yearEndBalance['totals']['assets'], 2),
            ],
            [
                'key' => 'total_equity',
                'label' => 'Total Ekuitas (Neraca Tutup Buku)',
                'amount' => round((float) ($yearEndBalance['totals']['liabilities_equity']
                    - $this->totalLiabilities($yearEndBalance)), 2),
            ],
            [
                'key' => 'total_liability',
                'label' => 'Total Utang',
                'amount' => round((float) $this->totalLiabilities($yearEndBalance), 2),
            ],
            [
                'key' => 'retained_balance',
                'label' => 'Saldo Laba Ditahan (termasuk tahun ini)',
                'amount' => round((float) ($yearEndBalance['totals']['retained_balance'] ?? 0), 2),
            ],
            [
                'key' => 'allocation_pct',
                'label' => 'Persentase Alokasi Laba',
                'amount' => round((float) ($allocation['summary']['pct_allocated'] ?? 0), 2),
            ],
        ];

        return [
            'period' => $period,
            'year' => $year,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? config('app.name')),
                'short_name' => $profile?->short_name,
                'address' => $profile?->address,
                'registration_number' => $profile?->registration_number,
                'tax_number' => $profile?->tax_number,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
                'province_name' => $profile?->province_name,
            ],
            'is_preview' => true,
            'chapters' => $chapters,
            'highlights' => $highlights,
            'allocation_summary' => [
                'surplus' => $allocation['surplus'],
                'lines' => $allocation['lines'],
                'totals' => $allocation['totals'],
            ],
            'closing_journal_summary' => [
                'entries' => count($closingJournal['entries']),
                'surplus' => $closingJournal['totals']['surplus'],
                'revenue' => $closingJournal['totals']['revenue'],
                'expense' => $closingJournal['totals']['expense'],
                'balanced' => $closingJournal['balanced'],
            ],
            'balance_sheet' => [
                'sections' => $yearEndBalance['sections'],
                'totals' => $yearEndBalance['totals'],
                'balanced' => $yearEndBalance['balanced'],
            ],
            'income_statement' => [
                'groups' => $yearEndIncome['groups'],
                'summary' => $yearEndIncome['summary'],
            ],
            'policies' => [
                'Basis pencatatan adalah akrual dengan jurnal berpasangan (debit = kredit).',
                'Saldo bulanan merupakan projection dari jurnal posted, bukan sumber kebenaran terpisah.',
                'Piutang pinjaman diukur sebesar sisa pokok (due − paid) pada jadwal angsuran.',
                'Pendapatan jasa diakui saat jurnal angsuran posted.',
                'Aset kas meliputi akun dengan kode awalan 1.1.01.',
                'Tahun buku tutup per 31 Desember. Jurnal tutup buku memindahkan saldo akun nominal ke Laba Ditahan.',
            ],
        ];
    }

    /**
     * Simpan catatan CALK tutup buku.
     *
     * @param  array<string, string>  $notes  key=>body per chapter.
     */
    public function saveNotes(int $year, array $notes): void
    {
        $this->settings->set(self::NOTES_KEY, $notes, 'json');
    }

    private function defaultChapters(array $storedNotes, int $year): array
    {
        $defaults = [
            'bab_i_umum' => [
                'title' => 'I. Umum',
                'body' => "Laporan keuangan tutup buku tahun {$year} disusun berdasarkan SAK EMKM dan prinsip akrual. "
                    ."Periode tutup buku mencakup 1 January – 31 December {$year}. "
                    .'Sebelum tutup buku dilakukan proses rekonsiliasi dan verifikasi saldo seluruh akun.',
            ],
            'bab_ii_kebijakan' => [
                'title' => 'II. Ikhtisar Kebijakan Akuntansi',
                'body' => 'Tidak terdapat perubahan kebijakan akuntansi yang material dibanding periode sebelumnya. '
                    .'Dasar pencatatan tetap menggunakan accrual basis. Aset tetap dinilai dengan harga perolehan '
                    .'dikurangi akumulasi penyusutan. Piutang pinjaman disajikan sebesar saldo terutang.',
            ],
            'bab_iii_neraca' => [
                'title' => 'III. Penjelasan Pos-pos Neraca',
                'body' => 'Saldo akun nominal (pendapatan & beban) sudah dinolkan pada proses tutup buku dan '
                    .'dipindahkan ke akun Laba Ditahan (3.2.01.01) melalui akun Ikhtisar Laba Rugi (3.6.01). '
                    ."Penjelasan rinci tiap pos neraca tercantum di lampiran Neraca Tutup Buku tahun {$year}.",
            ],
            'bab_iv_lr' => [
                'title' => 'IV. Penjelasan Laba Rugi',
                'body' => "Surplus tahun buku {$year} merupakan selisih total pendapatan (4.x) dengan total beban (5.x) "
                    .'dan telah dipindahkan ke Laba Ditahan. Detail akun nominal tersedia di CALK Laba Rugi tutup buku.',
            ],
            'bab_v_alokasi' => [
                'title' => 'V. Alokasi Laba',
                'body' => "Alokasi laba tahun buku {$year} direncanakan menggunakan pos standar koperasi. "
                    .'Rincian persentase dan nominal tiap pos tercantum di laporan Alokasi Laba. '
                    .'Jurnal alokasi akan diposting setelah RAT (Rapat Anggota Tahunan) mengesahkan.',
            ],
            'bab_vi_peristiwa' => [
                'title' => 'VI. Peristiwa Penting Setelah Tanggal Neraca',
                'body' => 'Tidak ada peristiwa penting setelah tanggal neraca yang memerlukan penyesuaian atau '
                    .'pengungkapan material sampai dengan tanggal laporan ini diterbitkan.',
            ],
        ];

        foreach ($defaults as $key => $info) {
            $defaults[$key]['body'] = isset($storedNotes[$key]) && is_string($storedNotes[$key]) && $storedNotes[$key] !== ''
                ? (string) $storedNotes[$key]
                : $info['body'];
            $defaults[$key]['key'] = $key;
        }

        return array_values($defaults);
    }

    private function totalLiabilities(array $yearEndBalance): float
    {
        // Hitung total liabilitas dari sections bertipe liability
        $total = 0.0;
        foreach ($yearEndBalance['sections'] as $section) {
            if (($section['account_type'] ?? '') === 'liability') {
                $total += (float) ($section['balance'] ?? 0);
            }
        }

        return round($total, 2);
    }
}
