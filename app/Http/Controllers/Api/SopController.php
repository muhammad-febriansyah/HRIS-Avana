<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Sop;
use App\Models\User;
use App\Services\AiToolkit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company SOPs an employee may read from the mobile app.
 *
 * Same visibility rule as the web self-service screen and the AI assistant:
 * `public` for everyone, plus `private` for a holder of `sop.view`.
 */
class SopController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $this->currentEmployee($request);

        $data = $this->readable($request->user())
            ->with('category:id,name')
            ->orderBy('title')
            ->get()
            ->map(fn (Sop $sop): array => [
                'id' => $sop->id,
                'title' => $sop->title,
                'code' => $sop->code,
                'category' => $sop->category?->name ?? 'Umum',
                'summary' => $sop->summary,
                'version' => $sop->version,
                'visibility' => $sop->visibility,
                'effective_date' => $sop->effective_date?->toDateString(),
                'file_name' => $sop->file_name,
                'has_file' => $sop->file_path !== null,
            ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Stream an SOP's PDF. The visibility filter is re-applied to the record
     * itself so a private SOP stays unreachable by guessing its id.
     */
    public function download(Request $request, Sop $sop): StreamedResponse
    {
        $this->currentEmployee($request);

        abort_unless($this->readable($request->user())->whereKey($sop->getKey())->exists(), 404);
        abort_if($sop->file_path === null || ! Storage::disk('local')->exists($sop->file_path), 404);

        return Storage::disk('local')->download(
            $sop->file_path,
            $sop->file_name ?? ($sop->title.'.pdf'),
        );
    }

    /**
     * Mirrors {@see AiToolkit::visibleSops()} — keep the two in step, or the
     * app would list documents the assistant refuses to quote.
     *
     * @return Builder<Sop>
     */
    private function readable(User $user): Builder
    {
        $query = Sop::query()
            ->where('tenant_id', $user->tenant_id)
            ->active();

        if (! $user->isSuperAdmin() && ! $user->hasPermissionTo('sop.view')) {
            $query->publiclyVisible();
        }

        return $query;
    }
}
