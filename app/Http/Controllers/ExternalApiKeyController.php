<?php

namespace App\Http\Controllers;

use App\Models\ExternalApiKey;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        // The creation fallback renders a list too; only GET query parameters are list filters.
        $input = $request->isMethod('GET') ? $request->query() : [];
        $rules = [
            'search' => 'nullable|string|max:255',
            'statuses' => 'nullable|array|max:3',
            'statuses.*' => 'required|string|in:Active,Expired,Revoked',
            'expiration' => 'nullable|array|max:2',
            'expiration.*' => 'required|string|in:never,dated',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'sort' => 'nullable|string|in:newest,oldest,name_asc,expires_asc,last_used_desc',
            'per_page' => 'nullable|integer|in:10,20,50,100',
            'page' => 'nullable|integer|min:1|max:1000000',
        ];
        if (!empty($input['from'])) {
            $rules['to'] .= '|after_or_equal:from';
        }
        $validated = Validator::make($input, $rules)->validate();
        $filters = [
            'search' => trim($validated['search'] ?? ''),
            'statuses' => array_values(array_unique($validated['statuses'] ?? [])),
            'expiration' => array_values(array_unique($validated['expiration'] ?? [])),
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
            'sort' => $validated['sort'] ?? 'newest',
        ];
        $perPage = (int) ($validated['per_page'] ?? 20);
        $pageNumber = (int) ($validated['page'] ?? 1);
        $query = ExternalApiKey::where('user_id', $request->user()->id);
        if ($filters['search'] !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $query->where(function ($query) use ($search) {
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("prefix LIKE ? ESCAPE '!'", [$search]);
            });
        }
        if ($filters['statuses'] !== []) {
            $now = now('UTC');
            $query->where(function ($query) use ($filters, $now) {
                foreach ($filters['statuses'] as $status) {
                    $query->orWhere(function ($query) use ($status, $now) {
                        if ($status === 'Revoked') {
                            $query->whereNotNull('revoked_at');
                        } elseif ($status === 'Expired') {
                            $query->whereNull('revoked_at')->where('expires_at', '<=', $now);
                        } else {
                            $query->whereNull('revoked_at')->where(function ($query) use ($now) {
                                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                            });
                        }
                    });
                }
            });
        }
        if (count($filters['expiration']) === 1) {
            $filters['expiration'][0] === 'never'
                ? $query->whereNull('expires_at')
                : $query->whereNotNull('expires_at');
        }
        if ($filters['from'] !== '') {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $query->where('created_at', '<', Carbon::createFromFormat('!Y-m-d', $filters['to'], 'UTC')->addDay());
        }
        if ($filters['sort'] === 'name_asc') {
            $query->orderBy('name');
        } elseif ($filters['sort'] === 'expires_asc') {
            $query->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')->orderBy('expires_at');
        } elseif ($filters['sort'] === 'last_used_desc') {
            $query->orderByRaw('CASE WHEN last_used_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('last_used_at');
        }
        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';
        $query->orderBy('created_at', $direction)->orderBy('id', $direction);
        $columns = ['id', 'name', 'prefix', 'created_at', 'last_used_at', 'expires_at', 'revoked_at'];
        $page = (clone $query)->paginate($perPage, $columns, 'page', $pageNumber);
        if ($pageNumber > $page->lastPage()) {
            $page = $query->paginate($perPage, $columns, 'page', $page->lastPage());
        }

        return [
            'data' => $page->getCollection()->map(fn ($key) => $key->summary())->all(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
            'per_page' => $page->perPage(), 'filters' => $filters,
        ];
    }
}
