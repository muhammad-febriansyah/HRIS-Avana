<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\BuildsSecurityPanels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\PrivateFile;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use BuildsSecurityPanels;

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        // The two-factor secret, its QR code and the recovery codes are only
        // safe to hand to a session that proved the password recently. Without
        // that proof the panel renders locked, behind a link to Keamanan Akun
        // — the screen whose middleware forces the confirmation.
        $unlocked = $this->passwordRecentlyConfirmed($request);

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // When the login belongs to an employee, the Karyawan record owns
            // the name. Letting the account rename itself here would leave the
            // two spellings apart, which is exactly what this screen is for.
            'employeeName' => $employee?->full_name,
            'phone' => $user->phone,
            'avatarUrl' => PrivateFile::urlFor($user->avatar_path ?? $employee?->photo_path),
            'hasOwnAvatar' => $user->avatar_path !== null,
            'securityUnlocked' => $unlocked,
        ] + ($unlocked ? $this->securityPanelProps($user, $request) : []));
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // The name field is read-only for a linked account, so whatever the
        // browser posted for it is ignored — the Karyawan record decides.
        if (($employee = $user->employee) !== null) {
            $data['name'] = $employee->full_name;
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // The login authenticates by email, and the Karyawan screen shows the
        // employee's email as the address that login uses. Keep the employee
        // row in step so the two never disagree.
        if ($employee !== null && $employee->email !== $user->email) {
            $employee->update(['email' => $user->email]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Replace or clear the account's own photo.
     *
     * Separate from update() because PHP only populates $_FILES on POST — a
     * multipart PATCH arrives with an empty body.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove' => ['nullable', 'boolean'],
        ], [
            'avatar.image' => 'Foto harus berupa gambar.',
            'avatar.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $user = $request->user();
        $photo = $request->file('avatar');

        // avatar_path is deliberately outside #[Fillable] — nothing mass
        // assigns a file path — so it is written explicitly.
        if ($photo !== null) {
            PrivateFile::delete($user->avatar_path);
            $user->forceFill(['avatar_path' => PrivateFile::store($photo, 'avatars')])->save();
        } elseif ($request->boolean('remove')) {
            PrivateFile::delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil diperbarui.']);

        return to_route('profile.edit');
    }

    /**
     * Whether this session confirmed its password inside the auth timeout —
     * the same window Laravel's own password.confirm middleware honours.
     */
    private function passwordRecentlyConfirmed(Request $request): bool
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');

        if ($confirmedAt === null) {
            return false;
        }

        return (time() - $confirmedAt) < config('auth.password_timeout', 10800);
    }
}
