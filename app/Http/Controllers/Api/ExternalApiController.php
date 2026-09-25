<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\FirmwareVersions;
use App\Models\ConfigVersions;
use Illuminate\Http\Request;

class ExternalApiController extends Controller
{
    public function capabilities()
    {
        return response()->json([
            'version' => 'v1', 'access' => 'developer',
            'endpoints' => [
                'GET /api/external/v1/devices', 'GET /api/external/v1/devices/{id}',
                'PATCH /api/external/v1/devices/{id}', 'GET /api/external/v1/devices/{id}/data',
                'GET /api/external/v1/devices/{id}/report',
                'GET /api/external/v1/devices/{id}/telemetry',
                'GET /api/external/v1/remote-control?serial_no={serial}',
                'POST /api/external/v1/remote-control',
                'GET /api/external/v1/firmware', 'POST /api/external/v1/firmware',
                'GET /api/external/v1/firmware/{version}/download',
                'GET /api/external/v1/configuration', 'POST /api/external/v1/configuration',
                'GET /api/external/v1/configuration/{version}/download',
            ],
        ]);
    }

    public function devices(Request $request)
    {
        $data = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);

        return response()->json(Device::orderBy('id')->paginate($data['per_page'] ?? 20, [
            'id', 'serial_no', 'name', 'sku', 'state', 'address_1', 'address_2', 'city',
            'address_state', 'zip_code', 'country', 'latitude', 'longitude', 'created_at', 'updated_at',
        ]));
    }

    public function firmware(Request $request)
    {
        return $this->releases($request, FirmwareVersions::class);
    }

    public function configuration(Request $request)
    {
        return $this->releases($request, ConfigVersions::class);
    }

    private function releases(Request $request, string $model)
    {
        $data = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);

        return response()->json($model::latest('id')->paginate($data['per_page'] ?? 20, [
            'id', 'version', 'prefix', 'description', 'created_at',
        ]));
    }
}
