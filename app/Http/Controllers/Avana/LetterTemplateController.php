<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\GeneratedLetter;
use App\Models\LetterTemplate;
use App\Models\Tenant;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LetterTemplateController extends Controller
{
    /**
     * Roles that may always manage letter templates within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed letter template type enum values.
     *
     * @var array<int, string>
     */
    private const TYPES = ['kontrak', 'sk', 'paklaring', 'referensi', 'custom'];

    /**
     * Display the letter templates together with recently generated letters.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $templates = LetterTemplate::forTenant($tenantId)
            ->latest('id')
            ->get()
            ->map(fn (LetterTemplate $template): array => $this->transformTemplate($template));

        $generatedLetters = GeneratedLetter::forTenant($tenantId)
            ->with('employee:id,full_name')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (GeneratedLetter $letter): array => $this->transformGeneratedLetter($letter));

        return Inertia::render('avana/surat/index', [
            'templates' => $templates,
            'generatedLetters' => $generatedLetters,
            'employees' => $this->employeeOptions($tenantId),
            'templateOptions' => LetterTemplate::forTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (LetterTemplate $template): array => [
                    'value' => $template->id,
                    'label' => $template->name,
                ])
                ->all(),
            'types' => $this->typeOptions(),
        ]);
    }

    /**
     * Show the form for creating a new letter template.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('avana/surat/create', [
            'types' => $this->typeOptions(),
            'placeholders' => $this->placeholderTokens(),
        ]);
    }

    /**
     * Show the form for editing an existing letter template.
     */
    public function edit(Request $request, LetterTemplate $letterTemplate): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $letterTemplate);

        return Inertia::render('avana/surat/edit', [
            'template' => [
                'id' => $letterTemplate->id,
                'name' => $letterTemplate->name,
                'type' => $letterTemplate->type,
                'body' => $letterTemplate->body,
                'is_active' => $letterTemplate->is_active,
            ],
            'types' => $this->typeOptions(),
            'placeholders' => $this->placeholderTokens(),
        ]);
    }

    /**
     * Persist a new letter template under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateTemplate($request);

        LetterTemplate::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.surat')
            ->with('success', 'Template surat berhasil ditambahkan');
    }

    /**
     * Update an existing letter template.
     */
    public function update(Request $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $letterTemplate);

        $letterTemplate->update($this->validateTemplate($request));

        return redirect()->route('avana.surat')
            ->with('success', 'Template surat berhasil diperbarui');
    }

    /**
     * Delete a letter template.
     */
    public function destroy(Request $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $letterTemplate);

        $letterTemplate->delete();

        return back()->with('success', 'Template surat dihapus');
    }

    /**
     * Render a template against an employee and persist the generated letter.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'letter_template_id' => [
                'required',
                'integer',
                Rule::exists('letter_templates', 'id')->where('tenant_id', $tenantId),
            ],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'letter_number' => ['nullable', 'string', 'max:255'],
            'generated_at' => ['nullable', 'date'],
        ]);

        $template = LetterTemplate::forTenant($tenantId)->findOrFail($data['letter_template_id']);
        $employee = Employee::forTenant($tenantId)
            ->with(['position:id,name', 'tenant'])
            ->findOrFail($data['employee_id']);

        $date = $data['generated_at'] ?? now()->toDateString();

        GeneratedLetter::create([
            'tenant_id' => $tenantId,
            'letter_template_id' => $template->id,
            'employee_id' => $employee->id,
            'letter_number' => $data['letter_number'] ?? null,
            'title' => $template->name.' - '.$employee->full_name,
            'body' => $this->renderBody($template->body, $employee, $date),
            'generated_at' => $date,
        ]);

        return redirect()->route('avana.surat')
            ->with('success', 'Surat berhasil dibuat');
    }

    /**
     * Render a minimal printable view for a single generated letter.
     */
    public function print(Request $request, GeneratedLetter $generatedLetter): \Illuminate\Http\Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $generatedLetter);

        $pdf = Pdf::loadView('pdf.surat', [
            'letter' => [
                'title' => $generatedLetter->title,
                'body' => $generatedLetter->body,
                'letter_number' => $generatedLetter->letter_number,
                'generated_at_label' => $generatedLetter->generated_at
                    ? $this->formatDate($generatedLetter->generated_at->toDateString())
                    : null,
            ],
            'company' => [
                'name' => $this->companyName($request->user()->tenant_id),
            ],
        ])->setPaper('a4');

        return $pdf->download('surat-'.$generatedLetter->id.'.pdf');
    }

    /**
     * Delete a generated letter.
     */
    public function destroyGenerated(Request $request, GeneratedLetter $generatedLetter): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $generatedLetter);

        $generatedLetter->delete();

        return back()->with('success', 'Surat dihapus');
    }

    /**
     * Validate the create/update payload for a letter template.
     *
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'body' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /**
     * Replace the supported `{{token}}` placeholders inside a template body.
     *
     * Matching is case-insensitive and tolerant of surrounding whitespace.
     * Missing columns/relations resolve to an empty string rather than crash.
     */
    private function renderBody(string $body, Employee $employee, ?string $date): string
    {
        $tenant = $employee->tenant;

        $replacements = [
            'nama' => (string) ($employee->full_name ?? ''),
            'nip' => (string) ($employee->employee_number ?? ''),
            'jabatan' => (string) ($employee->position?->name ?? ''),
            'tanggal' => $this->formatDate($date),
            'perusahaan' => $tenant
                ? (string) ($tenant->company_name ?: $tenant->name)
                : '',
        ];

        $rendered = $body;

        foreach ($replacements as $token => $value) {
            $rendered = preg_replace_callback(
                '/\{\{\s*'.$token.'\s*\}\}/i',
                static fn (): string => $value,
                $rendered,
            ) ?? $rendered;
        }

        return $rendered;
    }

    /**
     * Format a date string into an Indonesian long date (e.g. 1 Juli 2026).
     */
    private function formatDate(?string $date): string
    {
        return Carbon::parse($date ?? now())
            ->locale('id')
            ->translatedFormat('d F Y');
    }

    /**
     * Resolve the tenant's display company name, falling back to its name.
     */
    private function companyName(int $tenantId): string
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return '';
        }

        return (string) ($tenant->company_name ?: $tenant->name);
    }

    /**
     * Build the row shape consumed by the templates table.
     *
     * @return array<string, mixed>
     */
    private function transformTemplate(LetterTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'type_label' => $this->typeLabel($template->type),
            'is_active' => $template->is_active,
            'updated_at' => $template->updated_at?->toDateString(),
        ];
    }

    /**
     * Build the row shape consumed by the generated-letters table.
     *
     * @return array<string, mixed>
     */
    private function transformGeneratedLetter(GeneratedLetter $letter): array
    {
        return [
            'id' => $letter->id,
            'title' => $letter->title,
            'employee_name' => $letter->employee?->full_name,
            'letter_number' => $letter->letter_number,
            'generated_at' => $letter->generated_at?->toDateString(),
        ];
    }

    /**
     * Build the tenant's selectable employee options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of template type enum options.
     *
     * @return array<int, array<string, string>>
     */
    private function typeOptions(): array
    {
        return collect(self::TYPES)
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => $this->typeLabel($type),
            ])
            ->all();
    }

    /**
     * Indonesian label for a template type enum value.
     */
    private function typeLabel(string $type): string
    {
        return [
            'kontrak' => 'Surat Kontrak',
            'sk' => 'Surat Keputusan',
            'paklaring' => 'Paklaring',
            'referensi' => 'Surat Referensi',
            'custom' => 'Kustom',
        ][$type] ?? $type;
    }

    /**
     * The placeholder tokens that a template body may reference.
     *
     * @return array<int, array<string, string>>
     */
    private function placeholderTokens(): array
    {
        return [
            ['token' => '{{nama}}', 'label' => 'Nama karyawan'],
            ['token' => '{{nip}}', 'label' => 'Nomor induk karyawan'],
            ['token' => '{{jabatan}}', 'label' => 'Jabatan karyawan'],
            ['token' => '{{tanggal}}', 'label' => 'Tanggal surat'],
            ['token' => '{{perusahaan}}', 'label' => 'Nama perusahaan'],
        ];
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, LetterTemplate|GeneratedLetter $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}
