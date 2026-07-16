<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Services\FieldVisitPhotoStore;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Employee self-service field visits / visiting pekerjaan (list + report). */
class FieldVisitController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = FieldVisit::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('photos')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FieldVisit $visit): array => [
                'id' => $visit->id,
                'visit_date' => self::dateString($visit->visit_date),
                'location' => $visit->location,
                'client_name' => $visit->client_name,
                'purpose' => $visit->purpose,
                'notes' => $visit->notes,
                'photo_urls' => FieldVisitPhotoStore::urls($visit),
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
            ...FieldVisitPhotoStore::rules(),
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
            'status' => 'submitted',
        ]);

        FieldVisitPhotoStore::attach($visit, $request->file('photos') ?? []);

        return response()->json(['message' => 'Kunjungan tercatat', 'data' => ['id' => $visit->id]], 201);
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     *
     * Matched on DateTimeInterface, not Illuminate\Support\Carbon: the app runs
     * Date::use(CarbonImmutable::class), and CarbonImmutable does not extend the
     * Illuminate class — so a narrower check silently passes the raw datetime
     * string through.
     */
    private static function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
