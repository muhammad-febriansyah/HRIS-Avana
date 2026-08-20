<?php

namespace App\Http\Controllers;

use App\Models\WebsiteSetting;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public terms of service — same pattern as {@see PrivacyPolicyController}:
 * content is editable by a super admin instead of hardcoded.
 */
class TermsOfServiceController extends Controller
{
    public function __invoke(): Response
    {
        $settings = WebsiteSetting::cached();

        return Inertia::render('public/legal/terms', [
            'content' => $settings->terms_of_service ?? WebsiteSetting::defaultTermsOfServiceHtml(),
        ]);
    }
}
