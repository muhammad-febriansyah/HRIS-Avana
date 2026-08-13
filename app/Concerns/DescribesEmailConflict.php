<?php

namespace App\Concerns;

use App\Models\User;

trait DescribesEmailConflict
{
    /**
     * Explain who is already signing in with this email, or null when it is
     * free. "Email sudah digunakan akun lain" alone is a dead end for an
     * admin: the address is nowhere to be seen on the Karyawan screen, because
     * the owner is a row in `users` — an account whose employee was deleted, or
     * one that was never attached to an employee at all. Naming the owner turns
     * the error into the next step, which is almost always linking that account
     * rather than inventing a second address for the same person.
     */
    protected function emailConflictMessage(string $email, int $tenantId): ?string
    {
        $user = User::where('email', $email)
            ->with(['employee' => fn ($query) => $query->withTrashed()])
            ->first();

        if ($user === null) {
            return null;
        }

        // Another tenant's account. Saying whose would leak a name across the
        // tenant boundary, so this one stays deliberately vague.
        if ((int) $user->tenant_id !== $tenantId) {
            return 'Email sudah digunakan akun lain.';
        }

        $employee = $user->employee;

        if ($employee !== null && $employee->trashed()) {
            return "Email sudah dipakai akun login milik karyawan {$employee->full_name} yang sudah dihapus. Kosongkan kolom Password Login, lalu pakai \"Tautkan Akun\" untuk memakai kembali akun itu — atau isi email lain.";
        }

        // Resigned/deactivated employees keep their row (only deletion
        // soft-deletes it), so this is the common "email is no longer really
        // in use" case — and unlike a delete, it doesn't need admin judgment
        // to resolve: releaseStaleLogin() frees the address the moment it's
        // actually reused, so there is nothing to block here.
        if ($employee !== null && $employee->status !== 'active') {
            return null;
        }

        if ($employee !== null) {
            return "Email sudah dipakai akun login karyawan {$employee->full_name}. Gunakan email lain.";
        }

        return "Email sudah dipakai akun \"{$user->name}\" yang belum tertaut ke karyawan mana pun. Kosongkan kolom Password Login, lalu pakai \"Tautkan Akun\" untuk memakai akun itu — atau isi email lain.";
    }

    /**
     * Free an inactive ex-employee's login email right before it's actually
     * reused, so a new hire can take the address without the old account's
     * `users.email` uniqueness getting in the way.
     *
     * Deliberately lazy rather than done the moment an employee goes
     * inactive: as long as nobody has claimed the address, the ex-employee's
     * own login attempt still resolves to their real account and reports
     * "Akun tidak aktif" — accurate and clear — instead of "email atau kata
     * sandi salah" from a silently vanished address.
     */
    protected function releaseStaleLogin(string $email, int $tenantId, ?int $exceptEmployeeId = null): void
    {
        if (! str_contains($email, '@')) {
            return;
        }

        $user = User::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->with('employee')
            ->first();

        if ($user === null) {
            return;
        }

        $employee = $user->employee;

        if ($employee === null
            || $employee->id === $exceptEmployeeId
            || (! $employee->trashed() && $employee->status === 'active')) {
            return;
        }

        [$local, $domain] = explode('@', $email, 2);

        $user->forceFill(['email' => "{$local}+former-{$employee->id}@{$domain}"])->save();
    }
}
