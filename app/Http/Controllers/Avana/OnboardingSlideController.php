<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSlide;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin CRUD for the mobile app onboarding/intro slides. Each slide has a
 * title, optional subtitle and an image (SVG vector or raster) stored on the
 * public disk; replacing/removing an image deletes the previous file so stale
 * uploads don't accumulate.
 */
class OnboardingSlideController extends Controller
{
    use AuthorizesRequests;

    private const DISK = 'public';

    private const FOLDER = 'onboarding';

    /**
     * List every slide in display order.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OnboardingSlide::class);

        $slides = OnboardingSlide::query()->ordered()->get()->map(fn (OnboardingSlide $slide): array => [
            'id' => $slide->id,
            'title' => $slide->title,
            'subtitle' => $slide->subtitle,
            'image_url' => $slide->imageUrl(),
            'sort_order' => $slide->sort_order,
            'is_active' => $slide->is_active,
        ]);

        return Inertia::render('avana/onboarding-slides/index', [
            'slides' => $slides,
        ]);
    }

    /**
     * Create a new slide.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', OnboardingSlide::class);

        $validated = $this->validateSlide($request);

        $slide = new OnboardingSlide;
        $slide->fill($this->textAttributes($request, $validated));

        if ($request->file('image') instanceof UploadedFile) {
            $slide->image_path = $request->file('image')->store(self::FOLDER, self::DISK);
        }

        $slide->save();

        return back()->with('success', 'Slide onboarding berhasil ditambahkan');
    }

    /**
     * Update an existing slide.
     */
    public function update(Request $request, OnboardingSlide $onboardingSlide): RedirectResponse
    {
        $this->authorize('update', $onboardingSlide);

        $validated = $this->validateSlide($request);

        $onboardingSlide->fill($this->textAttributes($request, $validated));

        if ($request->file('image') instanceof UploadedFile) {
            $this->deleteFile($onboardingSlide->image_path);
            $onboardingSlide->image_path = $request->file('image')->store(self::FOLDER, self::DISK);
        } elseif ($request->boolean('remove_image')) {
            $this->deleteFile($onboardingSlide->image_path);
            $onboardingSlide->image_path = null;
        }

        $onboardingSlide->save();

        return back()->with('success', 'Slide onboarding berhasil diperbarui');
    }

    /**
     * Delete a slide and its stored image.
     */
    public function destroy(OnboardingSlide $onboardingSlide): RedirectResponse
    {
        $this->authorize('delete', $onboardingSlide);

        $this->deleteFile($onboardingSlide->image_path);
        $onboardingSlide->delete();

        return back()->with('success', 'Slide onboarding berhasil dihapus');
    }

    /**
     * Shared validation rules. SVG is allowed alongside common raster formats.
     *
     * @return array<string, mixed>
     */
    private function validateSlide(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp', 'max:2048'],
        ]);
    }

    /**
     * Non-file attributes to persist.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function textAttributes(Request $request, array $validated): array
    {
        return [
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Delete a previously stored file if it exists.
     */
    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
