<?php

declare(strict_types=1);

namespace App\Http\Controllers\Savings;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Savings\Models\SavingsTransaction;
use App\Domain\Savings\Services\SavingsPrintExcel;
use App\Domain\Savings\Services\SavingsService;
use App\Models\User;
use App\Support\ReportPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dokumen cetak simpanan yang mengikuti desain SIUPK:
 *  - KOP buku simpanan
 *  - Formulir pembukaan rekening
 *  - Rekening koran
 *  - Cetak pada buku (passbook)
 *  - Cetak pada kwitansi
 *
 * Halaman dirender sebagai HTML biasa dan langsung memanggil window.print(),
 * sama seperti partial cetak pada aplikasi SIUPK.
 */
final class SavingsPrintController
{
    public function __construct(
        private readonly SavingsService $savings,
        private readonly ReportPdf $pdf,
        private readonly SavingsPrintExcel $excel,
    ) {}

    public function kop(int $account): Response
    {
        return response()->view('savings.print.kop', $this->kopData($account));
    }

    public function kopPdf(int $account): Response|StreamedResponse
    {
        $data = $this->kopData($account);

        return $this->pdf->download('savings.print.kop', $data, $this->pdfFilename('kop-simpanan', $data));
    }

    public function kopExcel(int $account): StreamedResponse
    {
        return $this->excel->kop($this->kopData($account));
    }

    public function formulir(int $account): Response
    {
        return response()->view('savings.print.formulir', $this->formulirData($account));
    }

    public function formulirPdf(int $account): Response|StreamedResponse
    {
        $data = $this->formulirData($account);
        $data['pdf'] = true;

        return $this->pdf->download('savings.print.formulir', $data, $this->pdfFilename('formulir-simpanan', $data));
    }

    public function formulirExcel(int $account): StreamedResponse
    {
        return $this->excel->formulir($this->formulirData($account));
    }

    /**
     * Formulir kosong, sama seperti tombol "Form Simpanan" pada SIUPK.
     * Dipakai petugas untuk mengisi formulir pembukaan rekening di atas kertas.
     */
    public function formulirBlank(): Response
    {
        return response()->view('savings.print.formulir', [
            'title' => 'Formulir Simpanan',
            'account' => [
                'row_id' => null,
                'account_number' => '',
                'opened_at' => null,
                'authority_type' => 1,
                'institution_name' => null,
                'institution_position' => null,
                'guardian_name' => null,
                'relationship' => null,
                'notes' => null,
                'member' => [
                    'name' => '',
                    'nik' => '',
                    'address' => '',
                    'phone' => '',
                    'gender' => '',
                    'birth_place' => '',
                    'birth_date' => '',
                    'occupation' => '',
                ],
                'product' => ['name' => ''],
            ],
            'organization' => $this->organization(),
            'pencetak' => (string) (auth()->user()?->name ?? ''),
            'penerima' => (string) (auth()->user()?->name ?? ''),
        ]);
    }

    public function koran(Request $request, int $account): Response
    {
        return response()->view('savings.print.koran', $this->koranData($request, $account));
    }

    public function koranPdf(Request $request, int $account): Response|StreamedResponse
    {
        $data = $this->koranData($request, $account);
        $suffix = $this->statementSuffix($data['statement'] ?? []);

        return $this->pdf->download('savings.print.koran', $data, $this->pdfFilename('rekening-koran', $data, $suffix));
    }

    public function koranExcel(Request $request, int $account): StreamedResponse
    {
        return $this->excel->koran($this->koranData($request, $account));
    }

