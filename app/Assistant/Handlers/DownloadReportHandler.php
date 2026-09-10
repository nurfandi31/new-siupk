<?php

declare(strict_types=1);

namespace App\Assistant\Handlers;

use App\Assistant\ToolHandlerBase;
use App\Models\User;

final class DownloadReportHandler extends ToolHandlerBase
{
    public function name(): string
    {
        return 'download_report';
    }

    public function description(): string
    {
        return 'Menghasilkan tombol/link direct download untuk laporan keuangan (Neraca, Laba Rugi, Arus Kas, Buku Besar, Jurnal, dll) dan laporan pinjaman (Portofolio, LPP, Kolektibilitas, dll) dalam format PDF atau Excel.';
    }

    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['report_type'],
            'properties' => [
                'report_type' => [
                    'type' => 'string',
                    'description' => 'Jenis laporan: balance_sheet (neraca), income_statement (laba rugi), cash_flow (arus kas), trial_balance (neraca saldo), equity_change (perubahan modal), calk, general_ledger (buku besar), journals (jurnal transaksi), financial_health, fixed_assets, portfolio, schedule_vs_actual, lpp_desa, lpp_kelompok, kolek_desa, cadangan_penghapusan, members, groups.',
                ],
                'format' => [
                    'type' => 'string',
                    'enum' => ['pdf', 'excel'],
                    'description' => 'Format file: pdf atau excel (default pdf).',
                ],
                'month' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 12,
                    'description' => 'Bulan laporan (1-12). Omit atau isi null jika laporan tahunan penuh.',
                ],
                'year' => [
                    'type' => 'integer',
                    'minimum' => 2000,
                    'maximum' => 2100,
                    'description' => 'Tahun laporan (contoh: 2026). Default tahun saat ini.',
                ],
                'from_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Tanggal awal periode (YYYY-MM-DD), khusus laporan jurnal transaksi.',
                ],
                'to_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Tanggal akhir periode (YYYY-MM-DD), khusus laporan jurnal transaksi.',
                ],
                'account_id' => [
                    'type' => 'integer',
                    'description' => 'ID akun spesifik, khusus laporan buku besar.',
                ],
                'as_of_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Posisi tanggal tertentu (YYYY-MM-DD), khusus daftar aset tetap.',
                ],
            ],
        ];
    }

    protected function invoke(array $params, User $actor): array
    {
        return $this->tools->downloadReport($params);
    }
}
