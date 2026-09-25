<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DeviceTelemetryData;
use Illuminate\Http\Request;

class DeviceTelemetryController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $device = Device::findOrFail($id);
        $user = $request->user();
        abort_unless($user && (
            (int) $user->id === (int) $device->user_id || $user->isDeveloper()
        ), 403);

        return response()->json(app(DeviceTelemetryData::class)->forDevice($device), 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
