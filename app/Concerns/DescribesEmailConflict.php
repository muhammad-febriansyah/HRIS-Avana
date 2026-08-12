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

        if ($employee !== null) {
            return "Email sudah dipakai akun login karyawan {$employee->full_name}. Gunakan email lain.";
        }

        return "Email sudah dipakai akun \"{$user->name}\" yang belum tertaut ke karyawan mana pun. Kosongkan kolom Password Login, lalu pakai \"Tautkan Akun\" untuk memakai akun itu — atau isi email lain.";
    }
}
