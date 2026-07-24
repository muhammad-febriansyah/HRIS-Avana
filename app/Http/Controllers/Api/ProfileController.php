<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Employee self-service profile (view + limited self-update). */
class ProfileController extends Controller
{
    use ResolvesApiEmployee;

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->employeeProfile($this->currentEmployee($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        // Personal data the employee may maintain about themselves. Org-owned
        // fields (name, employee number, status, department, salary, …) stay
        // read-only and are only editable through the admin console.
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

        return response()->json(['data' => $this->employeeProfile($employee->fresh())]);
    }

    /**
     * Upload / replace the employee's profile photo (avatar).
     *
     * Kept separate from update() because the mobile client sends this as a
     * multipart POST, while the text fields go through a JSON PUT.
     */
    public function updatePhoto(Request $request): JsonResponse
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

        $path = $request->file('photo')->store("employee-photos/{$employee->tenant_id}", 'public');

        $employee->update(['photo_path' => $path]);

        // Remove the previous file only after the new one is persisted, so a
        // failed save never leaves the employee without a photo.
        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['data' => $this->employeeProfile($employee->fresh())]);
    }
}
