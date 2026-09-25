<?php

namespace App\Http\Controllers;

use App\Models\ExternalApiKey;
use Illuminate\Http\Request;

class ExternalApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $keys = $this->keys($request);
        if ($request->expectsJson()) {
            return response()->json($keys)->header('Cache-Control', 'no-store');
        }

        return response()->view('developer-api-keys', ['keys' => $keys, 'plainTextKey' => null])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'expires_at' => 'nullable|date_format:Y-m-d|after:today',
        ]);
        [$key, $secret] = ExternalApiKey::issue($request->user(), $data['name'], $data['expires_at'] ?? null);
        if ($request->expectsJson()) {
            return response()->json(['data' => $key->summary(), 'plain_text_key' => $secret], 201)
                ->header('Cache-Control', 'no-store');
        }

        // Render the secret once; never put it in flash data, URLs, or persisted session state.
        return response()->view('developer-api-keys', ['keys' => $this->keys($request), 'plainTextKey' => $secret], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function revoke(Request $request, ExternalApiKey $key)
    {
        abort_unless((int) $key->user_id === (int) $request->user()->id, 404);
        if (!$key->revoked_at) {
            $key->forceFill(['revoked_at' => now()])->save();
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => $key->summary()])->header('Cache-Control', 'no-store');
        }

        return redirect()->route('developer.api-keys.index')->with('success', 'API key revoked.');
    }

    private function keys(Request $request): array
    {
        $page = ExternalApiKey::where('user_id', $request->user()->id)->latest('id')->paginate(20);

        return [
            'data' => $page->getCollection()->map(fn ($key) => $key->summary())->all(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ];
    }
}
