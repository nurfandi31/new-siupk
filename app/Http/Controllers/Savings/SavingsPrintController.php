<?php

declare(strict_types=1);

namespace App\Http\Controllers\Savings;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Savings\Models\SavingsTransaction;
use App\Domain\Savings\Services\SavingsService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    ) {}

    public function kop(int $account): Response
    {
        $model = $this->savings->findAccount($account);

        return response()->view('savings.print.kop', [
            'title' => 'Cetak KOP buku '.$model->account_number,
            'account' => $this->savings->accountRow($model),
            'organization' => $this->organization(),
        ]);
    }

    public function formulir(int $account): Response
    {
        $model = $this->savings->findAccount($account);

        return response()->view('savings.print.formulir', [
            'title' => 'Formulir Simpanan '.$model->account_number,
            'account' => $this->savings->accountRow($model),
            'organization' => $this->organization(),
            'pencetak' => (string) (auth()->user()?->name ?? ''),
            'penerima' => (string) (auth()->user()?->name ?? ''),
        ]);
    }

    public function koran(Request $request, int $account): Response
    {
        $model = $this->savings->findAccount($account);
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', 0);

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        if ($month < 0 || $month > 12) {
            $month = 0;
        }

        $statement = $this->savings->statement($model, $year, $month);

        return response()->view('savings.print.koran', [
            'title' => 'Cetak Rekening Koran '.$model->account_number,
            'account' => $statement['account'],
            'organization' => $this->organization(),
            'statement' => $statement,
        ]);
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
    private function organization(): array
    {
        $profile = OrganizationProfile::query()->first();

        return [
            'legal_name' => $profile?->legal_name ?? 'KOPERASI LEMBAGA KEUANGAN MIKRO',
            'short_name' => $profile?->short_name,
            'address' => $profile?->address,
            'district_name' => $profile?->district_name,
            'regency_name' => $profile?->regency_name,
            'phone' => $profile?->phone,
            'email' => $profile?->email,
            'website' => $profile?->website,
            'logo_path' => $profile?->logo_path,
        ];
    }

    private function userDisplay(int $transactionUserId): string
    {
        $current = auth()->user();
        $creator = $transactionUserId > 0 ? User::query()->find($transactionUserId) : null;

        $currentLabel = (string) ($current?->initials ?: $current?->name ?: '');
        $creatorLabel = (string) ($creator?->initials ?: $creator?->name ?: '');

        if ($creator === null || $creator->row_id === $current?->row_id) {
            return $currentLabel;
        }

        return $currentLabel.' / '.$creatorLabel;
    }
}
