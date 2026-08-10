<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\FaceScanLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of face enrollment and verification attempts.
 *
 * Face scanning happens on the employee's phone, so a failure that only occurs
 * on one platform is otherwise invisible to HR and support. This screen shows
 * what the device measured on each attempt — how many faces it saw, the head
 * angles, the match score — grouped so a device-specific problem stands out.
 */
class FaceScanLogController extends Controller
{
    use AppliesBranchScope;
    use AuthorizesRequests;

    /**
     * Page-size choices offered to the user.
     *
     * @var array<int, int>
     */
    private const PER_PAGE = [15, 25, 50, 100];

    /**
     * Indonesian labels for the reason codes the app and API emit.
     *
     * @var array<string, string>
     */
    public const REASON_LABELS = [
        'camera_unavailable' => 'Kamera tidak tersedia',
        'camera_error' => 'Gagal membuka kamera',
        'no_face' => 'Wajah tidak terdeteksi',
        'multi_face' => 'Lebih dari satu wajah',
        'too_far' => 'Wajah terlalu jauh dari kamera',
        'not_frontal' => 'Wajah tidak lurus / mata tertutup',
        'not_centered' => 'Wajah tidak di tengah bingkai',
        'expression_neutral' => 'Diminta netral, terdeteksi senyum',
        'expression_smile' => 'Diminta senyum, belum senyum',
        'embed_failed' => 'Model wajah gagal memproses',
        'scan_error' => 'Error saat memindai',
        'captured' => 'Frame berhasil diambil',
        'enrolled' => 'Wajah terdaftar',
        'enroll_failed' => 'Pendaftaran wajah ditolak server',
        'face_match' => 'Wajah cocok',
        'face_mismatch' => 'Wajah tidak cocok',
        'face_missing' => 'Wajah tidak dikirim',
        'face_detected' => 'Wajah terdeteksi',
        'not_enrolled' => 'Belum mendaftarkan wajah',
    ];

    /**
     * Render the tenant-scoped, paginated face scan log.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $search = trim((string) $request->query('search', '')) ?: null;
        $context = in_array($request->query('context'), FaceScanLog::CONTEXTS, true)
            ? $request->query('context')
            : null;
        $outcome = in_array($request->query('outcome'), FaceScanLog::OUTCOMES, true)
            ? $request->query('outcome')
            : null;
        $platform = in_array($request->query('platform'), ['ios', 'android'], true)
            ? $request->query('platform')
            : null;
        $perPage = in_array((int) $request->query('per_page'), self::PER_PAGE, true)
            ? (int) $request->query('per_page')
            : 25;

        $query = FaceScanLog::query()
            ->forTenant($tenantId)
            ->with('employee:id,full_name,employee_number,branch_id')
            ->when($search !== null, function ($query) use ($search): void {
                $query->whereHas('employee', function ($inner) use ($search): void {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($context !== null, fn ($q) => $q->where('context', $context))
            ->when($outcome !== null, fn ($q) => $q->where('outcome', $outcome))
            ->when($platform !== null, fn ($q) => $q->where('platform', $platform));

        $this->applyBranchScopeViaEmployee($query, $request->user());

        $logs = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('avana/absensi-log/index', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (FaceScanLog $log): array => $this->transform($log))->all(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'context' => $context,
                'outcome' => $outcome,
                'platform' => $platform,
                'per_page' => (string) $perPage,
            ],
            'summary' => $this->summary($request, $tenantId),
        ]);
    }

    /**
     * Failure mix over the last seven days, split by platform — the quickest
     * way to tell "face recognition is broken" from "broken on iPhone".
     *
     * @return array<string, mixed>
     */
    private function summary(Request $request, int|string $tenantId): array
    {
        $since = now()->subDays(7);

        $base = fn () => tap(
            FaceScanLog::forTenant($tenantId)->where('created_at', '>=', $since),
            fn ($query) => $this->applyBranchScopeViaEmployee($query, $request->user()),
        );

        $byPlatform = $base()
            ->selectRaw('platform, outcome, count(*) as c')
            ->groupBy('platform', 'outcome')
            ->get()
            ->groupBy(fn ($row): string => $row->platform ?? 'lainnya')
            ->map(fn ($rows): array => [
                'ok' => (int) $rows->firstWhere('outcome', 'ok')?->c,
                'fail' => (int) $rows->firstWhere('outcome', 'fail')?->c,
                'blocked' => (int) $rows->firstWhere('outcome', 'blocked')?->c,
            ])
            ->all();

        $topReasons = $base()
            ->where('outcome', '!=', 'ok')
            ->selectRaw('reason, count(*) as c')
            ->groupBy('reason')
            ->orderByDesc('c')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'reason' => $row->reason,
                'label' => self::REASON_LABELS[$row->reason] ?? $row->reason,
                'count' => (int) $row->c,
            ])
            ->all();

        return ['by_platform' => $byPlatform, 'top_reasons' => $topReasons];
    }

    /**
     * Shape a single log row for the front-end table.
     *
     * @return array<string, mixed>
     */
    private function transform(FaceScanLog $log): array
    {
        return [
            'id' => $log->id,
            'context' => $log->context,
            'outcome' => $log->outcome,
            'reason' => $log->reason,
            'reason_label' => self::REASON_LABELS[$log->reason] ?? $log->reason,
            'message' => $log->message,
            'step' => $log->step,
            'metrics' => $log->metrics,
            'employee' => $log->employee?->full_name,
            'employee_number' => $log->employee?->employee_number,
            'platform' => $log->platform,
            'os_version' => $log->os_version,
            'device_model' => $log->device_model,
            'app_version' => $log->app_version,
            'created_at' => $log->created_at?->translatedFormat('d M Y H:i:s'),
        ];
    }
}
