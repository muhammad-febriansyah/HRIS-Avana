<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('avana/faqs/index', [
            'faqs' => Faq::query()->latest('id')->get(['id', 'question', 'answer']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);
        Faq::create($this->validated($request));

        return back()->with('success', 'FAQ berhasil ditambahkan');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $this->ensureSuperAdmin($request);
        $faq->update($this->validated($request));

        return back()->with('success', 'FAQ berhasil diperbarui');
    }

    public function destroy(Request $request, Faq $faq): RedirectResponse
    {
        $this->ensureSuperAdmin($request);
        $faq->delete();

        return back()->with('success', 'FAQ berhasil dihapus');
    }

    /** @return array{question: string, answer: string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
        ]);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isSuperAdmin(), 403);
    }
}
