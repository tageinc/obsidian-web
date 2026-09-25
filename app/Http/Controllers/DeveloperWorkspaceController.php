<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\FirmwareVersions;
use App\Models\ConfigVersions;
use Illuminate\Support\Facades\Log;

class DeveloperWorkspaceController extends Controller
{
    public function legacyRedirect(Request $request)
    {
        $query = $request->server->get('QUERY_STRING', '');

        return redirect()->to(route('developer-workspace').($query !== '' ? '?'.$query : ''));
    }

    /**
     * Show independently searchable firmware and configuration release histories.
     */
    public function index(Request $request)
    {
        $rules = ['page' => 'nullable|integer|min:1|max:1000000'];
        foreach (['firmware', 'config'] as $kind) {
            $rules += [
                $kind.'_search' => 'nullable|string|max:255',
                $kind.'_prefixes' => 'nullable|array|max:100',
                $kind.'_prefixes.*' => 'required|string|max:255',
                $kind.'_versions' => 'nullable|array|max:100',
                $kind.'_versions.*' => 'required|string|max:255',
                $kind.'_from' => 'nullable|date_format:Y-m-d',
                $kind.'_to' => 'nullable|date_format:Y-m-d',
                $kind.'_sort' => 'nullable|in:newest,oldest,version_desc,version_asc,prefix_asc',
                $kind.'_show' => 'nullable|integer|min:1|max:100',
                $kind.'_page' => 'nullable|integer|min:1|max:1000000',
            ];
            if ($request->filled($kind.'_from')) {
                $rules[$kind.'_to'] .= '|after_or_equal:'.$kind.'_from';
            }
        }
        $validated = $request->validate($rules);
        $activeSection = $request->query('section') === 'config' ? 'config' : 'firmware';
        $releaseStates = [];
        $releaseQueries = [];

        foreach (['firmware', 'config'] as $kind) {
            $filters = [
                'search' => trim($validated[$kind.'_search'] ?? ''),
                'prefixes' => array_values(array_unique($validated[$kind.'_prefixes'] ?? [])),
                'versions' => array_values(array_unique($validated[$kind.'_versions'] ?? [])),
                'from' => $validated[$kind.'_from'] ?? '',
                'to' => $validated[$kind.'_to'] ?? '',
                'sort' => $validated[$kind.'_sort'] ?? 'newest',
            ];
            $size = (int) ($validated[$kind.'_show'] ?? 10);
            $page = (int) ($validated[$kind.'_page'] ?? ($activeSection === $kind ? ($validated['page'] ?? 1) : 1));
            $releaseStates[$kind] = ['filters' => $filters, 'perPage' => $size, 'page' => $page];
            $releaseQueries[$kind] = [$kind.'_show' => $size, $kind.'_page' => $page];
            foreach ($filters as $name => $value) {
                if ($value !== '' && $value !== [] && !($name === 'sort' && $value === 'newest')) {
                    $releaseQueries[$kind][$kind.'_'.$name] = $value;
                }
            }
        }

        $releaseData = [];
        foreach (['firmware' => FirmwareVersions::class, 'config' => ConfigVersions::class] as $kind => $model) {
            $state = $releaseStates[$kind];
            $records = $this->releaseQuery($model, $state['filters'])
                ->paginate($state['perPage'], ['version', 'prefix', 'description', 'created_at'], $kind.'_page', $state['page'])
                ->appends(array_merge($releaseQueries['firmware'], $releaseQueries['config'], ['section' => $kind]));
            $otherKind = $kind === 'firmware' ? 'config' : 'firmware';
            $preservedQuery = [];
            foreach ($releaseQueries[$otherKind] as $name => $value) {
                foreach (is_array($value) ? $value : [$value] as $item) {
                    $preservedQuery[] = ['name' => $name.(is_array($value) ? '[]' : ''), 'value' => (string) $item];
                }
            }
            $releaseData[$kind] = [
                'records' => $records,
                'filters' => $state['filters'],
                'filterOptions' => [
                    'prefixes' => $model::query()->whereNotNull('prefix')->where('prefix', '<>', '')
                        ->distinct()->orderBy('prefix')->pluck('prefix')->map(fn ($prefix) => (string) $prefix)->all(),
                    'versions' => $model::query()->distinct()->pluck('version')
                        ->map(fn ($version) => (string) $version)->sort(SORT_NATURAL)->reverse()->values()->all(),
                ],
                'preservedQuery' => $preservedQuery,
            ];
        }

        return view('developer-workspace', [
            'firmwareUpdates' => $releaseData['firmware']['records'],
            'configVersions' => $releaseData['config']['records'],
            'firmwarePaginationSize' => $releaseStates['firmware']['perPage'],
            'configPaginationSize' => $releaseStates['config']['perPage'],
            'releaseData' => $releaseData,
            'releaseQueries' => $releaseQueries,
        ]);
    }