    public function buku(int $account, int $transaction): Response
    {
        $model = $this->savings->findAccount($account);
        $row = SavingsTransaction::query()
            ->where('account_row_id', $model->row_id)
            ->whereKey($transaction)
            ->firstOrFail();

        $count = SavingsTransaction::query()
            ->where('account_row_id', $model->row_id)
            ->where('row_id', '<=', $row->row_id)
            ->count();

        $user = $this->userDisplay((int) ($row->created_by_user_id ?? 0));

        return response()->view('savings.print.buku', [
            'title' => 'Cetak Pada Buku '.$model->account_number,
            'account' => $this->savings->accountRow($model),
            'transaction' => $this->savings->transactionRow($row),
            'transactionCount' => $count,
            'user' => $user,
            'kode' => (int) $row->code,
            'debit' => $row->direction === 'out' ? (float) $row->amount : 0.0,
            'kredit' => $row->direction === 'in' ? (float) $row->amount : 0.0,
            'saldo' => (float) $row->balance_after,
        ]);
    }

    public function kwitansi(int $account, int $transaction): Response
    {
        $model = $this->savings->findAccount($account);
        $row = SavingsTransaction::query()
            ->where('account_row_id', $model->row_id)
            ->whereKey($transaction)
            ->firstOrFail();

        return response()->view('savings.print.kwitansi', [
            'title' => 'Cetak Pada Kwitansi '.$model->account_number,
            'account' => $this->savings->accountRow($model),
            'transaction' => $this->savings->transactionRow($row),
            'user' => $this->userDisplay((int) ($row->created_by_user_id ?? 0)),
            'kode' => (int) $row->code,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function kopData(int $account): array
    {
        $model = $this->savings->findAccount($account);

        return [
            'title' => 'Cetak KOP buku '.$model->account_number,
            'account' => $this->savings->accountRow($model),
            'organization' => $this->organization(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formulirData(int $account): array
    {
        $model = $this->savings->findAccount($account);

        return [
            'title' => 'Formulir Simpanan '.$model->account_number,
            'account' => $this->savings->accountRow($model),
            'organization' => $this->organization(),
            'pencetak' => (string) (auth()->user()?->name ?? ''),
            'penerima' => (string) (auth()->user()?->name ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function koranData(Request $request, int $account): array
    {
        $model = $this->savings->findAccount($account);
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        if ($year !== 0 && ($year < 2000 || $year > 2100)) {
            $year = (int) now()->year;
        }

        if ($month < 0 || $month > 12) {
            $month = (int) now()->month;
        }

        $statement = $this->savings->statement($model, $year, $month);

        return [
            'title' => 'Cetak Rekening Koran '.$model->account_number,
            'account' => $statement['account'],
            'organization' => $this->organization(),
            'statement' => $statement,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdfFilename(string $prefix, array $data, ?string $suffix = null): string
    {
        $number = (string) ($data['account']['account_number'] ?? 'rekening');
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $number) ?: 'rekening';
        $parts = array_filter([$prefix, $safe, $suffix], static fn (?string $part): bool => $part !== null && $part !== '');

        return implode('-', $parts).'.pdf';
    }

    /**
     * @param  array<string, mixed>  $statement
     */
    private function statementSuffix(array $statement): string
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
     * @return array<string, mixed>
     */
    private function organization(): array
    {
        $profile = OrganizationProfile::query()->first();

        return [
            'legal_name' => $profile?->legal_name ?: 'KOPERASI LEMBAGA KEUANGAN MIKRO',
            'short_name' => $profile?->short_name ?: null,
            'address' => $profile?->address ?: null,
            'district_name' => $profile?->district_name ?: null,
            'regency_name' => $profile?->regency_name ?: null,
            'phone' => $profile?->phone ?: null,
            'email' => $profile?->email ?: null,
            'website' => $profile?->website ?: null,
            'logo_path' => $profile?->logo_path ?: null,
        ];
    }

    private function userDisplay(int $transactionUserId): string
    {
        $current = auth()->user();
        $creator = $transactionUserId > 0 ? User::query()->find($transactionUserId) : null;

        $currentLabel = (string) ($current?->initials ?: $current?->username ?: $current?->name ?: '');
        $creatorLabel = (string) ($creator?->initials ?: $creator?->username ?: $creator?->name ?: '');

        if ($creator === null || $creator->row_id === $current?->row_id) {
            return $currentLabel;
        }

        return $currentLabel.' / '.$creatorLabel;
    }
}
