@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card custom-card">
                <div class="card-header custom-card-header">{{ __('Dashboard view') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @elseif(session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <p class="welcome-text">{{ __('Welcome') }} {{ Auth::user()->name }}!</p>
                    @if ($address_1)
                    <div class="address-container">
                        <p class="address-label">{{ __('Address:') }}</p>
                        <p class="address-line">{{ $address_1 }}, {{ $address_2 }}</p>
                        <p class="address-line">{{ $city }}, {{ $state }} {{ $zip_code }}</p>
                        <p class="address-line">{{ $country }}</p>
                        <p class="device-count">{{ __('Number of devices: ') }}{{ $deviceRegisterCount }}</p>
                    </div>
                    @else
                        <p class="no-address">{{ __('No address found for this user.') }}</p>
                    @endif            
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
