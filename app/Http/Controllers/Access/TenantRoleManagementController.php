<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionChecker;
use App\Http\Requests\Access\TenantRoleRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class TenantRoleManagementController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionChecker $permissions,
    ) {}

    public function index(): Response
    {
        $this->permissions->ensureSystemRoles();

        $excludedCodes = ['regency_supervisor', 'province_supervisor'];

        $roles = Role::query()
            ->whereNotIn('code', $excludedCodes)
            ->withCount('userRoles')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        $rolesPayload = $roles->map(fn (Role $role): array => [
            'row_id' => $role->row_id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'is_locked' => $role->code === 'admin',
            'user_count' => (int) $role->user_roles_count,
            'permissions_count' => $role->code === 'admin'
                ? count($this->allTenantPermissionsFlat())
                : count($this->resolveRolePermissions($role)),
        ]);

        return Inertia::render('Access/Roles/Index', [
            'roles' => $rolesPayload,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Access/Roles/Form', [
            'role' => null,
            'permissionGroups' => $this->groupedTenantPermissions(),
        ]);
    }

    public function store(TenantRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (in_array(strtolower($data['code']), ['admin', 'regency_supervisor', 'province_supervisor'], true)) {
            return back()->with('error', 'Kode role tersebut dilindungi dan tidak dapat digunakan.');
        }

        Role::query()->create([
            'name' => $data['name'],
            'code' => Str::slug($data['code'], '_'),
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return to_route('access.roles.index')->with('success', 'Role kustom berhasil ditambahkan.');
    }

    public function edit(Role $role): Response
    {
        $this->assertBelongs($role);

        $isLocked = $role->code === 'admin';
        $activePermissions = $isLocked
            ? $this->allTenantPermissionsFlat()
            : $this->resolveRolePermissions($role);

        return Inertia::render('Access/Roles/Form', [
            'role' => [
                'row_id' => $role->row_id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'is_locked' => $isLocked,
                'permissions' => $activePermissions,
            ],
            'permissionGroups' => $this->groupedTenantPermissions(),
        ]);
    }

    public function update(TenantRoleRequest $request, Role $role): RedirectResponse
    {
        $this->assertBelongs($role);

        if ($role->code === 'admin') {
            return back()->with('error', 'Role Administrator dikunci dan tidak dapat diubah.');
        }

        $data = $request->validated();

        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
        ];

        // Only allow code change for non-system roles
        if (! $role->is_system) {
            $updateData['code'] = Str::slug($data['code'], '_');
        }

        $role->update($updateData);

        return to_route('access.roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->assertBelongs($role);

        if ($role->is_system || $role->code === 'admin') {
            return back()->with('error', 'Role bawaan sistem tidak dapat dihapus.');
        }

        $userCount = UserRole::query()->where('role_row_id', (int) $role->row_id)->count();
        if ($userCount > 0) {
            return back()->with('error', "Role ini sedang digunakan oleh {$userCount} pengguna dan tidak dapat dihapus.");
        }

        $role->delete();

        return to_route('access.roles.index')->with('success', 'Role berhasil dihapus.');
    }

    private function resolveRolePermissions(Role $role): array
    {
        if (is_array($role->permissions)) {
            return $role->permissions;
        }

        $packs = config('permissions.roles', []);

        return $packs[$role->code]['permissions'] ?? [];
    }

    /** @return list<string> */
    private function allTenantPermissionsFlat(): array
    {
        $flat = [];
        foreach ($this->groupedTenantPermissions() as $group) {
            foreach ($group['permissions'] as $perm) {
                $flat[] = $perm['key'];
            }
        }

        return $flat;
    }

    /** @return list<array{category: string, label: string, icon: string, permissions: list<array{key: string, label: string, description: string}>}> */
    private function groupedTenantPermissions(): array
    {
        return [
            [
                'category' => 'master',
                'label' => 'Data Master',
                'icon' => 'folder_shared',
                'permissions' => [
                    ['key' => 'members.view', 'label' => 'Lihat Anggota', 'description' => 'Melihat daftar dan profil anggota'],
                    ['key' => 'members.manage', 'label' => 'Kelola Anggota', 'description' => 'Tambah, ubah, dan hapus data anggota'],
                    ['key' => 'groups.view', 'label' => 'Lihat Kelompok', 'description' => 'Melihat daftar kelompok pemanfaat'],
                    ['key' => 'groups.manage', 'label' => 'Kelola Kelompok', 'description' => 'Tambah, ubah, dan hapus data kelompok'],
                    ['key' => 'villages.view', 'label' => 'Lihat Desa', 'description' => 'Melihat daftar desa/wilayah'],
                    ['key' => 'villages.manage', 'label' => 'Kelola Desa', 'description' => 'Ubah data desa/wilayah'],
                    ['key' => 'institutions.view', 'label' => 'Lihat Lembaga', 'description' => 'Melihat daftar lembaga eksternal'],
                    ['key' => 'institutions.manage', 'label' => 'Kelola Lembaga', 'description' => 'Tambah, ubah, dan hapus lembaga'],
                ],
            ],
            [
                'category' => 'lending',
                'label' => 'Pinjaman (siupk)',
                'icon' => 'account_balance',
                'permissions' => [
                    ['key' => 'loans.view', 'label' => 'Lihat Pinjaman & Laporan', 'description' => 'Melihat tahapan perguliran dan laporan pinjaman'],
                    ['key' => 'loans.propose', 'label' => 'Input Proposal', 'description' => 'Mendaftarkan proposal pinjaman baru'],
                    ['key' => 'loans.verify', 'label' => 'Verifikasi Pinjaman', 'description' => 'Melakukan verifikasi berkas dan lapangan'],
                    ['key' => 'loans.approve', 'label' => 'Penetapan Alokasi', 'description' => 'Menetapkan persetujuan alokasi pinjaman'],
                    ['key' => 'loans.disburse', 'label' => 'Pencairan Pinjaman', 'description' => 'Mencatat pencairan dana pinjaman ke kelompok'],
                    ['key' => 'loans.manage', 'label' => 'Kelola & Reschedule', 'description' => 'Edit proposal, reschedule, dan penghapusan piutang'],
                ],
            ],
            [
                'category' => 'accounting',
                'label' => 'Akuntansi & Keuangan',
                'icon' => 'receipt_long',
                'permissions' => [
                    ['key' => 'journals.view', 'label' => 'Lihat Jurnal & Akun', 'description' => 'Melihat daftar jurnal dan bagan akun (COA)'],
                    ['key' => 'journals.create', 'label' => 'Input Jurnal Umum', 'description' => 'Membuat dan memposting jurnal umum/pembuka'],
                    ['key' => 'installments.record', 'label' => 'Catat Angsuran', 'description' => 'Mencatat pembayaran angsuran pinjaman'],
                    ['key' => 'assets.view', 'label' => 'Lihat Inventaris', 'description' => 'Melihat daftar inventaris barang dan aset'],
                    ['key' => 'assets.manage', 'label' => 'Kelola Inventaris', 'description' => 'Tambah, ubah, dan hapus aset inventaris'],
                    ['key' => 'period_close.view', 'label' => 'Lihat Tutup Buku', 'description' => 'Melihat status periode dan tutup buku'],
                    ['key' => 'period_close.manage', 'label' => 'Proses Tutup Buku', 'description' => 'Menutup buku bulanan, tahunan, dan alokasi surplus'],
                    ['key' => 'reports.view', 'label' => 'Lihat Laporan Keuangan', 'description' => 'Melihat dan cetak neraca, laba rugi, arus kas, dll.'],
                    ['key' => 'reports.manage', 'label' => 'Kelola CALK', 'description' => 'Mengisi catatan atas laporan keuangan (CALK)'],
                    ['key' => 'tax.view', 'label' => 'Taksiran Pajak', 'description' => 'Melihat perhitungan taksiran pajak'],
                ],
            ],
            [
                'category' => 'operations',
                'label' => 'Operasional & Sistem',
                'icon' => 'tune',
                'permissions' => [
                    ['key' => 'budgeting.view', 'label' => 'Lihat Anggaran (E-Budgeting)', 'description' => 'Melihat rencana anggaran operasional'],
                    ['key' => 'budgeting.manage', 'label' => 'Kelola Anggaran', 'description' => 'Menyusun dan mengesahkan anggaran'],
                    ['key' => 'messages.send', 'label' => 'Kirim Notifikasi WA', 'description' => 'Mengirim notifikasi tagihan WhatsApp'],
                    ['key' => 'billing.view', 'label' => 'Lihat Tagihan Layanan', 'description' => 'Melihat invoice langganan sistem SaaS'],
                    ['key' => 'billing.pay', 'label' => 'Bayar Tagihan Layanan', 'description' => 'Melakukan checkout pembayaran SaaS'],
                    ['key' => 'assistant.use', 'label' => 'Asisten AI Ariel', 'description' => 'Menggunakan asisten AI dan tool bantuannya'],
                    ['key' => 'village_user.access', 'label' => 'Akses Operator Desa', 'description' => 'Mode khusus terbatas untuk operator desa'],
                    ['key' => 'settings.manage', 'label' => 'Pengaturan Tenant', 'description' => 'Mengatur identitas lembaga, logo, dan sistem'],
                    ['key' => 'users.view', 'label' => 'Lihat Daftar Pengguna', 'description' => 'Melihat staf dan operator tenant'],
                    ['key' => 'users.manage', 'label' => 'Kelola Pengguna', 'description' => 'Tambah, ubah status, dan reset password staf'],
                    ['key' => 'roles.view', 'label' => 'Lihat Daftar Role', 'description' => 'Melihat daftar role dan hak akses'],
                    ['key' => 'roles.manage', 'label' => 'Kelola Role & Hak Akses', 'description' => 'Membuat dan menyesuaikan hak akses role'],
                ],
            ],
        ];
    }

    private function assertBelongs(Role $role): void
    {
        abort_unless((int) $role->tenant_id === $this->tenantContext->id(), 404);
    }
}
