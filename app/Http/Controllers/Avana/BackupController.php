<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DatabaseDumper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export the platform database.
 *
 * This hands the whole application's data to whoever asks, so the gate is the
 * feature: super admin only, and every download is written to the audit trail
 * with the actor and their address. Nothing here is tenant-scoped on purpose —
 * a platform backup that silently omitted rows would restore into a broken
 * copy, which is worse than no backup.
 */
class BackupController extends Controller
{
    /**
     * Show what the database holds, so the size of the download is known before
     * it is started.
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        try {
            $dumper = new DatabaseDumper;
            $tables = $dumper->summary($dumper->tables());
            $error = null;
        } catch (RuntimeException $exception) {
            $tables = [];
            $error = $exception->getMessage();
        }

        return Inertia::render('avana/backup/index', [
            'tables' => $tables,
            'totalTables' => count($tables),
            'totalRows' => array_sum(array_column($tables, 'rows')),
            'connection' => config('database.default'),
            'error' => $error,
        ]);
    }

    /**
     * Stream the dump as a `.sql` download.
     *
     * Streamed rather than assembled: a production database does not fit in a
     * request's memory, and the client should start receiving bytes before the
     * last table has been read.
     */
    public function download(Request $request): StreamedResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'tables' => ['sometimes', 'array'],
            'tables.*' => ['string'],
            'with_data' => ['sometimes', 'boolean'],
            'compress' => ['sometimes', 'boolean'],
        ]);

        $dumper = new DatabaseDumper;
        $available = $dumper->tables();

        $requested = $data['tables'] ?? [];
        $tables = $requested === []
            ? $available
            : array_values(array_intersect($available, $requested));

        abort_if($tables === [], 422, 'Tidak ada tabel yang cocok untuk diekspor.');

        $withData = (bool) ($data['with_data'] ?? true);
        $compress = (bool) ($data['compress'] ?? false);

        $this->recordDownload($request, $tables, $withData);

        $name = 'avanahr-'.config('database.default').'-'.now()->format('Ymd-His').'.sql'.($compress ? '.gz' : '');

        return response()->streamDownload(function () use ($dumper, $tables, $withData, $compress): void {
            $sink = $compress ? deflate_init(ZLIB_ENCODING_GZIP) : null;

            foreach ($dumper->dump($tables, $withData) as $chunk) {
                echo $sink !== null ? deflate_add($sink, $chunk, ZLIB_NO_FLUSH) : $chunk;
                flush();
            }

            if ($sink !== null) {
                echo deflate_add($sink, '', ZLIB_FINISH);
                flush();
            }
        }, $name, [
            'Content-Type' => $compress ? 'application/gzip' : 'application/sql',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Write the download to the audit trail.
     *
     * An export is the one action that can carry every record out of the
     * building, so it leaves a mark naming who took it and what they took.
     *
     * @param  array<int, string>  $tables
     */
    private function recordDownload(Request $request, array $tables, bool $withData): void
    {
        /** @var User $user */
        $user = $request->user();

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'auditable_type' => 'database',
            'auditable_id' => 0,
            'action' => 'export',
            'new_values' => [
                'tables' => count($tables),
                'with_data' => $withData,
                'connection' => config('database.default'),
            ],
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Abort with 403 unless the acting user is a platform super admin.
     */
    private function ensureSuperAdmin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->roles()->where('code', 'super_admin')->exists(), 403);
    }
}
