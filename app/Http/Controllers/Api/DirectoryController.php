<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Employee self-service company directory: search active colleagues within the
 * caller's tenant and view a colleague's contact card, role, and manager.
 */
class DirectoryController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /** Paginated, searchable list of active colleagues. */
    public function index(Request $request): JsonResponse
    {
        $me = $this->currentEmployee($request);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Employee::forTenant($me->tenant_id)
            ->where('status', 'active')
            ->with(['position:id,name', 'department:id,name'])
            ->when($data['search'] ?? null, fn ($query, string $search) => $query->where(fn ($group) => $group
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('employee_number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($data['department_id'] ?? null, fn ($query, int $id) => $query->where('department_id', $id))
            ->orderBy('full_name')
            ->paginate($data['per_page'] ?? 25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Employee $e): array => $this->card($e, $me->id)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** A colleague's full contact card, including their manager (atasan). */
    public function show(Request $request, int $employee): JsonResponse
    {
        $me = $this->currentEmployee($request);

        $person = Employee::forTenant($me->tenant_id)
            ->where('status', 'active')
            ->with([
                'position:id,name',
                'department:id,name',
                'branch:id,name',
                'manager:id,full_name,employee_number,position_id',
                'manager.position:id,name',
            ])
            ->find($employee);

        abort_if($person === null, 404);

        $manager = $person->manager;

        return response()->json(['data' => array_merge($this->card($person, $me->id), [
            'email' => $person->email,
            'phone' => $person->phone,
            'branch' => $person->branch?->name,
            'join_date' => $person->join_date?->toDateString(),
            'manager' => $manager !== null ? [
                'id' => $manager->id,
                'name' => $manager->full_name,
                'employee_number' => $manager->employee_number,
                'position' => $manager->position?->name,
                'initials' => $this->initials($manager->full_name),
                'avatar_color' => $this->avatarColor($manager->full_name),
            ] : null,
        ])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Employee $employee, int $meId): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'position' => $employee->position?->name,
            'department' => $employee->department?->name,
            'photo_url' => $employee->photo_path !== null ? Storage::disk('public')->url($employee->photo_path) : null,
            'initials' => $this->initials($employee->full_name),
            'avatar_color' => $this->avatarColor($employee->full_name),
            'is_me' => $employee->id === $meId,
        ];
    }

    private function initials(?string $fullName): string
    {
        $initials = collect(preg_split('/\s+/', trim((string) $fullName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    private function avatarColor(?string $fullName): string
    {
        return self::AVATAR_PALETTE[crc32((string) $fullName) % count(self::AVATAR_PALETTE)];
    }
}
