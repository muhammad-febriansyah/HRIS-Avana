<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\ActivityLogger;
use App\Services\PersonalDataEraser;
use App\Services\PersonalDataExporter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The two rights UU PDP 27/2022 gives a person over the data an employer holds
 * about them: a copy of it, and its erasure.
 *
 * Both are deliberately heavy. The export hands over a file containing a
 * complete personal profile, and the erasure cannot be undone, so each is
 * gated on the permission that governs the employee record itself and written
 * to the audit trail with the actor and their address.
 */
class PersonalDataController extends Controller
{
    use AuthorizesRequests;

    /**
     * Download everything held about one employee as JSON.
     */
    public function export(Request $request, Employee $employee, PersonalDataExporter $exporter): JsonResponse
    {
        $this->authorize('update', $employee);

        $payload = $exporter->export($employee);

        $this->record($request, $employee, 'data_exported', [
            'sections' => array_keys($payload),
        ]);

        $filename = 'data-pribadi-'.($employee->employee_number ?: $employee->public_id).'-'
            .now()->format('Ymd-His').'.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            // The file is a complete personal profile; keep it out of every
            // cache between here and the browser.
            'Cache-Control' => 'no-store, max-age=0',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Erase the employee's personal data, keeping the records the company must
     * retain. Irreversible.
     */
    public function erase(Request $request, Employee $employee, PersonalDataEraser $eraser): RedirectResponse
    {
        $this->authorize('update', $employee);

        $request->validate([
            // Typing the name is the confirmation: this cannot be undone, and a
            // single click on the wrong row would erase the wrong person.
            'confirm_name' => ['required', 'string'],
        ]);

        if (trim($request->string('confirm_name')->toString()) !== trim((string) $employee->full_name)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Nama konfirmasi tidak cocok dengan nama karyawan.',
            ]);

            return back();
        }

        if (! $eraser->eligible($employee)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Karyawan masih aktif. Nonaktifkan atau isi tanggal keluar dulu sebelum menghapus data pribadinya.',
            ]);

            return back();
        }

        $name = (string) $employee->full_name;
        $summary = $eraser->erase($employee);

        $this->record($request, $employee, 'data_erased', $summary + ['name' => $name]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data pribadi '.$name.' dihapus. Riwayat penggajian dan absensi tetap tersimpan.',
        ]);

        return redirect()->route('avana.employees.index');
    }

    /**
     * Write both trails: the audit log (what happened to which record) and the
     * activity log (what this user did).
     *
     * @param  array<string, mixed>  $properties
     */
    private function record(Request $request, Employee $employee, string $action, array $properties): void
    {
        AuditLog::create([
            'tenant_id' => $employee->tenant_id,
            'user_id' => $request->user()->id,
            'auditable_type' => $employee->getMorphClass(),
            'auditable_id' => $employee->getKey(),
            'action' => $action,
            'old_values' => null,
            'new_values' => $properties,
            'ip_address' => $request->ip(),
        ]);

        ActivityLogger::log(
            $action,
            $action === 'data_erased'
                ? 'Menghapus data pribadi karyawan #'.$employee->getKey()
                : 'Mengunduh data pribadi karyawan #'.$employee->getKey(),
            properties: $properties,
            user: $request->user(),
            subject: $employee,
        );
    }
}
