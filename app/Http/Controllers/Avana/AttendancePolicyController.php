<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\User;
use App\Support\DeviceIntegrity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-tenant attendance anti-spoofing policy. HR admins decide whether a face
 * mismatch or a failed device-integrity check blocks a punch or merely flags it
 * for review, and toggle mandatory face enrollment and the liveness challenge.
 */
class AttendancePolicyController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function edit(Request $request): Response
    {
        $this->ensureCanManage($request);

        $policy = AttendancePolicy::resolve((int) ($request->user()->tenant_id ?? 0));

        return Inertia::render('avana/absensi-kebijakan/index', [
            'policy' => [
                'attendance_scope' => $policy->attendance_scope ?? AttendancePolicy::SCOPE_ASSIGNED,
                'device_binding_enabled' => (bool) ($policy->device_binding_enabled ?? true),
                'face_mode' => $policy->face_mode ?? AttendancePolicy::FACE_MODE_RECOGNITION,
                'require_face_enrollment' => (bool) $policy->require_face_enrollment,
                'require_liveness_challenge' => (bool) $policy->require_liveness_challenge,
                'face_enforcement' => $policy->face_enforcement,
                'integrity_enforcement' => $policy->integrity_enforcement,
                'block_mock_location' => (bool) $policy->block_mock_location,
                'block_rooted' => (bool) $policy->block_rooted,
                'block_emulator' => (bool) $policy->block_emulator,
            ],
            'attestationEnabled' => DeviceIntegrity::attestationEnabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $data = $request->validate([
            'attendance_scope' => ['required', Rule::in(AttendancePolicy::SCOPES)],
            'device_binding_enabled' => ['boolean'],
            'face_mode' => ['sometimes', Rule::in(AttendancePolicy::FACE_MODES)],
            'require_face_enrollment' => ['boolean'],
            'require_liveness_challenge' => ['boolean'],
            'face_enforcement' => ['required', Rule::in(['block', 'flag'])],
            'integrity_enforcement' => ['required', Rule::in(['block', 'flag'])],
            'block_mock_location' => ['boolean'],
            'block_rooted' => ['boolean'],
            'block_emulator' => ['boolean'],
        ]);

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => (int) ($request->user()->tenant_id ?? 0)],
            $data,
        );

        return back()->with('success', 'Kebijakan absensi tersimpan.');
    }

    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless($user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty(), 403);
    }
}
