<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use App\Support\Excel\XlsxWriter;
use RuntimeException;

/**
 * Builds Excel workbooks for the three legacy migration datasets:
 * group loans, individual loans, and savings.
 *
 * The exports intentionally read from the legacy source tables. They are meant
 * for audit / verification before or after a cutover run, and include the
 * related installment or mutation sheets so the numbers can be traced.
 */
final class LegacyMigrationExcelExporter
{
    public function __construct(
        private LegacyConnection $legacy,
    ) {}

    public function groupLoans(string $suffix): XlsxWriter
    {
        $suffix = $this->validateSuffix($suffix);
        $table = $this->legacy->pinjamanKelompokTable($suffix);
        $this->assertTableExists($table);

        $writer = new XlsxWriter;
        $writer->addSheet('Pinjaman Kelompok');

        $groupJoin = '';
        $groupName = 'NULL AS nama_kelompok';
        $groupTable = $this->legacy->kelompokTable($suffix);
        if ($this->legacy->tableExists($groupTable)) {
            $groupJoin = " LEFT JOIN `{$groupTable}` k ON k.id = p.id_kel";
            $groupName = 'k.nama_kelompok';
        }

        $rows = $this->legacy->select(
            "SELECT p.*, {$groupName} FROM `{$table}` p{$groupJoin} ORDER BY p.id ASC"
        );

        $this->writeSheet($writer, $this->groupLoanColumns(), $rows);

        $this->addScheduleSheet(
            $writer,
            'Rencana Angsuran',
            $this->legacy->rencanaAngsuranTable($suffix),
        );
        $this->addPaymentSheet(
            $writer,
            'Realisasi Angsuran',
            $this->legacy->realAngsuranTable($suffix),
        );

        return $writer;
    }

    public function memberLoans(string $suffix): XlsxWriter
    {
        $suffix = $this->validateSuffix($suffix);
        $table = $this->legacy->pinjamanAnggotaTable($suffix);
        $this->assertTableExists($table);

        $writer = new XlsxWriter;
        $writer->addSheet('Pinjaman Individu');

        $memberJoin = '';
        $memberName = 'NULL AS nama_anggota';
        $memberTable = $this->legacy->anggotaTable($suffix);
        if ($this->legacy->tableExists($memberTable)) {
            $memberJoin = " LEFT JOIN `{$memberTable}` a ON a.id = p.nia";
            $memberName = 'a.namadepan AS nama_anggota';
        }

        $groupJoin = '';
        $groupName = 'NULL AS nama_kelompok';
        $groupTable = $this->legacy->kelompokTable($suffix);
        if ($this->legacy->tableExists($groupTable)) {
            $groupJoin = " LEFT JOIN `{$groupTable}` k ON k.id = p.id_kel";
            $groupName = 'k.nama_kelompok';
        }

        $rows = $this->legacy->select(
            "SELECT p.*, {$memberName}, {$groupName}
             FROM `{$table}` p{$memberJoin}{$groupJoin}
             WHERE p.jenis_pinjaman = 'I'
             ORDER BY p.id ASC"
        );

        $this->writeSheet($writer, $this->memberLoanColumns(), $rows);

        $this->addScheduleSheet(
            $writer,
            'Rencana Angsuran Individu',
            $this->legacy->rencanaAngsuranIndividuTable($suffix),
        );
        $this->addPaymentSheet(
            $writer,
            'Realisasi Angsuran Individu',
            $this->legacy->realAngsuranIndividuTable($suffix),
        );

        return $writer;
    }

    public function savings(string $suffix): XlsxWriter
    {
        $suffix = $this->validateSuffix($suffix);
        $table = $this->legacy->simpananAnggotaTable($suffix);
        $this->assertTableExists($table);

        $writer = new XlsxWriter;
        $writer->addSheet('Rekening Simpanan');

        $productJoin = '';
        $productName = 'NULL AS nama_js';
        $productTable = $this->legacy->jenisSimpananTable();
        if ($this->legacy->tableExists($productTable)) {
            $productJoin = " LEFT JOIN `{$productTable}` js ON js.id = s.jenis_simpanan";
            $productName = 'js.nama_js';
        }

        $memberJoin = '';
        $memberName = 'NULL AS nama_anggota';
        $memberTable = $this->legacy->anggotaTable($suffix);
        if ($this->legacy->tableExists($memberTable)) {
            $memberJoin = " LEFT JOIN `{$memberTable}` a ON a.id = s.nia";
            $memberName = 'a.namadepan AS nama_anggota';
        }

        $lastBalance = 'NULL AS last_sum';
        $realTable = $this->legacy->realSimpananTable($suffix);
        if ($this->legacy->tableExists($realTable)) {
            $lastBalance = "(SELECT r.sum FROM `{$realTable}` r
                WHERE r.cif = s.id
                ORDER BY r.tgl_transaksi DESC, r.id DESC LIMIT 1) AS last_sum";
        }

