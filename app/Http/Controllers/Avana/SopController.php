<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Sop;
use App\Models\SopCategory;
use App\Models\User;
use App\Services\AiToolkit;
use App\Support\PdfTextExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD for the tenant's SOP library: the "Jenis SOP" master (categories) and
 * the SOP documents themselves.
 *
 * Every uploaded PDF is run through {@see PdfTextExtractor} so the AI assistant
 * can answer from its actual content. `visibility` decides who the assistant
 * may quote a document to — see {@see AiToolkit}.
 */
class SopController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'sop';

    /**
     * Private disk holding the SOP PDFs; they are served through
     * {@see self::download()} so private documents never get a public URL.
     */
    private const DISK = 'local';

    /**
     * List the tenant's SOP documents alongside the category master.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $sops = Sop::forTenant($tenantId)
            ->with(['category:id,name', 'uploader:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Sop $sop): array => $this->transformSop($sop));

        $categories = SopCategory::forTenant($tenantId)
            ->withCount('sops')
            ->orderBy('name')
            ->get()
            ->map(fn (SopCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description,
                'status' => $category->status,
                'sop_count' => $category->sops_count,
            ]);

        return Inertia::render('avana/sop/index', [
            'sops' => $sops,
            'categories' => $categories,
            'kpis' => [
                'total' => $sops->count(),
                'public' => $sops->where('visibility', Sop::VISIBILITY_PUBLIC)->count(),
                'private' => $sops->where('visibility', Sop::VISIBILITY_PRIVATE)->count(),
                'categories' => $categories->count(),
                'indexed' => $sops->where('has_content', true)->count(),
            ],
        ]);
    }

    /**
     * Store a new SOP document and index its PDF text for the assistant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate(
            $this->rules($tenantId, fileRequired: true),
            $this->messages(),
        );

        $file = $request->file('file');
        $path = $file->store("sop/{$tenantId}", self::DISK);

        Sop::create([
            'tenant_id' => $tenantId,
            'sop_category_id' => $data['sop_category_id'] ?? null,
            'code' => $data['code'] ?? null,
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'content' => $this->resolveContent($request, $file->getRealPath()),
            'visibility' => $data['visibility'],
            'status' => $data['status'],
            'version' => $data['version'] ?? null,
            'effective_date' => $data['effective_date'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'SOP berhasil disimpan');
    }

    /**
     * Update an SOP, replacing its PDF (and re-indexing) when a new one is sent.
     */
    public function update(Request $request, Sop $sop): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $sop);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate(
            $this->rules($tenantId, fileRequired: false, recordId: (int) $sop->getKey()),
            $this->messages(),
        );

        $attributes = [
            'sop_category_id' => $data['sop_category_id'] ?? null,
            'code' => $data['code'] ?? null,
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'visibility' => $data['visibility'],
            'status' => $data['status'],
            'version' => $data['version'] ?? null,
            'effective_date' => $data['effective_date'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            if ($sop->file_path) {
                Storage::disk(self::DISK)->delete($sop->file_path);
            }

            $attributes['file_path'] = $file->store("sop/{$tenantId}", self::DISK);
            $attributes['file_name'] = $file->getClientOriginalName();
            $attributes['file_size'] = $file->getSize();
            $attributes['content'] = $this->resolveContent($request, $file->getRealPath());
        } elseif ($request->filled('content')) {
            $attributes['content'] = (string) $request->input('content');
        }

        $sop->update($attributes);

        return back()->with('success', 'SOP diperbarui');
    }

    /**
     * Soft delete an SOP and drop its stored PDF.
     */
    public function destroy(Request $request, Sop $sop): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $sop);

        if ($sop->file_path) {
            Storage::disk(self::DISK)->delete($sop->file_path);
        }

        $sop->update(['file_path' => null]);
        $sop->delete();

        return back()->with('success', 'SOP dihapus');
    }

    /**
     * Stream an SOP's PDF back to an authorised user.
     */
    public function download(Request $request, Sop $sop): StreamedResponse
    {
        $this->ensureCan($request, 'view');
        $this->ensureTenantOwnership($request, $sop);

        abort_if($sop->file_path === null || ! Storage::disk(self::DISK)->exists($sop->file_path), 404);

        return Storage::disk(self::DISK)->download(
            $sop->file_path,
            $sop->file_name ?? ($sop->title.'.pdf'),
        );
    }

    /**
     * Create a "Jenis SOP" (category) for the tenant.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate($this->categoryRules($tenantId), $this->messages());

        SopCategory::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Jenis SOP dibuat');
    }

    /**
     * Update a "Jenis SOP" belonging to the tenant.
     */
    public function updateCategory(Request $request, SopCategory $sopCategory): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $sopCategory);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate(
            $this->categoryRules($tenantId, (int) $sopCategory->getKey()),
            $this->messages(),
        );

        $sopCategory->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Jenis SOP diperbarui');
    }

    /**
     * Soft delete a "Jenis SOP"; its documents keep working, uncategorised.
     */
    public function destroyCategory(Request $request, SopCategory $sopCategory): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $sopCategory);

        Sop::forTenant((int) $request->user()->tenant_id)
            ->where('sop_category_id', $sopCategory->id)
            ->update(['sop_category_id' => null]);

        $sopCategory->delete();

        return back()->with('success', 'Jenis SOP dihapus');
    }

    /**
     * The text the assistant answers from: the admin's own text when supplied,
     * otherwise whatever can be pulled out of the uploaded PDF.
     */
    private function resolveContent(Request $request, string|false $realPath): ?string
    {
        $typed = trim((string) $request->input('content', ''));

        if ($typed !== '') {
            return $typed;
        }

        $extracted = $realPath === false ? '' : PdfTextExtractor::fromFile($realPath);

        return $extracted !== '' ? $extracted : null;
    }

    /**
     * Build the row shape consumed by the SOP table.
     *
     * @return array<string, mixed>
     */
    private function transformSop(Sop $sop): array
    {
        return [
            'id' => $sop->id,
            'sop_category_id' => $sop->sop_category_id,
            'category' => $sop->category?->name,
            'code' => $sop->code,
            'title' => $sop->title,
            'summary' => $sop->summary,
            'content' => $sop->content,
            'visibility' => $sop->visibility,
            'status' => $sop->status,
            'version' => $sop->version,
            'effective_date' => $sop->effective_date?->toDateString(),
            'file_name' => $sop->file_name,
            'file_size_label' => $this->humanFileSize($sop->file_size),
            'has_content' => filled($sop->content),
            'uploaded_by' => $sop->uploader?->name,
            'updated_at' => $sop->updated_at?->toDateString(),
        ];
    }

    /**
     * Tenant-scoped validation rules for an SOP document.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(int $tenantId, bool $fileRequired, ?int $recordId = null): array
    {
        $code = Rule::unique('sops', 'code')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at'));

        if ($recordId !== null) {
            $code->ignore($recordId);
        }

        return [
            'sop_category_id' => [
                'nullable',
                'integer',
                Rule::exists('sop_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => ['nullable', 'string', 'max:255', $code],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string', 'max:40000'],
            'visibility' => ['required', Rule::in([Sop::VISIBILITY_PRIVATE, Sop::VISIBILITY_PUBLIC])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'version' => ['nullable', 'string', 'max:50'],
            'effective_date' => ['nullable', 'date'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * Tenant-scoped validation rules for a "Jenis SOP".
     *
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(int $tenantId, ?int $recordId = null): array
    {
        $name = Rule::unique('sop_categories', 'name')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at'));

        if ($recordId !== null) {
            $name->ignore($recordId);
        }

        return [
            'name' => ['required', 'string', 'max:255', $name],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Indonesian validation messages shared by both forms.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'title.required' => 'Judul SOP wajib diisi.',
            'name.required' => 'Nama jenis SOP wajib diisi.',
            'name.unique' => 'Jenis SOP dengan nama ini sudah ada.',
            'code.unique' => 'Kode SOP sudah digunakan.',
            'visibility.required' => 'Tipe SOP wajib dipilih.',
            'visibility.in' => 'Tipe SOP harus private atau public.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
            'file.required' => 'Berkas PDF wajib diunggah.',
            'file.mimes' => 'Berkas SOP harus berformat PDF.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }

    /**
     * Render a byte count as a human-readable size label.
     */
    private function humanFileSize(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, 1).' '.$units[$unitIndex];
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Sop|SopCategory $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds the SOP permission.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}
