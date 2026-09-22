@php
    $usesVue = config('frontend.vue3.profile');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @php
        $profileValues = [];
        foreach (['name', 'email', 'phone_number', 'address_1', 'address_2', 'city', 'state', 'zip_code', 'country'] as $field) {
            $profileValues[$field] = old($field, $user->getAttribute($field));
        }
    @endphp
    @include('frontend.mount', ['page' => 'profile', 'props' => [
        'csrfToken' => csrf_token(),
        'action' => route('profile.update'),
        'values' => $profileValues,
        'errors' => $errors->messages(),
        'success' => session('success'),
        'sessionError' => session('error'),
        'links' => ['dashboard' => route('dashboard')],
    ]])
@else
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="Breadcrumb" class="mb-2">
                <ol class="list-unstyled d-flex flex-wrap small text-muted mb-0">
                    <li><a href="{{ route('dashboard') }}" class="text-muted text-nowrap">Dashboard</a><span class="mx-2" aria-hidden="true">›</span></li>
                    <li aria-current="page">Profile</li>
                </ol>
            </nav>
            <h1 class="h3 mb-3">Profile</h1>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible" role="status">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss notification"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ __('Please correct the highlighted fields and save your profile again.') }}</div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')

                        <fieldset class="mb-4">
                            <legend class="h5">{{ __('Contact information') }}</legend>
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __('Name') }}</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="255" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">{{ __('Email address') }}</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" maxlength="255" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">{{ __('Phone number') }}</label>
                                    <input type="tel" class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" autocomplete="tel" maxlength="20" aria-describedby="phone-help">
                                    <div id="phone-help" class="form-text">{{ __('You may keep your current number. For a new number, use 10–15 digits, including the country code if needed.') }}</div>
                                    @error('phone_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5">{{ __('Address') }}</legend>
                            <div class="row">
                                @foreach ([
                                    'address_1' => ['Address line 1', 'address-line1', 'col-12'],
                                    'address_2' => ['Address line 2', 'address-line2', 'col-12'],
                                    'city' => ['City', 'address-level2', 'col-md-6'],
                                    'state' => ['State / Province', 'address-level1', 'col-md-6'],
                                    'zip_code' => ['ZIP / Postal code', 'postal-code', 'col-md-6'],
                                    'country' => ['Country', 'country-name', 'col-md-6'],
                                ] as $field => [$label, $autocomplete, $column])
                                    <div class="{{ $column }} mb-3">
                                        <label for="{{ $field }}" class="form-label">{{ __($label) }}</label>
                                        <input type="text" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $user->{$field}) }}" autocomplete="{{ $autocomplete }}" maxlength="255">
                                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5">{{ __('Change password') }}</legend>
                            <p id="password-help" class="text-muted small">{{ __('Leave both fields blank to keep your current password. A new password must contain at least 8 characters.') }}</p>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">{{ __('New password') }}</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" minlength="8" aria-describedby="password-help">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}</label>
                                    <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8">
                                    @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </fieldset>

                        <button type="submit" class="btn btn-primary">{{ __('Save profile') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
