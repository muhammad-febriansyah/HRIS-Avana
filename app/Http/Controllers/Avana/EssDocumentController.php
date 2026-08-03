<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Dokumen Saya" — the employee's own personal file (contracts, certificates,
 * ID scans). Only their own documents are listed or accepted.
 */
class EssDocumentController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Display labels for the stored category slugs, matching the form's picker.
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'kontrak' => 'Kontrak',
        'sertifikat' => 'Sertifikat',
        'identitas' => 'Identitas',
        'medis' => 'Medis',
        'lainnya' => 'Lainnya',
    ];

    /**
     * List the employee's documents, newest upload first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $documents = EmployeeDocument::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('avana/saya/dokumen', [
            'documents' => $documents->map(fn (EmployeeDocument $document): array => [
                'id' => $document->id,
                'name' => $document->name,
                // Fall back to the raw slug for anything HR uploaded under a
                // category this screen does not offer.
                'type' => self::TYPE_LABELS[$document->type] ?? $document->type,
                'size' => (int) $document->file_size,
                'uploaded_at' => $document->uploaded_at?->toDateString(),
                'url' => PrivateFile::urlFor($document->file_path),
            ])->values(),
        ]);
    }

    /**
     * Upload a document into the employee's own file.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'name.required' => 'Nama dokumen wajib diisi.',
            'file.required' => 'Berkas wajib diunggah.',
            'file.mimes' => 'Format berkas harus pdf, jpg, jpeg, png, atau webp.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ]);

        $file = $request->file('file');
        $path = PrivateFile::store($file, "employee-documents/{$employee->tenant_id}");

        EmployeeDocument::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'lainnya',
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        return back()->with('success', 'Dokumen diunggah');
    }
}
