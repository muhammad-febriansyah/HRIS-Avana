<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    /**
     * Roles that may always manage employee surveys within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed survey status enum values.
     *
     * @var array<int, string>
     */
    private const STATUSES = ['draft', 'active', 'closed'];

    /**
     * Allowed survey question type enum values.
     *
     * @var array<int, string>
     */
    private const QUESTION_TYPES = ['rating', 'text', 'choice'];

    /**
     * Display the surveys with their question builder and a response summary.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $surveys = Survey::forTenant($tenantId)
            ->with(['questions' => fn ($query) => $query->orderBy('id'), 'questions.responses'])
            ->withCount('responses')
            ->latest('id')
            ->get()
            ->map(fn (Survey $survey): array => $this->shapeSurvey($survey));

        return Inertia::render('avana/survei/index', [
            'surveys' => $surveys,
            'employees' => $this->employeeOptions($tenantId),
            'kpis' => [
                'active_surveys' => $surveys->where('status', 'active')->count(),
                'total_responses' => $surveys->sum('responses_count'),
                'total_surveys' => $surveys->count(),
            ],
        ]);
    }

    /**
     * Persist a new survey under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'is_anonymous' => ['boolean'],
        ]);

        Survey::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'is_anonymous' => $data['is_anonymous'] ?? true,
        ]);

        return redirect()->route('avana.survei')
            ->with('success', 'Survei berhasil dibuat');
    }

    /**
     * Append a question to an existing survey within the acting user's tenant.
     */
    public function storeQuestion(Request $request, Survey $survey): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $survey);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in(self::QUESTION_TYPES)],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
        ]);

        SurveyQuestion::create([
            'tenant_id' => $survey->tenant_id,
            'survey_id' => $survey->id,
            'question' => $data['question'],
            'type' => $data['type'],
            'options' => $data['type'] === 'choice' ? array_values($data['options'] ?? []) : null,
        ]);

        return back()->with('success', 'Pertanyaan ditambahkan');
    }

    /**
     * Record a respondent's answers for the survey's questions.
     */
    public function respond(Request $request, Survey $survey): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $survey);

        $tenantId = $survey->tenant_id;

        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', "exists:employees,id,tenant_id,{$tenantId}"],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.survey_question_id' => [
                'required',
                'integer',
                Rule::exists('survey_questions', 'id')->where('survey_id', $survey->id),
            ],
            'answers.*.answer' => ['nullable', 'string'],
        ]);

        $employeeId = $survey->is_anonymous ? null : ($data['employee_id'] ?? null);

        foreach ($data['answers'] as $answer) {
            SurveyResponse::create([
                'tenant_id' => $tenantId,
                'survey_id' => $survey->id,
                'survey_question_id' => $answer['survey_question_id'],
                'employee_id' => $employeeId,
                'answer' => $answer['answer'] ?? null,
            ]);
        }

        return back()->with('success', 'Respons survei tersimpan');
    }

    /**
     * Delete a survey together with its questions and responses.
     */
    public function destroy(Request $request, Survey $survey): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $survey);

        $survey->delete();

        return back()->with('success', 'Survei dihapus');
    }

    /**
     * Build the row shape (with question + response summary) for the index page.
     *
     * @return array<string, mixed>
     */
    private function shapeSurvey(Survey $survey): array
    {
        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'description' => $survey->description,
            'status' => $survey->status,
            'is_anonymous' => (bool) $survey->is_anonymous,
            'responses_count' => $survey->responses_count,
            'questions' => $survey->questions->map(fn (SurveyQuestion $question): array => [
                'id' => $question->id,
                'question' => $question->question,
                'type' => $question->type,
                'options' => $question->options,
                'response_count' => $question->responses->count(),
                'avg_rating' => $question->type === 'rating' && $question->responses->isNotEmpty()
                    ? round((float) $question->responses->avg(fn (SurveyResponse $response): float => (float) $response->answer), 2)
                    : null,
            ])->all(),
        ];
    }

    /**
     * Build the selectable employee option list for the acting tenant.
     *
     * @return Collection<int, array{id: int, name: string, employee_number: string|null}>
     */
    private function employeeOptions(int $tenantId): Collection
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]);
    }

    /**
     * Abort with 404 when the survey does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Survey $survey): void
    {
        abort_if((int) $survey->tenant_id !== (int) $request->user()->tenant_id, 404);
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
