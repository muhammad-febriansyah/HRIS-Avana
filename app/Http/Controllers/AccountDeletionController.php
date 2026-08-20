<?php

namespace App\Http\Controllers;

use App\Models\WebsiteSetting;
use Inertia\Inertia;
use Inertia\Response;

class AccountDeletionController extends Controller
{
    public function __invoke(): Response
    {
        $settings = WebsiteSetting::cached();

        return Inertia::render('public/legal/account-deletion', [
            'content' => $settings->account_deletion ?? WebsiteSetting::defaultAccountDeletionHtml(),
        ]);
    }
}
