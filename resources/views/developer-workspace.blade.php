@php
    $usesVue = config('frontend.vue3.developer');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @php
        $developerPageData = function ($records, $size, $section) use ($releaseData) {
            return [
                'rows' => $records->getCollection()->map(function ($record) use ($section) {
                    return [
                        'version' => $record->version,
                        'prefix' => $record->prefix,
                        'description' => $record->description,
                        'createdAt' => optional($record->created_at)->toIso8601String(),
                        'id' => $record->id,
                        'updateUrl' => route('developer.'.$section.'.update', $record->id),
                        'deleteUrl' => route('developer.'.$section.'.destroy', $record->id),
                    ];
                })->values()->all(),
                'filters' => $releaseData[$section]['filters'],
                'filterOptions' => $releaseData[$section]['filterOptions'],
                'preservedQuery' => $releaseData[$section]['preservedQuery'],
                'pagination' => [
                    'total' => $records->total(),
                    'from' => $records->firstItem(),
                    'to' => $records->lastItem(),
                    'currentPage' => $records->currentPage(),
                    'perPage' => (int) $size,
                    'lastPage' => $records->lastPage(),
                    'sizes' => array_values(array_unique([2, 10, 20, 50, 100, (int) $size])),
                    'links' => $records->linkCollection()->map(function ($link) {
                        return ['url' => $link['url'], 'label' => html_entity_decode(strip_tags($link['label']), ENT_QUOTES, 'UTF-8'), 'active' => $link['active']];
                    })->all(),
                ],
            ];
        };
        $uploadErrors = array_intersect_key($errors->messages(), array_flip(['firmware', 'config', 'version', 'description', 'prefix', 'device_family']));
        $failedUpload = session('error') && in_array(session('active_upload'), ['firmware', 'config'], true) ? session('active_upload') : null;
        $activeUpload = $failedUpload ?? old('_upload_kind', session('active_upload', $errors->has('config') ? 'config' : request('section', 'firmware')));
        $activeUpload = $activeUpload === 'config' ? 'config' : 'firmware';
        $initiallyOpenUpload = !empty($uploadErrors) || $failedUpload ? $activeUpload : null;
    @endphp
    @include('frontend.mount', ['page' => 'developer', 'props' => [
        'csrfToken' => csrf_token(),
        'links' => [
            'dashboard' => route('dashboard'),
            'developer' => route('developer-workspace'),
            'uploadFirmware' => route('uploadFirmware'),
            'uploadConfig' => route('uploadConfig'),
            'apiKeys' => route('developer.api-keys.index'),
        ],
        'firmware' => $developerPageData($firmwareUpdates, $firmwarePaginationSize, 'firmware'),
        'config' => $developerPageData($configVersions, $configPaginationSize, 'config'),
        'activeUpload' => $activeUpload,
        'activeSection' => request('section') === 'api-keys' && !$initiallyOpenUpload ? 'api-keys' : $activeUpload,
        'initiallyOpenUpload' => $initiallyOpenUpload,
        'values' => ['version' => old('version'), 'description' => old('description'), 'prefix' => old('prefix')],
        'errors' => $uploadErrors,
        'success' => session('success'),
        'sessionError' => session('error'),
    ]])
@else
@php
    $legacyUploadKind = session('error') && in_array(session('active_upload'), ['firmware', 'config'], true)
        ? session('active_upload') : old('_upload_kind');
    $legacyUploadValue = function ($kind, $field) use ($legacyUploadKind) {
        return $legacyUploadKind === $kind ? old($field) : null;
    };
@endphp

