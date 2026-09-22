<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;

/** Authorizes browser routes only; external device/API contracts are separate. */
class EnsureBrowserDeviceAccess
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->route('id');
        $serial = $request->input('serial_no');
        $device = $id !== null
            ? Device::find($id)
            : (is_string($serial) && strlen($serial) <= 255
                ? Device::where('serial_no', $serial)->first()
                : null);

        // Let the existing controllers retain missing-device and validation responses.
        if ($device) {
            $user = $request->user();
            abort_unless($user && (
                (int) $device->user_id === (int) $user->id || $user->isDeveloper()
            ), 403);
        }

        return $next($request);
    }
}
