<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\PartnerDocumentDownload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PartnerDocumentController extends Controller
{
    public function partnership(): InertiaResponse
    {
        return Inertia::render('public/partnership', [
            'faqs' => Faq::query()
                ->latest('id')
                ->get(['id', 'question', 'answer'])
                ->map(fn (Faq $faq): array => [
                    'q' => $faq->question,
                    'a' => $faq->answer,
                ])
                ->values(),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $path = public_path('avana/company-profile-avana-hr.pdf');

        abort_unless(is_file($path), 404);

        PartnerDocumentDownload::create([
            'visitor_hash' => hash('sha256', $request->ip().'|'.$request->userAgent()),
            // Set when a logged-in mitra downloads from their portal; null
            // for an anonymous visitor on the public partner page.
            'user_id' => $request->user()?->id,
        ]);

        return response()->download($path, 'Company-Profile-AvanaHR.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
