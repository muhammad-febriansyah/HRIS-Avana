<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * Roles that may always manage announcements within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Display the announcement feed: pinned first, then published, then draft.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $announcements = Announcement::forTenant($tenantId)
            ->get()
            ->sort(fn (Announcement $a, Announcement $b): int => $this->sortKey($b) <=> $this->sortKey($a))
            ->values()
            ->map(fn (Announcement $announcement): array => $this->transformAnnouncement($announcement));

        return Inertia::render('avana/pengumuman/index', [
            'announcements' => $announcements,
            'kpis' => [
                'total' => $announcements->count(),
                'published' => $announcements->where('status', 'published')->count(),
                'draft' => $announcements->where('status', 'draft')->count(),
            ],
        ]);
    }

    /**
     * Persist a new (draft) announcement under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateAnnouncement($request);

        Announcement::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'] ?? null,
            'pinned' => $data['pinned'] ?? false,
            'status' => 'draft',
        ]);

        return redirect()->route('avana.pengumuman')
            ->with('success', 'Pengumuman berhasil dibuat');
    }

    /**
     * Update an existing announcement.
     */
    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $announcement);

        $data = $this->validateAnnouncement($request);

        $announcement->update([
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'] ?? null,
            'pinned' => $data['pinned'] ?? false,
        ]);

        return redirect()->route('avana.pengumuman')
            ->with('success', 'Pengumuman berhasil diperbarui');
    }

    /**
     * Publish an announcement: set status published and stamp the publish time.
     */
    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $announcement);

        $announcement->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return back()->with('success', 'Pengumuman diterbitkan');
    }

    /**
     * Delete an announcement.
     */
    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $announcement);

        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus');
    }

    /**
     * Validate the create/update payload for an announcement.
     *
     * @return array<string, mixed>
     */
    private function validateAnnouncement(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'pinned' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Build the descending sort tuple used to order the feed.
     *
     * @return array<int, int>
     */
    private function sortKey(Announcement $announcement): array
    {
        return [
            $announcement->pinned ? 1 : 0,
            $announcement->status === 'published' ? 1 : 0,
            ($announcement->published_at ?? $announcement->created_at)?->getTimestamp() ?? 0,
        ];
    }

    /**
     * Build the card shape consumed by the announcement feed.
     *
     * @return array<string, mixed>
     */
    private function transformAnnouncement(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'category' => $announcement->category,
            'status' => $announcement->status,
            'pinned' => (bool) $announcement->pinned,
            'published_at' => $announcement->published_at?->toDateTimeString(),
            'created_at' => $announcement->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Announcement $announcement): void
    {
        abort_if((int) $announcement->tenant_id !== (int) $request->user()->tenant_id, 404);
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
