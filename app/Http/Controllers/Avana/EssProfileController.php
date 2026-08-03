<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Profil Saya" — the web counterpart of the mobile /me/profile endpoints.
 * Org-owned fields (name, employee number, department, salary, …) stay
 * read-only; only the personal data an employee maintains is editable.
 */
class EssProfileController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Show the employee's own profile.
     */
    public function edit(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        return Inertia::render('avana/saya/profil', [
            'profile' => $this->employeeProfile($employee),
        ]);
    }

    /**
     * Persist the personal fields the employee is allowed to change.
     */
    public function update(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'nik' => ['nullable', 'digits:16'],
            'gender' => ['nullable', 'in:male,female,unspecified'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'religion' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'string', 'max:255'],
        ], [
            'email.email' => 'Format email tidak valid.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birth_date.date' => 'Tanggal lahir tidak valid.',
        ]);

        $employee->update($data);

        return back()->with('success', 'Profil diperbarui');
    }

    /**
     * Replace the employee's avatar.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Foto wajib diunggah.',
            'photo.image' => 'Berkas harus berupa gambar.',
            'photo.mimes' => 'Format foto harus jpg, jpeg, png, atau webp.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $oldPath = $employee->photo_path;

        $path = PrivateFile::store($request->file('photo'), "employee-photos/{$employee->tenant_id}");

        $employee->update(['photo_path' => $path]);

        // Drop the previous file only once the new one is persisted, so a failed
        // save never leaves the employee without a photo.
        if ($oldPath !== null && $oldPath !== $path) {
            PrivateFile::delete($oldPath);
        }

        return back()->with('success', 'Foto profil diperbarui');
    }
}
