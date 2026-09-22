@php
    $usesVue = config('frontend.vue3.create_device');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @php
        $deviceValues = \App\Support\DeviceCreationData::values(Auth::user());
    @endphp
    @include('frontend.mount', ['page' => 'create-device', 'props' => [
        'creating' => true,
        'csrfToken' => csrf_token(),
        'action' => route('create-device.store'),
        'values' => $deviceValues,
        'errors' => $errors->messages(),
        'success' => session('success'),
        'sessionError' => session('error'),
        'links' => ['dashboard' => route('dashboard')],
    ]])
@else
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mb-3">Back to dashboard</a>
            <h1 class="h3 mb-3">Create Device</h1>
            <p class="text-muted">Add your device details and installation address, then save everything together.</p>

            <div class="card">
                <div class="card-body">
                    @include('partials.device-form-feedback')
                    <form id="device-form" action="{{ route('create-device.store') }}" method="POST">
                        @csrf
                        <h2 class="h5 mb-3">Device details</h2>
                        <div class="row">
                            @foreach (['serial_no' => 'Serial number', 'sku' => 'SKU', 'order_no' => 'Order number'] as $field => $label)
                                <div class="col-md-6 form-group mb-3">
                                    <label for="{{ $field }}">{{ $label }}</label>
                                    <input id="{{ $field }}" name="{{ $field }}" type="text" maxlength="255" value="{{ old($field) }}"
                                           class="form-control @error($field) is-invalid @enderror" required>
                                    @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endforeach
                        </div>

                        @include('partials.device-form-fields', ['device' => null, 'creating' => true])
                        <button type="submit" class="btn btn-primary">Create Device</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
