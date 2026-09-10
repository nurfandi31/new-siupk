<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\Group;
use App\Services\TenantSettingService;
use App\Services\WhatsappGatewayService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tagihan batch + notifikasi angsuran (group phone → random beneficiary exclude payer).
 */
final class WhatsappNotificationService
{
    public function __construct(
        private WhatsappGatewayService $gateway,
        private TenantSettingService $settings,
        private TenantContext $context,
    ) {}

    /**
     * Pinjaman aktif dengan angsuran belum lunas pada due_date.
     *
     * @return list<array{
     *     loan_row_id:int,
     *     installment_row_id:int,
     *     installment_number:int,
     *     loan_number:?string,
     *     borrower:string,
     *     due_date:string,
     *     amount:float,
     *     principal:float,
     *     interest:float,
     *     penalty:float,
     *     phone:?string,
     *     phone_source:string,
     *     can_send:bool
     * }>
     */
    public function dueOn(CarbonImmutable $dueDate): array
    {
        $tenantId = $this->context->id();
        $date = $dueDate->toDateString();

        $rows = DB::connection('tenant')
            ->table('loan_installments as i')
            ->join('loans as l', function ($join): void {
                $join->on('l.tenant_id', '=', 'i.tenant_id')
                    ->on('l.row_id', '=', 'i.loan_row_id');
            })
            ->leftJoin('loan_borrowers as b', function ($join): void {
                $join->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($join): void {
                $join->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('members as m', function ($join): void {
                $join->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('people as p', function ($join): void {
                $join->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->where('i.tenant_id', $tenantId)
            ->whereIn('l.status', ['active', 'disbursed'])
            ->where('i.due_date', $date)
            ->whereRaw('(i.principal_due + i.interest_due + i.penalty_due) > (i.principal_paid + i.interest_paid + i.penalty_paid)')
            ->orderBy('g.name')
            ->orderBy('l.loan_number')
            ->get([
                'i.row_id as installment_row_id',
                'i.installment_number',
                'i.due_date',
                'i.principal_due',
                'i.interest_due',
                'i.penalty_due',
                'i.principal_paid',
                'i.interest_paid',
                'i.penalty_paid',
                'l.row_id as loan_row_id',
                'l.loan_number',
                'g.row_id as group_row_id',
                'g.name as group_name',
                'g.phone as group_phone',
                'p.full_name as member_name',
                'p.phone as member_phone',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $principal = max(0, (float) $row->principal_due - (float) $row->principal_paid);
            $interest = max(0, (float) $row->interest_due - (float) $row->interest_paid);
            $penalty = max(0, (float) $row->penalty_due - (float) $row->penalty_paid);
            $amount = round($principal + $interest + $penalty, 2);

            $phone = null;
            $source = 'none';
            $groupPhone = $this->gateway->normalizePhone((string) ($row->group_phone ?? ''));
            if ($groupPhone !== '') {
                $phone = $groupPhone;
                $source = 'group';
            } else {
                $memberPhone = $this->gateway->normalizePhone((string) ($row->member_phone ?? ''));
                if ($memberPhone !== '') {
                    $phone = $memberPhone;
                    $source = 'member';
                } else {
                    $fallback = $this->randomBeneficiaryPhone((int) $row->loan_row_id, excludeMemberId: null);
                    if ($fallback !== null) {
                        $phone = $fallback['phone'];
                        $source = 'beneficiary';
                    }
                }
            }

            $out[] = [
                'loan_row_id' => (int) $row->loan_row_id,
                'installment_row_id' => (int) $row->installment_row_id,
                'installment_number' => (int) $row->installment_number,
                'loan_number' => $row->loan_number,
                'borrower' => (string) ($row->group_name ?: $row->member_name ?: '—'),
                'due_date' => (string) $row->due_date,
                'amount' => $amount,
                'principal' => round($principal, 2),
                'interest' => round($interest, 2),
                'penalty' => round($penalty, 2),
                'phone' => $phone,
                'phone_source' => $source,
                'can_send' => $phone !== null && $phone !== '',
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $installmentRowIds
     * @return array{sent:int,failed:int,skipped:int,results:list<array{installment_row_id:int,success:bool,message:string,phone:?string}>}
     */
    public function sendBilling(array $installmentRowIds, CarbonImmutable $dueDate): array
    {
        $wanted = array_fill_keys(array_map('intval', $installmentRowIds), true);
        $items = array_values(array_filter(
            $this->dueOn($dueDate),
            fn (array $row): bool => isset($wanted[$row['installment_row_id']]),
        ));

        $template = (string) ($this->settings->get('whatsapp.template_billing', '') ?: $this->defaultBillingTemplate());
        $results = [];
        $sent = $failed = $skipped = 0;

        $toSend = [];
        foreach ($items as $item) {
            if (! $item['can_send'] || $item['phone'] === null) {
                $skipped++;
                $results[] = [
                    'installment_row_id' => $item['installment_row_id'],
                    'success' => false,
                    'message' => 'Tidak ada nomor HP.',
                    'phone' => null,
                ];

                continue;
            }

            $message = $this->render($template, [
                'nama' => $item['borrower'],
                'angsuran_ke' => (string) $item['installment_number'],
                'total' => $this->formatMoney($item['amount']),
                'pokok' => $this->formatMoney($item['principal']),
                'jasa' => $this->formatMoney($item['interest']),
                'denda' => $this->formatMoney($item['penalty']),
                'tanggal' => $this->formatDate($item['due_date']),
                'pinjaman' => (string) ($item['loan_number'] ?: '#'.$item['loan_row_id']),
                'produk' => '',
            ]);

            $toSend[] = [
                'item' => $item,
                'payload' => ['number' => $item['phone'], 'text' => $message],
            ];
        }

        if (count($toSend) > 1) {
            $bulkPayload = array_column($toSend, 'payload');
            $bulkRes = $this->gateway->sendMessages($bulkPayload, $this->gateway->resolveActiveInstance());
            if ($bulkRes['success']) {
                $sent += count($toSend);
                foreach ($toSend as $entry) {
                    $results[] = [
                        'installment_row_id' => $entry['item']['installment_row_id'],
                        'success' => true,
                        'message' => 'Pesan massal terkirim.',
                        'phone' => $entry['item']['phone'],
                    ];
                }
            } else {
                foreach ($toSend as $entry) {
                    $res = $this->gateway->sendText($entry['item']['phone'], $entry['payload']['text']);
                    if ($res['success']) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                    $results[] = [
                        'installment_row_id' => $entry['item']['installment_row_id'],
                        'success' => $res['success'],
                        'message' => $res['message'],
                        'phone' => $entry['item']['phone'],
                    ];
                }
            }
        } elseif (count($toSend) === 1) {
            $entry = $toSend[0];
            $res = $this->gateway->sendText($entry['item']['phone'], $entry['payload']['text']);
            if ($res['success']) {
                $sent++;
            } else {
                $failed++;
            }
            $results[] = [
                'installment_row_id' => $entry['item']['installment_row_id'],
                'success' => $res['success'],
                'message' => $res['message'],
                'phone' => $entry['item']['phone'],
            ];
        }

        return compact('sent', 'failed', 'skipped', 'results');
    }

    /**
     * Setelah angsuran berhasil: group phone → else random beneficiary exclude payer.
     *
     * @return array{sent:bool,phone:?string,message:string}|null null jika gateway off
     */
    public function notifyInstallmentPayment(
        Loan $loan,
        int $payerMemberRowId,
        float $principal,
        float $interest,
        float $penalty,
        string $transactionDate,
        ?int $installmentNumber = null,
    ): ?array {
        if (! $this->gateway->isConfigured() || ! $this->gateway->isEnabled()) {
            return null;
        }

        $loan->loadMissing(['borrower.group', 'product']);
        $group = $loan->borrower?->group;
        $target = $this->resolveInstallmentRecipient($loan, $group, $payerMemberRowId);

        if ($target === null) {
            return [
                'sent' => false,
                'phone' => null,
                'message' => 'Tidak ada nomor HP kelompok/pemanfaat untuk notifikasi.',
            ];
        }

        $total = round($principal + $interest + $penalty, 2);
        $template = (string) ($this->settings->get('whatsapp.template_installment', '') ?: $this->defaultInstallmentTemplate());
        $message = $this->render($template, [
            'nama' => $target['name'],
            'angsuran_ke' => $installmentNumber ? (string) $installmentNumber : '-',
            'total' => $this->formatMoney($total),
            'pokok' => $this->formatMoney($principal),
            'jasa' => $this->formatMoney($interest),
            'denda' => $this->formatMoney($penalty),
            'tanggal' => $this->formatDate($transactionDate),
            'pinjaman' => (string) ($loan->loan_number ?: '#'.$loan->row_id),
            'produk' => (string) ($loan->product?->name ?? ''),
            'penyetor' => $this->memberName($payerMemberRowId) ?? '-',
        ]);

        try {
            $result = $this->gateway->sendText($target['phone'], $message);

            return [
                'sent' => $result['success'],
                'phone' => $target['phone'],
                'message' => $result['message'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Installment WhatsApp notify failed', [
                'tenant_id' => $this->context->id(),
                'loan_row_id' => $loan->row_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'phone' => $target['phone'],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{phone:string,name:string}|null
     */
    private function resolveInstallmentRecipient(Loan $loan, ?Group $group, int $payerMemberRowId): ?array
    {
        $groupPhone = $this->gateway->normalizePhone((string) ($group?->phone ?? ''));
        if ($groupPhone !== '') {
            return [
                'phone' => $groupPhone,
                'name' => (string) ($group?->name ?: 'Kelompok'),
            ];
        }

        return $this->randomBeneficiaryPhone((int) $loan->row_id, $payerMemberRowId);
    }

    /**
     * @return array{phone:string,name:string}|null
     */
    private function randomBeneficiaryPhone(int $loanRowId, ?int $excludeMemberId): ?array
    {
        $tenantId = $this->context->id();

        $query = DB::connection('tenant')
            ->table('loan_beneficiaries as lb')
            ->join('members as m', function ($join) use ($tenantId): void {
                $join->on('m.row_id', '=', 'lb.member_row_id')
                    ->where('m.tenant_id', '=', $tenantId);
            })
            ->join('people as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'm.person_row_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('lb.tenant_id', $tenantId)
            ->where('lb.loan_row_id', $loanRowId)
            ->whereNotNull('p.phone')
            ->where('p.phone', '!=', '');

        if ($excludeMemberId !== null && $excludeMemberId > 0) {
            $query->where('lb.member_row_id', '!=', $excludeMemberId);
        }

        /** @var Collection<int, object> $candidates */
        $candidates = $query->get(['lb.member_row_id', 'p.full_name', 'p.phone']);
        if ($candidates->isEmpty()) {
            return null;
        }

        $pick = $candidates->random();
        $phone = $this->gateway->normalizePhone((string) $pick->phone);
        if ($phone === '') {
            return null;
        }

        return [
            'phone' => $phone,
            'name' => (string) $pick->full_name,
        ];
    }

    private function memberName(int $memberRowId): ?string
    {
        if ($memberRowId <= 0) {
            return null;
        }

        $name = DB::connection('tenant')
            ->table('members as m')
            ->join('people as p', function ($join): void {
                $join->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->where('m.tenant_id', $this->context->id())
            ->where('m.row_id', $memberRowId)
            ->value('p.full_name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function render(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{'.$key.'}', $value, $out);
        }

        return trim($out);
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function formatDate(string $ymd): string
    {
        try {
            return CarbonImmutable::parse($ymd)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private function defaultBillingTemplate(): string
    {
        return 'Yth. Bapak/Ibu {nama}, tagihan angsuran ke-{angsuran_ke} pinjaman {pinjaman} sebesar Rp {total} jatuh tempo {tanggal}. Mohon segera diselesaikan. Terima kasih.';
    }

    private function defaultInstallmentTemplate(): string
    {
        return 'Terima kasih. Pembayaran angsuran ke-{angsuran_ke} a/n {penyetor} (kelompok/pinjaman {pinjaman}) sebesar Rp {total} telah diterima pada {tanggal}.';
    }
}
