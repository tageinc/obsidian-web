@php
    $usesVue = config('frontend.vue3.device_edit');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @php
        $deviceValues = [];
        foreach (['alias', 'address_1', 'address_2', 'city', 'state', 'zip_code', 'country', 'latitude', 'longitude'] as $field) {
            $default = $device->getAttribute($field);
            $deviceValues[$field] = old($field, $field === 'country' && !$default ? 'US' : $default);
        }
    @endphp
    @include('frontend.mount', ['page' => 'edit-device', 'props' => [
        'creating' => false,
        'csrfToken' => csrf_token(),
        'action' => route('device.update', ['id' => $device->id]),
        'values' => $deviceValues,
        'fixedIdentity' => [
            'serialNo' => $device->serial_no,
            'sku' => $device->sku,
            'orderNo' => $device->order_no,
        ],
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
            <h1 class="h3 mb-3">Edit device</h1>
            <p class="text-muted">Update your device name and installation details, then save everything together.</p>

            <div class="card">
                <div class="card-body">
                    @include('partials.device-form-feedback')
                    <h2 class="h5 mb-3">Registered device</h2>
                    <dl class="row">
                        <dt class="col-sm-4">Serial number</dt>
                        <dd class="col-sm-8">{{ $device->serial_no }}</dd>
                        <dt class="col-sm-4">SKU</dt>
                        <dd class="col-sm-8">{{ $device->sku ?? 'Not provided' }}</dd>
                        <dt class="col-sm-4">Order number</dt>
                        <dd class="col-sm-8">{{ $device->order_no ?? 'Not provided' }}</dd>
                    </dl>
                    <form id="device-form" action="{{ route('device.update', ['id' => $device->id]) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('partials.device-form-fields', ['creating' => false])
                        <button type="submit" class="btn btn-primary">Update device</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
