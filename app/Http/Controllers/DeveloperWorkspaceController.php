<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\FirmwareVersions;
use App\Models\ConfigVersions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\TrackerConfigurationSchema;

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
                ->paginate($state['perPage'], ['id', 'version', 'prefix', 'description', 'created_at'], $kind.'_page', $state['page'])
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
            $query->orderByReleaseVersion($direction);
        } elseif ($filters['sort'] === 'prefix_asc') {
            $query->orderBy('prefix');
        }
        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';

        return $query->orderBy('created_at', $direction)->orderBy('id', $direction);
    }


    public function uploadFirmware(Request $request)
    {
        return $this->uploadRelease($request, 'firmware', FirmwareVersions::class);
    }

    public function uploadConfig(Request $request)
    {
        return $this->uploadRelease($request, 'config', ConfigVersions::class);
    }

    public function updateFirmware(Request $request, $firmware)
    {
        return $this->updateRelease($request, $firmware, 'firmware', FirmwareVersions::class);
    }

    public function updateConfig(Request $request, $configuration)
    {
        return $this->updateRelease($request, $configuration, 'config', ConfigVersions::class);
    }

    public function deleteFirmware(Request $request, $firmware)
    {
        return $this->deleteRelease($request, $firmware, 'firmware', FirmwareVersions::class);
    }

    public function deleteConfig(Request $request, $configuration)
    {
        return $this->deleteRelease($request, $configuration, 'config', ConfigVersions::class);
    }

    private function uploadRelease(Request $request, string $kind, string $model)
    {
        $title = $kind === 'firmware' ? 'Firmware' : 'Configuration';
        $extension = $kind === 'firmware' ? 'bin' : 'json';
        $this->normalizeReleaseVersion($request);
        $rules = [
            $kind => $kind === 'firmware' ? 'required|file|max:10240' : 'required|file|mimes:json|max:1024',
            'version' => $this->releaseVersionRules($model),
            'description' => 'required|string|max:255',
            'prefix' => 'required|string|max:255',
        ];
        if ($kind === 'config') {
            $rules['device_family'] = ['required', Rule::in([TrackerConfigurationSchema::FAMILY])];
            $rules['prefix'] = ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/D'];
        }
        $request->validate($rules);
        if ($kind === 'config') {
            app(TrackerConfigurationSchema::class)->validate($request->file('config')->get());
        }
        $path = null;
        $committed = false;
        try {
            $record = DB::transaction(function () use ($request, $rules, $kind, $model, $extension, &$path, &$committed) {
                $this->lockReleaseWrites($request);
                $data = $request->validate($rules);
                // The stored identity never depends on editable version text or user input.
                $path = $request->file($kind)->storeAs('public/'.$kind, Str::uuid().'.'.$extension);
                if (!$path) {
                    return null;
                }
                DB::afterCommit(function () use (&$committed) {
                    $committed = true;
                });

                return $model::create([
                    'version' => $data['version'], 'prefix' => $data['prefix'],
                    'description' => $data['description'], 'file_path' => $path,
                ] + ($kind === 'config' ? ['device_family' => TrackerConfigurationSchema::FAMILY, 'schema_version' => TrackerConfigurationSchema::VERSION] : []));
            });
        } catch (\Throwable $exception) {
            // A post-commit cache failure must not remove an already-persisted release file.
            if ($path && !$committed) {
                Storage::delete($path);
            }
            throw $exception;
        }
        if (!$record) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to store '.strtolower($title).'.'], 500);
            }
            return back()->with('error', 'There was an issue uploading the '.strtolower($title).' file.')->with('active_upload', $kind)
                ->withInput($request->only(['_upload_kind', 'version', 'description', 'prefix']));
        }
        if ($kind === 'config') {
            Log::info('device.config_uploaded');
        }
        if ($request->expectsJson()) {
            return response()->json(['id' => $record->id, 'version' => (string) $record->version, 'message' => $title.' uploaded successfully.'.($kind === 'config' ? ' Available for polling; application and persistence require device telemetry.' : '')], 201);
        }

        return back()->with('success', $title.' v'.$record->version.' uploaded successfully!'.($kind === 'config' ? ' Available for polling; application and persistence require device telemetry.' : ''))->with('active_upload', $kind);
    }

    private function updateRelease(Request $request, $id, string $kind, string $model)
    {
        $this->normalizeReleaseVersion($request);
        $record = DB::transaction(function () use ($request, $id, $model) {
            $this->lockReleaseWrites($request);
            $record = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            $data = $request->validate([
                'version' => array_merge(['sometimes'], $this->releaseVersionRules($model, $record->id)),
                'description' => 'sometimes|required|string|max:255',
                'prefix' => 'prohibited', 'firmware' => 'prohibited', 'config' => 'prohibited', 'file_path' => 'prohibited',
                'created_at' => 'prohibited', 'updated_at' => 'prohibited', 'id' => 'prohibited',
                'device_family' => 'prohibited', 'schema_version' => 'prohibited',
            ]);
            $record->fill(Arr::only($data, ['version', 'description']))->save();

            return $record;
        });
        $message = ($kind === 'firmware' ? 'Firmware' : 'Configuration').' updated successfully.';
        if ($request->expectsJson()) {
            $this->flashReleaseSuccess($request, $kind, $message);
            return response()->json(['id' => $record->id, 'version' => (string) $record->version, 'message' => $message]);
        }

        return back()->with('success', $message)->with('active_upload', $kind);
    }

    private function deleteRelease(Request $request, $id, string $kind, string $model)
    {
        $path = null;
        $staged = null;
        $committed = false;
        $otherModel = $kind === 'firmware' ? ConfigVersions::class : FirmwareVersions::class;
        try {
            DB::transaction(function () use ($request, $id, $kind, $model, $otherModel, &$path, &$staged, &$committed) {
                $this->lockReleaseWrites($request);
                $record = $model::whereKey($id)->lockForUpdate()->firstOrFail();
                $path = $record->file_path;
                // Shared legacy files remain available until their final reference is removed.
                if ($path && !$model::where('file_path', $path)->whereKeyNot($record->id)->exists()
                    && !$otherModel::where('file_path', $path)->exists() && Storage::exists($path)) {
                    $extension = $kind === 'firmware' ? 'bin' : 'json';
                    $destination = $kind.'-deletions/'.Str::uuid().'.'.$extension;
                    if (!Storage::move($path, $destination)) {
                        throw new \RuntimeException('Unable to stage release deletion.');
                    }
                    $staged = $destination;
                }
                DB::afterCommit(function () use (&$committed, &$staged, $id, $kind) {
                    $committed = true;
                    if ($staged) {
                        try {
                            if (!Storage::delete($staged)) {
                                throw new \RuntimeException('Private cleanup failed.');
                            }
                        } catch (\Throwable $exception) {
                            // The public file and release are gone; retain private recovery bytes.
                            Log::warning($kind.'.private_deletion_cleanup_failed', ['release_id' => (string) $id]);
                        }
                    }
                });
                $record->delete();
            });
        } catch (\Throwable $exception) {
            if ($staged && !$committed && !Storage::move($staged, $path)) {
                Log::error($kind.'.deletion_restore_failed', ['release_id' => (string) $id]);
            }
            throw $exception;
        }
        $message = ($kind === 'firmware' ? 'Firmware' : 'Configuration').' deleted successfully.';
        if ($request->expectsJson()) {
            $this->flashReleaseSuccess($request, $kind, $message);
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message)->with('active_upload', $kind);
    }

    private function normalizeReleaseVersion(Request $request): void
    {
        if (is_int($request->input('version'))) {
            $request->merge(['version' => (string) $request->input('version')]);
        }
    }

    private function flashReleaseSuccess(Request $request, string $kind, string $message): void
    {
        if ($request->hasSession()) {
            $request->session()->flash('success', $message);
            $request->session()->flash('active_upload', $kind);
        }
    }

    private function releaseVersionRules(string $model, $ignore = null): array
    {
        return ['required', 'string', $model === ConfigVersions::class ? 'max:64' : 'max:255', 'regex:/^[1-9][0-9]*$/D', Rule::unique((new $model)->getTable(), 'version')->ignore($ignore)];
    }

    private function lockReleaseWrites(Request $request): void
    {
        // Both authenticated entry points share one Developer. Serialize their writes so
        // versions remain unique per release kind without rewriting duplicate legacy data.
        if ($request->user()) {
            $request->user()->newQuery()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
        }
    }

    /**
     * Get the latest version of the config
     * @param prefix Alphanumeric representation of a product
     * @return The string represents the latest version of the config table
     */
    public function getLatestConfigVersionNumber($prefix = null)
    {
        $latestConfig = ConfigVersions::where('prefix', $prefix)->orderByReleaseVersion()->first();
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
        $latestFirmware = FirmwareVersions::where('prefix', $prefix)->orderByReleaseVersion()->first();
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
            $firmware = FirmwareVersions::where('prefix', $prefix)->orderByReleaseVersion()->first();
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
            $config = ConfigVersions::where('prefix', $prefix)->orderByReleaseVersion()->first();
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
