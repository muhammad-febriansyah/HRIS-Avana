<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * Roles that may always manage announcements within their tenant.
     *
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'announcement';

    /**
     * Display the announcement feed: pinned first, then published, then draft.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

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
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateAnnouncement($request);

        Announcement::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'] ?? null,
            'pinned' => $data['pinned'] ?? false,
            'status' => 'draft',
            ...$this->storeAttachment($request->file('attachment'), $tenantId),
        ]);

        return redirect()->route('avana.pengumuman')
            ->with('success', 'Pengumuman berhasil dibuat');
    }

    /**
     * Update an existing announcement.
     */
    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $announcement);

        $data = $this->validateAnnouncement($request);

        $attributes = [
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'] ?? null,
            'pinned' => $data['pinned'] ?? false,
        ];

        $file = $request->file('attachment');

        if ($file !== null) {
            // A replacement was uploaded: drop the old file before repointing.
            $announcement->deleteAttachmentFile();
            $attributes += $this->storeAttachment($file, (int) $announcement->tenant_id);
        } elseif ($request->boolean('remove_attachment')) {
            $announcement->deleteAttachmentFile();
            $attributes += $this->emptyAttachment();
        }

        $announcement->update($attributes);

        return redirect()->route('avana.pengumuman')
            ->with('success', 'Pengumuman berhasil diperbarui');
    }

    /**
     * Publish an announcement: set status published and stamp the publish time.
     */
    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCan($request, 'approve');
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
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $announcement);

        $announcement->deleteAttachmentFile();
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
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'remove_attachment' => ['nullable', 'boolean'],
        ], [
            'attachment.mimes' => 'Lampiran harus berupa PDF atau gambar (JPG, PNG, WEBP).',
            'attachment.max' => 'Ukuran lampiran maksimal 10 MB.',
        ]);
    }

    /**
     * Store an uploaded attachment and return the columns describing it.
     *
     * @return array<string, mixed>
     */
    private function storeAttachment(?UploadedFile $file, int $tenantId): array
    {
        if ($file === null) {
            return $this->emptyAttachment();
        }

        return [
            'attachment_path' => $file->store("announcements/{$tenantId}", Announcement::ATTACHMENT_DISK),
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    /**
     * The attachment columns cleared back to null.
     *
     * @return array<string, null>
     */
    private function emptyAttachment(): array
    {
        return [
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
            'attachment_size' => null,
        ];
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
            'attachment' => $announcement->attachment_path === null ? null : [
                'url' => $announcement->attachmentUrl(),
                'name' => $announcement->attachment_name,
                'mime' => $announcement->attachment_mime,
                'size' => $announcement->attachment_size,
                'is_image' => $announcement->attachmentIsImage(),
            ],
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
