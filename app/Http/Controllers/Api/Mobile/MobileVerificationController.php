<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Services\LoanService;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class MobileVerificationController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status'); // e.g. draft, proposed, verified
        $villageId = $request->query('village_id');
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);

        /** @var User|null $user */
        $user = $request->user();

        $query = Loan::query()
            ->with([
                'product:row_id,code,name',
                'borrower.group:row_id,name,address,organization_unit_row_id',
                'borrower.group.village:row_id,name',
                'borrower.member.person:row_id,full_name,phone',
                'borrower.member.village:row_id,name',
                'beneficiaries.member.person:row_id,full_name',
            ]);

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['draft', 'proposed', 'verified']);
        }

        // Scope to user's assigned village if restricted
        if ($user !== null && $user->isVillageUser() && $user->village_row_id !== null) {
            $query->whereHas('borrower.group', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id))
                ->orWhereHas('borrower.member', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id));
        }

        if ($villageId !== null && is_numeric($villageId)) {
            $query->where(function ($q) use ($villageId): void {
                $q->whereHas('borrower.group', fn ($g) => $g->where('organization_unit_row_id', (int) $villageId))
                    ->orWhereHas('borrower.member', fn ($m) => $m->where('organization_unit_row_id', (int) $villageId));
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('borrower.group', fn ($g) => $g->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('borrower.member.person', fn ($p) => $p->where('full_name', 'like', "%{$search}%")
                        ->orWhere('national_identity_number', 'like', "%{$search}%"));
            });
        }

        $proposals = $query->orderBy('row_id', 'desc')->paginate($perPage);

        $items = collect($proposals->items())->map(function (Loan $loan): array {
            $borrower = $loan->borrower;
            $group = $borrower?->group;
            $member = $borrower?->member;

            $borrowerName = $group !== null ? $group->name : ($member?->person?->full_name ?? 'Nasabah');
            $borrowerType = $group !== null ? 'Kelompok' : 'Individu';
            $villageName = $group?->village?->name ?? $member?->village?->name ?? '-';

            $totalProposed = (float) $loan->principal_amount;
            $verifiedSum = (float) $loan->beneficiaries->sum('verified_amount');

            return [
                'id' => (int) $loan->id,
                'row_id' => (int) $loan->row_id,
                'public_id' => $loan->public_id,
                'loan_number' => $loan->loan_number ?? "PROP-{$loan->id}",
                'status' => $loan->status,
                'product_name' => $loan->product?->name ?? 'Pinjaman Modal Usaha',
                'product_code' => $loan->product?->code ?? 'SPP',
                'borrower_type' => $borrowerType,
                'borrower_name' => $borrowerName,
                'village_name' => $villageName,
                'proposed_at' => $loan->proposed_at?->format('Y-m-d'),
                'proposed_amount' => $totalProposed,
                'verified_amount' => $verifiedSum > 0 ? $verifiedSum : $totalProposed,
                'term_months' => (int) $loan->term_months,
                'installment_method' => $loan->installment_method ?? 'flat',
                'beneficiary_count' => $loan->beneficiaries->count(),
                'has_survey' => $loan->status === 'verified' || ! empty($loan->verification_notes),
            ];
        });

        return ApiResponse::success($items->all(), 'Berhasil', 200, [
            'current_page' => $proposals->currentPage(),
            'last_page' => $proposals->lastPage(),
            'per_page' => $proposals->perPage(),
            'total' => $proposals->total(),
            'has_more' => $proposals->hasMorePages(),
        ]);
    }

    public function show(Request $request, string|int $loanId): JsonResponse
    {
        $loan = Loan::query()
            ->with([
                'product',
                'borrower.group.village',
                'borrower.member.person',
                'borrower.member.village',
                'committee.member.person',
                'beneficiaries.member.person',
                'statusHistories',
            ])
            ->where('row_id', $loanId)
            ->orWhere('id', $loanId)
            ->first();

        if ($loan === null) {
            return ApiResponse::error('Data usulan pinjaman tidak ditemukan.', 404);
        }

        $borrower = $loan->borrower;
        $group = $borrower?->group;
        $member = $borrower?->member;

        $borrowerName = $group !== null ? $group->name : ($member?->person?->full_name ?? 'Nasabah');
        $borrowerType = $group !== null ? 'Kelompok' : 'Individu';
        $villageName = $group?->village?->name ?? $member?->village?->name ?? '-';

        $committee = $loan->committee->map(fn ($c) => [
            'position' => $c->position,
            'member_row_id' => $c->member_row_id,
            'name' => $c->member?->person?->full_name ?? $c->member_name_snapshot ?? '-',
            'phone' => $c->member?->person?->phone ?? '-',
        ])->keyBy('position');

        $beneficiaries = $loan->beneficiaries->map(fn ($b) => [
            'member_row_id' => (int) $b->member_row_id,
            'member_id' => (int) ($b->member?->id ?? $b->member_row_id),
            'full_name' => $b->member?->person?->full_name ?? "Anggota #{$b->member_row_id}",
            'nik' => $b->member?->person?->national_identity_number ?? '-',
            'phone' => $b->member?->person?->phone ?? '-',
            'proposed_amount' => (float) ($b->proposed_amount ?? $b->allocated_amount),
            'verified_amount' => (float) ($b->verified_amount ?? $b->proposed_amount ?? $b->allocated_amount),
        ]);

        $default5C = [
            [
                'dimension' => 'Character (Karakter & Reputasi)',
                'description' => 'Integritas, kejujuran, dan rekam jejak hubungan sosial pemohon di desa.',
                'rating' => 5,
            ],
            [
                'dimension' => 'Capacity (Kapasitas & Usaha)',
                'description' => 'Kelangsungan usaha, omzet harian/bulanan, dan kemampuan mengangsur.',
                'rating' => 5,
            ],
            [
                'dimension' => 'Capital (Permodalan)',
                'description' => 'Kondisi modal sendiri, aset operasional, dan kas cadangan.',
                'rating' => 4,
            ],
            [
                'dimension' => 'Collateral / Guarantee (Jaminan/Tanggung Renteng)',
                'description' => 'Efektivitas sistem tanggung renteng kelompok atau jaminan kebendaan.',
                'rating' => 5,
            ],
            [
                'dimension' => 'Condition (Kondisi Pasar & Ekonomi)',
                'description' => 'Faktor persaingan pasar lokal, musiman, dan stabilitas usaha.',
                'rating' => 4,
            ],
        ];

        return ApiResponse::success([
            'id' => (int) $loan->id,
            'row_id' => (int) $loan->row_id,
            'public_id' => $loan->public_id,
            'loan_number' => $loan->loan_number ?? "PROP-{$loan->id}",
            'status' => $loan->status,
            'product' => [
                'row_id' => (int) $loan->product?->row_id,
                'code' => $loan->product?->code,
                'name' => $loan->product?->name,
                'interest_method' => $loan->product?->interest_method,
            ],
            'borrower_type' => $borrowerType,
            'borrower_name' => $borrowerName,
            'group_name' => $group?->name,
            'group_address' => $group?->address ?? '-',
            'village_name' => $villageName,
            'committee' => [
                'chair' => $committee->get('chair'),
                'secretary' => $committee->get('secretary'),
                'treasurer' => $committee->get('treasurer'),
            ],
            'proposed_at' => $loan->proposed_at?->format('Y-m-d'),
            'verified_at' => $loan->verified_at?->format('Y-m-d'),
            'principal_amount' => (float) $loan->principal_amount,
            'verified_amount' => (float) ($loan->beneficiaries->sum('verified_amount') ?: $loan->principal_amount),
            'term_months' => (int) $loan->term_months,
            'installment_method' => $loan->installment_method ?? 'flat',
            'principal_frequency' => $loan->principal_frequency ?? 'monthly',
            'interest_frequency' => $loan->interest_frequency ?? 'monthly',
            'verification_notes' => $loan->verification_notes,
            'guidance_notes' => $loan->guidance_notes,
            'beneficiaries' => $beneficiaries->all(),
            'evaluation_5c_guide' => $default5C,
        ]);
    }

    public function verify(Request $request, string|int $loanId, LoanService $loanService): JsonResponse
    {
        $loan = Loan::query()
            ->with(['beneficiaries'])
            ->where('row_id', $loanId)
            ->orWhere('id', $loanId)
            ->first();

        if ($loan === null) {
            return ApiResponse::error('Data usulan pinjaman tidak ditemukan.', 404);
        }

        if (! in_array($loan->status, ['draft', 'proposed', 'verified'], true)) {
            return ApiResponse::error("Pinjaman dengan status '{$loan->status}' tidak dapat diverifikasi lagi.", 422);
        }

        $validator = Validator::make($request->all(), [
            'verified_at' => ['required', 'date', 'before_or_equal:today'],
            'verification_amount' => ['nullable', 'numeric', 'min:0'],
            'verification_notes' => ['nullable', 'string', 'max:5000'],
            'verified_amounts' => ['nullable', 'array'],
            'verified_amounts.*' => ['numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'scoring_5c' => ['nullable', 'array'],
            'signature_base64' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi formulir verifikasi gagal.', 422, $validator->errors()->toArray());
        }

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->row_id;

        $verifiedAmounts = $request->input('verified_amounts', []);
        $totalVerified = ! empty($verifiedAmounts) && is_array($verifiedAmounts)
            ? (float) array_sum($verifiedAmounts)
            : (float) ($request->input('verification_amount') ?? $loan->principal_amount);

        // Build structured note including surveyor info, 5C scoring, and GPS location
        $notesParts = [];
        if (! empty($request->input('verification_notes'))) {
            $notesParts[] = trim((string) $request->input('verification_notes'));
        }

        $scoring5C = $request->input('scoring_5c');
        if (is_array($scoring5C) && ! empty($scoring5C)) {
            $notesParts[] = "[Skor 5C: Karakter {$scoring5C['character']}/5, Kapasitas {$scoring5C['capacity']}/5, Modal {$scoring5C['capital']}/5, Agunan {$scoring5C['collateral']}/5, Kondisi {$scoring5C['condition']}/5]";
        }

        $lat = $request->input('latitude');
        $lng = $request->input('longitude');
        if ($lat !== null && $lng !== null) {
            $notesParts[] = "[GeoTag: Lat {$lat}, Lng {$lng}]";
        }

        $combinedNotes = implode(' | ', $notesParts);

        $data = [
            'verified_at' => $request->input('verified_at'),
            'verification_amount' => $totalVerified,
            'verification_notes' => $combinedNotes !== '' ? $combinedNotes : "Diverifikasi oleh {$user->name} via Mobile",
            'verified_amounts' => $verifiedAmounts,
        ];

        try {
            $updatedLoan = $loanService->verify($loan, $data, $userId);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::success([
            'id' => (int) $updatedLoan->id,
            'row_id' => (int) $updatedLoan->row_id,
            'loan_number' => $updatedLoan->loan_number,
            'status' => $updatedLoan->status,
            'verified_at' => $updatedLoan->verified_at?->format('Y-m-d'),
            'verified_amount' => (float) $updatedLoan->beneficiaries->sum('verified_amount'),
            'verification_notes' => $updatedLoan->verification_notes,
        ], 'Hasil verifikasi dan survei lapangan berhasil disimpan.');
    }
}
