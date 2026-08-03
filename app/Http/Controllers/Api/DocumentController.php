<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Employee self-service personal documents (list + upload). */
class DocumentController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = EmployeeDocument::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeDocument $doc): array => [
                'id' => $doc->id,
                'name' => $doc->name,
                'type' => $doc->type,
                'url' => PrivateFile::urlFor($doc->file_path),
                'size' => (int) $doc->file_size,
                'uploaded_at' => $doc->uploaded_at?->toDateTimeString(),
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $file = $request->file('file');
        $path = $file->store('employee-documents', 'public');

        $doc = EmployeeDocument::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        return response()->json(['message' => 'Dokumen diunggah', 'data' => ['id' => $doc->id]], 201);
    }
}
