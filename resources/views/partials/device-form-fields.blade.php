<div class="form-group mb-4">
    <label for="alias">Device name</label>
    <input id="alias" name="alias" type="text" maxlength="255" value="{{ old('alias', optional($device)->alias) }}"
           class="form-control @error('alias') is-invalid @enderror" required>
    @error('alias') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<h2 class="h5 mb-3">Installation address</h2>
<div class="row">
    @foreach ([
        'address_1' => ['Address line 1', 'address-line1', 'col-12'],
        'address_2' => ['Address line 2 (optional)', 'address-line2', 'col-12'],
        'city' => ['City', 'address-level2', 'col-md-6'],
        'state' => ['State / province / region', 'address-level1', 'col-md-6'],
        'zip_code' => ['Postal code', 'postal-code', 'col-md-6'],
        'country' => ['Country', 'country-name', 'col-md-6'],
    ] as $field => [$label, $autocomplete, $column])
        @php
            $default = $registering ? Auth::user()->getAttribute($field) : $device->getAttribute($field);
            $default = $field === 'country' && !$default ? 'US' : $default;
        @endphp
        <div class="{{ $column }} form-group mb-3">
            <label for="{{ $field }}">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="text" maxlength="255" autocomplete="{{ $autocomplete }}"
                   value="{{ old($field, $default) }}" class="form-control @error($field) is-invalid @enderror"
                   @if ($field !== 'address_2') required @endif>
            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
</div>

<h2 class="h5 mt-3 mb-2">Device coordinates</h2>
<p class="text-muted small">Enter the installation location in decimal degrees.{{ $registering ? '' : ' If coordinates are not available, leave both fields blank.' }}</p>
<p class="small"><a href="https://www.latlong.net/convert-address-to-lat-long.html" target="_blank" rel="noopener noreferrer">Find coordinates from an address</a></p>
<div class="row mb-3">
    @foreach (['latitude' => ['Latitude', 90], 'longitude' => ['Longitude', 180]] as $field => [$label, $limit])
        <div class="col-md-6 form-group mb-3">
            <label for="{{ $field }}">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="number" step="any" min="-{{ $limit }}" max="{{ $limit }}"
                   value="{{ old($field, optional($device)->getAttribute($field)) }}" class="form-control @error($field) is-invalid @enderror"
                   @if ($registering) required @endif>
            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach
</div>