<div class="container">
    <h2>Developer Workspace</h2>
    <nav aria-label="Developer tools" class="mb-3">
        <strong>Tools</strong>
        <a href="{{ route('developer.api-keys.index') }}">External API Keys</a>
    </nav>

    <!-- Success and Error Messages -->
    @if (session('success'))
    <div class="alert alert-success" role="status">
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div class="alert alert-danger" role="alert">
        {{ session('error') }}
    </div>
    @endif

	<!--Update container-->
	<div class="container">
		<div class="row">
			<!-- Firmware Upload and Table -->
			<h3>Firmware Updates</h3>
			<div class="col-md-12 firmware-section" style="background-color: var(--obsidian-surface-subtle, #f2f2f2); border: 1px solid var(--obsidian-border, #cccccc); box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.12);">
					<form action="{{ route('uploadFirmware') }}" method="post" enctype="multipart/form-data">
						@csrf
                        <input type="hidden" name="_upload_kind" value="firmware">
                        @if ($errors->any() && $legacyUploadKind === 'firmware')
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0">
                                    @foreach (['firmware' => 'firmware', 'version' => 'firmware-version', 'description' => 'firmware-description', 'prefix' => 'firmware-prefix'] as $field => $target)
                                        @foreach ($errors->get($field) as $message)
                                            <li><a href="#{{ $target }}">{{ $message }}</a></li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="form-group">
                            <label for="firmware-version">Firmware version</label>
                            <input id="firmware-version" type="number" name="version" min="1" step="1" required value="{{ $legacyUploadValue('firmware', 'version') }}" class="form-control{{ $legacyUploadKind === 'firmware' && $errors->has('version') ? ' is-invalid' : '' }}" aria-describedby="firmware-version-help{{ $legacyUploadKind === 'firmware' && $errors->has('version') ? ' firmware-version-error' : '' }}">
                            <small id="firmware-version-help" class="form-text">Enter a whole number of 1 or greater.</small>
                            @if ($legacyUploadKind === 'firmware')
                                @error('version')<div id="firmware-version-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>
						<div class="form-group">
							<label for="firmware">Firmware File (.bin):</label>
							<input id="firmware" type="file" name="firmware" accept=".bin" required class="form-control" />
						</div>
						<div class="form-group">
							<label for="firmware-description">Firmware Description:</label>
							<textarea name="description" id="firmware-description" class="form-control" required maxlength="255" placeholder="Enter a description for the firmware update">{{ $legacyUploadValue('firmware', 'description') }}</textarea>
						</div>
						<div class="form-group">
						<label for="firmware-prefix">Prefix:</label>
							<input id="firmware-prefix" type="text" name="prefix" class="form-control" placeholder="Enter prefix" maxlength="255" value="{{ $legacyUploadValue('firmware', 'prefix') }}" required>
						</div>
						<input type="submit" value="Upload Firmware" class="btn btn-success">
					</form>		
					<br>					
					<!-- Existing Firmware Updates Section -->
					<div class="firmware-updates">
                        <div class="table-responsive position-relative">
						<table class="table table-bordered">
							<thead>
								<tr>
									<th>Version</th>
									<th>Prefix</th>
									<th>Description</th>
									<th>Date Uploaded</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
								</tr>
							</thead>
							<tbody>
								@foreach ($firmwareUpdates as $update)
								<tr>
									<td>v{{ $update->version }}</td>
									<td>{{ $update->prefix }}</td>
									<td>{{ $update->description }}</td>
									<td><time data-release-uploaded datetime="{{ optional($update->created_at)->toIso8601String() }}">{{ optional($update->created_at)->format('Y-m-d H:i:s T') ?? '—' }}</time></td>
                                    <td>
                                        <div data-release-actions data-kind="firmware" data-title="Firmware" data-csrf-token="{{ csrf_token() }}" data-props="{{ json_encode([
                                            'id' => $update->id, 'version' => (string) $update->version,
                                            'prefix' => $update->prefix, 'description' => $update->description,
                                            'updateUrl' => route('developer.firmware.update', $update->id),
                                            'deleteUrl' => route('developer.firmware.destroy', $update->id),
                                        ]) }}"></div>
                                    </td>
								</tr>
								@endforeach
							</tbody>
						</table>
                        </div>
						{{-- Firmware Pagination Size Selection --}}
						<form action="{{ route('developer-workspace') }}" method="GET">
							<input type="hidden" name="section" value="firmware">
                            @foreach ($releaseQueries as $queryValues)
                                @foreach ($queryValues as $queryName => $queryValue)
                                    @if (!in_array($queryName, ['firmware_show', 'firmware_page'], true))
                                        @foreach (is_array($queryValue) ? $queryValue : [$queryValue] as $queryItem)
                                            <input type="hidden" name="{{ $queryName }}{{ is_array($queryValue) ? '[]' : '' }}" value="{{ $queryItem }}">
                                        @endforeach
                                    @endif
                                @endforeach
                            @endforeach
							<select name="firmware_show" onchange="this.form.submit()">
                                @foreach (array_unique([2, 10, 20, 50, 100, $firmwarePaginationSize]) as $size)
                                    <option value="{{ $size }}"{{ $firmwarePaginationSize == $size ? ' selected' : '' }}>Show {{ $size }}</option>
                                @endforeach
							</select>
						</form>
						{{-- Firmware Pagination Links --}}
						{{ $firmwareUpdates->links() }}
					</div>
				</div>
		</div>
		<div class="row">
			<h3>Config Updates</h3>
			<!-- Configuration Upload and Table -->
			<div class="col-md-12 config-section" style="background-color: var(--obsidian-surface-subtle, #f2f2f2); border: 1px solid var(--obsidian-border, #cccccc); box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.12);">
				<form action="{{ route('uploadConfig') }}" method="post" enctype="multipart/form-data">
					@csrf
                    <input type="hidden" name="_upload_kind" value="config">
                        <p>Uploaded configurations are available for polling. Application and persistence require device telemetry.</p>
                        <div class="form-group">
                            <label for="config-device_family">Device family</label>
                            <select id="config-device_family" name="device_family" class="form-control" required>
                                <option value="smart-panels-esp32">Smart Panels ESP32 · schema 1</option>
                            </select>
                            <small class="form-text">All 12 supported settings are required. Maximum JSON size: 4096 bytes.</small>
                            @error('device_family')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                    @if ($errors->any() && $legacyUploadKind === 'config')
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                @foreach (['config' => 'config', 'version' => 'config-version', 'description' => 'config-description', 'prefix' => 'config-prefix'] as $field => $target)
                                    @foreach ($errors->get($field) as $message)
                                        <li><a href="#{{ $target }}">{{ $message }}</a></li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="form-group">
                        <label for="config-version">Configuration version</label>
                        <input id="config-version" type="number" name="version" min="1" step="1" required value="{{ $legacyUploadValue('config', 'version') }}" class="form-control{{ $legacyUploadKind === 'config' && $errors->has('version') ? ' is-invalid' : '' }}" aria-describedby="config-version-help{{ $legacyUploadKind === 'config' && $errors->has('version') ? ' config-version-error' : '' }}">
                        <small id="config-version-help" class="form-text">Enter a whole number of 1 or greater.</small>
                        @if ($legacyUploadKind === 'config')
                            @error('version')<div id="config-version-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                    </div>
					<div class="form-group">
						<label for="config">Configuration File (.json):</label>
						<input id="config" type="file" name="config" accept=".json" required class="form-control" />
					</div>
					<div class="form-group">
						<label for="config-description">Config Description:</label>
						<textarea name="description" id="config-description" class="form-control" required maxlength="255" placeholder="Enter a description for the configuration update">{{ $legacyUploadValue('config', 'description') }}</textarea>
					</div>
					<div class="form-group">
						<label for="config-prefix">Prefix:</label>
						<input id="config-prefix" type="text" name="prefix" class="form-control" maxlength="255" placeholder="Enter prefix" value="{{ $legacyUploadValue('config', 'prefix') }}" required>
					</div>
					<input type="submit" value="Upload JSON Config" class="btn btn-success">
				</form>
				<br>
				<!-- Configuration Updates Section -->
				<div class="config-updates">
                    <div class="table-responsive position-relative">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th>Version</th>
								<th>Prefix</th>
								<th>Description</th>
								<th>Date Uploaded</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							@forelse ($configVersions as $config)
							<tr>
								<td>v{{ $config->version }}</td>
								<td>{{ $config->prefix }}</td>
								<td>{{ $config->description }}</td>
								<td><time data-release-uploaded datetime="{{ optional($config->created_at)->toIso8601String() }}">{{ optional($config->created_at)->format('Y-m-d H:i:s T') ?? '—' }}</time></td>
                                <td>
                                    <div data-release-actions data-kind="config" data-title="Configuration" data-csrf-token="{{ csrf_token() }}" data-props="{{ json_encode([
                                        'id' => $config->id, 'version' => (string) $config->version,
                                        'prefix' => $config->prefix, 'description' => $config->description,
                                        'updateUrl' => route('developer.config.update', $config->id),
                                        'deleteUrl' => route('developer.config.destroy', $config->id),
                                    ]) }}"></div>
                                </td>
							</tr>
							@empty
							<tr>
								<td colspan="5">No configuration updates found.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
                    </div>
					{{-- Configuration Pagination Size Selection --}}
						<form action="{{ route('developer-workspace') }}" method="GET">
							<input type="hidden" name="section" value="config">
                            @foreach ($releaseQueries as $queryValues)
                                @foreach ($queryValues as $queryName => $queryValue)
                                    @if (!in_array($queryName, ['config_show', 'config_page'], true))
                                        @foreach (is_array($queryValue) ? $queryValue : [$queryValue] as $queryItem)
                                            <input type="hidden" name="{{ $queryName }}{{ is_array($queryValue) ? '[]' : '' }}" value="{{ $queryItem }}">
                                        @endforeach
                                    @endif
                                @endforeach
                            @endforeach
						<select name="config_show" onchange="this.form.submit()">
                            @foreach (array_unique([2, 10, 20, 50, 100, $configPaginationSize]) as $size)
                                <option value="{{ $size }}"{{ $configPaginationSize == $size ? ' selected' : '' }}>Show {{ $size }}</option>
                            @endforeach
						</select>
					</form>
					{{-- Configuration Pagination Links --}}
					{{ $configVersions->links() }}
				</div>
			</div>
		</div>
	</div>
</div>

	
	
		
<!-- Include Leaflet.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    const uploadDateFormat = new Intl.DateTimeFormat(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: 'numeric', minute: '2-digit', second: '2-digit', timeZoneName: 'short',
    });
    document.querySelectorAll('[data-release-uploaded]').forEach((element) => {
        const uploaded = new Date(element.dateTime);
        element.textContent = Number.isFinite(uploaded.getTime())
            ? uploadDateFormat.format(uploaded)
            : '—';
    });
</script>

@endif
@endsection
