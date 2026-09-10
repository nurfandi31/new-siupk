<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanPayment;
use App\Models\Platform\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class NotificationCenterController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['unread_count' => 0, 'items' => []]);
        }

        $readIds = is_array($user->notifications_read)
            ? $user->notifications_read
            : (array) ($request->session()->get('notifications_read', []));

        $items = [];
        $today = CarbonImmutable::today();

        try {
            // 1. Platform Invoices (Subscription & Service Fees)
            $tenant = $user->tenant;
            if ($tenant !== null) {
                $unpaidInvoices = Invoice::query()
                    ->where('tenant_id', $tenant->row_id)
                    ->whereIn('status', ['issued', 'pending_payment', 'overdue'])
                    ->orderByRaw("CASE WHEN status = 'overdue' THEN 0 ELSE 1 END")
                    ->oldest('due_at')
                    ->get();

                if ($unpaidInvoices->isNotEmpty()) {
                    $latestInvoice = $unpaidInvoices->first();
                    $isOverdue = $latestInvoice->status === 'overdue' || ($latestInvoice->due_at && $latestInvoice->due_at->isPast());
                    $isBlocking = (bool) $latestInvoice->blocks_access;
                    $targetUrl = "/billing/invoices/{$latestInvoice->row_id}";

                    $id = 'tenant_invoice_'.$latestInvoice->row_id;
                    $title = $isBlocking
                        ? "Tagihan #{$latestInvoice->number} (Akses Terkunci)"
                        : ($isOverdue ? "Tagihan Overdue #{$latestInvoice->number}" : 'Tagihan Menunggu Pembayaran');

                    $items[] = [
                        'id' => $id,
                        'type' => 'invoice',
                        'title' => $title,
                        'message' => $isBlocking
                            ? 'Akses operasional ditangguhkan hingga tagihan sebesar Rp '.number_format((float) $latestInvoice->remainingAmount(), 0, ',', '.').' dilunasi.'
                            : 'Tagihan Rp '.number_format((float) $latestInvoice->remainingAmount(), 0, ',', '.').($latestInvoice->due_at ? ' jatuh tempo '.$latestInvoice->due_at->format('d M Y') : '').'.',
                        'time' => $isBlocking ? 'Wajib Lunas' : ($isOverdue ? 'Overdue' : ($latestInvoice->due_at?->diffForHumans() ?? 'Perlu Tindakan')),
                        'target_url' => $targetUrl,
                        'icon' => $isBlocking ? 'lock' : 'receipt_long',
                        'variant' => ($isBlocking || $isOverdue) ? 'danger' : 'warning',
                        'read' => in_array($id, $readIds, true),
                        'actor' => 'Admin Platform',
                    ];
                }
            }

            // 2. Proposed loans needing verification / approval
            $proposedLoans = Loan::query()
                ->with(['borrower.group', 'borrower.member.person'])
                ->where('status', 'proposed')
                ->latest('row_id')
                ->take(3)
                ->get();

            if ($proposedLoans->isNotEmpty()) {
                $userIds = $proposedLoans->pluck('created_by_user_id')->filter()->unique()->values()->all();
                $userMap = ! empty($userIds) ? User::query()->whereIn('row_id', $userIds)->pluck('name', 'row_id')->all() : [];

                foreach ($proposedLoans as $proposal) {
                    $borrowerName = $proposal->borrower?->group?->name
                        ?? $proposal->borrower?->member?->person?->full_name
                        ?? 'Kelompok';

                    $creatorName = ($proposal->created_by_user_id && isset($userMap[$proposal->created_by_user_id]))
                        ? $userMap[$proposal->created_by_user_id]
                        : 'Petugas Lapangan';

                    $id = 'loan_proposed_'.$proposal->row_id;
                    $items[] = [
                        'id' => $id,
                        'type' => 'loan_proposed',
                        'title' => 'Proposal: '.$borrowerName,
                        'message' => 'Pengajuan pinjaman Rp '.number_format((float) ($proposal->proposed_amount ?? $proposal->principal_amount), 0, ',', '.').' menunggu verifikasi & persetujuan.',
                        'time' => $proposal->proposed_at?->diffForHumans() ?? 'Perlu Tindakan',
                        'target_url' => "/lending/loans/{$proposal->row_id}",
                        'icon' => 'assignment_late',
                        'variant' => 'warning',
                        'read' => in_array($id, $readIds, true),
                        'actor' => $creatorName,
                    ];
                }
            }

            // 3. Overdue loan installments
            $overdueInstallments = LoanInstallment::query()
                ->with(['loan.borrower.group', 'loan.borrower.member.person'])
                ->where('status', 'pending')
                ->where('due_date', '<', $today->toDateString())
                ->latest('due_date')
                ->take(3)
                ->get();

            if ($overdueInstallments->isNotEmpty()) {
                foreach ($overdueInstallments as $inst) {
                    $borrowerName = $inst->loan?->borrower?->group?->name
                        ?? $inst->loan?->borrower?->member?->person?->full_name
                        ?? 'Peminjam';

                    $instAmount = (float) $inst->principal_due + (float) $inst->interest_due;
                    $loanRowId = $inst->loan_row_id ?? $inst->loan?->row_id;

                    $id = 'installment_overdue_'.$inst->row_id;
                    $items[] = [
                        'id' => $id,
                        'type' => 'loan_overdue',
                        'title' => 'Tunggakan: '.$borrowerName,
                        'message' => 'Angsuran ke-'.$inst->installment_number.' sebesar Rp '.number_format($instAmount, 0, ',', '.').' melewati jatuh tempo.',
                        'time' => $inst->due_date?->diffForHumans() ?? 'Terlambat',
                        'target_url' => $loanRowId ? "/lending/loans/{$loanRowId}" : '/lending/loans',
                        'icon' => 'warning',
                        'variant' => 'danger',
                        'read' => in_array($id, $readIds, true),
                        'actor' => null, // Delinquency is system alert, not an actor action
                    ];
                }
            }

            // 4. Recent loan payments recorded by users
            $recentPayments = LoanPayment::query()
                ->with(['loan.borrower.group', 'loan.borrower.member.person'])
                ->latest('row_id')
                ->take(3)
                ->get();

            if ($recentPayments->isNotEmpty()) {
                $userIds = $recentPayments->pluck('created_by_user_id')->filter()->unique()->values()->all();
                $userMap = ! empty($userIds) ? User::query()->whereIn('row_id', $userIds)->pluck('name', 'row_id')->all() : [];

                foreach ($recentPayments as $payment) {
                    $borrowerName = $payment->loan?->borrower?->group?->name
                        ?? $payment->loan?->borrower?->member?->person?->full_name
                        ?? 'Peminjam';
                    $recorderName = ($payment->created_by_user_id && isset($userMap[$payment->created_by_user_id]))
                        ? $userMap[$payment->created_by_user_id]
                        : 'Kasir';

                    $loanRowId = $payment->loan_row_id ?? $payment->loan?->row_id;
                    $id = 'payment_recent_'.$payment->row_id;

                    $items[] = [
                        'id' => $id,
                        'type' => 'payment_activity',
                        'title' => 'Penerimaan Angsuran: '.$borrowerName,
                        'message' => 'Pembayaran angsuran Rp '.number_format((float) $payment->amount, 0, ',', '.').' dicatat oleh '.$recorderName.'.',
                        'time' => $payment->created_at?->diffForHumans() ?? 'Baru saja',
                        'target_url' => $loanRowId ? "/lending/loans/{$loanRowId}" : '/lending/loans',
                        'icon' => 'payments',
                        'variant' => 'success',
                        'read' => in_array($id, $readIds, true),
                        'actor' => $recorderName,
                    ];
                }
            }

            // 5. Recent journal entries created by users
            $recentJournals = JournalEntry::query()
                ->latest('row_id')
                ->take(3)
                ->get();

            if ($recentJournals->isNotEmpty()) {
                $userIds = $recentJournals->pluck('created_by_user_id')->filter()->unique()->values()->all();
                $userMap = ! empty($userIds) ? User::query()->whereIn('row_id', $userIds)->pluck('name', 'row_id')->all() : [];

                foreach ($recentJournals as $journal) {
                    $creatorName = ($journal->created_by_user_id && isset($userMap[$journal->created_by_user_id]))
                        ? $userMap[$journal->created_by_user_id]
                        : 'Petugas Akuntansi';

                    $id = 'journal_recent_'.$journal->row_id;
                    $journalNumber = $journal->journal_number ?: 'Umum';
                    $targetUrl = '/accounting/journals?q='.urlencode((string) $journalNumber);

                    $items[] = [
                        'id' => $id,
                        'type' => 'journal_activity',
                        'title' => 'Jurnal: '.$journalNumber,
                        'message' => sprintf('Jurnal %s (%s) dicatat oleh %s.', $journalNumber, $journal->description ?: 'Transaksi Operasional', $creatorName),
                        'time' => $journal->created_at?->diffForHumans() ?? 'Baru saja',
                        'target_url' => $targetUrl,
                        'icon' => 'receipt_long',
                        'variant' => 'info',
                        'read' => in_array($id, $readIds, true),
                        'actor' => $creatorName,
                    ];
                }
            }

            // Default welcome/info notification if empty
            if (empty($items)) {
                $id = 'system_status_ok';
                $items[] = [
                    'id' => $id,
                    'type' => 'system',
                    'title' => 'Operasional Normal',
                    'message' => 'Tidak ada tunggakan atau pengajuan tertunda yang membutuhkan tindakan saat ini.',
                    'time' => 'Hari ini',
                    'target_url' => '/dashboard',
                    'icon' => 'check_circle',
                    'variant' => 'success',
                    'read' => in_array($id, $readIds, true),
                    'actor' => null,
                ];
            }
        } catch (Throwable) {
            $id = 'system_info';
            $items[] = [
                'id' => $id,
                'type' => 'system',
                'title' => 'Sistem Informasi SIUPK',
                'message' => 'Selamat datang di Sistem Informasi Dana Bergulir Masyarakat.',
                'time' => 'Informasi',
                'target_url' => '/dashboard',
                'icon' => 'info',
                'variant' => 'info',
                'read' => in_array($id, $readIds, true),
                'actor' => null,
            ];
        }

        $unreadCount = count(array_filter($items, fn (array $item): bool => ! $item['read']));

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $id = $request->input('id');
        $ids = $request->input('ids');

        $readIds = [];
        if ($user !== null && is_array($user->notifications_read)) {
            $readIds = $user->notifications_read;
        } else {
            $readIds = (array) ($request->session()->get('notifications_read', []));
        }

        if (is_array($ids)) {
            foreach ($ids as $singleId) {
                if (is_string($singleId) && $singleId !== '' && ! in_array($singleId, $readIds, true)) {
                    $readIds[] = $singleId;
                }
            }
        } elseif (is_string($id) && $id !== '') {
            if (! in_array($id, $readIds, true)) {
                $readIds[] = $id;
            }
        } else {
            $allIds = ['system_status_ok', 'system_info'];
            $readIds = array_unique(array_merge($readIds, $allIds));
        }

        $readIds = array_values(array_unique($readIds));

        if ($user !== null) {
            $user->notifications_read = $readIds;
            $user->save();
        }

        $request->session()->put('notifications_read', $readIds);

        return response()->json(['success' => true]);
    }
}
