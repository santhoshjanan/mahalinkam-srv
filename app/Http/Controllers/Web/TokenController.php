<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Tokens', [
            'tokens' => $request->user()->tokens()
                ->latest()
                ->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($validated['name']);

        return back()->with('flash', ['token' => $token->plainTextToken]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->tokens()->where('id', $id)->firstOrFail()->delete();

        return back();
    }
}