        $rows = $this->legacy->select(
            "SELECT s.*, {$productName}, {$memberName}, {$lastBalance}
             FROM `{$table}` s{$productJoin}{$memberJoin}
             ORDER BY s.id ASC"
        );

        $this->writeSheet($writer, $this->savingsAccountColumns(), $rows);

        if ($this->legacy->tableExists($realTable)) {
            $writer->addSheet('Mutasi Simpanan');
            $transaksiJoin = '';
            $description = 'NULL AS keterangan_transaksi';
            $debitAccount = 'NULL AS rekening_debit';
            $creditAccount = 'NULL AS rekening_kredit';
            $transaksiTable = $this->legacy->transaksiTable($suffix);
            if ($this->legacy->tableExists($transaksiTable)) {
                $transaksiJoin = " LEFT JOIN `{$transaksiTable}` t ON t.idt = r.idt";
                $description = 't.keterangan_transaksi';
                $debitAccount = 't.rekening_debit';
                $creditAccount = 't.rekening_kredit';
            }

            $transactionRows = $this->legacy->select(
                "SELECT r.*, {$description}, {$debitAccount}, {$creditAccount}
                 FROM `{$realTable}` r{$transaksiJoin}
                 WHERE COALESCE(r.real_k, 0) <> 0 OR COALESCE(r.real_d, 0) <> 0
                 ORDER BY r.cif ASC, r.tgl_transaksi ASC, r.id ASC"
            );

            $this->writeSheet($writer, $this->savingsTransactionColumns(), $transactionRows);
        }

