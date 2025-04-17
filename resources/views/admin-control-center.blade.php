@extends('layouts.app')

@section('content')

<div class="container">
    <h2>Admin Control Center</h2>

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
						<form action="{{ route('admin-control-center') }}" method="GET">
							<select name="firmware_show" onchange="this.form.submit()">
								<option value="2"{{ $firmwarePaginationSize == 2 ? ' selected' : '' }}>Show 2</option>
								<option value="10"{{ $firmwarePaginationSize == 10 ? ' selected' : '' }}>Show 10</option>
								<option value="20"{{ $firmwarePaginationSize == 20 ? ' selected' : '' }}>Show 20</option>
							</select>
						</form>
						{{-- Firmware Pagination Links --}}
						{{ $firmwareUpdates->appends(['firmware_show' => $firmwarePaginationSize, 'config_show' => $configPaginationSize])->links() }}
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
					<form action="{{ route('admin-control-center') }}" method="GET">
						<select name="config_show" onchange="this.form.submit()">
							<option value="2"{{ $configPaginationSize == 2 ? ' selected' : '' }}>Show 2</option>
							<option value="10"{{ $configPaginationSize == 10 ? ' selected' : '' }}>Show 10</option>
							<option value="20"{{ $configPaginationSize == 20 ? ' selected' : '' }}>Show 20</option>
						</select>
					</form>
					{{-- Configuration Pagination Links --}}
					{{ $configVersions->appends(['firmware_show' => $firmwarePaginationSize, 'config_show' => $configPaginationSize])->links() }}
				</div>
			</div>
		</div>
	</div>
</div>

	
	
		
<!-- Include Leaflet.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>


@endsection
