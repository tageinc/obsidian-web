<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSettingsController extends Controller
{
    private const RESPONSE_HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function show(Request $request)
    {
        $settings = $request->user()->settings()->first() ?? new UserSettings();

        return response()->json(['data' => [
            'receive_app_activity_emails' => $settings->receive_app_activity_emails,
            'theme_mode' => $settings->theme_mode,
        ]], 200, self::RESPONSE_HEADERS);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'receive_app_activity_emails' => 'required_without:theme_mode|boolean',
            'theme_mode' => 'required_without:receive_app_activity_emails|string|in:adaptive,light,dark',
        ]);
        if (array_key_exists('receive_app_activity_emails', $data)) {
            $data['receive_app_activity_emails'] = (bool) $data['receive_app_activity_emails'];
        }
        $settings = DB::transaction(function () use ($request, $data) {
            // Lock the existing user so concurrent first saves share one settings row.
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            return $user->settings()->updateOrCreate([], $data);
        });

        return response()->json(['data' => [
            'receive_app_activity_emails' => $settings->receive_app_activity_emails,
            'theme_mode' => $settings->theme_mode,
        ]], 200, self::RESPONSE_HEADERS);
    }
}
