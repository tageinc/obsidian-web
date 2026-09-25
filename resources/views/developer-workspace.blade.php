@php
    $usesVue = config('frontend.vue3.developer');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @php
        $developerPageData = function ($records, $size, $section) use ($releaseData) {
            return [
                'rows' => $records->getCollection()->map(function ($record) {
                    return [
                        'version' => $record->version,
                        'prefix' => $record->prefix,
                        'description' => $record->description,
                        'createdAt' => optional($record->created_at)->format('Y-m-d H:i:s'),
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
        $uploadErrors = array_intersect_key($errors->messages(), array_flip(['firmware', 'config', 'description', 'prefix']));
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
        'values' => ['description' => old('description'), 'prefix' => old('prefix')],
        'errors' => $uploadErrors,
        'success' => session('success'),
        'sessionError' => session('error'),
    ]])
@else

<div class="container">
    <h2>Developer Workspace</h2>
    <nav aria-label="Developer tools" class="mb-3">
        <strong>Tools</strong>
        <a href="{{ route('developer.api-keys.index') }}">External API Keys</a>
    </nav>

    <!-- Success and Error Messages -->
    @if (session('success'))
    <div style="color: green; background-color: lightgreen; border: 1px solid green; padding: 10px; margin-top: 10px; font-size: 16px; text-align: center;">
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div style="color: red; background-color: pink; border: 1px solid red; padding: 10px; margin-top: 10px; font-size: 16px; text-align: center;">
        {{ session('error') }}
    </div>
    @endif

	<!--Update container-->
	<div class="container">
		<div class="row">
			<!-- Firmware Upload and Table -->
			<h3>Firmware Updates</h3>
			<div class="col-md-12 firmware-section" style="background-color: #f2f2f2; border: 1px solid #cccccc; box-shadow: 0px 0px 10px #cccccc;">		
					<form action="{{ route('uploadFirmware') }}" method="post" enctype="multipart/form-data">
						@csrf
						<div class="form-group">
							<label for="firmware">Firmware File (.bin):</label>
							<input type="file" name="firmware" accept=".bin" required class="form-control" />
						</div>
						<div class="form-group">
							<label for="description">Firmware Description:</label>
							<textarea name="description" id="description" class="form-control" required placeholder="Enter a description for the firmware update"></textarea>
						</div>
						<div class="form-group">
						<label for="prefix">Prefix:</label>
							<input type="text" name="prefix" class="form-control" placeholder="Enter prefix" required>
						</div>
						<input type="submit" value="Upload Firmware" class="btn btn-success">
					</form>		
					<br>					
					<!-- Existing Firmware Updates Section -->
					<div class="firmware-updates">
						<table class="table table-bordered">
							<thead>
								<tr>
									<th>Version</th>
									<th>Prefix</th>
									<th>Description</th>
									<th>Date Uploaded</th>
								</tr>
							</thead>
							<tbody>
								@foreach ($firmwareUpdates as $update)
								<tr>
									<td>{{ $update->version }}</td>
									<td>{{ $update->prefix }}</td>
									<td>{{ $update->description }}</td>
									<td>{{ $update->created_at->format('Y-m-d H:i:s') }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
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
		<div class="row">
			<h3>Config Updates</h3>
			<!-- Configuration Upload and Table -->
			<div class="col-md-12 config-section" style="background-color: #f2f2f2; border: 1px solid #cccccc; box-shadow: 0px 0px 10px #cccccc;">
				<form action="{{ route('uploadConfig') }}" method="post" enctype="multipart/form-data">
					@csrf
					<div class="form-group">
						<label for="config">Configuration File (.json):</label>
						<input type="file" name="config" accept=".json" required class="form-control" />
					</div>
					<div class="form-group">
						<label for="description">Config Description:</label>
						<textarea name="description" id="description" class="form-control" required placeholder="Enter a description for the configuration update"></textarea>
					</div>
					<div class="form-group">
						<label for="prefix">Prefix:</label>
						<input type="text" name="prefix" class="form-control" placeholder="Enter prefix" required>
					</div>
					<input type="submit" value="Upload JSON Config" class="btn btn-success">
				</form>
				<br>
				<!-- Configuration Updates Section -->
				<div class="config-updates">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th>Version</th>
								<th>Prefix</th>
								<th>Description</th>
								<th>Date Uploaded</th>
							</tr>
						</thead>
						<tbody>
							@forelse ($configVersions as $config)
							<tr>
								<td>{{ $config->version }}</td>
								<td>{{ $config->prefix }}</td>
								<td>{{ $config->description }}</td>
								<td>{{ $config->created_at->format('Y-m-d H:i:s') }}</td>
							</tr>
							@empty
							<tr>
								<td colspan="4">No configuration updates found.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
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


@endif
@endsection
