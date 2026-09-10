<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\AccountOpeningBalanceService;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Onboarding\Services\TenantOnboardingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AggregateJournalRequest;
use App\Http\Requests\Accounting\ManualOpeningBalanceRequest;
use App\Models\Platform\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantOnboardingImportController extends Controller
{
    public function index(Tenant $tenant, AccountOpeningBalanceService $openingService): Response
    {
        $accounts = Account::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type']);

        $existingOpening = JournalEntry::query()
            ->where(function ($q): void {
                $q->where('transaction_type', 'pemindahan_saldo')
                    ->orWhere('description', 'like', '%Opening Balances%')
                    ->orWhere('description', 'like', '%Saldo Awal%');
            })
            ->with(['lines.account:row_id,code,name'])
            ->latest('row_id')
            ->first();

        $currentYear = $openingService->currentFiscalYear();
        $manualOpeningsByYear = [];
        foreach ([$currentYear, $currentYear - 1, $currentYear + 1] as $year) {
            $rows = $openingService->getByYear($year);
            if ($rows !== []) {
                $manualOpeningsByYear[$year] = $rows;
            }
        }

        return Inertia::render('Onboarding/ImportWizard', [
            'tenantRowId' => (int) $tenant->row_id,
            'accounts' => $accounts,
            'existingOpening' => $existingOpening ? [
                'row_id' => $existingOpening->row_id,
                'as_of_date' => $existingOpening->transaction_date?->toDateString(),
                'reference' => $existingOpening->journal_number ?: ('SALDO-AWAL-'.$existingOpening->row_id),
                'amount' => (float) $existingOpening->lines->sum('debit'),
                'lines' => $existingOpening->lines->map(fn ($l) => [
                    'account_row_id' => $l->account_row_id,
                    'account_code' => $l->account?->code,
                    'account_name' => $l->account?->name,
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                ]),
            ] : null,
            'manualOpeningsByYear' => $manualOpeningsByYear,
            'currentFiscalYear' => $currentYear,
        ]);
    }

    public function saveOpeningBalances(Tenant $tenant, Request $request, TenantOnboardingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'as_of_date' => ['required', 'date', 'before_or_equal:today'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_row_id' => ['required', 'integer'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $userId = (int) $request->user()->row_id;
        $service->saveOpeningBalances($validated['lines'], (string) $validated['as_of_date'], $userId);

        return redirect()->back()->with('success', 'Jurnal Saldo Awal Keuangan tenant berhasil disimpan & diposting.');
    }

    /**
     * Simpan opening balance per-fiscal_year ke tabel `account_opening_balances`
     * dengan source='manual'. Idempotent; preserve 'year_close' (tidak overwrite).
     */
    public function saveManualOpening(
        Tenant $tenant,
        ManualOpeningBalanceRequest $request,
        AccountOpeningBalanceService $openingService,
    ): RedirectResponse {
        $userId = (int) $request->user()->row_id;
        $fiscalYear = (int) $request->validated('fiscal_year');
        $lines = $request->validated('lines');

        try {
            $written = $openingService->upsert($fiscalYear, $lines, $userId);
        } catch (\DomainException $e) {
            return back()->withErrors(['lines' => $e->getMessage()]);
        }

        $msg = $written > 0
            ? "Saldo awal tahun {$fiscalYear} berhasil disimpan ({$written} akun diupdate)."
            : "Saldo awal tahun {$fiscalYear} tidak berubah (tidak ada baris valid).";

        return back()->with('success', $msg);
    }

    /**
     * Posting jurnal agregat multi-line untuk backfill transaksi antar tanggal
     * opening balance dan tanggal join (mis. Jan-Mei jika join Juni).
     */
    public function saveAggregateJournal(
        Tenant $tenant,
        AggregateJournalRequest $request,
        JournalPostingService $poster,
    ): RedirectResponse {
        $data = $request->validated();
        $userId = (int) $request->user()->row_id;

        $entry = DB::connection('tenant')->transaction(function () use ($data, $userId): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $data['transaction_date'],
                'sequence_number' => 0,
                'source_type' => 'manual',
                'transaction_type' => 'pemindahan_saldo',
                'source_row_id' => null,
                'description' => $data['description'],
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $lineNumber = 1;
            foreach ($data['lines'] as $line) {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);
                if ($debit <= 0.0 && $credit <= 0.0) {
                    continue;
                }
                $entry->lines()->create([
                    'line_number' => $lineNumber++,
                    'account_row_id' => (int) $line['account_row_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }

            return $entry->fresh(['lines']);
        });

        try {
            $posted = $poster->post($entry, $userId);
        } catch (\DomainException $e) {
            return back()->withErrors(['lines' => 'Jurnal agregat gagal diposting: '.$e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Jurnal agregat 5-bulanan berhasil dicatat (#%d · %s · Rp total %s).',
            $posted->row_id,
            $posted->transaction_date?->toDateString() ?? '-',
            number_format((float) $posted->lines->sum('debit'), 0, ',', '.'),
        ));
    }

    public function importActiveLoans(Tenant $tenant, Request $request, TenantOnboardingService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $userId = (int) $request->user()->row_id;
        $result = $service->importActiveLoans($request->file('file'), $userId);

        $msg = sprintf('Berhasil mengimpor %d pinjaman aktif.', $result['imported']);
        if ($result['skipped'] > 0) {
            $msg .= sprintf(' (%d dilewati)', $result['skipped']);
        }

        $redirect = redirect()->back()->with('success', $msg);
        if ($result['errors'] !== []) {
            $redirect->with('warning', 'Beberapa baris memiliki kesalahan: '.implode(' | ', array_slice($result['errors'], 0, 5)));
        }

        return $redirect;
    }

    public function downloadTemplate(Tenant $tenant, string $type, TenantOnboardingService $service): StreamedResponse
    {
        return $service->downloadCsvTemplate($type);
    }
}
