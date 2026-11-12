<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ReportLetterheadParityTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function identity(): array
    {
        return [
            'legal_name' => 'BUMDESMA MAJU BERSAMA',
            'short_name' => 'BMB',
            'district_name' => 'Sukamaju',
            'regency_name' => 'Cianjur',
            'province_name' => 'Jawa Barat',
            'registration_number' => 'AHU-0012345.AH.01.07.TAHUN 2026',
            'tax_number' => '01.234.567.8-901.000',
            'address' => 'Jl. Raya Sukamaju No. 1',
            'phone' => '0812-3456-7890',
            'email' => 'bumdesma@example.test',
            'website' => 'https://bumdesma.example.test',
        ];
    }

    public function test_standard_letterhead_renders_organization_identity_and_contact(): void
    {
        $html = view('reports.pdf.layout', [
            'title' => 'Uji Kop',
            'identity' => $this->identity(),
        ])->render();

        self::assertStringContainsString('BUMDESMA MAJU BERSAMA', $html);
        self::assertStringContainsString('KEC. SUKAMAJU KAB. CIANJUR PROV. JAWA BARAT', $html);
        self::assertStringContainsString('SK Kemenkumham RI No. AHU-0012345.AH.01.07.TAHUN 2026', $html);
        self::assertStringContainsString('Jl. Raya Sukamaju No. 1', $html);
        self::assertStringContainsString('Telp. 0812-3456-7890', $html);
    }

    public function test_letter_letterhead_renders_complete_contact_including_email_and_tax(): void
    {
        $html = view('reports.pdf.layout', [
            'title' => 'Uji Kop Surat',
            'identity' => $this->identity(),
            'letterhead_variant' => 'letter',
        ])->render();

        self::assertStringContainsString('BUMDESMA MAJU BERSAMA', $html);
        self::assertStringContainsString('KEC. SUKAMAJU KAB. CIANJUR', $html);
        self::assertStringContainsString('bumdesma@example.test', $html);
        self::assertStringContainsString('https://bumdesma.example.test', $html);
        self::assertStringContainsString('NPWP: 01.234.567.8-901.000', $html);
    }

    public function test_annual_surat_pengantar_uses_letter_letterhead_variant(): void
    {
        $html = view('reports.pdf.annual.surat_pengantar', [
            'identity' => $this->identity(),
            'year' => 2026,
            'date_formatted' => '31 Desember 2026',
            'period_label' => 'Tahun 2026',
        ])->render();

        self::assertStringContainsString('BUMDESMA MAJU BERSAMA', $html);
        self::assertStringContainsString('bumdesma@example.test', $html);
        self::assertStringContainsString('https://bumdesma.example.test', $html);
        self::assertStringContainsString('NPWP: 01.234.567.8-901.000', $html);
    }

    public function test_kolek_kelompok_renders_dynamic_collectibility_level_columns(): void
    {
        $html = view('reports.pdf.lending.kolek_kelompok', [
            'identity' => $this->identity(),
            'period_label' => 'Juli 2026',
            'levels' => [
                ['level' => 1, 'nama' => 'Lancar', 'prosentase' => 0.5, 'bucket_key' => 'kolek1_lancar'],
                ['level' => 2, 'nama' => 'Diragukan', 'prosentase' => 50, 'bucket_key' => 'kolek2_diragukan'],
            ],
            'products' => [[
                'product_code' => 'P1',
                'product_name' => 'Modal Usaha',
                'villages' => [[
                    'village_name' => 'Sukamaju',
                    'loans' => [[
                        'group_name' => 'Kelompok A',
                        'loan_id' => 1,
                        'group_code' => 'G1',
                        'loan_number' => 'L1',
                        'alokasi' => 1000,
                        'saldo' => 1000,
                        'tunggakan_pokok' => 0,
                        'tunggakan_jasa' => 0,
                        'kolek_nama' => 'Lancar',
                        'bucket_key' => 'kolek1_lancar',
                    ]],
                    'subtotal' => [
                        'alokasi' => 1000,
                        'saldo' => 1000,
                        'tunggakan_pokok' => 0,
                        'tunggakan_jasa' => 0,
                        'kolek1_lancar' => 1000,
                        'kolek2_diragukan' => 0,
                        'peminjam_count' => 1,
                    ],
                ]],
                'totals' => [
                    'alokasi' => 1000,
                    'saldo' => 1000,
                    'tunggakan_pokok' => 0,
                    'tunggakan_jasa' => 0,
                    'kolek1_lancar' => 1000,
                    'kolek2_diragukan' => 0,
                    'peminjam_count' => 1,
                ],
            ]],
        ])->render();

        self::assertStringContainsString('Saldo per Kolektibilitas', $html);
        self::assertStringContainsString('Diragukan', $html);
        self::assertStringContainsString('1.000', $html);
    }

    public function test_ojk_collectibility_renders_loan_level_detail(): void
    {
        $html = view('reports.pdf.ojk.collectibility', [
            'identity' => $this->identity(),
            'period_label' => 'Juli 2026',
            'as_of' => '31/07/2026',
            'borrower_scope' => 'all',
            'buckets' => [[
                'code' => 1,
                'label' => 'Lancar',
                'days_label' => '0 hari',
                'loan_count' => 1,
                'outstanding' => 1000,
                'share_pct' => 100,
                'rate_pct' => 0.5,
                'allowance_required' => 5,
                'allowance_formed' => 5,
            ]],
            'totals' => [
                'loan_count' => 1,
                'outstanding' => 1000,
                'allowance_required' => 5,
                'allowance_formed' => 5,
            ],
            'rows' => [[
                'loan_id' => 77,
                'loan_number' => 'L-2026-0077',
                'principal_amount' => 1000,
                'outstanding' => 1000,
                'days_overdue' => 0,
                'overdue_months' => 0,
            ]],
        ])->render();

        self::assertStringContainsString('RINCIAN PINJAMAN PER KONTRAK (KBP)', $html);
        self::assertStringContainsString('L-2026-0077', $html);
        self::assertStringContainsString('1,000.00', $html);
    }

    public function test_ojk_collectibility_v2_renders_loan_level_detail(): void
    {
        $bucket = [
            'loan_count' => 0,
            'outstanding' => 0,
            'allowance_required' => 0,
            'allowance_formed' => 0,
            'selisih' => 0,
        ];

        $html = view('reports.pdf.ojk.collectibility_v2', [
            'identity' => $this->identity(),
            'period_label' => 'Juli 2026',
            'as_of' => '31/07/2026',
            'borrower_scope' => 'all',
            'buckets_meta' => [
                ['label' => 'Lancar'],
                ['label' => 'DPK'],
                ['label' => 'Kurang Lancar'],
                ['label' => 'Diragukan'],
                ['label' => 'Macet'],
            ],
            'products' => [[
                'product_row_id' => 1,
                'product_code' => 'P1',
                'product_name' => 'Modal Usaha',
                'loan_count' => 1,
                'outstanding' => 1000,
                'allowance_required' => 5,
                'allowance_formed' => 5,
                'selisih' => 0,
                'buckets' => [$bucket, $bucket, $bucket, $bucket, $bucket],
            ]],
            'grand_total' => [
                'loan_count' => 1,
                'outstanding' => 1000,
                'allowance_required' => 5,
                'allowance_formed' => 5,
                'selisih' => 0,
            ],
            'rows' => [[
                'loan_id' => 77,
                'loan_number' => 'L-2026-0077',
                'loan_product_row_id' => 1,
                'principal_amount' => 1000,
                'outstanding' => 1000,
                'days_overdue' => 30,
                'overdue_months' => 1,
            ]],
        ])->render();

        self::assertStringContainsString('RINCIAN PINJAMAN PER KONTRAK (KBP2 / POJK 41)', $html);
        self::assertStringContainsString('L-2026-0077', $html);
        self::assertStringContainsString('P1', $html);
    }

    public function test_ojk_allowance_renders_npl_summary(): void
    {
        $html = view('reports.pdf.ojk.allowance', [
            'identity' => $this->identity(),
            'period_label' => 'Juli 2026',
            'as_of' => '31/07/2026',
            'borrower_scope' => 'all',
            'compliance_pct' => 100,
            'buckets' => [
                [
                    'code' => 1,
                    'label' => 'Lancar',
                    'days_label' => '0 hari',
                    'loan_count' => 8,
                    'outstanding' => 800,
                    'share_pct' => 80,
                    'rate_pct' => 0.5,
                    'allowance_required' => 4,
                    'allowance_formed' => 4,
                ],
                [
                    'code' => 4,
                    'label' => 'Diragukan',
                    'days_label' => '121 - 180 hari',
                    'loan_count' => 1,
                    'outstanding' => 100,
                    'share_pct' => 10,
                    'rate_pct' => 50,
                    'allowance_required' => 50,
                    'allowance_formed' => 50,
                ],
                [
                    'code' => 5,
                    'label' => 'Macet',
                    'days_label' => '> 180 hari',
                    'loan_count' => 1,
                    'outstanding' => 100,
                    'share_pct' => 10,
                    'rate_pct' => 100,
                    'allowance_required' => 100,
                    'allowance_formed' => 100,
                ],
            ],
            'totals' => [
                'loan_count' => 10,
                'outstanding' => 1000,
                'allowance_required' => 154,
                'allowance_formed' => 154,
            ],
        ])->render();

        self::assertStringContainsString('Tingkat Kolektibilitas', $html);
        self::assertStringContainsString('NPL', $html);
        self::assertStringContainsString('20.00%', $html);
    }
}
