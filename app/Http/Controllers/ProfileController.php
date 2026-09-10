<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateAccountRequest;
use App\Http\Requests\Profile\UpdatePhotoRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController
{
    /** @return array<string, string> */
    public static function educationOptions(): array
    {
        return [
            'sd' => 'SD',
            'smp' => 'SMP',
            'sma_smk' => 'SMA/SMK',
            'd3' => 'D3',
            's1' => 'S1',
            's2' => 'S2',
            's3' => 'S3',
        ];
    }

    public function edit(): Response
    {
        $user = auth()->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'nik' => $user->nik,
                'name' => (string) $user->name,
                'initials' => $user->initials,
                'birth_place' => $user->birth_place,
                'birth_date' => $user->birth_date?->toDateString(),
                'address' => $user->address,
                'phone' => $user->phone,
                'education' => $user->education,
                'appointed_at' => $user->appointed_at?->toDateString(),
                'term_end_at' => $user->term_end_at?->toDateString(),
            ],
            'account' => [
                'username' => (string) $user->username,
            ],
            'photoUrl' => $user->photo_path
                ? asset('storage/'.ltrim((string) $user->photo_path, '/')).'?v='.($user->updated_at?->timestamp ?? time())
                : null,
            'educationOptions' => collect(self::educationOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $user->fill([
            'nik' => $data['nik'] ?? null,
            'name' => $data['name'],
            'initials' => $data['initials'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => app(PhoneNormalizer::class)->normalize((string) $data['phone']),
            'education' => $data['education'] ?? null,
            'appointed_at' => $data['appointed_at'] ?? null,
            'term_end_at' => $data['term_end_at'] ?? null,
        ]);
        $user->save();

        return redirect()
            ->route('profile.edit', ['tab' => 'personal'])
            ->with('success', ['message' => 'Data pribadi berhasil disimpan.', 'tab' => 'personal']);
    }

    public function updateAccount(UpdateAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $user->username = $data['username'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()
            ->route('profile.edit', ['tab' => 'account'])
            ->with('success', ['message' => 'Akun berhasil diperbarui.', 'tab' => 'account']);
    }

    public function updatePhoto(UpdatePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $file = $request->file('photo');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $path = $file->storeAs(
            'users/'.$user->getKey(),
            'photo.'.$extension,
            'public',
        );

        $user->photo_path = $path;
        $user->touch();
        $user->save();

        return redirect()
            ->route('profile.edit', ['tab' => 'photo'])
            ->with('success', ['message' => 'Foto profil berhasil diunggah.', 'tab' => 'photo']);
    }

    public function destroyPhoto(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->photo_path = null;
            $user->touch();
            $user->save();
        }

        return redirect()
            ->route('profile.edit', ['tab' => 'photo'])
            ->with('success', ['message' => 'Foto profil dihapus.', 'tab' => 'photo']);
    }
}
