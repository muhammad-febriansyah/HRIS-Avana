<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Sop;
use App\Models\User;
use App\Services\AiToolkit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "SOP Perusahaan" — the read-only SOP library an employee may browse.
 *
 * Mirrors what the AI assistant is allowed to quote: the `public` documents for
 * everyone, plus the `private` ones for a user who also holds `sop.view`.
 * Reading the PDF directly beats having to ask the assistant for it.
 */
class EssSopController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * List the active SOPs this employee may read, grouped by category.
     */
    public function index(Request $request): Response
    {
        $this->currentEmployee($request);

        $sops = $this->readable($request->user())
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

        return Inertia::render('avana/saya/sop', [
            'sops' => $sops,
            'categories' => $sops->pluck('category')->unique()->sort()->values(),
        ]);
    }

    /**
     * Stream an SOP's PDF to an employee allowed to read it.
     */
    public function download(Request $request, Sop $sop): StreamedResponse
    {
        $this->currentEmployee($request);

        // Re-run the visibility filter on the record itself: a private SOP must
        // never be reachable by guessing its id.
        abort_unless($this->readable($request->user())->whereKey($sop->getKey())->exists(), 404);
        abort_if($sop->file_path === null || ! Storage::disk('local')->exists($sop->file_path), 404);

        return Storage::disk('local')->download(
            $sop->file_path,
            $sop->file_name ?? ($sop->title.'.pdf'),
        );
    }

    /**
     * The SOPs a user may read: `public` for everyone, everything for a holder
     * of `sop.view`. Same rule as {@see AiToolkit::visibleSops()}.
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
