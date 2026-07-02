<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSlide;
use Illuminate\Http\JsonResponse;

/**
 * Public endpoint serving the mobile app's onboarding/intro slides. No auth —
 * shown before login, mirroring {@see AppConfigController}.
 */
class OnboardingSlideController extends Controller
{
    /**
     * Active slides in display order.
     */
    public function index(): JsonResponse
    {
        $slides = OnboardingSlide::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (OnboardingSlide $slide): array => $slide->toApiArray());

        return response()->json(['data' => $slides]);
    }
}
