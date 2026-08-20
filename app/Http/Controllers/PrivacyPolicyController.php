<?php

namespace App\Http\Controllers;

use App\Models\WebsiteSetting;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public privacy policy — content is editable by a super admin (Website
 * Settings → Kebijakan Privasi) instead of hardcoded, since this is the URL
 * submitted to app store listings (Play Console, App Store Connect).
 */
class PrivacyPolicyController extends Controller
{
    public function __invoke(): Response
    {
        $settings = WebsiteSetting::cached();

        return Inertia::render('public/legal/privacy', [
            'content' => $settings->privacy_policy ?? WebsiteSetting::defaultPrivacyPolicyHtml(),
        ]);
    }
}
