<?php

declare(strict_types=1);

namespace App\Domain\Savings\Services;

use App\Support\Excel\XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor Excel untuk dokumen simpanan.
 *
 * Bentuknya mengikuti data pada dokumen cetak, tetapi disusun sebagai tabel
 * agar mudah diperiksa dan diolah kembali di spreadsheet.
 */
final class SavingsPrintExcel
{
    private const B = XlsxWriter::STYLE_BOLD;

    private const D = XlsxWriter::STYLE_DEFAULT;

    private const R = XlsxWriter::STYLE_RUPIAH;

    private const RB = XlsxWriter::STYLE_RUPIAH_BOLD;

    /**
     * @param  array<string, mixed>  $data
     */
    public function kop(array $data): StreamedResponse
    {
        $account = $this->array($data['account'] ?? null);
        $organization = $this->array($data['organization'] ?? null);
        $member = $this->array($account['member'] ?? null);
        $product = $this->array($account['product'] ?? null);

        $w = new XlsxWriter;
        $w->addSheet('KOP Simpanan');
        $w->setColumnWidths([24, 62]);
        $w->addRow([(string) ($organization['legal_name'] ?? '')], [self::B]);
        $w->addRow(['KOP BUKU SIMPANAN'], [self::B]);
        $w->addRow([]);
        $w->addRow(['Rekening', (string) ($account['account_number'] ?? '')], [self::B, self::D]);
        $w->addRow(['CIF', (int) ($account['row_id'] ?? 0)], [self::B, self::D]);
        $w->addRow(['Nama Nasabah', (string) ($member['name'] ?? '')], [self::B, self::D]);
        $w->addRow(['Alamat', $this->fullAddress($member)], [self::B, self::D]);
        $w->addRow(['Telepon', (string) ($member['phone'] ?? '')], [self::B, self::D]);
        $w->addRow(['Jenis Simpanan', (string) ($product['name'] ?? '')], [self::B, self::D]);
        $w->addRow(['Dibuka', (string) ($account['opened_at'] ?? '')], [self::B, self::D]);
        $w->addRow([]);
        $w->addRow(['Dicetak pada', now()->format('Y-m-d H:i:s')], [self::B, self::D]);

        return $w->download($this->filename('kop-simpanan', $account));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function formulir(array $data): StreamedResponse
    {
        $account = $this->array($data['account'] ?? null);
        $organization = $this->array($data['organization'] ?? null);
        $member = $this->array($account['member'] ?? null);
        $product = $this->array($account['product'] ?? null);
        $isInstitution = (int) ($account['authority_type'] ?? 1) === 2;
        $birth = trim(($member['birth_place'] ?? '').', '.($member['birth_date'] ?? ''), ', ');

        $fields = [
            ['N I K', (string) ($member['nik'] ?? '')],
            ['Nama Lengkap', (string) ($member['name'] ?? '')],
            ['Nama Alias', ''],
            ['Tempat, Tanggal Lahir', $birth],
            ['Alamat', (string) ($member['address'] ?? '')],
            ['No. Handphone', (string) ($member['phone'] ?? '')],
            ['Agama', ''],
            ['Status Pernikahan', ''],
            ['Pendidikan Terakhir', ''],
            ['Pekerjaan', (string) ($member['occupation'] ?? '')],
            ['Alamat Pekerjaan', (string) ($member['address'] ?? '')],
            ['NPWP', ''],
            ['Penghasilan per Bulan', ''],
            ['Sumber Dana', ''],
            ['Jenis Rekening', (string) ($product['name'] ?? '')],
            ['Status Permohonan', $isInstitution ? 'Kuasa' : 'Pribadi'],
        ];

        if ($isInstitution) {
            $fields[] = ['Nama Lembaga', (string) ($account['institution_name'] ?? '')];
            $fields[] = ['Jabatan', (string) ($account['institution_position'] ?? '')];
        }

        $fields[] = ['Pengampu', (string) ($account['guardian_name'] ?? '')];
        $fields[] = ['Hubungan', (string) ($account['relationship'] ?? '')];
        $fields[] = ['Nama Gadis Ibu Kandung', ''];

        $w = new XlsxWriter;
        $w->addSheet('Formulir Simpanan');
        $w->setColumnWidths([6, 30, 58]);
        $w->addRow([(string) ($organization['legal_name'] ?? '')], [self::B]);
        $w->addRow(['FORMULIR SIMPANAN NASABAH'], [self::B]);
        $w->addRow([]);
        $w->addRow(['', 'CIF', (int) ($account['row_id'] ?? 0)], [self::D, self::B, self::D]);
        $w->addRow(['', 'No Rekening', (string) ($account['account_number'] ?? '')], [self::D, self::B, self::D]);
        $w->addRow([]);
        $w->addRow(['No', 'Field', 'Nilai'], [self::B, self::B, self::B]);

        foreach ($fields as $index => [$label, $value]) {
            $w->addRow([$index + 1, $label, $value], [self::D, self::D, self::D]);
        }

        $w->addRow([]);
        $w->addRow(['', 'Dicetak Oleh', (string) ($data['pencetak'] ?? '')], [self::D, self::B, self::D]);
        $w->addRow(['', 'Dicetak Pada', now()->format('Y-m-d H:i:s')], [self::D, self::B, self::D]);

        return $w->download($this->filename('formulir-simpanan', $account));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function koran(array $data): StreamedResponse
    {
        $account = $this->array($data['account'] ?? null);
        $organization = $this->array($data['organization'] ?? null);
        $statement = $this->array($data['statement'] ?? null);
        $member = $this->array($account['member'] ?? null);
        $product = $this->array($account['product'] ?? null);
        $allTime = (bool) ($statement['all_time'] ?? false);
        $month = (int) ($statement['month'] ?? 0);

        $w = new XlsxWriter;
        $w->addSheet('Rekening Koran');
        $w->setColumnWidths([6, 14, 9, 48, 16, 16, 16, 6]);
        $w->addRow([(string) ($organization['legal_name'] ?? '')], [self::B]);
        $w->addRow(['REKENING KORAN '.strtoupper((string) ($product['name'] ?? ''))], [self::B]);
        $w->addRow([(string) ($statement['period_label'] ?? '')], [self::B]);
        $w->addRow([]);
        $w->addRow(['Nomor Rekening', (string) ($account['account_number'] ?? '')], [self::B, self::D]);
        $w->addRow(['CIF / NIK', trim((string) ($account['row_id'] ?? '').' / '.(string) ($member['nik'] ?? ''))], [self::B, self::D]);
        $w->addRow(['Nama Kreditur', (string) ($member['name'] ?? '')], [self::B, self::D]);
        $w->addRow(['Tanggal Buka', (string) ($account['opened_at'] ?? '')], [self::B, self::D]);
        $w->addRow(['Alamat', (string) ($member['address'] ?? '')], [self::B, self::D]);
        $w->addRow(['Telepon', (string) ($member['phone'] ?? '')], [self::B, self::D]);
        $w->addRow([]);
        $w->addRow(
            ['NO', 'TANGGAL', 'REF', 'KETERANGAN', 'DEBIT', 'KREDIT', 'SALDO', 'P'],
            [self::B, self::B, self::B, self::B, self::B, self::B, self::B, self::B],
        );

        if ($allTime && $month > 0) {
            $w->addRow(['', '', '', 'Mohon untuk memilih tahun juga', '', '', '', ''], [self::D, self::D, self::D, self::B, self::D, self::D, self::D, self::D]);
        } elseif (! $allTime) {
            $openingLabel = $month > 0
                ? 'Saldo Sebelum '.(string) ($statement['period_label'] ?? '')
                : 'Saldo Sebelum '.(string) ($statement['year'] ?? '');

            $w->addRow(
                ['', '', '', $openingLabel, '', '', (float) ($statement['opening_balance'] ?? 0), ''],
                [self::D, self::D, self::D, self::B, self::D, self::D, self::R, self::D],
            );
        }

        foreach (($statement['transactions'] ?? []) as $index => $transaction) {
            $transaction = $this->array($transaction);
            $isOut = (string) ($transaction['direction'] ?? '') === 'out';
            $isIn = (string) ($transaction['direction'] ?? '') === 'in';

            $w->addRow(
                [
                    $index + 1,
                    (string) ($transaction['transaction_date'] ?? ''),
                    (int) ($transaction['row_id'] ?? 0),
                    (string) ($transaction['description'] ?? ''),
                    $isOut ? (float) ($transaction['amount'] ?? 0) : 0,
                    $isIn ? (float) ($transaction['amount'] ?? 0) : 0,
                    (float) ($transaction['balance_after'] ?? 0),
                    (int) ($transaction['created_by_id'] ?? 0),
                ],
                [self::D, self::D, self::D, self::D, self::R, self::R, self::R, self::D],
            );
        }

        $w->addRow(
            ['', '', '', 'TOTAL SALDO', '', '', (float) ($statement['closing_balance'] ?? 0), ''],
            [self::D, self::D, self::D, self::B, self::D, self::D, self::RB, self::D],
        );

        return $w->download($this->filename('rekening-koran', $account, $this->periodSuffix($statement)));
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function filename(string $prefix, array $account, ?string $suffix = null): string
    {
        $number = (string) ($account['account_number'] ?? 'rekening');
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $number) ?: 'rekening';
        $parts = array_filter([$prefix, $safe, $suffix], static fn (?string $part): bool => $part !== null && $part !== '');

        return implode('-', $parts).'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $statement
     */
    private function periodSuffix(array $statement): string
    {
        if ((bool) ($statement['all_time'] ?? false)) {
            return 'semua-tahun';
        }

        $year = (string) ($statement['year'] ?? '');
        $month = (int) ($statement['month'] ?? 0);

        return $month > 0
            ? $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT)
            : $year;
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function fullAddress(array $member): string
    {
        $address = trim((string) ($member['address'] ?? ''));
        $village = trim((string) ($member['village'] ?? ''));

        return $village !== '' && ! str_contains($address, $village)
            ? trim($address.' '.$village)
            : $address;
    }

    /**
     * @return array<string, mixed>
     */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
