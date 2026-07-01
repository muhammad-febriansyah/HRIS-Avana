<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    /**
     * Roles allowed to manage custom field definitions.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Supported field input types.
     *
     * @var array<int, string>
     */
    private const TYPES = ['text', 'number', 'date', 'select'];

    /**
     * List the tenant's custom field definitions.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $fields = CustomField::forTenant($tenantId)
            ->where('entity', 'employee')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'key', 'label', 'type', 'options', 'is_required', 'sort_order', 'status'])
            ->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $field->options ?? [],
                'is_required' => $field->is_required,
                'sort_order' => $field->sort_order,
                'status' => $field->status,
            ]);

        return Inertia::render('avana/custom-fields/index', [
            'fields' => $fields,
            'types' => self::TYPES,
        ]);
    }

    /**
     * Create a new custom field definition.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validated($request);

        $key = Str::slug($data['label'], '_');
        $base = $key !== '' ? $key : 'field';
        $key = $base;
        $suffix = 1;

        while (CustomField::forTenant($tenantId)->where('entity', 'employee')->where('key', $key)->exists()) {
            $key = $base.'_'.(++$suffix);
        }

        CustomField::create([
            'tenant_id' => $tenantId,
            'entity' => 'employee',
            'key' => $key,
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $data['type'] === 'select' ? $this->parseOptions($data['options'] ?? null) : null,
            'is_required' => $data['is_required'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => 'active',
        ]);

        return back()->with('success', 'Field kustom ditambahkan');
    }

    /**
     * Update an existing custom field definition.
     */
    public function update(Request $request, CustomField $field): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $field);

        $data = $this->validated($request);

        $field->update([
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $data['type'] === 'select' ? $this->parseOptions($data['options'] ?? null) : null,
            'is_required' => $data['is_required'] ?? false,
            'sort_order' => $data['sort_order'] ?? $field->sort_order,
            'status' => $data['status'] ?? $field->status,
        ]);

        return back()->with('success', 'Field kustom diperbarui');
    }

    /**
     * Delete a custom field definition.
     */
    public function destroy(Request $request, CustomField $field): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $field);

        $field->delete();

        return back()->with('success', 'Field kustom dihapus');
    }

    /**
     * Validate the custom-field payload.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'options' => ['nullable'],
            'is_required' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    /**
     * Normalise select options from an array or comma/newline string.
     *
     * @param  array<int, string>|string|null  $raw
     * @return array<int, string>
     */
    private function parseOptions(array|string|null $raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw)) {
            $values = preg_split('/[\r\n,]+/', $raw) ?: [];
        } else {
            $values = [];
        }

        return array_values(array_filter(array_map('trim', $values), fn (string $v): bool => $v !== ''));
    }

    /**
     * Abort with 404 when the record is outside the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, CustomField $field): void
    {
        abort_if((int) $field->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user holds a privileged role.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless($user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty(), 403);
    }
}
