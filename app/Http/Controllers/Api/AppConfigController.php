<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use Illuminate\Http\JsonResponse;

/** Public app branding for the mobile splash / login screens. */
class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = WebsiteSetting::cached();

        return response()->json([
            'data' => array_merge(
                $settings->toBrandingArray(),
                ['favicon_url' => $settings->faviconUrl()],
            ),
        ]);
    }
}
