@php
    $usesVue = config('frontend.vue3.device_edit');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @php
        $deviceValues = [];
        foreach (['serial_no', 'sku', 'order_no', 'name', 'address_1', 'address_2', 'city', 'address_state', 'zip_code', 'country', 'latitude', 'longitude'] as $field) {
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
@endif
@endsection