    private function releaseQuery(string $model, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = $model::query();
        if ($filters['search'] !== '') {
            // Use an explicit escape character so MySQL and SQLite treat search text literally.
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $query->where(function ($query) use ($search) {
                $query->whereRaw("version LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("prefix LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$search]);
            });
        }
        foreach (['prefixes' => 'prefix', 'versions' => 'version'] as $filter => $column) {
            if ($filters[$filter] !== []) {
                $query->whereIn($column, $filters[$filter]);
            }
        }
        if ($filters['from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (in_array($filters['sort'], ['version_desc', 'version_asc'], true)) {
            $direction = $filters['sort'] === 'version_asc' ? 'asc' : 'desc';
            $query->orderByRaw('CAST(version AS DECIMAL(20, 0)) '.$direction);
        } elseif ($filters['sort'] === 'prefix_asc') {
            $query->orderBy('prefix');
        }
        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';

        return $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }


    /**
     * Handle the firmware file upload.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadFirmware(Request $request)
    {
        $request->validate([
            'firmware' => 'required|file|max:10240', // 10MB Max
            'description' => 'required|string|max:255',
            'prefix' => 'required|string|max:255' // Optional prefix field
        ]);

        $file = $request->file('firmware');
        $version = $this->generateFirmwareVersionNumber();
        $prefix = $request->input('prefix');
        $filename = $prefix ? "{$prefix}_firmware_{$version}.bin" : "firmware_{$version}.bin";
        $path = $file->storeAs('public/firmware', $filename);

        if ($path) {
            FirmwareVersions::create([
                'version' => $version,
                'prefix' => $prefix,
                'file_path' => $path,
                'description' => $request->input('description')
            ]);

            if ($request->expectsJson()) {
                return response()->json(['version' => (string) $version, 'message' => 'Firmware uploaded successfully.'], 201);
            }
            $message = "Firmware v{$version} uploaded successfully!";
            return back()->with('success', $message)->with('active_upload', 'firmware');
        } else {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to store firmware.'], 500);
            }
            return back()->with('error', 'There was an issue uploading the firmware file.')->with('active_upload', 'firmware')
                ->withInput($request->only(['_upload_kind', 'description', 'prefix']));
        }
    }

    /**
     * Handle the JSON configuration file upload.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadConfig(Request $request)
    {
        $request->validate([
            'config' => 'required|file|mimes:json|max:1024', // 1MB Max for JSON file
            'description' => 'required|string|max:255',
            'prefix' => 'required|string|max:255' // Optional prefix field
        ]);

        $configFile = $request->file('config');
        $configFileVersion = $this->generateConfigVersionNumber();
        $prefix = $request->input('prefix');
        $configFilename = $prefix ? "{$prefix}_config_{$configFileVersion}.json" : "config_{$configFileVersion}.json";
        $configPath = $configFile->storeAs('public/config', $configFilename);

        if ($configPath) {
            ConfigVersions::create([
                'file_path' => $configPath,
                'prefix' => $prefix,
                'version' => $configFileVersion,
                'description' => $request->input('description') // Use description from the request
            ]);

            Log::info('device.config_uploaded');
            if ($request->expectsJson()) {
                return response()->json(['version' => (string) $configFileVersion, 'message' => 'Configuration uploaded successfully.'], 201);
            }
            return back()->with('success', "Config file uploaded successfully!")->with('active_upload', 'config');
        } else {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to store configuration.'], 500);
            }
            return back()->with('error', 'There was an issue uploading the config file.')->with('active_upload', 'config')
                ->withInput($request->only(['_upload_kind', 'description', 'prefix']));
        }
    }
    /**
     * Generate a new version number.
     *
     * @return string
     */
    private function generateFirmwareVersionNumber()
    {
        $latestVersion = FirmwareVersions::orderBy('version', 'desc')->first();
        return $latestVersion ? (((int) $latestVersion->version) + 1) : '1';
    }

