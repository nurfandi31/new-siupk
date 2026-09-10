<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Accounting\Models\Account;
use App\Domain\Desktop\Services\UpdateManifestService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\Member;
use App\Domain\Sync\Services\DesktopPushApplyService;
use App\Models\Scopes\VillageScope;
use App\Models\User;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

final class MobileSyncController
{
    public function __construct(
        private readonly DesktopPushApplyService $pushService,
        private readonly TenantContext $context,
        private readonly UpdateManifestService $updateManifest,
    ) {}

    public function collection(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $since = trim((string) $request->query('since', ''));

        $validator = Validator::make(
            ['since' => $since],
            ['since' => ['nullable', 'date']],
            [],
            ['since' => 'waktu sinkron terakhir'],
        );

        if ($validator->fails()) {
            return ApiResponse::error('Parameter since tidak valid.', 422, $validator->errors()->toArray());
        }

        $sinceCarbon = $since === '' ? null : Carbon::parse($since);
        $query = Loan::query()
            ->with([
                'product:row_id,code,name',
                'borrower.group:row_id,name,address,organization_unit_row_id',
                'borrower.group.village:row_id,name',
                'borrower.member.person:row_id,full_name,phone',
                'borrower.member.village:row_id,name',
                'installments',
            ])
            ->whereIn('status', ['active', 'disbursed']);

        if ($user !== null && $user->isVillageUser() && $user->village_row_id !== null) {
            $query->whereHas('borrower.group', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id))
                ->orWhereHas('borrower.member', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id));
        }

        if ($sinceCarbon !== null) {
            $query->where(function ($q) use ($sinceCarbon): void {
                $q->where('updated_at', '>', $sinceCarbon)
                    ->orWhere('created_at', '>', $sinceCarbon);
            });
        }

        $loans = $query->orderBy('row_id')->get();
        $memberIds = $loans->flatMap(function (Loan $loan) {
            $ids = collect($loan->borrower?->member_row_id !== null ? [$loan->borrower->member_row_id] : []);

            return $ids->merge($loan->beneficiaries->pluck('member_row_id'));
        })
            ->filter()->unique()->values();

        $members = Member::on('tenant')->with('person:row_id,full_name,phone')
            ->withoutGlobalScope(VillageScope::class)
            ->whereIn('row_id', $memberIds)
            ->get(['row_id', 'id', 'person_row_id', 'member_number', 'organization_unit_row_id']);

        $accounts = Account::on('tenant')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', '1.1.01.%')
            ->orderBy('code')
            ->get(['row_id', 'id', 'code', 'name']);

        $generatedAt = now()->toIso8601String();

        return ApiResponse::success([
            'generated_at' => $generatedAt,
            'synced_at' => $generatedAt,
            'loans' => $loans->map(fn (Loan $loan): array => $this->loanPayload($loan))->all(),
            'installments' => $loans->flatMap(fn (Loan $loan) => $loan->installments)->values()->all(),
            'members' => $members->map(fn (Member $member): array => [
                'id' => (int) $member->id,
                'row_id' => (int) $member->row_id,
                'member_number' => $member->member_number,
                'name' => $member->person?->full_name,
                'phone' => $member->person?->phone,
            ])->all(),
            'accounts' => $accounts->map(fn (Account $account): array => [
                'id' => (int) $account->id,
                'row_id' => (int) $account->row_id,
                'code' => $account->code,
                'name' => $account->name,
            ])->all(),
        ], 'Data penagihan offline berhasil dimuat.');
    }

    public function push(Request $request): JsonResponse
    {
        if ($this->updateManifest->outdated($request->header('X-App-Version'))) {
            return response()->json([
                'status' => 'error',
                'code' => 'CLIENT_OUTDATED',
                'min_supported_version' => (string) config('desktop-update.min_version'),
            ], 426);
        }

        $validated = $request->validate([
            'mutations' => ['required', 'array', 'max:200'],
            'mutations.*.mutation_uuid' => ['required', 'uuid'],
            'mutations.*.table_name' => ['required', 'string', 'max:100'],
            'mutations.*.operation' => ['required', 'string', 'in:insert,update,delete'],
            'mutations.*.row_public_id' => ['required', 'integer'],
            'mutations.*.payload' => ['required', 'array'],
            'mutations.*.client_updated_at' => ['nullable', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->pushService->applyWithPolicy(
            $this->context->tenant(),
            $validated['mutations'],
            null,
            DesktopPushApplyService::mobileTableOperations(),
            (int) $user->row_id,
        );

        return ApiResponse::success($result, 'Sinkronisasi mobile selesai.');
    }

    private function loanPayload(Loan $loan): array
    {
        return [
            'id' => (int) $loan->id,
            'row_id' => (int) $loan->row_id,
            'loan_number' => $loan->loan_number,
            'status' => $loan->status,
            'principal_amount' => (float) $loan->principal_amount,
            'product_name' => $loan->product?->name,
            'product_code' => $loan->product?->code,
            'updated_at' => $loan->updated_at?->toIso8601String(),
            'created_at' => $loan->created_at?->toIso8601String(),
            'borrower' => [
                'group_id' => $loan->borrower?->group?->id,
                'group_name' => $loan->borrower?->group?->name,
                'member_id' => $loan->borrower?->member?->id,
                'member_name' => $loan->borrower?->member?->person?->full_name,
            ],
            'beneficiaries' => $loan->beneficiaries->map(fn ($beneficiary): array => [
                'id' => (int) $beneficiary->id,
                'member_row_id' => (int) $beneficiary->member_row_id,
                'allocated_amount' => (float) $beneficiary->allocated_amount,
            ])->all(),
            'installments' => $loan->installments->map(fn ($installment): array => $installment->toArray())->all(),
        ];
    }
}
