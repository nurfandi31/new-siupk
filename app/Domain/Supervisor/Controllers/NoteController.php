<?php

declare(strict_types=1);

namespace App\Domain\Supervisor\Controllers;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Supervisor\Models\SupervisorNote;
use App\Domain\Supervisor\Services\NoteService;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Catatan Pengawas — CRUD + acknowledgement.
 *
 * Routes (defined in routes/web.php):
 *   GET    /supervisor/notes                  → Inertia list page
 *   GET    /supervisor/notes/create           → Form create
 *   POST   /supervisor/notes                  → Store
 *   GET    /supervisor/notes/{id}             → Detail
 *   GET    /supervisor/notes/{id}/edit        → Form edit
 *   PUT    /supervisor/notes/{id}             → Update
 *   DELETE /supervisor/notes/{id}             → Delete
 *   POST   /supervisor/notes/{id}/submit      → Submit (draft → submitted)
 *   POST   /supervisor/notes/{id}/acknowledge → Acknowledge (submitted → acknowledged)
 *
 * Authorization:
 *   - Hanya user dengan role pengawas / supervisor / direktur yang dapat membuat.
 *   - Permission `supervisor.notes` ditegaskan di controller (denyUnless).
 *   - Logika canEdit/canAcknowledge berada di NoteService.
 */
final class NoteController
{
    public function __construct(
        private readonly NoteService $service,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request, 'supervisor.notes');

        $year = (int) $request->query('year', (int) date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $monthRaw = $request->query('month', 'all');
        $month = null;
        if ($monthRaw !== null && $monthRaw !== '' && $monthRaw !== 'all' && $monthRaw !== '0') {
            $m = (int) $monthRaw;
            if ($m >= 1 && $m <= 12) {
                $month = $m;
            }
        }

        $category = $request->query('category');
        $category = is_string($category) && $category !== '' ? $category : null;

        $status = $request->query('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        $page = max(1, (int) $request->query('page', 1));

        $payload = $this->service->index($year, $month, $category, $status, $page);

        return Inertia::render('Supervisor/Notes/Index', [
            ...$payload,
            'categories' => SupervisorNote::CATEGORY_LABELS,
            'monthLabels' => $this->monthLabels(),
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => SupervisorNote::STATUS_DRAFT, 'label' => 'Draft'],
                ['value' => SupervisorNote::STATUS_SUBMITTED, 'label' => 'Disubmit'],
                ['value' => SupervisorNote::STATUS_ACKNOWLEDGED, 'label' => 'Disetujui Manajemen'],
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize($request, 'supervisor.notes');

        return Inertia::render('Supervisor/Notes/Edit', [
            'note' => null,
            'identity' => $this->service->identityForCreate(),
            'categories' => SupervisorNote::CATEGORY_LABELS,
            'monthLabels' => $this->monthLabels(),
            'filters' => [
                'year' => (int) date('Y'),
                'month' => (int) date('n'),
            ],
            'can' => [
                'edit' => true,
                'submit' => true,
                'acknowledge' => false,
                'delete' => true,
            ],
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request, 'supervisor.notes');

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        try {
            $result = $this->service->create($request->all(), $user);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return to_route('supervisor.notes.show', ['id' => $result['note']['row_id']])
            ->with('success', 'Catatan pengawas berhasil dibuat.');
    }

    public function show(Request $request, int $id): InertiaResponse
    {
        $this->authorize($request, 'supervisor.notes');

        try {
            $payload = $this->service->show($id, $request->user());
        } catch (DomainException $e) {
            abort(404, $e->getMessage());
        }

        return Inertia::render('Supervisor/Notes/Show', [
            ...$payload,
            'monthLabels' => $this->monthLabels(),
        ]);
    }

    public function edit(Request $request, int $id): InertiaResponse
    {
        $this->authorize($request, 'supervisor.notes');

        try {
            $payload = $this->service->show($id, $request->user());
        } catch (DomainException $e) {
            abort(404, $e->getMessage());
        }

        if (! ($payload['can']['edit'] ?? false)) {
            abort(403, 'Catatan ini tidak dapat diedit.');
        }

        return Inertia::render('Supervisor/Notes/Edit', [
            ...$payload,
            'monthLabels' => $this->monthLabels(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize($request, 'supervisor.notes');

        try {
            $result = $this->service->update($id, $request->all(), $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return to_route('supervisor.notes.show', ['id' => $result['note']['row_id']])
            ->with('success', 'Catatan pengawas berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorize($request, 'supervisor.notes');

        try {
            $this->service->delete($id, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('supervisor.notes.index')->with('success', 'Catatan dihapus.');
    }

    public function submit(Request $request, int $id): RedirectResponse
    {
        $this->authorize($request, 'supervisor.notes');

        try {
            $this->service->submit($id, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Catatan disubmit untuk ditinjau manajemen.');
    }

    public function acknowledge(Request $request, int $id): RedirectResponse
    {
        $this->authorize($request, 'supervisor.notes');

        try {
            $this->service->acknowledge($id, $request->all(), $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Catatan ditandai telah disetujui.');
    }

    private function authorize(Request $request, string $permission): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        // Superadmin bypass
        if ($user->is_superadmin === true) {
            return;
        }

        // Pengawas/Supervisor role bypass — di SIUPKNext, supervisor internal lembaga
        // diidentifikasi melalui permission `supervisor.notes`. Mid-level fallback:
        // user dengan permission `reports.manage` (direktur/manager) dapat juga mengelola.
        $allowed = $this->hasEffectivePermission($user, $permission)
            || $this->hasEffectivePermission($user, 'reports.manage');

        if (! $allowed) {
            abort(403, "Missing permission: {$permission}");
        }
    }

    private function hasEffectivePermission(User $user, string $permission): bool
    {
        $checker = app(PermissionChecker::class);

        return $checker->allows($user, $permission);
    }

    /**
     * @return array<int|string, string>
     */
    private function monthLabels(): array
    {
        return [
            'all' => 'Semua Bulan',
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }
}
