<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AppActivityController extends Controller
{
    private const PAGE_SIZE = 20;
    private const RESPONSE_HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function index(Request $request)
    {
        $data = $request->validate(['cursor' => 'sometimes|nullable|string|max:1024']);
        $query = $request->user()->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id');
        if (isset($data['cursor'])) {
            $cursor = $this->decodeCursor($data['cursor']);
            $query->where(function ($query) use ($cursor) {
                $query->where('created_at', '<', $cursor['created_at'])
                    ->orWhere(function ($query) use ($cursor) {
                        $query->where('created_at', $cursor['created_at'])->where('id', '<', $cursor['id']);
                    });
            });
        }
        $notifications = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $notifications->count() > self::PAGE_SIZE;
        $page = $notifications->take(self::PAGE_SIZE)->values();

        return response()->json([
            'data' => $page->map(fn (DatabaseNotification $notification) => $this->activity($notification))->all(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'next_cursor' => $hasMore ? $this->encodeCursor($page->last()) : null,
        ], 200, self::RESPONSE_HEADERS);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        // Preserve the first reader's timestamp if another request won the race.
        $request->user()->notifications()->whereKey($notification->id)->whereNull('read_at')
            ->update(['read_at' => now()]);
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();

        return response()->json([
            'data' => $this->activity($notification),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], 200, self::RESPONSE_HEADERS);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->delete();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()], 200, self::RESPONSE_HEADERS);
    }

    public function clear(Request $request)
    {
        $request->user()->notifications()->delete();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()], 200, self::RESPONSE_HEADERS);
    }

    private function activity(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $url = $data['url'] ?? null;

        return [
            'id' => $notification->id,
            'title' => (string) ($data['title'] ?? 'App activity'),
            'body' => (string) ($data['body'] ?? ''),
            'url' => is_string($url) && preg_match('#\A/devices/[0-9]+\z#', $url) ? $url : null,
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }

    private function encodeCursor(DatabaseNotification $notification): string
    {
        return rtrim(strtr(base64_encode(json_encode([
            'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
            'id' => $notification->id,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decodeCursor(string $encoded): array
    {
        $decoded = preg_match('/\A[A-Za-z0-9_-]+\z/', $encoded)
            ? base64_decode(strtr($encoded, '-_', '+/'), true) : false;
        $cursor = $decoded === false ? null : json_decode($decoded, true, 8);
        if (!is_array($cursor) || count($cursor) !== 2 || Validator::make($cursor, [
            'created_at' => 'required|string|date_format:Y-m-d H:i:s',
            'id' => 'required|string|uuid',
        ])->fails()) {
            throw ValidationException::withMessages(['cursor' => 'The activity cursor is invalid.']);
        }

        return $cursor;
    }
}