    /**
     * Generate a new version number for config files.
     *
     * @return string
     */
    private function generateConfigVersionNumber()
    {
        $latestConfigVersion = ConfigVersions::orderBy('version', 'desc')->first();
        return $latestConfigVersion ? (((int) $latestConfigVersion->version) + 1) : '1';
    }

    /**
     * Get the latest version of the config
     * @param prefix Alphanumeric representation of a product
     * @return The string represents the latest version of the config table
     */
    public function getLatestConfigVersionNumber($prefix = null)
    {
        $latestConfig = ConfigVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
		if($latestConfig){
			$latestVersion = array('version' => (string)$latestConfig->version);
			$json = json_encode($latestVersion);
		}else{
			$json = "{\"version\":\"0\"}";
		}
        return $json;
    }

    /**
     * Get the latest version of the firmware
     * @param prefix Alphanumeric representation of a product
     * @return The string represents the latest version of the config table
     */
    public function getLatestFirmwareVersionNumber($prefix = null)
    {
        $latestFirmware = FirmwareVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
		if($latestFirmware){
			$latestVersion = array('version' => (string)$latestFirmware->version);
			$json = json_encode($latestVersion);
		}else{
			$json = "{\"version\":\"0\"}";
		}
        return $json;
    }

    /**
     * Serve the firmware file.
     *
     * @param  string|null  $version
     * @return \Illuminate\Http\Response
     */
    public function serveFirmwareByVersion($version = null)
    {
        // Log::info('Firmware request received. version: ' . $version);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($version) {
            $firmware = FirmwareVersions::where('version', $version)->first();
            if ($firmware) {
                // Log::info('Specific firmware version requested: ' . $firmware->version);
            } else {
                // Log::info('Requested firmware version not found: ' . $version);
            }
        } else {
            $firmware = FirmwareVersions::latest()->first();
            // Log::info('No version version requested. Serving latest firmware version: ' . $firmware->version);
        }

        if (!$firmware || !Storage::exists($firmware->file_path)) {
            // Log::error('Firmware file not found or does not exist. File path: ' . ($firmware ? $firmware->file_path : 'N/A'));
            abort(404, 'Firmware file not found.');
        }

        return Storage::download($firmware->file_path);
    }

    /**
     * Serve the configuration file.
     *
     * @param  string|null  $version
     * @return \Illuminate\Http\Response
     */
    public function serveConfigByVersion($version = null)
    {
        // Log::info('Configuration file request received. Version: ' . $version);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($version) {
            $config = ConfigVersions::where('version', $version)->first();
            if ($config) {
                // Log::info('Specific config version requested: ' . $config->version);
            } else {
                // Log::info('Requested config version not found: ' . $version);
            }
        } else {
            $config = ConfigVersions::latest()->first();
            // Log::info('No specific version requested. Serving latest config version: ' . $config->version);
        }

        if (!$config || !Storage::exists($config->file_path)) {
            // Log::error('Configuration file not found or does not exist. File path: ' . ($config ? $config->file_path : 'N/A'));
            abort(404, 'Configuration file not found.');
        }

        return Storage::download($config->file_path);
    }


    public function serveFirmwareByPrefix($prefix = null)
    {
        // Log::info('Firmware file request received. Prefix: ' . $prefix);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($prefix) {
            $firmware = FirmwareVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
            if ($firmware) {
                // Log::info('Specific firmware prefix requested: ' . $firmware->prefix);
            } else {
                // Log::info('Requested firmware prefix not found: ' . $prefix);
            }
        } else {
            // Log::info('No specific prefix requested. Failed to serve.');
        }

        if (!$firmware || !Storage::exists($firmware->file_path)) {
            // Log::error('Configuration file not found or does not exist. File path: ' . ($firmware ? $firmware->file_path : 'N/A'));
            abort(404, 'Configuration file not found.');
        }

        return Storage::download($firmware->file_path);
    }


    public function serveConfigByPrefix($prefix = null)
    {
        // Log::info('Configuration file request received. Version: ' . $prefix);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($prefix) {
            $config = ConfigVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
            if ($config) {
                // Log::info('Specific config prefix requested: ' . $config->prefix);
            } else {
                // Log::info('Requested config prefix not found: ' . $prefix);
            }
        } else {
            // Log::info('No specific prefix requested. Failed to serve config.');
        }

        if (!$config || !Storage::exists($config->file_path)) {
            // Log::error('Configuration file not found or does not exist. File path: ' . ($config ? $config->file_path : 'N/A'));
            abort(404, 'Configuration file not found.');
        }

        return Storage::download($config->file_path);
    }
}
