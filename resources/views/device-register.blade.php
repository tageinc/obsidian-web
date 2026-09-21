@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mb-3">Back to dashboard</a>
            <h1 class="h3 mb-3">Register device</h1>
            <p class="text-muted">Add your device details and installation address, then save everything together.</p>

            <div class="card">
                <div class="card-body">
                    @include('partials.device-form-feedback')
                    <form id="device-form" action="{{ route('dataInsert') }}" method="POST">
                        @csrf
                        <h2 class="h5 mb-3">Device details</h2>
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="hardware_id">Hardware</label>
                                <select id="hardware_id" name="hardware_id" class="form-control @error('hardware_id') is-invalid @enderror" required>
                                    @foreach ($hardwares as $hardware)
                                        <option value="{{ $hardware->id }}" @if ((string) old('hardware_id', 1) === (string) $hardware->id) selected @endif>{{ $hardware->name }}</option>
                                    @endforeach
                                </select>
                                @error('hardware_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            @foreach (['serial_no' => 'Serial number', 'sku' => 'SKU', 'order_no' => 'Order number'] as $field => $label)
                                <div class="col-md-6 form-group mb-3">
                                    <label for="{{ $field }}">{{ $label }}</label>
                                    <input id="{{ $field }}" name="{{ $field }}" type="text" maxlength="255" value="{{ old($field) }}"
                                           class="form-control @error($field) is-invalid @enderror" required>
                                    @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endforeach
                        </div>

                        @include('partials.device-form-fields', ['device' => null, 'registering' => true])
                        <button type="submit" class="btn btn-primary">Register device</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
