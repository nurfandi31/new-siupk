<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\User;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class MobileAuthController
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'identifier.required' => 'Username atau email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                'Validasi gagal',
                422,
                $validator->errors()->toArray()
            );
        }

        $identifier = trim((string) $request->input('identifier'));
        $password = (string) $request->input('password');
        $deviceName = (string) ($request->input('device_name') ?: 'Flutter Mobile App');

        /** @var User|null $user */
        $user = User::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->where('status', 'active')
            ->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return ApiResponse::error('Kredensial yang diberikan tidak valid.', 401);
        }

        if ($user->tenant_id === null && ! $user->is_superadmin && ! $user->isRegencyUser() && ! $user->isProvinceUser()) {
            $membership = $user->memberships()->where('status', 'active')->first();
            if ($membership !== null) {
                $user->forceFill(['tenant_id' => $membership->tenant_id])->saveQuietly();
            }
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $token = $user->createToken($deviceName)->plainTextToken;

        $tenant = $user->tenant;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->row_id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_superadmin' => (bool) $user->is_superadmin,
                'is_regency_user' => (bool) $user->is_regency_user,
                'is_province_user' => (bool) $user->is_province_user,
                'is_village_user' => (bool) $user->is_village_user,
                'village_row_id' => $user->village_row_id,
            ],
            'tenant' => $tenant !== null ? [
                'id' => $tenant->row_id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'district_name' => $tenant->district_name,
                'regency_name' => $tenant->regency_name,
                'is_training' => $tenant->isTraining(),
            ] : null,
        ], 'Login berhasil.');
    }

    public function me(Request $request, TenantContext $context, PermissionChecker $permissions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tenant = $user->tenant;
        $orgProfile = null;

        if ($context->isInitialized()) {
            $orgProfile = OrganizationProfile::query()->first();
        }

        $userPermissions = $context->isInitialized()
            ? $permissions->listFor($user)
            : ($user->is_superadmin ? ['*'] : []);

        return ApiResponse::success([
            'user' => [
                'id' => $user->row_id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_superadmin' => (bool) $user->is_superadmin,
                'is_regency_user' => (bool) $user->is_regency_user,
                'is_province_user' => (bool) $user->is_province_user,
                'is_village_user' => (bool) $user->is_village_user,
                'village_row_id' => $user->village_row_id,
                'permissions' => $userPermissions,
            ],
            'tenant' => $tenant !== null ? [
                'id' => $tenant->row_id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'district_name' => $tenant->district_name,
                'regency_name' => $tenant->regency_name,
                'is_training' => $tenant->isTraining(),
                'organization' => $orgProfile !== null ? [
                    'legal_name' => $orgProfile->legal_name,
                    'short_name' => $orgProfile->short_name,
                    'address' => $orgProfile->address,
                    'phone' => $orgProfile->phone,
                    'email' => $orgProfile->email,
                    'logo_path' => $orgProfile->logo_path,
                ] : null,
            ] : null,
        ], 'Profil berhasil dimuat.');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user !== null && method_exists($user, 'currentAccessToken') && $user->currentAccessToken() !== null) {
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, 'Logout berhasil.');
    }
}