        return $writer;
    }

    private function addScheduleSheet(XlsxWriter $writer, string $title, string $table): void
    {
        if (! $this->legacy->tableExists($table)) {
            return;
        }

        $rows = $this->legacy->select(
            "SELECT * FROM `{$table}`
             WHERE CAST(angsuran_ke AS UNSIGNED) > 0
             ORDER BY id ASC"
        );

        $writer->addSheet($title);
        $this->writeSheet($writer, $this->scheduleColumns(), $rows);
    }

    private function addPaymentSheet(XlsxWriter $writer, string $title, string $table): void
    {
        if (! $this->legacy->tableExists($table)) {
            return;
        }

        $rows = $this->legacy->select(
            "SELECT * FROM `{$table}`
             WHERE id > 0
               AND NOT (
                 COALESCE(realisasi_pokok, 0) = 0
                 AND COALESCE(realisasi_jasa, 0) = 0
               )
             ORDER BY id ASC"
        );

        $writer->addSheet($title);
        $this->writeSheet($writer, $this->paymentColumns(), $rows);
    }

    /**
     * @param  list<array{label: string, key: string, numeric?: bool}>  $columns
     * @param  list<object>  $rows
     */
    private function writeSheet(XlsxWriter $writer, array $columns, array $rows): void
    {
        $header = [];
        $headerStyles = [];
        $widths = [];
        foreach ($columns as $column) {
            $header[] = $column['label'];
            $headerStyles[] = XlsxWriter::STYLE_BOLD;
            $widths[] = max(12.0, min(40.0, (float) (mb_strlen($column['label']) + 4)));
        }

        $writer->setColumnWidths($widths);
        $writer->addRow($header, $headerStyles);

        foreach ($rows as $row) {
            $values = [];
            $styles = [];
            foreach ($columns as $column) {
                $value = $this->value($row, $column['key']);
                if (! empty($column['numeric'])) {
                    $value = $this->number($value);
                    $styles[] = XlsxWriter::STYLE_RUPIAH;
                } else {
                    $value = $this->text($value);
                    $styles[] = XlsxWriter::STYLE_DEFAULT;
                }
                $values[] = $value;
            }
            $writer->addRow($values, $styles);
        }
    }

    /**
     * @return list<array{label: string, key: string, numeric?: bool}>
     */
    private function groupLoanColumns(): array
    {
        return [
            ['label' => 'ID', 'key' => 'id'],
            ['label' => 'Nama Kelompok', 'key' => 'nama_kelompok'],
            ['label' => 'ID Kelompok', 'key' => 'id_kel'],
            ['label' => 'Jenis PP', 'key' => 'jenis_pp'],
            ['label' => 'Tanggal Proposal', 'key' => 'tgl_proposal'],
            ['label' => 'Tanggal Verifikasi', 'key' => 'tgl_verifikasi'],
            ['label' => 'Tanggal Dana', 'key' => 'tgl_dana'],
            ['label' => 'Tanggal Tunggu', 'key' => 'tgl_tunggu'],
            ['label' => 'Tanggal Cair', 'key' => 'tgl_cair'],
            ['label' => 'Tanggal Lunas', 'key' => 'tgl_lunas'],
            ['label' => 'Proposal', 'key' => 'proposal'],
            ['label' => 'Verifikasi', 'key' => 'verifikasi'],
            ['label' => 'Alokasi', 'key' => 'alokasi', 'numeric' => true],
            ['label' => 'SPK No', 'key' => 'spk_no'],
            ['label' => 'Sumber', 'key' => 'sumber'],
            ['label' => 'Prosentase Jasa', 'key' => 'pros_jasa'],
            ['label' => 'Jenis Jasa', 'key' => 'jenis_jasa'],
            ['label' => 'Jangka', 'key' => 'jangka'],
            ['label' => 'Sistem Angsuran', 'key' => 'sistem_angsuran'],
            ['label' => 'SA Jasa', 'key' => 'sa_jasa', 'numeric' => true],
            ['label' => 'Status', 'key' => 'status'],
            ['label' => 'Catatan Verifikasi', 'key' => 'catatan_verifikasi'],
            ['label' => 'Waktu Cair', 'key' => 'wt_cair'],
        ];
    }

    /**
     * @return list<array{label: string, key: string, numeric?: bool}>
     */
    private function memberLoanColumns(): array
    {
        return [
            ['label' => 'ID', 'key' => 'id'],
            ['label' => 'NIA', 'key' => 'nia'],
            ['label' => 'Nama Anggota', 'key' => 'nama_anggota'],
            ['label' => 'Nama Kelompok', 'key' => 'nama_kelompok'],
            ['label' => 'ID Kelompok', 'key' => 'id_kel'],
            ['label' => 'ID Pemanfaat', 'key' => 'id_pinkel'],
            ['label' => 'Jenis Pinjaman', 'key' => 'jenis_pinjaman'],
            ['label' => 'Jenis PP', 'key' => 'jenis_pp'],
            ['label' => 'Tanggal Proposal', 'key' => 'tgl_proposal'],
            ['label' => 'Tanggal Verifikasi', 'key' => 'tgl_verifikasi'],
            ['label' => 'Tanggal Dana', 'key' => 'tgl_dana'],
            ['label' => 'Tanggal Tunggu', 'key' => 'tgl_tunggu'],
            ['label' => 'Tanggal Cair', 'key' => 'tgl_cair'],
            ['label' => 'Tanggal Lunas', 'key' => 'tgl_lunas'],
            ['label' => 'Proposal', 'key' => 'proposal'],
            ['label' => 'Verifikasi', 'key' => 'verifikasi'],
            ['label' => 'Alokasi', 'key' => 'alokasi', 'numeric' => true],
            ['label' => 'Komponen Pokok', 'key' => 'kom_pokok', 'numeric' => true],
            ['label' => 'Komponen Jasa', 'key' => 'kom_jasa', 'numeric' => true],
            ['label' => 'SPK No', 'key' => 'spk_no'],
            ['label' => 'Sumber', 'key' => 'sumber'],
            ['label' => 'Prosentase Jasa', 'key' => 'pros_jasa'],
            ['label' => 'Jenis Jasa', 'key' => 'jenis_jasa'],
            ['label' => 'Jangka', 'key' => 'jangka'],
            ['label' => 'Sistem Angsuran', 'key' => 'sistem_angsuran'],
            ['label' => 'SA Jasa', 'key' => 'sa_jasa', 'numeric' => true],
            ['label' => 'Status', 'key' => 'status'],
            ['label' => 'Jaminan', 'key' => 'jaminan'],
            ['label' => 'Catatan Verifikasi', 'key' => 'catatan_verifikasi'],
            ['label' => 'Waktu Cair', 'key' => 'wt_cair'],
        ];
    }

    /**
     * @return list<array{label: string, key: string, numeric?: bool}>
     */
    private function scheduleColumns(): array
    {
        return [
            ['label' => 'ID', 'key' => 'id'],
            ['label' => 'Loan ID', 'key' => 'loan_id'],
            ['label' => 'Angsuran Ke', 'key' => 'angsuran_ke'],
            ['label' => 'Jatuh Tempo', 'key' => 'jatuh_tempo'],
            ['label' => 'Wajib Pokok', 'key' => 'wajib_pokok', 'numeric' => true],
            ['label' => 'Wajib Jasa', 'key' => 'wajib_jasa', 'numeric' => true],
            ['label' => 'Target Pokok', 'key' => 'target_pokok', 'numeric' => true],
            ['label' => 'Target Jasa', 'key' => 'target_jasa', 'numeric' => true],
        ];
    }

    /**
     * @return list<array{label: string, key: string, numeric?: bool}>
     */
    private function paymentColumns(): array
    {
        return [
            ['label' => 'ID', 'key' => 'id'],
            ['label' => 'Loan ID', 'key' => 'loan_id'],
            ['label' => 'Tanggal Transaksi', 'key' => 'tgl_transaksi'],
            ['label' => 'Realisasi Pokok', 'key' => 'realisasi_pokok', 'numeric' => true],
            ['label' => 'Realisasi Jasa', 'key' => 'realisasi_jasa', 'numeric' => true],
            ['label' => 'Sum Pokok', 'key' => 'sum_pokok', 'numeric' => true],
            ['label' => 'Sum Jasa', 'key' => 'sum_jasa', 'numeric' => true],
            ['label' => 'Saldo Pokok', 'key' => 'saldo_pokok', 'numeric' => true],
            ['label' => 'Saldo Jasa', 'key' => 'saldo_jasa', 'numeric' => true],
            ['label' => 'Tunggakan Pokok', 'key' => 'tunggakan_pokok', 'numeric' => true],
            ['label' => 'Tunggakan Jasa', 'key' => 'tunggakan_jasa', 'numeric' => true],
        ];
    }

    /**
     * @return list<array{label: string, key: string, numeric?: bool}>
     */
    private function savingsAccountColumns(): array
    {
        return [
            ['label' => 'ID', 'key' => 'id'],
            ['label' => 'Nomor Rekening', 'key' => 'nomor_rekening'],
            ['label' => 'Jenis Simpanan ID', 'key' => 'jenis_simpanan'],
            ['label' => 'Nama Produk', 'key' => 'nama_js'],
            ['label' => 'NIA', 'key' => 'nia'],
            ['label' => 'Nama Anggota', 'key' => 'nama_anggota'],
            ['label' => 'Jumlah', 'key' => 'jumlah', 'numeric' => true],
            ['label' => 'Tanggal Buka', 'key' => 'tgl_buka'],
            ['label' => 'Tanggal Tutup', 'key' => 'tgl_tutup'],
            ['label' => 'Tanggal ST', 'key' => 'tgl_st'],
            ['label' => 'Bunga', 'key' => 'bunga'],
            ['label' => 'Pajak', 'key' => 'pajak'],
            ['label' => 'Admin', 'key' => 'admin'],
            ['label' => 'Status', 'key' => 'status'],
            ['label' => 'SP', 'key' => 'sp'],
            ['label' => 'Lembaga', 'key' => 'lembaga'],
            ['label' => 'Jabatan', 'key' => 'jabatan'],
            ['label' => 'Pengampu', 'key' => 'pengampu'],
            ['label' => 'Hubungan', 'key' => 'hubungan'],
            ['label' => 'Catatan', 'key' => 'catatan_simpanan'],
            ['label' => 'Saldo Terakhir', 'key' => 'last_sum', 'numeric' => true],
        ];
    }

    /**
     * @return list<array{label: string, key: string, numeric?: bool}>
     */
    private function savingsTransactionColumns(): array
    {
        return [
            ['label' => 'ID', 'key' => 'id'],
            ['label' => 'CIF / Rekening ID', 'key' => 'cif'],
            ['label' => 'ID Transaksi', 'key' => 'idt'],
            ['label' => 'Tanggal Transaksi', 'key' => 'tgl_transaksi'],
            ['label' => 'Debit', 'key' => 'real_d', 'numeric' => true],
            ['label' => 'Kredit', 'key' => 'real_k', 'numeric' => true],
            ['label' => 'Saldo', 'key' => 'sum', 'numeric' => true],
            ['label' => 'Keterangan', 'key' => 'keterangan_transaksi'],
            ['label' => 'Rekening Debit', 'key' => 'rekening_debit'],
            ['label' => 'Rekening Kredit', 'key' => 'rekening_kredit'],
        ];
    }

    private function value(object $row, string $key): mixed
    {
        return $row->{$key} ?? null;
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        $text = trim((string) $value);
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return $text;
    }

    private function number(mixed $value): float|int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0;
        }

        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? (float) $raw : 0;
    }

    private function validateSuffix(string $suffix): string
    {
        if (! preg_match('/^\d+$/', $suffix)) {
            throw new RuntimeException('Suffix harus berupa angka.');
        }

        return $suffix;
    }

    private function assertTableExists(string $table): void
    {
        if (! $this->legacy->tableExists($table)) {
            throw new RuntimeException("Tabel legacy [{$table}] tidak ditemukan.");
        }
    }
}
