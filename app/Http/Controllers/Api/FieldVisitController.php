<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Employee self-service field visits / visiting pekerjaan (list + report). */
class FieldVisitController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = FieldVisit::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FieldVisit $visit): array => [
                'id' => $visit->id,
                'visit_date' => $visit->visit_date instanceof Carbon ? $visit->visit_date->toDateString() : $visit->visit_date,
                'location' => $visit->location,
                'client_name' => $visit->client_name,
                'purpose' => $visit->purpose,
                'notes' => $visit->notes,
                'photo_url' => $visit->photo_path ? Storage::disk('public')->url($visit->photo_path) : null,
                'status' => $visit->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'visit_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $visit = FieldVisit::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'visit_date' => $data['visit_date'],
            'location' => $data['location'],
            'client_name' => $data['client_name'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'notes' => $data['notes'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('field-visits', 'public') : null,
            'status' => 'submitted',
        ]);

        return response()->json(['message' => 'Kunjungan tercatat', 'data' => ['id' => $visit->id]], 201);
    }
}
