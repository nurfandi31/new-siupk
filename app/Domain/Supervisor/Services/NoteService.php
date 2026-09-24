<?php

declare(strict_types=1);

namespace App\Domain\Supervisor\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Supervisor\Models\SupervisorNote;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Service untuk CRUD catatan pengawas.
 *
 * Sumber data:
 *   - Tabel `supervisor_notes` (tenant connection) — catatan yang ditulis pengawas
 *   - Tabel `reports` — daftar laporan yang sudah disetujui pengawas untuk ditampilkan di Index
 */
final class NoteService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   period: array<string,mixed>,
     *   identity: array<string,mixed>,
     *   notes: array<int, array<string,mixed>>,
     *   paginator: array<string,mixed>,
     *   filters: array<string,mixed>,
     *   reports: list<array<string,mixed>>
     * }
     */
    public function index(int $year, ?int $month, ?string $category, ?string $status, int $page): array
    {
        $tenantId = (int) ($this->context->id() ?? 0);

        $query = SupervisorNote::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('row_id');

        if ($year > 0) {
            $query->where('period_year', $year);
        }
        if ($month !== null) {
            $query->where('period_month', $month);
        }
        if ($category !== null && $category !== '' && in_array($category, SupervisorNote::CATEGORIES, true)) {
            $query->where('category', $category);
        }
        if ($status !== null && $status !== '' && in_array($status, [
            SupervisorNote::STATUS_DRAFT,
            SupervisorNote::STATUS_SUBMITTED,
            SupervisorNote::STATUS_ACKNOWLEDGED,
        ], true)) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate(perPage: 20, page: $page);

        $notes = collect($paginator->items())->map(function (SupervisorNote $n): array {
            $supervisor = $n->supervisor;
            $ack = $n->acknowledger;

            return [
                'row_id' => (int) $n->row_id,
                'public_id' => (string) ($n->public_id ?? ''),
                'period_year' => (int) $n->period_year,
                'period_month' => $n->period_month !== null ? (int) $n->period_month : null,
                'period_label' => $this->periodLabel((int) $n->period_year, $n->period_month !== null ? (int) $n->period_month : null),
                'category' => (string) $n->category,
                'category_label' => $n->categoryLabel(),
                'subject' => (string) $n->subject,
                'content_preview' => mb_strimwidth((string) $n->content, 0, 140, '…'),
                'status' => (string) $n->status,
                'status_label' => $n->statusLabel(),
                'submitted_at' => $n->submitted_at?->toDateTimeString(),
                'acknowledged_at' => $n->acknowledged_at?->toDateTimeString(),
                'supervisor' => $supervisor ? [
                    'row_id' => (int) $supervisor->row_id,
                    'name' => trim(($supervisor->name ?? '') !== '' ? (string) $supervisor->name : (string) ($supervisor->phone ?? '—')),
                ] : null,
                'acknowledger' => $ack ? [
                    'row_id' => (int) $ack->row_id,
                    'name' => trim(($ack->name ?? '') !== '' ? (string) $ack->name : (string) ($ack->phone ?? '—')),
                ] : null,
            ];
        })->values()->all();

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'period_label' => $this->periodLabel($year, $month),
            ],
            'identity' => $this->identityPayload(),
            'notes' => $notes,
            'paginator' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'year' => $year,
                'month' => $month !== null ? (string) $month : 'all',
                'category' => $category ?? 'all',
                'status' => $status ?? 'all',
            ],
            'reports' => $this->approvedReports($tenantId),
        ];
    }

    /**
     * @return array{
     *   note: array<string,mixed>,
     *   identity: array<string,mixed>,
     *   categories: array<string,string>,
     *   statuses: array<string,string>,
     *   can: array<string,bool>
     * }
     */
    public function show(int $id, ?User $actor): array
    {
        $note = $this->resolveNote($id);

        return [
            'note' => $this->notePayload($note),
            'identity' => $this->identityPayload(),
            'categories' => SupervisorNote::CATEGORY_LABELS,
            'statuses' => [
                SupervisorNote::STATUS_DRAFT => 'Draft',
                SupervisorNote::STATUS_SUBMITTED => 'Disubmit',
                SupervisorNote::STATUS_ACKNOWLEDGED => 'Disetujui Manajemen',
            ],
            'can' => [
                'edit' => $this->canEdit($note, $actor),
                'acknowledge' => $this->canAcknowledge($note, $actor),
                'submit' => $this->canSubmit($note, $actor),
                'delete' => $this->canEdit($note, $actor),
            ],
        ];
    }

    /**
     * Identity payload for the create form (no note to resolve).
     *
     * @return array<string,mixed>
     */
    public function identityForCreate(): array
    {
        return $this->identityPayload();
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{note: array<string,mixed>}
     */
    public function create(array $input, User $actor): array
    {
        $validated = $this->validate($input);

        $note = SupervisorNote::query()->create([
            'supervisor_user_id' => (int) $actor->row_id,
            'period_year' => (int) $validated['period_year'],
            'period_month' => $validated['period_month'] ?? null,
            'category' => (string) $validated['category'],
            'subject' => (string) $validated['subject'],
            'content' => (string) $validated['content'],
            'status' => SupervisorNote::STATUS_DRAFT,
        ]);

        return ['note' => $this->notePayload($note->refresh())];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{note: array<string,mixed>}
     */
    public function update(int $id, array $input, ?User $actor): array
    {
        $note = $this->resolveNote($id);
        $this->assertCanEdit($note, $actor);

        $validated = $this->validate($input);

        $note->forceFill([
            'period_year' => (int) $validated['period_year'],
            'period_month' => $validated['period_month'] ?? null,
            'category' => (string) $validated['category'],
            'subject' => (string) $validated['subject'],
            'content' => (string) $validated['content'],
        ])->save();

        return ['note' => $this->notePayload($note->refresh())];
    }

    /**
     * @return array{note: array<string,mixed>}
     */
    public function submit(int $id, ?User $actor): array
    {
        $note = $this->resolveNote($id);
        $this->assertCanSubmit($note, $actor);

        $note->forceFill([
            'status' => SupervisorNote::STATUS_SUBMITTED,
            'submitted_at' => CarbonImmutable::now(),
        ])->save();

        return ['note' => $this->notePayload($note->refresh())];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{note: array<string,mixed>}
     */
    public function acknowledge(int $id, array $input, ?User $actor): array
    {
        $note = $this->resolveNote($id);
        $this->assertCanAcknowledge($note, $actor);

        $validated = validator($input, [
            'acknowledgment_note' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $note->forceFill([
            'status' => SupervisorNote::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => CarbonImmutable::now(),
            'acknowledged_by_user_id' => (int) $actor->row_id,
            'acknowledgment_note' => $validated['acknowledgment_note'] ?? null,
        ])->save();

        return ['note' => $this->notePayload($note->refresh())];
    }

    /**
     * @return array{deleted:int}
     */
    public function delete(int $id, ?User $actor): array
    {
        $note = $this->resolveNote($id);
        $this->assertCanEdit($note, $actor);

        $note->delete();

        return ['deleted' => 1];
    }

    /* ------------------------------------------------------------------ */
    /*                             Helpers */
    /* ------------------------------------------------------------------ */

    private function resolveNote(int $id): SupervisorNote
    {
        $note = SupervisorNote::query()->find($id);
        if ($note === null) {
            throw new DomainException("Catatan pengawas #{$id} tidak ditemukan.");
        }

        return $note;
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function validate(array $input): array
    {
        $validator = validator($input, [
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'category' => ['required', 'string', 'in:'.implode(',', SupervisorNote::CATEGORIES)],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'content' => ['required', 'string', 'min:3', 'max:10000'],
        ], [
            'period_year.required' => 'Tahun periode wajib diisi.',
            'category.required' => 'Kategori wajib dipilih.',
            'subject.required' => 'Judul catatan wajib diisi.',
            'content.required' => 'Isi catatan wajib diisi.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function canEdit(SupervisorNote $note, ?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }
        if ($actor->is_superadmin === true) {
            return true;
        }

        return $note->isDraft() && (int) $note->supervisor_user_id === (int) $actor->row_id;
    }

    private function canSubmit(SupervisorNote $note, ?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }
        if ($actor->is_superadmin === true) {
            return true;
        }

        return $note->isDraft() && (int) $note->supervisor_user_id === (int) $actor->row_id;
    }

    private function canAcknowledge(SupervisorNote $note, ?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }
        if ($actor->is_superadmin === true) {
            return true;
        }
        if (! $note->isSubmitted()) {
            return false;
        }

        // Manajemen/manajemen acknowledgement: anyone other than the original supervisor.
        return (int) $note->supervisor_user_id !== (int) $actor->row_id;
    }

    private function assertCanEdit(SupervisorNote $note, ?User $actor): void
    {
        if (! $this->canEdit($note, $actor)) {
            throw new DomainException('Catatan ini tidak dapat diedit.');
        }
    }

    private function assertCanSubmit(SupervisorNote $note, ?User $actor): void
    {
        if (! $this->canSubmit($note, $actor)) {
            throw new DomainException('Catatan ini tidak dapat disubmit.');
        }
    }

    private function assertCanAcknowledge(SupervisorNote $note, ?User $actor): void
    {
        if (! $this->canAcknowledge($note, $actor)) {
            throw new DomainException('Catatan ini tidak dapat di-acknowledge.');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function notePayload(SupervisorNote $note): array
    {
        $supervisor = $note->supervisor;
        $ack = $note->acknowledger;

        return [
            'row_id' => (int) $note->row_id,
            'public_id' => (string) ($note->public_id ?? ''),
            'supervisor_user_id' => (int) $note->supervisor_user_id,
            'period_year' => (int) $note->period_year,
            'period_month' => $note->period_month !== null ? (int) $note->period_month : null,
            'period_label' => $this->periodLabel((int) $note->period_year, $note->period_month !== null ? (int) $note->period_month : null),
            'category' => (string) $note->category,
            'category_label' => $n = $note->categoryLabel(),
            'subject' => (string) $note->subject,
            'content' => (string) $note->content,
            'status' => (string) $note->status,
            'status_label' => $note->statusLabel(),
            'submitted_at' => $note->submitted_at?->toDateTimeString(),
            'acknowledged_at' => $note->acknowledged_at?->toDateTimeString(),
            'acknowledgment_note' => $note->acknowledgment_note,
            'supervisor' => $supervisor ? [
                'row_id' => (int) $supervisor->row_id,
                'name' => trim(($supervisor->name ?? '') !== '' ? (string) $supervisor->name : (string) ($supervisor->phone ?? '—')),
            ] : null,
            'acknowledger' => $ack ? [
                'row_id' => (int) $ack->row_id,
                'name' => trim(($ack->name ?? '') !== '' ? (string) $ack->name : (string) ($ack->phone ?? '—')),
            ] : null,
            'created_at' => $note->created_at?->toDateTimeString(),
            'updated_at' => $note->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function identityPayload(): array
    {
        $profile = OrganizationProfile::query()->first();

        return [
            'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
            'short_name' => $profile?->short_name,
            'address' => $profile?->address,
            'registration_number' => $profile?->registration_number,
            'tax_number' => $profile?->tax_number,
            'manager_name' => $profile?->manager_name,
            'manager_title' => $profile?->manager_title,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function approvedReports(int $tenantId): array
    {
        // Daftar laporan yang sudah disetujui pengawas.
        // Sumber: tabel `reports` (apabila ada di tenant) dengan status approved.
        // Sebagai fallback, kueri ini mengembalikan daftar kosong — UI menampilkan empty state.
        try {
            $exists = Schema::connection('tenant')->hasTable('reports');
            if (! $exists) {
                return [];
            }

            $rows = DB::connection('tenant')
                ->table('reports')
                ->where('tenant_id', $tenantId)
                ->where('supervisor_approved', true)
                ->orderByDesc('approved_at')
                ->limit(20)
                ->get(['row_id', 'name', 'kind', 'period_year', 'period_month', 'approved_at']);

            return $rows->map(fn ($r) => [
                'row_id' => (int) $r->row_id,
                'name' => (string) $r->name,
                'kind' => (string) $r->kind,
                'period_year' => (int) $r->period_year,
                'period_month' => $r->period_month !== null ? (int) $r->period_month : null,
                'period_label' => $this->periodLabel((int) $r->period_year, $r->period_month !== null ? (int) $r->period_month : null),
                'approved_at' => $r->approved_at,
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function periodLabel(int $year, ?int $month): string
    {
        if ($month === null) {
            return "Tahun {$year}";
        }
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($names[$month] ?? "Bulan {$month}")." {$year}";
    }
}
